<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['locale', 'group', 'question', 'answer', 'is_published', 'show_on_home', 'sort'])]
class Faq extends Model
{
    use FlushesContentCache;

    protected $attributes = [
        'locale' => 'tr',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_on_home' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function html(): string
    {
        return Str::markdown($this->answer, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    /**
     * Yapılandırılmış veri için düz metin cevap.
     */
    public function plainAnswer(): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($this->html())) ?? '');
    }
}
