<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FlushesContentCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 301/302 yönlendirme. Kaynak yol site içi bir yoldur; hedef site içi yol ya da tam adres.
 */
#[Fillable(['from_path', 'to_path', 'code', 'is_active'])]
class Redirect extends Model
{
    use Auditable;
    use FlushesContentCache;

    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect): void {
            $redirect->from_path = SeoMeta::normalizePath($redirect->from_path);
        });
    }

    protected function casts(): array
    {
        return [
            'code' => 'integer',
            'is_active' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }
}
