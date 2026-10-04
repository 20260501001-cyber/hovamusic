<?php

namespace App\Domain\Delivery;

use App\Enums\ArtistRole;
use App\Enums\CreditRole;
use App\Enums\TerritoryMode;
use App\Models\MediaFile;
use App\Models\Release;
use App\Models\ReleaseArtist;
use App\Models\Track;
use App\Models\TrackArtist;
use App\Support\Format;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Yayın paketindeki metadata.json ve metadata.xlsx. Believe şablonu henüz yok;
 * XLSX'te her parça bir satırdır ve yayın bilgileri her satırda tekrarlanır.
 * Sütun sırası COLUMNS sabitindedir.
 */
class ReleaseMetadata
{
    /**
     * @var list<string>
     */
    public const COLUMNS = [
        'UPC', 'Release Title', 'Release Version', 'Release Type', 'Release Primary Artist(s)', 'Release Featuring Artist(s)',
        'Label', 'Genre', 'Subgenre', 'Release Language', 'Release Date', 'Original Release Date', 'P Line', 'C Line',
        'Explicit (Release)', 'Territories', 'Stores',
        'Track Number', 'ISRC', 'Track Title', 'Track Version', 'Track Primary Artist(s)', 'Track Featuring Artist(s)',
        'Composers', 'Lyricists', 'Producers', 'Track Language', 'Explicit (Track)', 'Duration', 'Preview Start (sec)',
        'Audio File', 'Cover File',
        'Primary Artist Spotify ID(s)', 'Primary Artist Apple Music ID(s)', 'Lyrics',
    ];

    public const RELATIONS = [
        'user', 'cover', 'genre', 'subgenre', 'platforms', 'artists',
        'tracks.audio', 'tracks.artists', 'tracks.credits',
    ];

    public function __construct(private readonly Release $release)
    {
        $this->release->loadMissing(self::RELATIONS);
    }

    public function coverFileName(): ?string
    {
        $cover = $this->release->cover;

        return $cover ? 'cover.'.$this->extension($cover, 'jpg') : null;
    }

    public function audioFileName(Track $track): ?string
    {
        if ($track->audio === null) {
            return null;
        }

        $title = self::safeName($track->displayTitle());

        return sprintf('audio/%02d - %s.%s', $track->position, $title, $this->extension($track->audio, 'wav'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $release = $this->release;

        return [
            'generated_at' => now()->toIso8601String(),
            'release' => [
                'id' => $release->ulid,
                'upc' => $release->upc,
                'title' => $release->title,
                'version' => $release->version,
                'type' => $release->type?->value,
                'label' => $release->label_name,
                'genre' => $release->genre?->name,
                'subgenre' => $release->subgenre?->name,
                'language' => $release->language,
                'release_date' => $release->release_date?->toDateString(),
                'original_release_date' => $release->original_release_date?->toDateString(),
                'p_line' => $release->p_line,
                'c_line' => $release->c_line,
                'explicit' => (bool) $release->explicit,
                'territories' => [
                    'mode' => $release->territory_mode->value,
                    'countries' => array_values($release->territories ?? []),
                ],
                'stores' => $release->platforms->pluck('name')->values()->all(),
                'primary_artists' => $this->artistList($release->artists, ArtistRole::Primary),
                'featuring_artists' => $this->artistList($release->artists, ArtistRole::Featuring),
                'cover' => $release->cover ? [
                    'file' => $this->coverFileName(),
                    'format' => $release->cover->format,
                    'width' => $release->cover->width,
                    'height' => $release->cover->height,
                    'sha256' => $release->cover->sha256,
                ] : null,
                'owner' => [
                    'id' => $release->user?->ulid,
                    'name' => $release->user?->name,
                    'email' => $release->user?->email,
                ],
            ],
            'tracks' => $release->tracks->map(fn (Track $track): array => [
                'position' => $track->position,
                'isrc' => $track->isrc,
                'title' => $track->title,
                'version' => $track->version,
                'primary_artists' => $this->trackPrimary($track)->pluck('name')->values()->all(),
                'featuring_artists' => $track->artists->where('role', ArtistRole::Featuring->value)->pluck('name')->values()->all(),
                'composers' => $track->creditNames(CreditRole::Composer),
                'lyricists' => $track->creditNames(CreditRole::Lyricist),
                'producers' => $track->creditNames(CreditRole::Producer),
                'language' => $track->language,
                'explicit' => (bool) $track->explicit,
                'duration_ms' => $track->duration_ms,
                'preview_start_sec' => $track->preview_start_sec,
                'lyrics' => $track->lyrics,
                'audio' => $track->audio ? [
                    'file' => $this->audioFileName($track),
                    'original_name' => $track->audio->original_name,
                    'format' => $track->audio->format,
                    'codec' => $track->audio->codec,
                    'sample_rate' => $track->audio->sample_rate,
                    'bit_depth' => $track->audio->bit_depth,
                    'channels' => $track->audio->channels,
                    'size' => $track->audio->size,
                    'sha256' => $track->audio->sha256,
                ] : null,
            ])->values()->all(),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<list<string|int|null>>
     */
    public function rows(): array
    {
        $release = $this->release;
        $primary = $release->artists->where('role', ArtistRole::Primary->value);

        $releaseColumns = [
            $release->upc,
            $release->title,
            $release->version,
            $release->type ? strtoupper($release->type->value) : null,
            $primary->pluck('name')->implode(', '),
            $release->artists->where('role', ArtistRole::Featuring->value)->pluck('name')->implode(', '),
            $release->label_name,
            $release->genre?->name,
            $release->subgenre?->name,
            $release->language,
            $release->release_date?->toDateString(),
            $release->original_release_date?->toDateString(),
            $release->p_line,
            $release->c_line,
            $release->explicit ? 'Yes' : 'No',
            $this->territories(),
            $release->platforms->pluck('name')->implode(', '),
        ];

        return $release->tracks->map(fn (Track $track): array => [
            ...$releaseColumns,
            $track->position,
            $track->isrc,
            $track->title,
            $track->version,
            $this->trackPrimary($track)->pluck('name')->implode(', '),
            $track->artists->where('role', ArtistRole::Featuring->value)->pluck('name')->implode(', '),
            implode(', ', $track->creditNames(CreditRole::Composer)),
            implode(', ', $track->creditNames(CreditRole::Lyricist)),
            implode(', ', $track->creditNames(CreditRole::Producer)),
            $track->language,
            $track->explicit ? 'Yes' : 'No',
            $track->duration_ms ? Format::duration($track->duration_ms) : null,
            $track->preview_start_sec,
            $this->audioFileName($track),
            $this->coverFileName(),
            $primary->pluck('spotify_artist_id')->filter()->implode(', '),
            $primary->pluck('apple_music_id')->filter()->implode(', '),
            $track->lyrics,
        ])->values()->all();
    }

    /**
     * XLSX'i geçici dosyaya yazar; çağıran dosyayı siler.
     */
    public function writeXlsx(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::COLUMNS, (new Style)->setFontBold()));

        foreach ($this->rows() as $row) {
            $writer->addRow(Row::fromValues(array_map(fn ($value) => $value ?? '', $row)));
        }

        $writer->close();
    }

    public static function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\/\\\\:*?"<>|]+/u', '_', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '', ' .');

        return mb_substr($name !== '' ? $name : 'dosya', 0, 120);
    }

    /**
     * @param  Collection<int, ReleaseArtist>  $artists
     * @return list<array{name: string, spotify_id: string|null, apple_music_id: string|null}>
     */
    private function artistList(Collection $artists, ArtistRole $role): array
    {
        return $artists->where('role', $role->value)->map(fn (ReleaseArtist $artist): array => [
            'name' => $artist->name,
            'spotify_id' => $artist->spotify_artist_id,
            'apple_music_id' => $artist->apple_music_id,
        ])->values()->all();
    }

    /**
     * Parçada ayrıca ana sanatçı yoksa yayının ana sanatçıları geçerlidir.
     *
     * @return Collection<int, ReleaseArtist|TrackArtist>
     */
    private function trackPrimary(Track $track): Collection
    {
        $own = $track->artists->where('role', ArtistRole::Primary->value);

        return $own->isNotEmpty() ? $own : $this->release->artists->where('role', ArtistRole::Primary->value);
    }

    private function territories(): string
    {
        $countries = implode(', ', $this->release->territories ?? []);

        return match ($this->release->territory_mode) {
            TerritoryMode::Worldwide => 'Worldwide',
            TerritoryMode::Include => 'Only: '.$countries,
            TerritoryMode::Exclude => 'Worldwide except: '.$countries,
        };
    }

    private function extension(MediaFile $media, string $fallback): string
    {
        $extension = strtolower(pathinfo((string) $media->path, PATHINFO_EXTENSION));

        if ($extension === 'jpeg') {
            return 'jpg';
        }

        return preg_match('/^[a-z0-9]{2,5}$/', $extension) ? $extension : $fallback;
    }
}
