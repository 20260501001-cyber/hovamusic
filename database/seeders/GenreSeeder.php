<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

/**
 * Geliştirme için örnek tür listesi. Üretimdeki liste admin panelinden yönetilir.
 */
class GenreSeeder extends Seeder
{
    private const GENRES = [
        'Pop' => ['Türkçe Pop', 'Dance Pop', 'Indie Pop'],
        'Rock' => ['Alternatif Rock', 'Anadolu Rock', 'Hard Rock'],
        'Hip-Hop/Rap' => ['Türkçe Rap', 'Trap'],
        'Elektronik' => ['House', 'Techno', 'Deep House'],
        'R&B/Soul' => [],
        'Caz' => [],
        'Klasik' => [],
        'Türk Halk Müziği' => [],
        'Türk Sanat Müziği' => [],
        'Arabesk' => [],
        'Metal' => [],
        'Dünya Müziği' => [],
        'Akustik' => [],
        'Enstrümantal' => [],
    ];

    public function run(): void
    {
        $sort = 0;

        foreach (self::GENRES as $name => $children) {
            $parent = Genre::query()->firstOrCreate(['name' => $name, 'parent_id' => null], ['is_active' => true, 'sort' => $sort++]);

            foreach ($children as $i => $child) {
                Genre::query()->firstOrCreate(['name' => $child, 'parent_id' => $parent->id], ['is_active' => true, 'sort' => $i]);
            }
        }
    }
}
