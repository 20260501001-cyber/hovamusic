<?php

namespace App\Models\Concerns;

use App\Support\Content\ContentCache;

/**
 * Herkese açık sitede görünen modeller değişince sayfa önbelleği yenilenir.
 */
trait FlushesContentCache
{
    public static function bootFlushesContentCache(): void
    {
        static::saved(fn () => ContentCache::flush());
        static::deleted(fn () => ContentCache::flush());
    }
}
