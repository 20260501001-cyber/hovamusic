<?php

namespace App\Domain\Billing;

/**
 * Standard Webhooks imzası (Polar bu standardı kullanır). İmzalanan içerik
 * "{webhook-id}.{webhook-timestamp}.{gövde}", anahtar webhook gizli anahtarıdır.
 * "whsec_" önekli anahtarın devamı base64 olarak çözülür; Polar panelinin verdiği
 * düz anahtar olduğu gibi kullanılır. Zaman damgası 5 dakikadan eskiyse reddedilir.
 */
class WebhookSignature
{
    public const TOLERANCE_SECONDS = 300;

    public function __construct(private readonly string $secret) {}

    public function verify(string $id, string $timestamp, string $signatureHeader, string $payload, ?int $now = null): bool
    {
        if ($this->secret === '' || $id === '' || ! ctype_digit($timestamp) || $signatureHeader === '') {
            return false;
        }

        $now ??= time();

        if (abs($now - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = $this->sign($id, $timestamp, $payload);

        foreach (preg_split('/\s+/', trim($signatureHeader)) ?: [] as $candidate) {
            [$version, $signature] = array_pad(explode(',', $candidate, 2), 2, '');

            if ($version === 'v1' && hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function sign(string $id, string $timestamp, string $payload): string
    {
        return base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$payload}", $this->key(), true));
    }

    private function key(): string
    {
        if (str_starts_with($this->secret, 'whsec_')) {
            return (string) base64_decode(substr($this->secret, 6), true);
        }

        return $this->secret;
    }
}
