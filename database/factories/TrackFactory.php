<?php

namespace Database\Factories;

use App\Models\Release;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'release_id' => Release::factory(),
            'position' => 1,
            'title' => fake()->words(2, true),
            'language' => 'tr',
        ];
    }
}
