<?php

namespace App\Domain\Releases;

use App\Domain\Isrc\IsrcAllocator;
use App\Enums\ArtistRole;
use App\Enums\CreditRole;
use App\Enums\IsrcSource;
use App\Enums\MediaStatus;
use App\Enums\ReleaseType;
use App\Enums\TerritoryMode;
use App\Models\Release;
use App\Models\Track;
use App\Rules\Isrc;
use App\Rules\Upc;
use App\Support\Format;
use App\Support\Locale\Countries;
use App\Support\Locale\Languages;
use App\Support\Settings;
use Carbon\CarbonInterface;

/**
 * Yayının tamamını adım adım kontrol eder. Adım numarası sihirbazdaki sırayla
 * aynıdır: 1 bilgiler, 2 kapak, 3 parçalar, 4 mağazalar.
 *
 * Hatalar alan anahtarıyla döner; sihirbaz mesajı ilgili alanın altında, özet adımı
 * ise adım adım liste olarak gösterir. Gönderimde ($final) parça sayısı/süre kuralı ve
 * hâlâ kontrol edilen ses dosyaları da engeldir; adımlar arasında geçerken değildir.
 */
class ReleaseValidator
{
    public const STEP_INFO = 1;

    public const STEP_COVER = 2;

    public const STEP_TRACKS = 3;

    public const STEP_STORES = 4;

    public const SINGLE_MAX_TRACKS = 3;

    public const SINGLE_MAX_TRACK_MS = 10 * 60 * 1000;

    public const EP_MIN_TRACKS = 4;

    public const EP_MAX_TRACKS = 6;

    public const EP_MAX_TOTAL_MS = 30 * 60 * 1000;

    public const ALBUM_MIN_TRACKS = 7;

    public const ALBUM_MIN_TOTAL_MS = 30 * 60 * 1000;

    public const PREVIEW_LENGTH_SEC = 30;

    public function __construct(
        private readonly Settings $settings,
        private readonly IsrcAllocator $isrc,
    ) {}

    /**
     * Gönderim kontrolü.
     *
     * @return array<int, list<string>> Adım numarasına göre hata mesajları; boşsa yayın gönderilebilir.
     */
    public function validate(Release $release): array
    {
        return array_map(array_values(...), $this->issues($release));
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function issues(Release $release, bool $final = true): array
    {
        $release->loadMissing(['artists', 'cover', 'platforms', 'genre', 'subgenre', 'tracks.audio', 'tracks.credits']);

        return array_filter([
            self::STEP_INFO => $this->info($release),
            self::STEP_COVER => $this->cover($release),
            self::STEP_TRACKS => $this->tracks($release, $final),
            self::STEP_STORES => $this->stores($release),
        ]);
    }

    /**
     * Bir adımdan sonrakine geçerken tamamlanması gerekenler.
     *
     * @return array<string, string>
     */
    public function step(Release $release, int $step): array
    {
        return $this->issues($release, final: false)[$step] ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function info(Release $release): array
    {
        $errors = [];

        if ($release->type === null) {
            $errors['type'] = __('release.validation.type_required');
        }

        if (blank($release->title)) {
            $errors['title'] = __('release.validation.title_required');
        }

        if ($release->artists->where('role', ArtistRole::Primary->value)->isEmpty()) {
            $errors['primary_artists'] = __('release.validation.primary_artist_required');
        }

        if (blank($release->label_name)) {
            $errors['label_name'] = __('release.validation.label_required');
        }

        if ($release->genre === null || ! $release->genre->is_active || $release->genre->parent_id !== null) {
            $errors['genre_id'] = __('release.validation.genre_required');
        } elseif ($release->subgenre !== null && $release->subgenre->parent_id !== $release->genre->id) {
            $errors['subgenre_id'] = __('release.validation.subgenre_mismatch');
        }

        if (! in_array($release->language, Languages::codes(), true)) {
            $errors['language'] = __('release.validation.language_required');
        }

        $earliest = $this->earliestReleaseDate();

        if ($release->release_date === null) {
            $errors['release_date'] = __('release.validation.release_date_required');
        } elseif ($release->release_date->lt($earliest)) {
            $errors['release_date'] = __('release.validation.release_date_too_early', [
                'date' => Format::longDate($release->release_date),
                'earliest' => Format::longDate($earliest),
            ]);
        }

        if ($release->original_release_date !== null && $release->release_date !== null
            && $release->original_release_date->gt($release->release_date)) {
            $errors['original_release_date'] = __('release.validation.original_date_after_release');
        }

        foreach (['p_line', 'c_line'] as $field) {
            if (! self::isValidCopyrightLine($release->{$field})) {
                $errors[$field] = __("release.validation.{$field}_format");
            }
        }

        if (! $release->explicit && $release->tracks->contains(fn (Track $track): bool => $track->explicit)) {
            $errors['explicit'] = __('release.validation.explicit_mismatch');
        }

        if (filled($release->upc)) {
            if (! preg_match('/^\d{12,13}$/', $release->upc)) {
                $errors['upc'] = __('release.validation.upc_format', ['length' => mb_strlen($release->upc)]);
            } elseif (! Upc::hasValidCheckDigit($release->upc)) {
                $errors['upc'] = __('release.validation.upc_check_digit');
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function cover(Release $release): array
    {
        $cover = $release->cover;

        if ($cover === null) {
            return ['cover' => __('release.validation.cover_required')];
        }

        if ($cover->validation_status !== MediaStatus::Valid) {
            return ['cover' => __('release.validation.cover_invalid')];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function tracks(Release $release, bool $final): array
    {
        $tracks = $release->tracks;

        if ($tracks->isEmpty()) {
            return ['tracks' => __('release.validation.tracks_required')];
        }

        $errors = [];
        $seenIsrc = [];

        foreach ($tracks as $track) {
            $label = __('release.validation.track_label', ['position' => Format::position($track->position)]);

            foreach ($this->trackIssues($track, $final) as $field => $message) {
                $errors["track.{$track->ulid}.{$field}"] = $label.' '.$message;
            }

            if (filled($track->isrc)) {
                if (isset($seenIsrc[$track->isrc])) {
                    $errors["track.{$track->ulid}.isrc_duplicate"] = $label.' '.__('release.validation.isrc_duplicate', ['isrc' => $track->formattedIsrc()]);
                }

                $seenIsrc[$track->isrc] = true;
            }
        }

        $allMeasured = $tracks->every(fn (Track $track): bool => $track->duration_ms !== null);

        if ($final && $release->type !== null && $allMeasured) {
            foreach ($this->typeRules($release) as $i => $message) {
                $errors["type_rules.{$i}"] = $message;
            }
        }

        return $errors;
    }

    /**
     * Tek parçanın eksikleri; mesajlarda parça numarası yoktur.
     *
     * @return array<string, string>
     */
    public function trackIssues(Track $track, bool $final = true): array
    {
        $track->loadMissing(['audio', 'credits']);
        $errors = [];

        if (blank($track->title)) {
            $errors['title'] = __('release.validation.track_title_required');
        }

        if ($track->audio === null) {
            $errors['audio'] = __('release.validation.track_audio_required');
        } elseif ($track->audio->validation_status === MediaStatus::Invalid) {
            $errors['audio'] = __('release.validation.track_audio_invalid');
        } elseif ($final && $track->audio->validation_status === MediaStatus::Pending) {
            $errors['audio'] = __('release.validation.track_audio_pending');
        }

        if (! in_array($track->language, Languages::codes(), true)) {
            $errors['language'] = __('release.validation.track_language_required');
        }

        if ($track->has_own_isrc && blank($track->isrc)) {
            $errors['isrc'] = __('release.validation.isrc_own_missing');
        } elseif (filled($track->isrc) && Isrc::normalize($track->isrc) === null) {
            $errors['isrc'] = __('release.validation.isrc_format');
        } elseif ($track->isrc_source === IsrcSource::User && $this->isrc->isReserved($track->isrc)) {
            $errors['isrc'] = __('isrc.reserved', ['prefix' => $this->isrc->registrant()]);
        }

        if ($track->creditNames(CreditRole::Composer) === []) {
            $errors['composers'] = __('release.validation.composer_required');
        }

        if ($track->language !== Languages::INSTRUMENTAL && $track->creditNames(CreditRole::Lyricist) === []) {
            $errors['lyricists'] = __('release.validation.lyricist_required');
        }

        if ($track->duration_ms !== null) {
            $latestStart = max(0, intdiv($track->duration_ms, 1000) - self::PREVIEW_LENGTH_SEC);

            if ($track->preview_start_sec > $latestStart) {
                $errors['preview_start_sec'] = __('release.validation.preview_too_late', [
                    'start' => Format::duration($track->preview_start_sec * 1000),
                    'duration' => Format::duration($track->duration_ms),
                    'latest' => Format::duration($latestStart * 1000),
                ]);
            }
        }

        return $errors;
    }

    /**
     * Mağaza kuralı: Single 1–3 parça ve her biri 10 dakikanın altında; EP 4–6 parça ve
     * toplam 30 dakikanın altında; Albüm en az 7 parça ya da toplam 30 dakika ve üstü.
     *
     * @return list<string>
     */
    private function typeRules(Release $release): array
    {
        $count = $release->tracks->count();
        $total = (int) $release->tracks->sum('duration_ms');

        return match ($release->type) {
            ReleaseType::Single => [
                ...($count > self::SINGLE_MAX_TRACKS ? [__('release.validation.single_track_count', ['count' => $count])] : []),
                ...$release->tracks
                    ->filter(fn (Track $track): bool => $track->duration_ms >= self::SINGLE_MAX_TRACK_MS)
                    ->map(fn (Track $track): string => __('release.validation.single_track_length', [
                        'position' => Format::position($track->position),
                        'duration' => Format::duration($track->duration_ms),
                    ]))
                    ->values()
                    ->all(),
            ],
            ReleaseType::Ep => array_values(array_filter([
                $count < self::EP_MIN_TRACKS || $count > self::EP_MAX_TRACKS
                    ? __('release.validation.ep_track_count', ['count' => $count]) : null,
                $total >= self::EP_MAX_TOTAL_MS
                    ? __('release.validation.ep_total_length', ['duration' => Format::duration($total)]) : null,
            ])),
            ReleaseType::Album => $count < self::ALBUM_MIN_TRACKS && $total < self::ALBUM_MIN_TOTAL_MS
                ? [__('release.validation.album_too_short', ['count' => $count, 'duration' => Format::duration($total)])]
                : [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function stores(Release $release): array
    {
        $errors = [];

        if ($release->platforms->where('is_active', true)->isEmpty()) {
            $errors['platforms'] = __('release.validation.platforms_required');
        }

        if ($release->territory_mode !== TerritoryMode::Worldwide) {
            $countries = array_intersect($release->territories ?? [], Countries::codes());

            if ($countries === []) {
                $errors['territories'] = __('release.validation.territories_required');
            }
        }

        return $errors;
    }

    public function earliestReleaseDate(): CarbonInterface
    {
        return today()->addDays($this->settings->releaseLeadDays());
    }

    public static function isValidCopyrightLine(?string $line): bool
    {
        return is_string($line) && (bool) preg_match('/^(19|20)\d{2} \S.{0,190}$/u', trim($line));
    }
}
