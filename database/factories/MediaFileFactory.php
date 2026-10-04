<?php

namespace Database\Factories;

use App\Enums\MediaKind;
use App\Enums\MediaStatus;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Kayıt yalnızca veritabanına yazılır; dosyanın kendisi gerekiyorsa test oluşturur.
 *
 * @extends Factory<MediaFile>
 */
class MediaFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => MediaKind::Cover,
            'disk' => 'private',
            'path' => 'covers/test/'.Str::lower((string) Str::ulid()).'.jpg',
            'original_name' => 'kapak.jpg',
            'mime' => 'image/jpeg',
            'size' => 2_400_000,
            'sha256' => hash('sha256', Str::random(32)),
            'format' => 'jpeg',
            'width' => 3000,
            'height' => 3000,
            'color_space' => 'RGB',
            'validation_status' => MediaStatus::Valid,
            'analyzed_at' => now(),
        ];
    }

    public function audio(int $durationMs = 200_000): static
    {
        return $this->state(fn (): array => [
            'kind' => MediaKind::Audio,
            'path' => 'audio/test/'.Str::lower((string) Str::ulid()).'.wav',
            'original_name' => 'parca.wav',
            'mime' => 'audio/wav',
            'size' => 35_000_000,
            'format' => 'wav',
            'width' => null,
            'height' => null,
            'color_space' => null,
            'codec' => 'pcm_s24le',
            'sample_rate' => 44100,
            'bit_depth' => 24,
            'channels' => 2,
            'duration_ms' => $durationMs,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'validation_status' => MediaStatus::Pending,
            'analyzed_at' => null,
        ]);
    }

    public function invalid(): static
    {
        return $this->state(fn (): array => [
            'validation_status' => MediaStatus::Invalid,
            'validation_errors' => ['Örnekleme hızı 22,05 kHz; en az 44,1 kHz olmalı.'],
        ]);
    }
}
