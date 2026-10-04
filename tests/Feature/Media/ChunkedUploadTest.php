<?php

use App\Domain\Media\ChunkedUploads;
use App\Domain\Media\ProbeResult;
use App\Enums\MediaStatus;
use App\Enums\ReleaseStatus;
use App\Enums\UploadStatus;
use App\Models\DuplicateFlag;
use App\Models\MediaFile;
use App\Models\Release;
use App\Models\Track;
use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('private');
    fakeAudioProbe();
    $this->release = Release::factory()->create();
    $this->track = Track::factory()->for($this->release)->create();
    $this->actingAs($this->release->user);
});

function startUpload(array $overrides = []): TestResponse
{
    return test()->postJson(route('panel.uploads.store'), array_merge([
        'track' => test()->track->ulid,
        'name' => 'parca.wav',
        'size' => 12_000,
        'fingerprint' => 'parca.wav:12000:1',
    ], $overrides));
}

function sendChunk(string $url, int $offset, string $bytes): TestResponse
{
    return test()->call('PATCH', $url, [], [], [], [
        'HTTP_UPLOAD_OFFSET' => (string) $offset,
        'CONTENT_TYPE' => 'application/octet-stream',
        'HTTP_ACCEPT' => 'application/json',
    ], $bytes);
}

it('uploads a file in chunks, then checks it in the queue', function () {
    $bytes = wavBytes(12_000 - 44);
    $start = startUpload(['size' => strlen($bytes)])->assertCreated()->assertJson(['offset' => 0, 'complete' => false]);
    $url = $start->json('url');

    sendChunk($url, 0, substr($bytes, 0, 5_000))->assertOk()->assertJson(['offset' => 5_000, 'complete' => false]);
    sendChunk($url, 5_000, substr($bytes, 5_000))->assertOk()->assertJson(['offset' => strlen($bytes), 'complete' => true]);

    $media = $this->track->fresh()->audio;

    expect($media)->not->toBeNull()
        ->and($media->validation_status)->toBe(MediaStatus::Valid)
        ->and($media->sha256)->toBe(hash('sha256', $bytes))
        ->and($media->duration_ms)->toBe(200_000)
        ->and($this->track->fresh()->duration_ms)->toBe(200_000);
    Storage::disk('private')->assertExists($media->path);
    expect(UploadSession::query()->sole()->status)->toBe(UploadStatus::Completed);
});

it('resumes an interrupted upload where it stopped', function () {
    $bytes = wavBytes(9_000);
    $url = startUpload(['size' => strlen($bytes)])->json('url');
    sendChunk($url, 0, substr($bytes, 0, 4_000))->assertOk();

    $again = startUpload(['size' => strlen($bytes)])->assertOk();

    expect($again->json('url'))->toBe($url)
        ->and($again->json('offset'))->toBe(4_000);

    sendChunk($url, 0, substr($bytes, 0, 4_000))->assertStatus(409)->assertJson(['offset' => 4_000]);
    sendChunk($url, 4_000, substr($bytes, 4_000))->assertOk()->assertJson(['complete' => true]);

    expect(Storage::disk('private')->get($this->track->fresh()->audio->path))->toBe($bytes);
});

it('rejects files by extension and size before the upload starts', function () {
    startUpload(['name' => 'parca.mp3'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Dosya uzantısı .mp3; WAV veya FLAC yükle.']);

    startUpload(['size' => 2 * 1024 * 1024 * 1024])
        ->assertStatus(422)
        ->assertJson(['message' => 'Dosya 2 GB; en fazla 1 GB olmalı.']);
});

it('checks the file signature, not the extension', function () {
    $bytes = str_repeat('x', 3_000);
    $url = startUpload(['size' => strlen($bytes)])->json('url');

    sendChunk($url, 0, $bytes)
        ->assertStatus(422)
        ->assertJson(['message' => 'Dosya WAV ya da FLAC değil; WAV veya FLAC yükle.']);

    expect($this->track->fresh()->audio_file_id)->toBeNull()
        ->and(MediaFile::query()->count())->toBe(0);
});

it('marks a file that does not meet the audio rules', function () {
    fakeAudioProbe(new ProbeResult('wav', 'pcm_s16le', 22050, 16, 2, 180_000));
    $bytes = wavBytes(2_000);
    $url = startUpload(['size' => strlen($bytes)])->json('url');

    sendChunk($url, 0, $bytes)->assertOk();

    $media = $this->track->fresh()->audio;
    expect($media->validation_status)->toBe(MediaStatus::Invalid)
        ->and($media->validation_errors)->toBe(['Örnekleme hızı 22,05 kHz; en az 44,1 kHz olmalı.']);
});

it('flags the same audio uploaded from another account', function () {
    $bytes = wavBytes(3_000);
    $other = MediaFile::factory()->audio()->create(['sha256' => hash('sha256', $bytes)]);
    $url = startUpload(['size' => strlen($bytes)])->json('url');

    sendChunk($url, 0, $bytes)->assertOk();

    $flag = DuplicateFlag::query()->sole();
    expect($flag->media_file_id)->toBe($this->track->fresh()->audio_file_id)
        ->and($flag->matched_media_file_id)->toBe($other->id);
});

it('does not flag re-uploads within the same account', function () {
    $bytes = wavBytes(3_000);
    MediaFile::factory()->audio()->for($this->release->user)->create(['sha256' => hash('sha256', $bytes)]);
    $url = startUpload(['size' => strlen($bytes)])->json('url');

    sendChunk($url, 0, $bytes)->assertOk();

    expect(DuplicateFlag::query()->count())->toBe(0);
});

it('replaces the previous audio file of the track', function () {
    $first = wavBytes(1_000);
    sendChunk(startUpload(['size' => strlen($first), 'fingerprint' => 'a'])->json('url'), 0, $first)->assertOk();
    $previous = $this->track->fresh()->audio;

    $second = wavBytes(2_000);
    sendChunk(startUpload(['size' => strlen($second), 'fingerprint' => 'b'])->json('url'), 0, $second)->assertOk();

    expect($this->track->fresh()->audio_file_id)->not->toBe($previous->id)
        ->and(MediaFile::query()->find($previous->id))->toBeNull();
    Storage::disk('private')->assertMissing($previous->path);
});

it('does not accept uploads for someone else\'s track or a locked release', function () {
    $this->actingAs(User::factory()->create());
    startUpload()->assertForbidden();

    $this->actingAs($this->release->user);
    $url = startUpload()->json('url');
    $this->release->forceFill(['status' => ReleaseStatus::InReview])->save();

    startUpload()->assertForbidden();
    sendChunk($url, 0, 'RIFF')->assertForbidden();
});

it('does not let another user continue an upload', function () {
    $url = startUpload()->json('url');

    $this->actingAs(User::factory()->create());

    sendChunk($url, 0, 'RIFF')->assertForbidden();
    $this->getJson($url)->assertForbidden();
});

it('cleans up uploads that were left unfinished', function () {
    $url = startUpload()->json('url');
    $session = UploadSession::query()->sole();
    sendChunk($url, 0, 'RIFF')->assertOk();

    $this->travel(ChunkedUploads::EXPIRES_AFTER_HOURS + 1)->hours();
    $this->artisan('hova:purge-uploads')->assertSuccessful();

    expect($session->fresh()->status)->toBe(UploadStatus::Expired);
    Storage::disk('private')->assertMissing($session->temp_path);
});
