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

    /**
     * Yayın takibi: UPC ile albüm araması.
     *
     * @throws SpotifyUnavailable
     */
    public function findAlbumByUpc(string $upc): ?SpotifyAlbum;

    /**
     * Yayın takibi: ISRC ile parça araması.
     *
     * @throws SpotifyUnavailable
     */
    public function findTrackByIsrc(string $isrc): ?SpotifyTrack;
}
