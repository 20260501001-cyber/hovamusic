<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Çerez tercihi. Zorunlu olmayan (analiz, pazarlama) betikler yalnızca izin
 * verilmişse sayfaya eklenir: @if (\App\Support\CookieConsent::allows('analytics')).
 */
class CookieConsent
{
    public const COOKIE = 'hm_cerez';

    public const VERSION = 1;

    public const CATEGORIES = ['analytics', 'marketing'];

    /**
     * @return array{v: int, id: string, analytics: bool, marketing: bool}|null
     */
    public static function current(?Request $request = null): ?array
    {
        $request ??= request();
        $data = json_decode((string) $request->cookie(self::COOKIE), true);

        if (! is_array($data) || ($data['v'] ?? null) !== self::VERSION) {
            return null;
        }

        return [
            'v' => self::VERSION,
            'id' => is_string($data['id'] ?? null) ? $data['id'] : (string) Str::uuid(),
            'analytics' => (bool) ($data['analytics'] ?? false),
            'marketing' => (bool) ($data['marketing'] ?? false),
        ];
    }

    public static function decided(?Request $request = null): bool
    {
        return self::current($request) !== null;
    }

    public static function allows(string $category, ?Request $request = null): bool
    {
        return (bool) (self::current($request)[$category] ?? false);
    }
}
