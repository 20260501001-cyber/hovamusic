<?php

namespace App\Jobs;

use App\Domain\Media\AudioProbe;
use App\Domain\Media\AudioValidator;
use App\Enums\MediaKind;
use App\Enums\MediaStatus;
use App\Models\DuplicateFlag;
use App\Models\MediaFile;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Yüklenen ses dosyasını ffprobe ile ölçer, kurallara göre işaretler ve SHA-256
 * özetiyle başka bir hesapta aynı dosya varsa admin için mükerrer kaydı açar.
 */
class AnalyzeAudioFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public MediaFile $media)
    {
        $this->onQueue('media');
    }

    public function handle(AudioProbe $probe, AudioValidator $validator): void
    {
        $media = $this->media;
        $path = $media->absolutePath();

        if (! is_file($path)) {
            return;
        }

        $sha256 = hash_file('sha256', $path);
        $result = $probe->probe($path);
        $errors = $result ? $validator->errors($result) : [__('media.audio.unreadable')];

        $media->forceFill([
            'sha256' => $sha256,
            'codec' => $result?->codec,
            'sample_rate' => $result?->sampleRate,
            'bit_depth' => $result?->bitDepth,
            'channels' => $result?->channels,
            'duration_ms' => $result?->durationMs,
            'validation_status' => $errors === [] ? MediaStatus::Valid : MediaStatus::Invalid,
            'validation_errors' => $errors === [] ? null : $errors,
            'analyzed_at' => now(),
        ])->save();

        Track::query()->where('audio_file_id', $media->id)->update(['duration_ms' => $result?->durationMs]);

        MediaFile::query()
            ->where('kind', MediaKind::Audio)
            ->where('sha256', $sha256)
            ->where('user_id', '!=', $media->user_id)
            ->pluck('id')
            ->each(fn (int $matchedId) => DuplicateFlag::query()->firstOrCreate([
                'media_file_id' => $media->id,
                'matched_media_file_id' => $matchedId,
            ]));
    }
}
