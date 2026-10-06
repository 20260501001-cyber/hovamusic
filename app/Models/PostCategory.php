<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'description', 'sort'])]
class PostCategory extends Model
{
    use FlushesContentCache;

    protected static function booted(): void
    {
        static::saving(function (PostCategory $category): void {
            $category->slug = Str::slug($category->slug ?: $category->name);
        });
    }

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
