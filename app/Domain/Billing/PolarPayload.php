<?php

namespace App\Domain\Billing;

use App\Models\PolarCustomer;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Polar webhook verisinden yardımcı okumalar: kullanıcı, tarih ve tutar.
 */
final class PolarPayload
{
    /**
     * Kullanıcı sırasıyla: ödeme oturumunda verdiğimiz external_customer_id (ulid),
     * metadata.user, kayıtlı Polar müşteri kimliği.
     *
     * @param  array<string, mixed>  $data
     */
    public static function user(array $data): ?User
    {
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $candidates = array_filter([
            $customer['external_id'] ?? null,
            $data['external_customer_id'] ?? null,
            $data['metadata']['user'] ?? null,
            $customer['metadata']['user'] ?? null,
        ], fn ($value): bool => is_string($value) && $value !== '');

        foreach ($candidates as $ulid) {
            $user = User::withTrashed()->where('ulid', $ulid)->first();

            if ($user !== null) {
                return $user;
            }
        }

        $customerId = $data['customer_id'] ?? $customer['id'] ?? null;

        if (is_string($customerId) && $customerId !== '') {
            return PolarCustomer::query()->where('polar_id', $customerId)->first()?->user;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function rememberCustomer(User $user, array $data): void
    {
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $customerId = $data['customer_id'] ?? $customer['id'] ?? null;

        if (! is_string($customerId) || $customerId === '') {
            return;
        }

        PolarCustomer::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['polar_id' => $customerId, 'email' => $customer['email'] ?? null],
        );
    }

    public static function date(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->utc() : null;
    }

    /**
     * Polar tutarları en küçük birimde (sent) gönderir.
     */
    public static function money(mixed $cents): string
    {
        $cents = (int) $cents;
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
