<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Adreslerde sıralı id yerine tahmin edilemeyen, küçük harfli bir ULID kullanılır.
 */
trait HasPublicUlid
{
    public static function bootHasPublicUlid(): void
    {
        static::creating(function (self $model): void {
            $model->ulid ??= Str::lower((string) Str::ulid());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
