<?php

namespace App\Models;

use App\Enums\TemplateType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Düzeltme isteği ve ret için hazır metinler. Admin seçer, metni düzenleyip gönderir.
 */
#[Fillable(['type', 'title', 'body', 'is_active', 'sort'])]
class ReviewTemplate extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'type' => TemplateType::class,
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @param  Builder<ReviewTemplate>  $query
     */
    public function scopeFor(Builder $query, TemplateType $type): void
    {
        $query->where('type', $type)->where('is_active', true)->orderBy('sort')->orderBy('title');
    }
}
