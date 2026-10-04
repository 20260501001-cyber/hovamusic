<?php

namespace App\Domain\Releases;

use App\Domain\Artists\AppleMusicLinkParser;
use App\Domain\Spotify\SpotifyLinkParser;
use App\Enums\ArtistRole;
use App\Models\Artist;
use App\Models\ReleaseArtist;
use App\Models\TrackArtist;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Yayın ve parça sanatçılarını formdaki seçimden kayda çevirir. Ana sanatçı yalnızca
 * kullanıcının kendi profillerinden seçilir. Featuring ya profil ya da yalnızca bu
 * yayın için yazılan konuk addır; konuk profil sayılmaz, sanatçı limitine girmez.
 */
class ArtistCredits
{
    public const KIND_PROFILE = 'profile';

    public const KIND_GUEST = 'guest';

    /**
     * @var list<array<string, mixed>>
     */
    public array $rows = [];

    /**
     * @var array<string, string>
     */
    public array $errors = [];

    /**
     * @param  list<string>  $primary  Profil ULID'leri
     * @param  list<array<string, mixed>>  $featuring
     */
    public static function resolve(User $user, array $primary, array $featuring): self
    {
        $result = new self;
        $profileIds = collect($featuring)
            ->where('kind', self::KIND_PROFILE)
            ->pluck('artist')
            ->merge($primary)
            ->filter(fn ($id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values();

        /** @var Collection<string, Artist> $profiles */
        $profiles = $user->artists()->whereIn('ulid', $profileIds)->get()->keyBy('ulid');
        $used = [];

        foreach (array_values($primary) as $ulid) {
            $artist = $profiles->get($ulid);

            if ($artist === null) {
                $result->errors['primary_artists'] = __('release.validation.artist_not_found');

                continue;
            }

            if (isset($used[$artist->id])) {
                continue;
            }

            $used[$artist->id] = true;
            $result->rows[] = self::profileRow($artist, ArtistRole::Primary, count($result->rows));
        }

        $position = 0;

        foreach (array_values($featuring) as $i => $entry) {
            if (($entry['kind'] ?? null) === self::KIND_PROFILE) {
                $artist = $profiles->get((string) ($entry['artist'] ?? ''));

                if ($artist === null) {
                    if (filled($entry['artist'] ?? null)) {
                        $result->errors["featuring.{$i}.artist"] = __('release.validation.artist_not_found');
                    }

                    continue;
                }

                if (isset($used[$artist->id])) {
                    continue;
                }

                $used[$artist->id] = true;
                $result->rows[] = self::profileRow($artist, ArtistRole::Featuring, $position++);

                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $spotify = trim((string) ($entry['spotify'] ?? ''));
            $apple = trim((string) ($entry['apple'] ?? ''));
            $spotifyId = SpotifyLinkParser::artistId($spotify);
            $appleId = AppleMusicLinkParser::artistId($apple);

            if ($spotify !== '' && $spotifyId === null) {
                $result->errors["featuring.{$i}.spotify"] = __('artist.spotify_link_invalid');
            }

            if ($apple !== '' && $appleId === null) {
                $result->errors["featuring.{$i}.apple"] = __('artist.apple_invalid');
            }

            $result->rows[] = [
                'artist_id' => null,
                'name' => mb_substr($name, 0, 150),
                'role' => ArtistRole::Featuring->value,
                'spotify_artist_id' => $spotifyId,
                'apple_music_id' => $appleId,
                'position' => $position++,
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function featuringRows(): array
    {
        return array_values(array_filter($this->rows, fn (array $row): bool => $row['role'] === ArtistRole::Featuring->value));
    }

    /**
     * Kayıtlı sanatçı satırlarını forma geri çevirir.
     *
     * @param  iterable<ReleaseArtist|TrackArtist>  $credits
     * @return list<array<string, mixed>>
     */
    public static function featuringForm(iterable $credits): array
    {
        $entries = [];

        foreach ($credits as $credit) {
            if ($credit->role !== ArtistRole::Featuring->value) {
                continue;
            }

            $entries[] = $credit->artist_id !== null && $credit->artist !== null && ! $credit->artist->trashed()
                ? ['kind' => self::KIND_PROFILE, 'artist' => $credit->artist->ulid, 'name' => '', 'spotify' => '', 'apple' => '']
                : ['kind' => self::KIND_GUEST, 'artist' => null, 'name' => $credit->name, 'spotify' => (string) $credit->spotify_artist_id, 'apple' => (string) $credit->apple_music_id];
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private static function profileRow(Artist $artist, ArtistRole $role, int $position): array
    {
        return [
            'artist_id' => $artist->id,
            'name' => $artist->name,
            'role' => $role->value,
            'spotify_artist_id' => $artist->spotify_artist_id,
            'apple_music_id' => $artist->apple_music_id,
            'position' => $position,
        ];
    }
}
