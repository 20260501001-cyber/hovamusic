<?php

use App\Domain\Media\AudioProbe;
use App\Domain\Media\ProbeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationData(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Deniz Yılmaz',
        'email' => 'Deniz@Example.com',
        'password' => 'guvenli-sifre-42',
        'password_confirmation' => 'guvenli-sifre-42',
        'account_type' => 'artist',
        'consents' => [
            'kvkk-aydinlatma' => '1',
            'uyelik-sozlesmesi' => '1',
        ],
    ], $overrides);
}

/**
 * 3000×3000 (ya da verilen boyutta) JPEG kapak üretir; $exif verilirse dosyaya
 * içinde bu metin geçen bir EXIF (APP1) bölümü eklenir.
 */
function makeJpeg(int $width = 3000, int $height = 3000, ?string $exif = null): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 76, 59, 255));
    $path = tempnam(sys_get_temp_dir(), 'hm-cover-');
    imagejpeg($image, $path, 70);
    imagedestroy($image);

    if ($exif !== null) {
        $bytes = file_get_contents($path);
        $payload = "Exif\0\0MM\0*\0\0\0\x08".$exif;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
        file_put_contents($path, substr($bytes, 0, 2).$segment.substr($bytes, 2));
    }

    return $path;
}

/**
 * Gri tonlamalı (renk tipi 0) PNG; GD bu tipte dosya yazamadığı için elle kurulur.
 */
function makeGrayscalePng(int $size = 3000): string
{
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $raw = str_repeat("\0".str_repeat("\x80", $size), $size);
    $path = tempnam(sys_get_temp_dir(), 'hm-gray-');

    file_put_contents($path, "\x89PNG\r\n\x1A\n"
        .$chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 0, 0, 0, 0))
        .$chunk('IDAT', gzcompress($raw))
        .$chunk('IEND', ''));

    return $path;
}

/**
 * Geçerli başlığa sahip küçük bir WAV dosyasının içeriği (ses verisi sessizlik).
 */
function wavBytes(int $dataBytes = 10_000): string
{
    $header = 'RIFF'.pack('V', 36 + $dataBytes).'WAVE'
        .'fmt '.pack('VvvVVvv', 16, 1, 2, 44100, 44100 * 2 * 3, 6, 24)
        .'data'.pack('V', $dataBytes);

    return $header.str_repeat("\0", $dataBytes);
}

/**
 * ffprobe yerine sabit sonuç döndüren ses ölçer.
 */
function fakeAudioProbe(?ProbeResult $result = null): void
{
    $result ??= new ProbeResult('wav', 'pcm_s24le', 44100, 24, 2, 200_000);

    app()->instance(AudioProbe::class, new class($result) implements AudioProbe
    {
        public function __construct(private readonly ?ProbeResult $result) {}

        public function probe(string $absolutePath): ?ProbeResult
        {
            return $this->result;
        }
    });
}
