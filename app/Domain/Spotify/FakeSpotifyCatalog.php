<?php

namespace App\Domain\Spotify;

/**
 * Testlerde ve anahtarsız geliştirmede kullanılan sahte katalog. Ağ isteği yapmaz.
 */
class FakeSpotifyCatalog implements SpotifyCatalog
{
    /**
     * @var array<string, SpotifyArtist>
     */
    private array $artists = [];

    /**
     * @var array<string, SpotifyAlbum>
     */
    private array $albums = [];

    /**
     * @var array<string, SpotifyTrack>
     */
    private array $tracks = [];

    public int $lookups = 0;

    public function __construct()
    {
        $this->add(new SpotifyArtist('4tZwfgrHOc3mvqYlEYSvVi', 'Deniz Yılmaz', 'https://open.spotify.com/artist/4tZwfgrHOc3mvqYlEYSvVi', 'https://i.scdn.co/image/deniz'));
        $this->add(new SpotifyArtist('6qqNVTkY8uBg9cP3Jd7DAH', 'Deniz Kara', 'https://open.spotify.com/artist/6qqNVTkY8uBg9cP3Jd7DAH', null));
        $this->add(new SpotifyArtist('1Xyo4u8uXC1ZmMpatF05PJ', 'Mavi Gece', 'https://open.spotify.com/artist/1Xyo4u8uXC1ZmMpatF05PJ', 'https://i.scdn.co/image/mavi'));
    }

    public function add(SpotifyArtist $artist): static
    {
        $this->artists[$artist->id] = $artist;

        return $this;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function searchArtists(string $query): array
    {
        $needle = mb_strtolower(trim($query));

        if (mb_strlen($needle) < 2) {
            return [];
        }

        return array_values(array_slice(array_filter(
            $this->artists,
            fn (SpotifyArtist $artist): bool => str_contains(mb_strtolower($artist->name), $needle),
        ), 0, 10));
    }

    public function findArtist(string $id): ?SpotifyArtist
    {
        return $this->artists[$id] ?? null;
    }

    public function addAlbum(string $upc, SpotifyAlbum $album): static
    {
        $this->albums[$upc] = $album;

        return $this;
    }

    public function addTrack(SpotifyTrack $track): static
    {
        $this->tracks[$track->isrc] = $track;

        return $this;
    }

    public function findAlbumByUpc(string $upc): ?SpotifyAlbum
    {
        $this->lookups++;

        return $this->albums[$upc] ?? null;
    }

    public function findTrackByIsrc(string $isrc): ?SpotifyTrack
    {
        $this->lookups++;

        return $this->tracks[strtoupper($isrc)] ?? null;
    }
}
