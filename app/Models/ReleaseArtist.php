<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yayın düzeyinde ana veya konuk sanatçı. Profil bağlıysa artist_id dolu olur;
 * profili olmayan konuk sanatçı yalnızca ad ve isteğe bağlı mağaza kimlikleriyle tutulur.
 */
#[Fillable(['artist_id', 'name', 'role', 'spotify_artist_id', 'apple_music_id', 'position'])]
class ReleaseArtist extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class)->withTrashed();
    }
}
