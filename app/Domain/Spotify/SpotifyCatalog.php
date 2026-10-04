<?php

namespace App\Domain\Spotify;

interface SpotifyCatalog
{
    public function isAvailable(): bool;

    /**
     * En fazla 10 sonuç döner.
     *
     * @return list<SpotifyArtist>
     *
     * @throws SpotifyUnavailable
     */
    public function searchArtists(string $query): array;

    /**
     * @throws SpotifyUnavailable
     */
    public function findArtist(string $id): ?SpotifyArtist;
}
