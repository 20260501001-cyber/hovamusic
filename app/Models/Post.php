<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\HasPublicUlid;
use App\Support\Images\ResponsiveImages;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Blog ve rehber yazısı. Gövde Markdown; HTML girdisi kaçışlanır.
 */
#[Fillable([
    'post_category_id', 'locale', 'title', 'slug', 'excerpt', 'body', 'cover_path', 'cover_alt', 'cover_width', 'cover_height',
    'author_name', 'status', 'published_at', 'seo_title', 'seo_description', 'noindex', 'created_by',
])]
class Post extends Model
{
    use FlushesContentCache;
    use HasPublicUlid;

    public const COVER_DISK = 'public';

    protected $attributes = [
        'locale' => 'tr',
        'status' => 'draft',
    ];

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            $post->slug = Str::slug($post->slug ?: $post->title);

            if ($post->status === PostStatus::Published && $post->published_at === null) {
                $post->published_at = now();
            }
        });

        // Kapak değişince WebP/AVIF sürümleri üretilir, eskileri silinir.
        static::saved(function (Post $post): void {
            if (! $post->wasRecentlyCreated && ! $post->wasChanged('cover_path')) {
                return;
            }

            $images = app(ResponsiveImages::class);
            $old = $post->getOriginal('cover_path');

            if ($old && $old !== $post->cover_path) {
                $images->delete(self::COVER_DISK, $old);
            }

            if ($post->cover_path) {
                $size = $images->generate(self::COVER_DISK, $post->cover_path);
                $post->forceFill(['cover_width' => $size['width'], 'cover_height' => $size['height']])->saveQuietly();
            }
        });

        static::deleted(function (Post $post): void {
            if ($post->cover_path) {
                app(ResponsiveImages::class)->delete(self::COVER_DISK, $post->cover_path);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'noindex' => 'boolean',
            'cover_width' => 'integer',
            'cover_height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PostCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    /**
     * Yayında ve yayın tarihi gelmiş yazılar.
     *
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published && $this->published_at !== null && $this->published_at->isPast();
    }

    public function html(): string
    {
        return Str::markdown($this->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    public function readingMinutes(): int
    {
        $words = preg_split('/\s+/u', trim(strip_tags($this->html())), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return max(1, (int) ceil(count($words) / 200));
    }

    public function url(): string
    {
        return route('blog.show', $this->slug);
    }

    /**
     * Kapak görselinin genişliğe göre sürümleri (WebP/AVIF), yoksa boş.
     *
     * @return array{webp: array<int, string>, avif: array<int, string>, fallback: string|null}
     */
    public function coverSources(): array
    {
        if ($this->cover_path === null) {
            return ['webp' => [], 'avif' => [], 'fallback' => null];
        }

        $disk = Storage::disk(self::COVER_DISK);
        $base = preg_replace('/\.[a-z0-9]+$/i', '', $this->cover_path);
        $sources = ['webp' => [], 'avif' => [], 'fallback' => $disk->url($this->cover_path)];

        foreach ([640, 1280] as $width) {
            foreach (['webp', 'avif'] as $format) {
                $path = "{$base}-{$width}.{$format}";

                if ($disk->exists($path)) {
                    $sources[$format][$width] = $disk->url($path);
                }
            }
        }

        return $sources;
    }
}
