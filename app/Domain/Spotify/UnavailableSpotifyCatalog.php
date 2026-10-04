<?php

namespace App\Domain\Spotify;

use Illuminate\Support\Facades\Log;

/**
 * Spotify anahtarları tanımlı değilken kullanılır: arama yapılamaz, kullanıcıya
 * bilgi verilir ve "yeni profil oluşturulsun" seçeneği açık kalır.
 */
class UnavailableSpotifyCatalog implements SpotifyCatalog
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function searchArtists(string $query): array
    {
        Log::notice('Spotify araması yapılamadı: SPOTIFY_CLIENT_ID / SPOTIFY_CLIENT_SECRET tanımlı değil.');

        throw new SpotifyUnavailable('Spotify bağlantısı yapılandırılmadı.');
    }

    public function findArtist(string $id): ?SpotifyArtist
    {
        throw new SpotifyUnavailable('Spotify bağlantısı yapılandırılmadı.');
    }

    public function findAlbumByUpc(string $upc): ?SpotifyAlbum
    {
        throw new SpotifyUnavailable('Spotify bağlantısı yapılandırılmadı.');
    }

    public function findTrackByIsrc(string $isrc): ?SpotifyTrack
    {
        throw new SpotifyUnavailable('Spotify bağlantısı yapılandırılmadı.');
    }
}
