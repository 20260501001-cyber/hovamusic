<?php

namespace App\Domain\Spotify;

final readonly class SpotifyTrack
{
    public function __construct(
        public string $id,
        public string $name,
        public string $url,
        public string $isrc,
        public ?string $albumId = null,
        public ?string $albumName = null,
        public ?string $albumUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        $album = is_array($data['album'] ?? null) ? $data['album'] : [];

        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            url: (string) ($data['external_urls']['spotify'] ?? 'https://open.spotify.com/track/'.$data['id']),
            isrc: strtoupper((string) ($data['external_ids']['isrc'] ?? '')),
            albumId: isset($album['id']) ? (string) $album['id'] : null,
            albumName: isset($album['name']) ? (string) $album['name'] : null,
            albumUrl: isset($album['id']) ? (string) ($album['external_urls']['spotify'] ?? 'https://open.spotify.com/album/'.$album['id']) : null,
        );
    }
}
