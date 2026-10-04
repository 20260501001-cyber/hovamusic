<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'spotify_artist_id', 'spotify_url', 'spotify_image_url', 'apple_music_id', 'create_new_spotify', 'create_new_apple'])]
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory, HasPublicUlid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'create_new_spotify' => 'boolean',
            'create_new_apple' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ReleaseArtist, $this>
     */
    public function releaseCredits(): HasMany
    {
        return $this->hasMany(ReleaseArtist::class);
    }

    /**
     * @return HasMany<TrackArtist, $this>
     */
    public function trackCredits(): HasMany
    {
        return $this->hasMany(TrackArtist::class);
    }

    public function isUsed(): bool
    {
        return $this->releaseCredits()->exists() || $this->trackCredits()->exists();
    }

    public function spotifyLabel(): string
    {
        if ($this->spotify_artist_id) {
            return $this->spotify_artist_id;
        }

        return $this->create_new_spotify ? __('artist.create_new_short') : '—';
    }

    public function appleLabel(): string
    {
        if ($this->apple_music_id) {
            return $this->apple_music_id;
        }

        return $this->create_new_apple ? __('artist.create_new_short') : '—';
    }
}
