<?php

namespace App\Domain\Billing;

use App\Enums\SubscriptionStatus;
use App\Events\PlanBecameActive;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SubscriptionActivated;
use App\Notifications\SubscriptionEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Polar abonelik olayını yerel kayda işler. Olaylar sırasız gelebilir; Polar'daki
 * değişiklik zamanı (modified_at) kayıttakinden eskiyse olay yok sayılır.
 */
class SubscriptionSync
{
    public function __construct(private readonly PlanHistoryRecorder $history) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws UnresolvableEvent
     */
    public function apply(array $data): Subscription
    {
        $providerId = (string) ($data['id'] ?? '');
        $user = PolarPayload::user($data);

        if ($providerId === '' || $user === null) {
            throw new UnresolvableEvent('Abonelik ya da kullanıcı bulunamadı.');
        }

        return DB::transaction(function () use ($data, $providerId, $user): Subscription {
            $subscription = Subscription::query()->where('provider_id', $providerId)->lockForUpdate()->first()
                ?? new Subscription(['provider' => 'polar', 'provider_id' => $providerId]);

            $modifiedAt = PolarPayload::date($data['modified_at'] ?? null) ?? PolarPayload::date($data['created_at'] ?? null);

            if ($subscription->exists && $modifiedAt !== null && $subscription->provider_updated_at?->greaterThan($modifiedAt)) {
                return $subscription;
            }

            $wasActive = $subscription->exists && $subscription->isActive();
            $plan = Plan::query()->where('polar_product_id', (string) ($data['product_id'] ?? ''))->first();

            if ($plan === null) {
                Log::warning('Polar ürünü hiçbir plana bağlı değil.', ['product_id' => $data['product_id'] ?? null]);
            }

            PolarPayload::rememberCustomer($user, $data);

            $subscription->user()->associate($user);
            $subscription->fill([
                'plan_id' => $plan?->id ?? $subscription->plan_id,
                'provider_customer_id' => $data['customer_id'] ?? $subscription->provider_customer_id,
                'status' => SubscriptionStatus::tryFrom((string) ($data['status'] ?? '')) ?? $subscription->status ?? SubscriptionStatus::Incomplete,
                'amount' => isset($data['amount']) ? PolarPayload::money($data['amount']) : $subscription->amount,
                'currency' => isset($data['currency']) ? strtoupper((string) $data['currency']) : $subscription->currency,
                'started_at' => PolarPayload::date($data['started_at'] ?? null) ?? $subscription->started_at,
                'current_period_start' => PolarPayload::date($data['current_period_start'] ?? null),
                'current_period_end' => PolarPayload::date($data['current_period_end'] ?? null),
                'cancel_at_period_end' => (bool) ($data['cancel_at_period_end'] ?? false),
                'canceled_at' => PolarPayload::date($data['canceled_at'] ?? null),
                'ended_at' => PolarPayload::date($data['ended_at'] ?? null),
                'provider_updated_at' => $modifiedAt ?? now(),
            ]);

            // Yeni dönem başladıysa yenileme hatırlatması yeniden gönderilebilir.
            if ($subscription->isDirty('current_period_end')) {
                $subscription->renewal_reminded_at = null;
            }

            $subscription->save();
            $subscription->setRelation('plan', $plan ?? $subscription->plan);

            $isActive = $subscription->isActive();
            $change = $this->history->sync($user);

            DB::afterCommit(function () use ($user, $subscription, $wasActive, $isActive, $change): void {
                if (! $wasActive && $isActive) {
                    $user->notify(new SubscriptionActivated($subscription));
                    event(new PlanBecameActive($user, $subscription));
                } elseif ($change === 'changed') {
                    event(new PlanBecameActive($user, $subscription));
                }

                if ($wasActive && ! $isActive && $subscription->expiry_notified_at === null && $user->activeSubscription() === null) {
                    $subscription->forceFill(['expiry_notified_at' => now()])->saveQuietly();
                    $user->notify(new SubscriptionEnded($subscription));
                }
            });

            return $subscription;
        });
    }
}
