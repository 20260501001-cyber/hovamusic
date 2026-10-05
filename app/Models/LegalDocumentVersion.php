<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Yasal metnin bir sürümü. Yayımlanan sürüm değiştirilmez; düzeltme yeni sürümle yapılır.
 */
#[Fillable(['legal_document_id', 'version', 'body', 'change_note', 'published_at', 'created_by'])]
class LegalDocumentVersion extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LegalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * Metin Markdown olarak girilir; HTML etiketleri kaçırılır.
     */
    public function html(): string
    {
        return Str::markdown($this->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}
