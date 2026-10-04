<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->name(),
            'spotify_artist_id' => null,
            'create_new_spotify' => true,
            'apple_music_id' => null,
            'create_new_apple' => true,
        ];
    }

    public function onSpotify(?string $id = null): static
    {
        return $this->state(function () use ($id): array {
            $id ??= Str::random(22);

            return [
                'spotify_artist_id' => $id,
                'spotify_url' => "https://open.spotify.com/artist/{$id}",
                'create_new_spotify' => false,
            ];
        });
    }
}
