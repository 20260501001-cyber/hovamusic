<?php

namespace App\Domain\Billing;

use App\Models\Checkout;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Doğrulanmış Polar olayını bir kez işler. Olay (provider, event_id) ile saklanır;
 * işlenmiş bir olay tekrar gelirse hiçbir şey yapılmaz. İşleme hata verirse olay
 * işlenmemiş kalır ve Polar'ın yeniden denemesinde tekrar işlenir.
 */
class PolarWebhookProcessor
{
    public function __construct(
        private readonly SubscriptionSync $subscriptions,
        private readonly OrderSync $orders,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return string processed, duplicate, ignored ya da unresolved
     */
    public function handle(string $eventId, array $payload): string
    {
        $type = (string) ($payload['type'] ?? 'unknown');

        WebhookEvent::query()->insertOrIgnore([
            'provider' => 'polar',
            'event_id' => $eventId,
            'type' => mb_substr($type, 0, 64),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::transaction(function () use ($eventId, $payload, $type): string {
            $event = WebhookEvent::query()->where('provider', 'polar')->where('event_id', $eventId)->lockForUpdate()->firstOrFail();

            if ($event->processed_at !== null) {
                return 'duplicate';
            }

            $event->attempts++;
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            try {
                $result = $this->dispatch($type, $data);
                $event->forceFill(['processed_at' => now(), 'error' => null])->save();

                return $result;
            } catch (UnresolvableEvent $e) {
                Log::warning('Polar olayı eşleştirilemedi.', ['event' => $eventId, 'type' => $type, 'error' => $e->getMessage()]);
                $event->forceFill(['processed_at' => now(), 'error' => $e->getMessage()])->save();

                return 'unresolved';
            } catch (Throwable $e) {
                // İşlemin kendi değişiklikleri geri alınır; olay işlenmemiş olarak kalır.
                DB::afterRollBack(fn () => WebhookEvent::query()->whereKey($event->id)->update([
                    'attempts' => $event->attempts,
                    'error' => mb_substr($e->getMessage(), 0, 1000),
                ]));

                throw $e;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatch(string $type, array $data): string
    {
        return match (true) {
            str_starts_with($type, 'subscription.') => $this->subscription($data),
            in_array($type, ['order.created', 'order.paid', 'order.updated', 'order.refunded'], true) => $this->order($data),
            $type === 'checkout.updated' => $this->checkout($data),
            default => 'ignored',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function subscription(array $data): string
    {
        $this->subscriptions->apply($data);

        return 'processed';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function order(array $data): string
    {
        $this->orders->apply($data);

        return 'processed';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkout(array $data): string
    {
        $status = (string) ($data['status'] ?? '');

        Checkout::query()->where('provider_id', (string) ($data['id'] ?? ''))->update([
            'status' => mb_substr($status, 0, 16),
            'completed_at' => in_array($status, ['succeeded', 'confirmed'], true) ? now() : null,
        ]);

        return 'processed';
    }
}
