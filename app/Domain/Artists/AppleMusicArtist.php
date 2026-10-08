<?php

namespace App\Domain\Artists;

final readonly class AppleMusicArtist
{
    public function __construct(
        public string $id,
        public string $name,
        public string $url,
        public ?string $genre = null,
    ) {}

    /**
     * @return array{id: string, name: string, url: string, image_url: null, genre: string|null}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'url' => $this->url, 'image_url' => null, 'genre' => $this->genre];
    }
}
