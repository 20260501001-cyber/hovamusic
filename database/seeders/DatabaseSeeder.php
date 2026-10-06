<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, PlatformSeeder::class, FaqSeeder::class]);

        if (app()->environment('local')) {
            $this->call([GenreSeeder::class, DemoSeeder::class]);
        }
    }
}
