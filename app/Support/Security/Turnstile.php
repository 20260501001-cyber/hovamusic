<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class Turnstile
{
    public function enabled(): bool
    {
        return filled(config('services.turnstile.secret_key'));
    }

    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return ! app()->isProduction();
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(config('services.turnstile.verify_url'), array_filter([
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (Throwable $e) {
            Log::warning('Turnstile doğrulaması yapılamadı.', ['error' => $e->getMessage()]);

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
