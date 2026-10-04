<?php

namespace Database\Factories;

use App\Enums\ArtistRole;
use App\Enums\CreditRole;
use App\Enums\ReleaseStatus;
use App\Enums\ReleaseType;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\MediaFile;
use App\Models\Platform;
use App\Models\Release;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Release>
 */
class ReleaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => ReleaseType::Single,
            'title' => fake()->words(2, true),
            'label_name' => config('hova.default_label'),
            'language' => 'tr',
            'release_date' => today()->addDays(14),
            'p_line' => now()->year.' Hova Music',
            'c_line' => now()->year.' Hova Music',
        ];
    }

    public function status(ReleaseStatus $status): static
    {
        return $this->afterMaking(function (Release $release) use ($status): void {
            $release->status = $status;
        });
    }

    /**
     * Gönderilebilir yayın: ana sanatçı, tür, geçerli kapak, kontrol edilmiş tek parça
     * ve bir mağaza.
     */
    public function complete(int $tracks = 1, int $durationMs = 200_000): static
    {
        return $this->afterCreating(function (Release $release) use ($tracks, $durationMs): void {
            $user = $release->user;
            $artist = Artist::factory()->for($user)->create();

            $release->artists()->create([
                'artist_id' => $artist->id,
                'name' => $artist->name,
                'role' => ArtistRole::Primary->value,
                'position' => 0,
            ]);

            $genre = Genre::query()->whereNull('parent_id')->first()
                ?? Genre::query()->create(['name' => 'Pop', 'slug' => 'pop', 'is_active' => true]);
            $platform = Platform::query()->first()
                ?? Platform::query()->create(['name' => 'Spotify', 'slug' => 'spotify', 'is_active' => true]);

            $release->forceFill([
                'genre_id' => $genre->id,
                'cover_media_id' => MediaFile::factory()->for($user)->create()->id,
            ])->save();
            $release->platforms()->sync([$platform->id]);

            for ($position = 1; $position <= $tracks; $position++) {
                $audio = MediaFile::factory()->for($user)->audio($durationMs)->create();
                $track = Track::factory()->for($release)->create([
                    'position' => $position,
                    'audio_file_id' => $audio->id,
                    'duration_ms' => $durationMs,
                ]);
                $track->credits()->createMany([
                    ['role' => CreditRole::Composer->value, 'name' => 'Besteci Adı', 'position' => 0],
                    ['role' => CreditRole::Lyricist->value, 'name' => 'Söz Yazarı', 'position' => 0],
                ]);
            }

            $release->unsetRelations();
        });
    }
}
