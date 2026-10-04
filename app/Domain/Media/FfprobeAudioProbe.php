<?php

namespace App\Domain\Media;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * ffprobe ile format, kodek, bit derinliği, örnekleme hızı, kanal ve süreyi okur.
 */
class FfprobeAudioProbe implements AudioProbe
{
    public function __construct(private readonly string $binary = 'ffprobe') {}

    public function probe(string $absolutePath): ?ProbeResult
    {
        $result = Process::timeout(300)->run([
            $this->binary, '-v', 'error', '-print_format', 'json', '-show_streams', '-show_format', $absolutePath,
        ]);

        if ($result->failed()) {
            Log::warning('ffprobe dosyayı okuyamadı.', ['error' => mb_substr($result->errorOutput(), 0, 500)]);

            return null;
        }

        return self::parse($result->output());
    }

    public static function parse(string $json): ?ProbeResult
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return null;
        }

        $stream = collect($data['streams'] ?? [])->first(fn ($stream): bool => is_array($stream) && ($stream['codec_type'] ?? null) === 'audio');

        if ($stream === null) {
            return null;
        }

        $codec = (string) ($stream['codec_name'] ?? '');
        $sampleFormat = (string) ($stream['sample_fmt'] ?? '');
        $bitDepth = (int) ($stream['bits_per_raw_sample'] ?? 0) ?: (int) ($stream['bits_per_sample'] ?? 0);

        if ($bitDepth === 0) {
            $bitDepth = match (rtrim($sampleFormat, 'p')) {
                'u8' => 8,
                's16' => 16,
                's32', 'flt' => 32,
                's64', 'dbl' => 64,
                default => 0,
            };
        }

        $duration = $stream['duration'] ?? $data['format']['duration'] ?? null;
        $formatName = (string) ($data['format']['format_name'] ?? '');

        return new ProbeResult(
            format: explode(',', $formatName)[0] ?: null,
            codec: $codec ?: null,
            sampleRate: isset($stream['sample_rate']) ? (int) $stream['sample_rate'] : null,
            bitDepth: $bitDepth ?: null,
            channels: isset($stream['channels']) ? (int) $stream['channels'] : null,
            durationMs: is_numeric($duration) ? (int) round((float) $duration * 1000) : null,
            isFloat: str_starts_with($codec, 'pcm_f') || in_array(rtrim($sampleFormat, 'p'), ['flt', 'dbl'], true),
        );
    }
}
