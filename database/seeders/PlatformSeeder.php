<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

/**
 * Başlangıç mağaza listesi. Admin panelinden eklenir, sıralanır ya da pasife alınır;
 * seeder yalnızca eksik olanları ekler, mevcut kayıtlara dokunmaz.
 */
class PlatformSeeder extends Seeder
{
    private const PLATFORMS = [
        'spotify' => 'Spotify',
        'apple-music' => 'Apple Music',
        'youtube-music' => 'YouTube Music',
        'amazon-music' => 'Amazon Music',
        'deezer' => 'Deezer',
        'tidal' => 'TIDAL',
        'tiktok' => 'TikTok',
        'meta' => 'Instagram ve Facebook',
        'shazam' => 'Shazam',
        'soundcloud' => 'SoundCloud',
        'audiomack' => 'Audiomack',
        'anghami' => 'Anghami',
        'boomplay' => 'Boomplay',
        'pandora' => 'Pandora',
        'iheartradio' => 'iHeartRadio',
        'kkbox' => 'KKBOX',
        'jiosaavn' => 'JioSaavn',
        'fizy' => 'fizy',
        'muud' => 'Muud',
    ];

    public function run(): void
    {
        $sort = 0;

        foreach (self::PLATFORMS as $slug => $name) {
            Platform::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true, 'sort' => $sort++]);
        }
    }
}
