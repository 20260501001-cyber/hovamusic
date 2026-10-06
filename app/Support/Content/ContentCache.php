<?php

namespace App\Support\Content;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Herkese açık sayfaların veritabanı sorguları için önbellek. İçerik (yazı, SSS,
 * plan, platform, SEO, yönlendirme) değişince sürüm artar ve tüm anahtarlar
 * kendiliğinden geçersiz olur.
 */
class ContentCache
{
    private const VERSION_KEY = 'content.version';

    private const TTL_SECONDS = 3600;

    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember('content:'.self::version().':'.$key, self::TTL_SECONDS, $callback);
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    private static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn (): int => 1);
    }
}
