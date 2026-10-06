<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FlushesContentCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Sayfa bazında SEO: yol (ör. "/fiyatlar") için başlık, açıklama, canonical, Open
 * Graph ve Twitter kartı. Boş bırakılan alan sayfanın varsayılanıyla dolar.
 */
#[Fillable(['path', 'title', 'description', 'canonical', 'og_title', 'og_description', 'og_image_path', 'twitter_card', 'noindex'])]
class SeoMeta extends Model
{
    use Auditable;
    use FlushesContentCache;

    protected $table = 'seo_meta';

    protected static function booted(): void
    {
        static::saving(function (SeoMeta $meta): void {
            $meta->path = self::normalizePath($meta->path);
        });
    }

    protected function casts(): array
    {
        return ['noindex' => 'boolean'];
    }

    public static function normalizePath(string $path): string
    {
        $path = '/'.trim((string) parse_url(trim($path), PHP_URL_PATH), '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
