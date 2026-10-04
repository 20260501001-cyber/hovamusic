<?php

namespace App\Domain\Spotify;

final readonly class SpotifyArtist
{
    public function __construct(
        public string $id,
        public string $name,
        public string $url,
        public ?string $imageUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        $images = collect($data['images'] ?? [])
            ->filter(fn ($image): bool => is_array($image) && filled($image['url'] ?? null))
            ->sortBy(fn (array $image): int => (int) ($image['width'] ?? 0));

        $image = $images->first(fn (array $image): bool => (int) ($image['width'] ?? 0) >= 64) ?? $images->last();

        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            url: (string) ($data['external_urls']['spotify'] ?? 'https://open.spotify.com/artist/'.$data['id']),
            imageUrl: $image['url'] ?? null,
        );
    }

    /**
     * @return array{id: string, name: string, url: string, image_url: string|null}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'url' => $this->url, 'image_url' => $this->imageUrl];
    }
}
