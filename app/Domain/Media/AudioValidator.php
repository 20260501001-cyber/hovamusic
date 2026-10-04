<?php

namespace App\Domain\Media;

use App\Support\Format;

/**
 * Ses dosyası kuralları: WAV (PCM) veya FLAC, 16 ya da 24 bit, en az 44,1 kHz.
 * Her hata mesajı ölçülen değeri ve beklenen değeri söyler.
 */
class AudioValidator
{
    public const MIN_SAMPLE_RATE = 44100;

    public const BIT_DEPTHS = [16, 24];

    /**
     * @return list<string>
     */
    public function errors(ProbeResult $result): array
    {
        $isWav = $result->format === 'wav' && str_starts_with((string) $result->codec, 'pcm_');
        $isFlac = $result->format === 'flac' && $result->codec === 'flac';

        if (! $isWav && ! $isFlac) {
            return [__('media.audio.format', ['format' => $this->formatLabel($result)])];
        }

        $errors = [];

        if ($result->isFloat) {
            $errors[] = __('media.audio.float', ['bits' => $result->bitDepth ?? 32]);
        } elseif (! in_array($result->bitDepth, self::BIT_DEPTHS, true)) {
            $errors[] = __('media.audio.bit_depth', ['bits' => $result->bitDepth ?? '?']);
        }

        if ($result->sampleRate === null || $result->sampleRate < self::MIN_SAMPLE_RATE) {
            $errors[] = __('media.audio.sample_rate', ['rate' => $result->sampleRate ? Format::kiloHertz($result->sampleRate) : '?']);
        }

        if ($result->durationMs === null || $result->durationMs < 1000) {
            $errors[] = __('media.audio.duration');
        }

        return $errors;
    }

    public function summary(ProbeResult $result): string
    {
        return collect([
            strtoupper((string) $result->format),
            $result->bitDepth ? $result->bitDepth.' bit' : null,
            $result->sampleRate ? Format::kiloHertz($result->sampleRate) : null,
            $result->channels ? trans_choice('media.audio.channels', $result->channels) : null,
            $result->durationMs ? Format::duration($result->durationMs) : null,
        ])->filter()->implode(' · ');
    }

    private function formatLabel(ProbeResult $result): string
    {
        return match ($result->codec) {
            'mp3' => 'MP3',
            'aac' => 'AAC',
            'alac' => 'ALAC',
            'vorbis' => 'OGG Vorbis',
            'opus' => 'Opus',
            default => strtoupper((string) ($result->format ?? $result->codec ?? '?')),
        };
    }
}
