<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hova Music önekiyle atanmış ISRC. Kayıt silinmez ve güncellenmez; kod bir daha atanmaz.
 */
#[Fillable(['isrc', 'year', 'sequence', 'track_id', 'assigned_by_type', 'assigned_by_id'])]
class IsrcCode extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'sequence' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function formatted(): string
    {
        return substr($this->isrc, 0, 2).'-'.substr($this->isrc, 2, 3).'-'.substr($this->isrc, 5, 2).'-'.substr($this->isrc, 7);
    }
}
