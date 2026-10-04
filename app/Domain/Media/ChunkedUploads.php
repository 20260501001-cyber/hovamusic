<?php

namespace App\Domain\Media;

use App\Enums\MediaKind;
use App\Enums\MediaStatus;
use App\Enums\UploadStatus;
use App\Jobs\AnalyzeAudioFile;
use App\Models\MediaFile;
use App\Models\Track;
use App\Models\UploadSession;
use App\Models\User;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ses dosyaları parça parça yüklenir. Bağlantı koparsa istemci aynı dosya için
 * yeni bir oturum açmaz; sunucu kaç bayt aldığını söyler ve yükleme oradan sürer.
 * Tamamlanan dosya magic byte ile kontrol edilir, özel diske taşınır ve ffprobe
 * kontrolü media kuyruğuna bırakılır. Yarım kalan yüklemeler 24 saatte silinir.
 */
class ChunkedUploads
{
    public const CHUNK_SIZE = 4 * 1024 * 1024;

    public const MAX_CHUNK_SIZE = 8 * 1024 * 1024;

    public const EXPIRES_AFTER_HOURS = 24;

    public const DISK = 'private';

    private const EXTENSIONS = ['wav', 'flac'];

    public function __construct(private readonly Settings $settings) {}

    public function start(User $user, Track $track, string $fileName, int $size, string $fingerprint): UploadSession
    {
        $extension = Str::lower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw new UploadRejected(__('media.audio.extension', ['ext' => $extension !== '' ? '.'.$extension : __('media.no_extension')]));
        }

        if ($size <= 0) {
            throw new UploadRejected(__('media.audio.empty'));
        }

        if ($size > $this->settings->audioMaxBytes()) {
            throw new UploadRejected(__('media.audio.too_large', [
                'size' => Format::bytes($size),
                'max' => Format::bytes($this->settings->audioMaxBytes()),
            ]));
        }

        $existing = UploadSession::query()
            ->where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->where('fingerprint', $fingerprint)
            ->where('total_size', $size)
            ->where('status', UploadStatus::Uploading)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($existing && $this->disk()->exists($existing->temp_path)) {
            return $existing;
        }

        $ulid = Str::lower((string) Str::ulid());
        $tempPath = "uploads/{$ulid}.part";
        $this->disk()->put($tempPath, '');

        $session = new UploadSession([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'file_name' => Str::limit(basename($fileName), 250, ''),
            'extension' => $extension,
            'total_size' => $size,
            'received_bytes' => 0,
            'fingerprint' => $fingerprint,
            'temp_path' => $tempPath,
            'status' => UploadStatus::Uploading,
            'expires_at' => now()->addHours(self::EXPIRES_AFTER_HOURS),
        ]);
        $session->ulid = $ulid;
        $session->save();

        return $session;
    }

    /**
     * @param  resource  $stream
     *
     * @throws OffsetMismatch
     * @throws UploadRejected
     */
    public function append(UploadSession $session, int $offset, $stream): UploadSession
    {
        return Cache::lock('upload:'.$session->id, 120)->block(15, function () use ($session, $offset, $stream): UploadSession {
            $session->refresh();

            if ($session->status !== UploadStatus::Uploading || $session->expires_at->isPast()) {
                throw new UploadRejected(__('media.upload.expired'));
            }

            if ($offset !== $session->received_bytes) {
                throw new OffsetMismatch($session->received_bytes);
            }

            $remaining = $session->total_size - $session->received_bytes;
            $chunk = stream_get_contents($stream, min(self::MAX_CHUNK_SIZE, $remaining) + 1);
            $length = strlen((string) $chunk);

            if ($length === 0 || $length > min(self::MAX_CHUNK_SIZE, $remaining)) {
                throw new UploadRejected(__('media.upload.bad_chunk'));
            }

            $handle = fopen($this->disk()->path($session->temp_path), 'ab');
            $written = fwrite($handle, $chunk);
            fclose($handle);

            if ($written !== $length) {
                $this->truncate($session);

                throw new UploadRejected(__('media.upload.bad_chunk'));
            }

            $session->forceFill([
                'received_bytes' => $session->received_bytes + $written,
                'expires_at' => now()->addHours(self::EXPIRES_AFTER_HOURS),
            ])->save();

            return $session->isComplete() ? $this->finish($session) : $session;
        });
    }

    public function cancel(UploadSession $session): void
    {
        $this->disk()->delete($session->temp_path);
        $session->forceFill(['status' => UploadStatus::Cancelled])->save();
    }

    public function purgeExpired(): int
    {
        $count = 0;

        UploadSession::query()
            ->where('status', UploadStatus::Uploading)
            ->where('expires_at', '<=', now())
            ->each(function (UploadSession $session) use (&$count): void {
                $this->disk()->delete($session->temp_path);
                $session->forceFill(['status' => UploadStatus::Expired])->save();
                $count++;
            });

        return $count;
    }

    private function finish(UploadSession $session): UploadSession
    {
        $session->loadMissing(['user', 'track.audio']);
        $format = $this->detectFormat($this->disk()->path($session->temp_path));

        if ($format === null) {
            $this->disk()->delete($session->temp_path);
            $session->forceFill(['status' => UploadStatus::Cancelled])->save();

            throw new UploadRejected(__('media.audio.not_audio'));
        }

        $mediaUlid = Str::lower((string) Str::ulid());
        $path = "audio/{$session->user->ulid}/{$mediaUlid}.{$format}";
        $this->disk()->move($session->temp_path, $path);

        return DB::transaction(function () use ($session, $format, $mediaUlid, $path): UploadSession {
            $media = new MediaFile([
                'kind' => MediaKind::Audio,
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => $session->file_name,
                'mime' => $format === 'flac' ? 'audio/flac' : 'audio/wav',
                'size' => $session->total_size,
                'format' => $format,
                'validation_status' => MediaStatus::Pending,
            ]);
            $media->ulid = $mediaUlid;
            $media->user()->associate($session->user);
            $media->save();

            $track = $session->track;
            $previous = $track->audio;

            $track->forceFill(['audio_file_id' => $media->id, 'duration_ms' => null])->save();

            if ($previous && ! Track::query()->where('audio_file_id', $previous->id)->exists()) {
                $previous->deleteWithFile();
            }

            $session->forceFill(['status' => UploadStatus::Completed, 'media_file_id' => $media->id])->save();

            AnalyzeAudioFile::dispatch($media)->afterCommit();

            return $session->setRelation('mediaFile', $media);
        });
    }

    private function detectFormat(string $path): ?string
    {
        $handle = fopen($path, 'rb');
        $header = (string) fread($handle, 12);
        fclose($handle);

        return match (true) {
            str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WAVE' => 'wav',
            str_starts_with($header, 'fLaC') => 'flac',
            default => null,
        };
    }

    private function truncate(UploadSession $session): void
    {
        $handle = fopen($this->disk()->path($session->temp_path), 'r+b');
        ftruncate($handle, $session->received_bytes);
        fclose($handle);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(self::DISK);
    }
}
