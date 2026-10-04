<?php

namespace App\Models;

use App\Enums\ArtistRole;
use App\Enums\ReleaseStatus;
use App\Enums\ReleaseType;
use App\Enums\TerritoryMode;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\ReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'type', 'title', 'version', 'label_name', 'genre_id', 'subgenre_id', 'language', 'release_date', 'original_release_date',
    'p_line', 'c_line', 'upc', 'spotify_album_id', 'explicit', 'territory_mode', 'territories', 'cover_media_id', 'wizard_step',
])]
class Release extends Model
{
    /** @use HasFactory<ReleaseFactory> */
    use Auditable, HasFactory, HasPublicUlid, SoftDeletes;

    protected $attributes = [
        'status' => 'draft',
        'territory_mode' => 'worldwide',
        'explicit' => false,
        'wizard_step' => 1,
    ];

    protected function casts(): array
    {
        return [
            'type' => ReleaseType::class,
            'status' => ReleaseStatus::class,
            'territory_mode' => TerritoryMode::class,
            'territories' => 'array',
            'explicit' => 'boolean',
            'release_date' => 'date',
            'original_release_date' => 'date',
            'submitted_at' => 'datetime',
            'locked_at' => 'datetime',
            'spotify_checked_at' => 'datetime',
            'wizard_step' => 'integer',
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
     * @return BelongsTo<Genre, $this>
     */
    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    /**
     * @return BelongsTo<Genre, $this>
     */
    public function subgenre(): BelongsTo
    {
        return $this->belongsTo(Genre::class, 'subgenre_id');
    }

    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'cover_media_id');
    }

    /**
     * @return HasMany<ReleaseArtist, $this>
     */
    public function artists(): HasMany
    {
        return $this->hasMany(ReleaseArtist::class)
            ->orderByRaw("case when role = 'primary' then 0 else 1 end")
            ->orderBy('position');
    }

    /**
     * @return HasMany<Track, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Platform, $this>
     */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'release_platforms');
    }

    /**
     * @return HasMany<ReleaseStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReleaseStatusLog::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<ReleaseStoreLink, $this>
     */
    public function storeLinks(): HasMany
    {
        return $this->hasMany(ReleaseStoreLink::class);
    }

    /**
     * @return HasMany<ReleaseRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(ReleaseRequest::class)->latest('id');
    }

    /**
     * @return HasMany<SpotifyMatch, $this>
     */
    public function spotifyMatches(): HasMany
    {
        return $this->hasMany(SpotifyMatch::class)->latest('id');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditableByOwner();
    }

    public function displayTitle(): string
    {
        $title = $this->title ?: __('release.untitled');

        return $this->version ? "{$title} ({$this->version})" : $title;
    }

    /**
     * Yayın düzeyindeki sanatçı adları, rol sırasıyla: "Ana sanatçı, Ana 2 feat. Konuk".
     * Çağıran, artists ilişkisini önceden yüklemelidir.
     */
    public function artistLine(): string
    {
        $primary = $this->artists->where('role', ArtistRole::Primary->value)->pluck('name')->implode(', ');
        $featuring = $this->artists->where('role', ArtistRole::Featuring->value)->pluck('name')->implode(', ');

        return trim($primary.($featuring !== '' ? ' feat. '.$featuring : ''));
    }

    public function totalDurationMs(): int
    {
        return (int) $this->tracks->sum('duration_ms');
    }
}
