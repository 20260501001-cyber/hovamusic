<?php

use App\Domain\Delivery\ReleaseArchive;
use App\Domain\Delivery\ReleaseMetadata;
use App\Domain\Media\MediaUrl;
use App\Enums\AdminRole;
use App\Enums\ReleaseStatus;
use App\Filament\Resources\Releases\Pages\ViewRelease;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Release;
use App\Support\Admin\AdminUrls;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

beforeEach(function () {
    Storage::fake('private');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->release = Release::factory()->complete(tracks: 2)->create(['title' => 'Gece Yarısı', 'upc' => '8721234567894']);
    $this->release->forceFill(['status' => ReleaseStatus::Approved])->save();
    $this->release->load(['cover', 'tracks.audio']);
    $this->release->tracks->each(fn ($track, $i) => $track->forceFill(['title' => 'Parça '.($i + 1), 'isrc' => 'GXLM5260000'.($i + 1)])->save());

    Storage::disk('private')->put($this->release->cover->path, 'kapak-baytlari');
    foreach ($this->release->tracks as $track) {
        Storage::disk('private')->put($track->audio->path, 'ses-'.$track->position);
    }
});

/**
 * @return array<string, string> Dosya adı => içerik
 */
function unzip(string $bytes): array
{
    $path = tempnam(sys_get_temp_dir(), 'hm-zip-');
    file_put_contents($path, $bytes);
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    $files = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $files[$name] = $zip->getFromIndex($i);
    }

    $zip->close();
    unlink($path);

    return $files;
}

/**
 * @return list<list<mixed>>
 */
function xlsxRows(string $bytes): array
{
    $path = tempnam(sys_get_temp_dir(), 'hm-xlsx-').'.xlsx';
    file_put_contents($path, $bytes);
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();
    unlink($path);

    return $rows;
}

it('streams a ZIP with the original audio files, the cover and the metadata', function () {
    $admin = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();

    $response = $this->actingAs($admin, 'admin')->get(AdminUrls::releaseArchive($this->release));

    $response->assertOk()->assertHeader('Content-Type', 'application/zip');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('.zip');

    $files = unzip($response->streamedContent());

    expect(array_keys($files))->toBe([
        'cover.jpg',
        'audio/01 - Parça 1.wav',
        'audio/02 - Parça 2.wav',
        'metadata.json',
        'metadata.xlsx',
    ])
        ->and($files['cover.jpg'])->toBe('kapak-baytlari')
        ->and($files['audio/02 - Parça 2.wav'])->toBe('ses-2');

    $json = json_decode($files['metadata.json'], true);
    expect($json['release']['title'])->toBe('Gece Yarısı')
        ->and($json['release']['upc'])->toBe('8721234567894')
        ->and($json['tracks'])->toHaveCount(2)
        ->and($json['tracks'][0]['isrc'])->toBe('GXLM52600001')
        ->and($json['tracks'][0]['composers'])->toBe(['Besteci Adı'])
        ->and($json['tracks'][1]['audio']['file'])->toBe('audio/02 - Parça 2.wav');

    $rows = xlsxRows($files['metadata.xlsx']);
    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toBe(ReleaseMetadata::COLUMNS)
        ->and($rows[1][0])->toBe('8721234567894')
        ->and($rows[1][array_search('ISRC', ReleaseMetadata::COLUMNS, true)])->toBe('GXLM52600001')
        ->and($rows[2][array_search('Track Title', ReleaseMetadata::COLUMNS, true)])->toBe('Parça 2');

    $log = AuditLog::query()->where('action', 'release.downloaded')->sole();
    expect($log->actor_id)->toBe($admin->id)
        ->and($log->subject_id)->toBe($this->release->id);
});

it('only serves the ZIP through a signed URL to reviewers', function () {
    $url = AdminUrls::releaseArchive($this->release);

    $this->actingAs(Admin::factory()->withRole(AdminRole::Finance)->create(), 'admin')->get($url)->assertForbidden();

    $reviewer = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();
    $this->actingAs($reviewer, 'admin')->get(route('admin.releases.archive', $this->release))->assertForbidden();

    $this->travel(AdminUrls::DOWNLOAD_TTL_MINUTES + 1)->minutes();
    $this->actingAs($reviewer, 'admin')->get($url)->assertForbidden();

    expect(AuditLog::query()->where('action', 'release.downloaded')->count())->toBe(0);
});

it('writes the archive without touching the disk', function () {
    $stream = fopen('php://memory', 'w+b');

    $entries = (new ReleaseArchive($this->release->fresh()))->stream($stream);
    rewind($stream);

    expect($entries)->toContain('metadata.json', 'metadata.xlsx', 'cover.jpg')
        ->and(array_keys(unzip(stream_get_contents($stream))))->toBe($entries)
        ->and(Storage::disk('private')->allFiles())->toHaveCount(3);
});

it('logs single file downloads and creates the download links when clicked', function () {
    $admin = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();
    $this->actingAs($admin, 'admin');
    $audio = $this->release->tracks->first()->audio;

    Livewire::test(ViewRelease::class, ['record' => $this->release->ulid])
        ->call('downloadMedia', $audio->ulid)
        ->assertRedirectContains('/medya/'.$audio->ulid);

    Livewire::test(ViewRelease::class, ['record' => $this->release->ulid])
        ->callAction('downloadAll')
        ->assertRedirectContains('/indir/yayin/'.$this->release->ulid);

    $this->get(MediaUrl::temporary($audio, download: true))->assertOk();
    $this->get(MediaUrl::temporary($audio))->assertOk();

    expect(AuditLog::query()->where('action', 'media.downloaded')->where('subject_id', $audio->id)->count())->toBe(1);
});

it('does not hand out files from another release', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::ReviewEditor)->create(), 'admin');
    $other = Release::factory()->complete()->create();

    Livewire::test(ViewRelease::class, ['record' => $this->release->ulid])
        ->call('downloadMedia', $other->fresh()->cover->ulid)
        ->assertNotFound();
});
