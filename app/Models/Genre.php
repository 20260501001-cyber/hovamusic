<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['parent_id', 'name', 'slug', 'sort', 'is_active'])]
class Genre extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        static::saving(function (Genre $genre): void {
            // Alt türlerde üst türün adı öne eklenir: aynı adlı alt türler çakışmasın.
            $parent = $genre->parent_id ? Genre::query()->whereKey($genre->parent_id)->value('name') : null;
            $genre->slug = $genre->slug ?: Str::slug(trim($parent.' '.$genre->name));
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
     * @return BelongsTo<Genre, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Genre::class, 'parent_id');
    }

    /**
     * @return HasMany<Genre, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Genre::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    public function isUsed(): bool
    {
        return Release::withTrashed()
            ->where(fn (Builder $query) => $query->where('genre_id', $this->id)->orWhere('subgenre_id', $this->id))
            ->exists();
    }

    /**
     * @param  Builder<Genre>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Genre>  $query
     */
    public function scopeTopLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }
}
