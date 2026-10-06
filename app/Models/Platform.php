<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FlushesContentCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'logo_path', 'is_active', 'sort'])]
class Platform extends Model
{
    use Auditable;
    use FlushesContentCache;

    protected static function booted(): void
    {
        static::saving(function (Platform $platform): void {
            $platform->slug = $platform->slug ?: Str::slug($platform->name);
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Release, $this>
     */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class, 'release_platforms');
    }

    /**
     * @param  Builder<Platform>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Platform>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('name');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
