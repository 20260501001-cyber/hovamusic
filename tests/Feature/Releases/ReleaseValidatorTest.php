<?php

use App\Domain\Releases\ReleaseValidator;
use App\Enums\CreditRole;
use App\Enums\ReleaseType;
use App\Models\Release;
use App\Support\Format;
use App\Support\Settings;

function releaseValidator(): ReleaseValidator
{
    return app(ReleaseValidator::class);
}

it('accepts a complete single', function () {
    $release = Release::factory()->complete()->create();

    expect(releaseValidator()->validate($release))->toBe([]);
});

it('lists what is missing in an empty draft, step by step', function () {
    $release = Release::factory()->create([
        'type' => null, 'title' => null, 'language' => null, 'release_date' => null, 'p_line' => null, 'c_line' => null,
    ]);

    $issues = releaseValidator()->issues($release);

    expect(array_keys($issues))->toBe([1, 2, 3, 4])
        ->and($issues[1])->toHaveKeys(['type', 'title', 'primary_artists', 'genre_id', 'language', 'release_date', 'p_line', 'c_line'])
        ->and($issues[2])->toBe(['cover' => 'Kapak yükle.'])
        ->and($issues[3])->toBe(['tracks' => 'En az bir parça ekle.']);
});

it('uses the lead time from the admin setting', function () {
    app(Settings::class)->put(['release_min_lead_days' => 5]);
    $release = Release::factory()->complete()->create(['release_date' => today()->addDays(4)]);

    expect(releaseValidator()->issues($release)[1]['release_date'])
        ->toContain('en erken '.Format::longDate(today()->addDays(5)));

    $release->update(['release_date' => today()->addDays(5)]);

    expect(releaseValidator()->validate($release->fresh()))->toBe([]);
});

it('checks UPC and copyright lines', function () {
    $release = Release::factory()->complete()->create(['upc' => '036000291453', 'p_line' => 'Hova Music']);

    expect(releaseValidator()->issues($release)[1])
        ->toMatchArray([
            'upc' => 'UPC\'nin kontrol hanesi tutmuyor; kodu kontrol et.',
            'p_line' => '℗ satırını yıl ve adla yaz, örneğin "2026 Hova Music".',
        ]);
});

it('requires a lyricist unless the track is instrumental', function () {
    $release = Release::factory()->complete()->create();
    $track = $release->tracks()->first();
    $track->credits()->where('role', CreditRole::Lyricist->value)->delete();

    expect(releaseValidator()->issues($release->fresh())[3])->toHaveKey("track.{$track->ulid}.lyricists");

    $track->update(['language' => 'zxx']);

    expect(releaseValidator()->validate($release->fresh()))->toBe([]);
});

it('requires the release to be explicit when a track is', function () {
    $release = Release::factory()->complete()->create();
    $release->tracks()->update(['explicit' => true]);

    expect(releaseValidator()->issues($release->fresh())[1])->toHaveKey('explicit');
});

it('checks the preview start against the measured duration', function () {
    $release = Release::factory()->complete(durationMs: 60_000)->create();
    $release->tracks()->update(['preview_start_sec' => 45]);

    expect(array_values(releaseValidator()->issues($release->fresh())[3]))
        ->toBe(['01. parça: önizleme 0:45\'te başlıyor; parça 1:00 sürdüğü için en geç 0:30 olabilir.']);
});

it('flags duplicate ISRCs inside a release', function () {
    $release = Release::factory()->complete(tracks: 2)->create(['type' => ReleaseType::Single]);
    $release->tracks()->update(['isrc' => 'TRA1B2600001']);

    expect(collect(releaseValidator()->issues($release->fresh())[3])->keys()->filter(fn ($key) => str_ends_with($key, 'isrc_duplicate')))
        ->toHaveCount(1);
});

it('applies the store rule for singles', function () {
    $release = Release::factory()->complete(tracks: 4)->create(['type' => ReleaseType::Single]);

    expect(releaseValidator()->validate($release)[3])
        ->toContain('Single en fazla 3 parça içerebilir; bu yayında 4 parça var. Yayın türünü EP ya da Albüm yap.');

    $long = Release::factory()->complete(durationMs: 10 * 60 * 1000)->create(['type' => ReleaseType::Single]);

    expect(releaseValidator()->validate($long)[3])
        ->toContain('Single\'daki parçalar 10 dakikadan kısa olmalı; 01. parça 10:00 sürüyor.');
});

it('applies the store rule for EPs', function () {
    $release = Release::factory()->complete(tracks: 3)->create(['type' => ReleaseType::Ep]);

    expect(releaseValidator()->validate($release)[3])->toContain('EP 4–6 parça içermeli; bu yayında 3 parça var.');

    $long = Release::factory()->complete(tracks: 4, durationMs: 8 * 60 * 1000)->create(['type' => ReleaseType::Ep]);

    expect(releaseValidator()->validate($long)[3])->toContain('EP\'nin toplam süresi 30 dakikanın altında olmalı; şu an 32:00.');
});

it('accepts albums with seven tracks or thirty minutes', function () {
    $short = Release::factory()->complete(tracks: 6, durationMs: 4 * 60 * 1000)->create(['type' => ReleaseType::Album]);
    $sevenTracks = Release::factory()->complete(tracks: 7, durationMs: 3 * 60 * 1000)->create(['type' => ReleaseType::Album]);
    $longEnough = Release::factory()->complete(tracks: 3, durationMs: 10 * 60 * 1000)->create(['type' => ReleaseType::Album]);

    expect(releaseValidator()->validate($short)[3])->toContain('Albüm en az 7 parça ya da toplam 30 dakika olmalı; şu an 6 parça, 24:00.')
        ->and(releaseValidator()->validate($sevenTracks))->toBe([])
        ->and(releaseValidator()->validate($longEnough))->toBe([]);
});

it('does not block moving forward while audio is still being checked', function () {
    $release = Release::factory()->complete()->create();
    $track = $release->tracks()->with('audio')->first();
    $track->audio->update(['validation_status' => 'pending']);

    expect(releaseValidator()->step($release->fresh(), 3))->toBe([])
        ->and(releaseValidator()->validate($release->fresh())[3])->toContain('01. parça: ses dosyası hâlâ kontrol ediliyor; birkaç saniye bekle.');
});

it('requires countries when the release is limited to territories', function () {
    $release = Release::factory()->complete()->create(['territory_mode' => 'include', 'territories' => []]);

    expect(releaseValidator()->issues($release)[4])->toBe(['territories' => 'En az bir ülke seç.']);

    $release->update(['territories' => ['TR', 'DE']]);

    expect(releaseValidator()->validate($release->fresh()))->toBe([]);
});
