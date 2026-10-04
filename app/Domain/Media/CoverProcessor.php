<?php

namespace App\Domain\Media;

use App\Enums\MediaKind;
use App\Enums\MediaStatus;
use App\Models\MediaFile;
use App\Models\User;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Kapak kontrolü: 3000×3000 px kare, JPG ya da PNG, RGB, en fazla 20 MB (admin
 * ayarı). Uzantıya değil magic byte ve MIME tipine bakılır. Geçen dosya yeniden
 * kodlanarak EXIF ve diğer metadata temizlenir, özel diske yazılır.
 */
class CoverProcessor
{
    public const SIZE = 3000;

    public const DISK = 'private';

    private const JPEG_QUALITY = 95;

    public function __construct(private readonly Settings $settings) {}

    /**
     * @throws CoverRejected
     */
    public function store(User $user, string $sourcePath, string $originalName): MediaFile
    {
        $format = $this->inspect($sourcePath);
        $ulid = Str::lower((string) Str::ulid());
        $path = "covers/{$user->ulid}/{$ulid}.".($format === 'png' ? 'png' : 'jpg');

        try {
            $image = ImageManager::usingDriver(GdDriver::class)->decodePath($sourcePath);
            $encoded = $format === 'png'
                ? $image->encode(new PngEncoder)
                : $image->encode(new JpegEncoder(quality: self::JPEG_QUALITY, strip: true));
        } catch (Throwable) {
            throw new CoverRejected([__('media.cover.unreadable')]);
        }

        Storage::disk(self::DISK)->put($path, (string) $encoded);
        $absolute = Storage::disk(self::DISK)->path($path);

        $media = new MediaFile([
            'kind' => MediaKind::Cover,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => Str::limit(basename($originalName), 250, ''),
            'mime' => $format === 'png' ? 'image/png' : 'image/jpeg',
            'size' => (int) filesize($absolute),
            'sha256' => hash_file('sha256', $absolute),
            'format' => $format === 'png' ? 'png' : 'jpeg',
            'width' => self::SIZE,
            'height' => self::SIZE,
            'color_space' => 'RGB',
            'validation_status' => MediaStatus::Valid,
            'analyzed_at' => now(),
        ]);
        $media->ulid = $ulid;
        $media->user()->associate($user);
        $media->save();

        return $media;
    }

    /**
     * Dosyayı ölçer; kuralları karşılamıyorsa ölçülen değerleri söyleyen tüm hataları
     * birlikte döndürür.
     *
     * @return 'jpeg'|'png'
     *
     * @throws CoverRejected
     */
    public function inspect(string $path): string
    {
        $size = (int) @filesize($path);
        $format = $this->detectFormat($path);

        if ($format === null) {
            throw new CoverRejected([__('media.cover.format')]);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($mime !== ($format === 'png' ? 'image/png' : 'image/jpeg')) {
            throw new CoverRejected([__('media.cover.format')]);
        }

        $errors = [];

        if ($size > $this->settings->coverMaxBytes()) {
            $errors[] = __('media.cover.too_large', [
                'size' => Format::bytes($size),
                'max' => Format::bytes($this->settings->coverMaxBytes()),
            ]);
        }

        $info = @getimagesize($path);

        if ($info === false) {
            throw new CoverRejected([__('media.cover.unreadable')]);
        }

        [$width, $height] = $info;

        if ($width !== self::SIZE || $height !== self::SIZE) {
            $errors[] = $width === $height
                ? __('media.cover.dimensions', ['width' => $width, 'height' => $height])
                : __('media.cover.not_square', ['width' => $width, 'height' => $height]);
        }

        $colorSpace = $this->colorSpace($path, $format, $info);

        if ($colorSpace !== 'RGB') {
            $errors[] = __('media.cover.color_space', ['space' => $colorSpace]);
        }

        if ($errors !== []) {
            throw new CoverRejected($errors);
        }

        return $format;
    }

    /**
     * @return 'jpeg'|'png'|null
     */
    private function detectFormat(string $path): ?string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        $header = (string) fread($handle, 8);
        fclose($handle);

        return match (true) {
            str_starts_with($header, "\xFF\xD8\xFF") => 'jpeg',
            $header === "\x89PNG\r\n\x1A\n" => 'png',
            default => null,
        };
    }

    /**
     * @param  array<int|string, mixed>  $info
     */
    private function colorSpace(string $path, string $format, array $info): string
    {
        if ($format === 'jpeg') {
            return match ((int) ($info['channels'] ?? 3)) {
                1 => __('media.cover.spaces.gray'),
                4 => 'CMYK',
                default => 'RGB',
            };
        }

        // PNG IHDR renk tipi: 0 gri, 2 RGB, 3 paletli, 4 gri + alfa, 6 RGBA.
        $handle = fopen($path, 'rb');
        fseek($handle, 25);
        $colorType = ord((string) fread($handle, 1));
        fclose($handle);

        return in_array($colorType, [0, 4], true) ? __('media.cover.spaces.gray') : 'RGB';
    }
}
