<?php

use App\Domain\Media\AudioValidator;
use App\Domain\Media\FfprobeAudioProbe;
use App\Domain\Media\ProbeResult;

it('parses ffprobe output for a 24 bit FLAC file', function () {
    $result = FfprobeAudioProbe::parse(json_encode([
        'streams' => [['codec_type' => 'audio', 'codec_name' => 'flac', 'sample_fmt' => 's32', 'bits_per_raw_sample' => '24', 'sample_rate' => '48000', 'channels' => 2, 'duration' => '222.5']],
        'format' => ['format_name' => 'flac', 'duration' => '222.5'],
    ]));

    expect($result->format)->toBe('flac')
        ->and($result->bitDepth)->toBe(24)
        ->and($result->sampleRate)->toBe(48000)
        ->and($result->durationMs)->toBe(222500)
        ->and($result->isFloat)->toBeFalse()
        ->and((new AudioValidator)->errors($result))->toBe([]);
});

it('reports the measured values of an unsuitable file', function () {
    $errors = (new AudioValidator)->errors(new ProbeResult('wav', 'pcm_s16le', 22050, 16, 2, 180_000));

    expect($errors)->toBe(['Örnekleme hızı 22,05 kHz; en az 44,1 kHz olmalı.']);
});

it('rejects floating point and 8 bit audio', function () {
    $validator = new AudioValidator;

    expect($validator->errors(new ProbeResult('wav', 'pcm_f32le', 44100, 32, 2, 180_000, isFloat: true)))
        ->toBe(['Ses 32 bit kayan noktalı; 16 ya da 24 bit olmalı.'])
        ->and($validator->errors(new ProbeResult('wav', 'pcm_u8', 44100, 8, 1, 180_000)))
        ->toBe(['Bit derinliği 8 bit; 16 ya da 24 bit olmalı.']);
});

it('rejects formats other than WAV and FLAC', function () {
    expect((new AudioValidator)->errors(new ProbeResult('mp3', 'mp3', 44100, null, 2, 180_000)))
        ->toHaveCount(1);
});

it('returns null for output without an audio stream', function () {
    expect(FfprobeAudioProbe::parse('{"streams":[{"codec_type":"video"}]}'))->toBeNull()
        ->and(FfprobeAudioProbe::parse('not json'))->toBeNull();
});
