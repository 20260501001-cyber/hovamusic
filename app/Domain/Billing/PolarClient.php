<?php

namespace App\Domain\Billing;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polar.sh API'si: ödeme oturumu ve müşteri portalı. Abonelik durumu buradan
 * okunmaz; yalnızca imzalı webhook'larla güncellenir.
 */
class PolarClient
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $server = 'sandbox',
    ) {}

    public static function fromConfig(): self
    {
        return new self((string) config('services.polar.access_token'), (string) config('services.polar.server', 'sandbox'));
    }

    public function isConfigured(): bool
    {
        return $this->accessToken !== '';
    }

    /**
     * @param  array<string, string>  $metadata
     * @return array{id: string, url: string}
     *
     * @throws BillingUnavailable
     */
    public function createCheckout(Plan $plan, User $user, string $successUrl, array $metadata): array
    {
        $response = $this->send('post', '/checkouts/', [
            'products' => [$plan->polar_product_id],
            'success_url' => $successUrl,
            'customer_email' => $user->email,
            'customer_name' => $user->name,
            'external_customer_id' => $user->ulid,
            'metadata' => $metadata,
        ]);

        $id = (string) ($response['id'] ?? '');
        $url = (string) ($response['url'] ?? '');

        if ($id === '' || $url === '') {
            throw new BillingUnavailable(__('plans.errors.provider'));
        }

        return ['id' => $id, 'url' => $url];
    }

    /**
     * Müşteri portalı: kart, plan değişikliği, iptal ve faturalar.
     *
     * @throws BillingUnavailable
     */
    public function customerPortalUrl(User $user): string
    {
        $customerId = $user->polarCustomer?->polar_id;
        $body = $customerId ? ['customer_id' => $customerId] : ['external_customer_id' => $user->ulid];

        $url = (string) ($this->send('post', '/customer-sessions/', $body)['customer_portal_url'] ?? '');

        if ($url === '') {
            throw new BillingUnavailable(__('plans.errors.provider'));
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws BillingUnavailable
     */
    private function send(string $method, string $path, array $body): array
    {
        if (! $this->isConfigured()) {
            throw new BillingUnavailable(__('plans.errors.not_configured'));
        }

        try {
            $response = $this->http()->{$method}($path, $body);
        } catch (ConnectionException $e) {
            Log::warning('Polar\'a bağlanılamadı.', ['path' => $path, 'error' => mb_substr($e->getMessage(), 0, 300)]);

            throw new BillingUnavailable(__('plans.errors.provider'), previous: $e);
        }

        if (! $response->successful()) {
            Log::warning('Polar isteği başarısız.', ['path' => $path, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

            throw new BillingUnavailable(__('plans.errors.provider'));
        }

        return (array) $response->json();
    }

    private function http(): PendingRequest
    {
        $base = $this->server === 'production' ? 'https://api.polar.sh/v1' : 'https://sandbox-api.polar.sh/v1';

        return Http::baseUrl($base)
            ->withToken($this->accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->retry(3, 200, fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false);
    }
}
