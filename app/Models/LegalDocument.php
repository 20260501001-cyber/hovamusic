<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FlushesContentCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Yasal metin. İçerik sürüm olarak tutulur; yayımlanmış en yüksek sürüm geçerlidir.
 * Onay kayıtları (consents) hangi sürümün onaylandığını saklar.
 */
#[Fillable(['slug', 'title', 'consent_type', 'is_public', 'sort'])]
class LegalDocument extends Model
{
    use Auditable;
    use FlushesContentCache;

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<LegalDocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(LegalDocumentVersion::class)->orderByDesc('version');
    }

    /**
     * @return HasOne<LegalDocumentVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(LegalDocumentVersion::class)
            ->ofMany(['version' => 'max'], fn ($query) => $query->whereNotNull('published_at')->where('published_at', '<=', now()));
    }
}
