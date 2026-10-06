<?php

namespace App\Support\Images;

/**
 * public/images/panel altındaki demo panel ekran görüntüleri. Dosyalar ve ölçüler
 * "php artisan hova:screenshots" ile üretilen manifest.json'dan okunur.
 */
class Screenshots
{
    public const DIRECTORY = 'images/panel';

    public const WIDTHS = [800, 1600];

    /**
     * @var array<string, array{width: int, height: int}>|null
     */
    private static ?array $manifest = null;

    /**
     * @return array{src: string, webp: string, avif: string|null, width: int, height: int}|null
     */
    public static function find(string $name): ?array
    {
        $entry = self::manifest()[$name] ?? null;

        if ($entry === null) {
            return null;
        }

        $srcset = function (string $format) use ($name): ?string {
            $parts = [];

            foreach (self::WIDTHS as $width) {
                $file = self::DIRECTORY."/{$name}-{$width}.{$format}";

                if (is_file(public_path($file))) {
                    $parts[] = asset($file).' '.$width.'w';
                }
            }

            return $parts !== [] ? implode(', ', $parts) : null;
        };

        $webp = $srcset('webp');

        if ($webp === null) {
            return null;
        }

        return [
            'src' => asset(self::DIRECTORY."/{$name}-".max(self::WIDTHS).'.webp'),
            'webp' => $webp,
            'avif' => $srcset('avif'),
            'width' => (int) $entry['width'],
            'height' => (int) $entry['height'],
        ];
    }

    /**
     * @return array<string, array{width: int, height: int}>
     */
    private static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $path = public_path(self::DIRECTORY.'/manifest.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return self::$manifest = is_array($data) ? $data : [];
    }
}
