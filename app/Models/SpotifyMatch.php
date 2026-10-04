<?php

namespace App\Models;

use App\Enums\SpotifyMatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Mağazalara gönderildi" durumundaki yayın için Spotify'da bulunan albüm. UPC ya da
 * parça ISRC'siyle eşleşir; admin onaylayınca mağaza bağlantısı olur.
 */
#[Fillable(['track_id', 'matched_by', 'spotify_album_id', 'album_name', 'album_url', 'spotify_track_id', 'status', 'handled_at'])]
class SpotifyMatch extends Model
{
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => SpotifyMatchStatus::class,
            'handled_at' => 'datetime',
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
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
