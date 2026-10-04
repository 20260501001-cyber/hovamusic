<?php

namespace App\Models;

use App\Enums\ArtistRole;
use App\Enums\CreditRole;
use App\Enums\IsrcSource;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\TrackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['position', 'title', 'version', 'isrc', 'has_own_isrc', 'isrc_source', 'explicit', 'language', 'preview_start_sec', 'lyrics', 'audio_file_id', 'duration_ms', 'spotify_track_id'])]
class Track extends Model
{
    /** @use HasFactory<TrackFactory> */
    use Auditable, HasFactory, HasPublicUlid;

    protected $attributes = [
        'explicit' => false,
        'has_own_isrc' => false,
        'preview_start_sec' => 0,
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'explicit' => 'boolean',
            'has_own_isrc' => 'boolean',
            'isrc_source' => IsrcSource::class,
            'preview_start_sec' => 'integer',
            'duration_ms' => 'integer',
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
     * @return BelongsTo<MediaFile, $this>
     */
    public function audio(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'audio_file_id');
    }

    /**
     * @return HasMany<TrackArtist, $this>
     */
    public function artists(): HasMany
    {
        return $this->hasMany(TrackArtist::class)
            ->orderByRaw("case when role = 'primary' then 0 else 1 end")
            ->orderBy('position');
    }

    /**
     * @return HasMany<TrackCredit, $this>
     */
    public function credits(): HasMany
    {
        return $this->hasMany(TrackCredit::class)->orderBy('role')->orderBy('position');
    }

    public function displayTitle(): string
    {
        $title = $this->title ?: __('release.track.untitled');

        return $this->version ? "{$title} ({$this->version})" : $title;
    }

    public function featuringLine(): string
    {
        return $this->artists->where('role', ArtistRole::Featuring->value)->pluck('name')->implode(', ');
    }

    /**
     * @return list<string>
     */
    public function creditNames(CreditRole $role): array
    {
        return $this->credits->where('role', $role->value)->pluck('name')->values()->all();
    }

    public function formattedIsrc(): ?string
    {
        if (! $this->isrc) {
            return null;
        }

        return substr($this->isrc, 0, 2).'-'.substr($this->isrc, 2, 3).'-'.substr($this->isrc, 5, 2).'-'.substr($this->isrc, 7);
    }
}
