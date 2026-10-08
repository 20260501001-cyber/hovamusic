<?php

namespace App\Domain\Artists;

use App\Domain\Spotify\SpotifyArtist;
use App\Domain\Spotify\SpotifyCatalog;
use App\Domain\Spotify\SpotifyLinkParser;
use App\Domain\Spotify\SpotifyUnavailable;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sanatçı adıyla Spotify ve Apple Music profili arar. Kullanıcı başına arama
 * sınırı uygular; hata yerine kullanıcıya gösterilecek mesaj döner.
 */
class ArtistFinder
{
    private const SEARCHES_PER_MINUTE = 30;

    /**
     * Listede gösterilen en fazla sonuç; doğru profil çoğunlukla ilk sıralardadır.
     */
    private const RESULTS = 5;

    public function __construct(
        private readonly SpotifyCatalog $spotify,
        private readonly AppleMusicCatalog $apple,
    ) {}

    /**
     * @return array{results: list<array{id: string, name: string, url: string, image_url: string|null}>, message: string}
     */
    public function spotify(string $name, int|string $userId): array
    {
        $name = trim($name);

        if (mb_strlen($name) < 2) {
            return ['results' => [], 'message' => ''];
        }

        if (! $this->allow('spotify-search:'.$userId)) {
            return ['results' => [], 'message' => __('artist.search_throttled')];
        }

        try {
            $results = array_map(fn (SpotifyArtist $artist): array => $artist->toArray(), array_slice($this->spotify->searchArtists($name), 0, self::RESULTS));
        } catch (SpotifyUnavailable) {
            return ['results' => [], 'message' => __('artist.spotify_unavailable')];
        }

        return ['results' => $results, 'message' => $results === [] ? __('artist.spotify_no_results', ['query' => $name]) : ''];
    }

    /**
     * Yapıştırılan Spotify linkini doğrular.
     *
     * @return array{artist: array{id: string, name: string, url: string, image_url: string|null}|null, message: string}
     */
    public function spotifyLink(string $link, int|string $userId): array
    {
        $id = SpotifyLinkParser::artistId($link);

        if ($id === null) {
            return ['artist' => null, 'message' => trim($link) === '' ? '' : __('artist.spotify_link_invalid')];
        }

        if (! $this->allow('spotify-search:'.$userId)) {
            return ['artist' => null, 'message' => __('artist.search_throttled')];
        }

        try {
            $artist = $this->spotify->findArtist($id);
        } catch (SpotifyUnavailable) {
            return ['artist' => null, 'message' => __('artist.spotify_unavailable')];
        }

        return $artist === null
            ? ['artist' => null, 'message' => __('artist.spotify_not_found')]
            : ['artist' => $artist->toArray(), 'message' => ''];
    }

    /**
     * @return array{results: list<array{id: string, name: string, url: string, image_url: null, genre: string|null}>, message: string}
     */
    public function apple(string $name, int|string $userId): array
    {
        $name = trim($name);

        if (mb_strlen($name) < 2) {
            return ['results' => [], 'message' => ''];
        }

        if (! $this->allow('apple-search:'.$userId)) {
            return ['results' => [], 'message' => __('artist.search_throttled')];
        }

        try {
            $results = array_map(fn (AppleMusicArtist $artist): array => $artist->toArray(), array_slice($this->apple->searchArtists($name), 0, self::RESULTS));
        } catch (AppleMusicUnavailable) {
            return ['results' => [], 'message' => __('artist.apple_unavailable')];
        }

        return ['results' => $results, 'message' => $results === [] ? __('artist.apple_no_results', ['query' => $name]) : ''];
    }

    private function allow(string $key): bool
    {
        if (RateLimiter::tooManyAttempts($key, self::SEARCHES_PER_MINUTE)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }
}
