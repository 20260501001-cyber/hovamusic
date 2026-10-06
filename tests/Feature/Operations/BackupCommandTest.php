<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hm-backup-'.uniqid();
    config(['hova.backup.path' => $this->root, 'hova.backup.keep_daily' => 7, 'hova.backup.keep_weekly' => 4]);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('describes the plan without writing anything in dry-run mode', function () {
    $this->artisan('hova:backup', ['--dry-run' => true])
        ->expectsOutputToContain('database.sql.gz')
        ->expectsOutputToContain('7 günlük, 4 haftalık')
        ->assertSuccessful();

    expect(is_dir($this->root))->toBeFalse();
});

it('writes the database dump, file archive and checksums, then prunes old backups', function () {
    $database = tempnam(sys_get_temp_dir(), 'hm-db-');
    file_put_contents($database, str_repeat('SQLite format 3', 20));
    config(['database.connections.sqlite.database' => $database]);

    foreach (range(1, 8) as $day) {
        File::ensureDirectoryExists($this->root.'/daily/2026-01-0'.min($day, 9).'_0000'.$day);
    }

    foreach (['2025-W01', '2025-W02', '2025-W03', '2025-W04', '2025-W05'] as $week) {
        File::ensureDirectoryExists($this->root.'/weekly/'.$week);
    }

    $this->artisan('hova:backup')->assertSuccessful();

    $daily = collect(File::directories($this->root.'/daily'))->sort()->values();
    $latest = $daily->last();

    expect($daily)->toHaveCount(7)
        ->and(gzdecode(file_get_contents($latest.'/database.sql.gz')))->toBe(file_get_contents($database))
        ->and(file_exists($latest.'/files.tar.gz'))->toBeTrue()
        ->and(file_get_contents($latest.'/SHA256SUMS'))->toContain(hash_file('sha256', $latest.'/database.sql.gz').'  database.sql.gz')
        ->and(File::directories($this->root.'/weekly'))->toHaveCount(4)
        ->and(is_dir($this->root.'/weekly/'.now()->format('o-\WW')))->toBeTrue();

    unlink($database);
});

it('fails loudly and leaves no partial backup when the dump fails', function () {
    config(['database.connections.sqlite.database' => sys_get_temp_dir().'/hm-olmayan-veritabani.sqlite']);

    $this->artisan('hova:backup')->assertFailed();

    expect(File::directories($this->root.'/daily'))->toBe([]);
});
