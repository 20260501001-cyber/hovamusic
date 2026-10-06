<?php

namespace App\Support\Images;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Herkese açık görseller için genişliğe göre WebP ve (sunucu destekliyorsa) AVIF
 * sürümleri üretir: "kapak.jpg" için "kapak-640.webp", "kapak-1280.avif" gibi.
 */
class ResponsiveImages
{
    public const WIDTHS = [640, 1280];

    private const WEBP_QUALITY = 80;

    private const AVIF_QUALITY = 55;

    /**
     * @param  list<int>  $widths
     * @return array{width: int, height: int}
     */
    public function generate(string $disk, string $path, array $widths = self::WIDTHS): array
    {
        $storage = Storage::disk($disk);
        $source = $storage->path($path);
        $base = preg_replace('/\.[a-z0-9]+$/i', '', $path);
        $manager = ImageManager::usingDriver(GdDriver::class);
        $original = $manager->decodePath($source);
        $size = ['width' => $original->width(), 'height' => $original->height()];

        foreach ($widths as $width) {
            $image = $manager->decodePath($source)->scaleDown(width: $width);
            $storage->put("{$base}-{$width}.webp", (string) $image->encode(new WebpEncoder(quality: self::WEBP_QUALITY, strip: true)));

            if ($this->supportsAvif()) {
                try {
                    $storage->put("{$base}-{$width}.avif", (string) $image->encode(new AvifEncoder(quality: self::AVIF_QUALITY, strip: true)));
                } catch (Throwable) {
                    // AVIF isteğe bağlı; WebP her zaman var.
                }
            }
        }

        return $size;
    }

    /**
     * Kaynağı ve üretilmiş sürümleri siler.
     */
    public function delete(string $disk, string $path, array $widths = self::WIDTHS): void
    {
        $base = preg_replace('/\.[a-z0-9]+$/i', '', $path);
        $files = [$path];

        foreach ($widths as $width) {
            $files[] = "{$base}-{$width}.webp";
            $files[] = "{$base}-{$width}.avif";
        }

        Storage::disk($disk)->delete($files);
    }

    private function supportsAvif(): bool
    {
        return function_exists('imageavif') && (bool) (gd_info()['AVIF Support'] ?? false);
    }
}
