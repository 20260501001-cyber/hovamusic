<?php

namespace App\Domain\Spotify;

final readonly class SpotifyAlbum
{
    public function __construct(
        public string $id,
        public string $name,
        public string $url,
        public ?string $upc = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            url: (string) ($data['external_urls']['spotify'] ?? 'https://open.spotify.com/album/'.$data['id']),
            upc: isset($data['external_ids']['upc']) ? (string) $data['external_ids']['upc'] : null,
        );
    }
}
