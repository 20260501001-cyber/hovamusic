<?php

use App\Domain\Media\MediaUrl;
use App\Enums\AdminRole;
use App\Enums\DuplicateFlagStatus;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\DuplicateFlags\DuplicateFlagResource;
use App\Filament\Resources\DuplicateFlags\Pages\ManageDuplicateFlags;
use App\Filament\Resources\Genres\GenreResource;
use App\Filament\Resources\Genres\Pages\ManageGenres;
use App\Filament\Resources\Platforms\Pages\ManagePlatforms;
use App\Filament\Resources\Platforms\PlatformResource;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\DuplicateFlag;
use App\Models\Genre;
use App\Models\MediaFile;
use App\Models\Platform;
use App\Models\Release;
use App\Support\Settings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lets only super admins manage stores, genres and settings', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::SuperAdmin)->create(), 'admin');

    expect(PlatformResource::canViewAny())->toBeTrue()
        ->and(GenreResource::canViewAny())->toBeTrue()
        ->and(ManageSettings::canAccess())->toBeTrue();

    $this->actingAs(Admin::factory()->withRole(AdminRole::ReviewEditor)->create(), 'admin');

    expect(PlatformResource::canViewAny())->toBeFalse()
        ->and(GenreResource::canViewAny())->toBeFalse()
        ->and(ManageSettings::canAccess())->toBeFalse()
        ->and(DuplicateFlagResource::canViewAny())->toBeTrue();

    $this->actingAs(Admin::factory()->withRole(AdminRole::Finance)->create(), 'admin');

    expect(DuplicateFlagResource::canViewAny())->toBeFalse();
});

it('does not delete a store or genre that is in use', function () {
    $admin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();
    $release = Release::factory()->complete()->create();
    $usedPlatform = $release->platforms()->first();
    $usedGenre = $release->genre;
    $freePlatform = Platform::query()->create(['name' => 'Yeni Mağaza', 'is_active' => true]);
    $parent = Genre::query()->create(['name' => 'Rock', 'is_active' => true]);
    Genre::query()->create(['name' => 'Anadolu Rock', 'parent_id' => $parent->id, 'is_active' => true]);

    expect($admin->can('delete', $usedPlatform))->toBeFalse()
        ->and($admin->can('delete', $freePlatform))->toBeTrue()
        ->and($admin->can('delete', $usedGenre))->toBeFalse()
        ->and($admin->can('delete', $parent))->toBeFalse();
});

it('generates unique slugs for stores and sub genres', function () {
    $parent = Genre::query()->create(['name' => 'Elektronik', 'is_active' => true]);
    $child = Genre::query()->create(['name' => 'Deep House', 'parent_id' => $parent->id, 'is_active' => true]);
    $platform = Platform::query()->create(['name' => 'Apple Music', 'is_active' => true]);

    expect($child->slug)->toBe('elektronik-deep-house')
        ->and($platform->slug)->toBe('apple-music');
});

it('saves the wizard settings and records the change', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::SuperAdmin)->create(), 'admin');

    Livewire::test(ManageSettings::class)
        ->assertSet('data.release_min_lead_days', 2)
        ->set('data.release_min_lead_days', 7)
        ->set('data.cover_max_mb', 15)
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(Settings::class);
    expect($settings->releaseLeadDays())->toBe(7)
        ->and($settings->coverMaxBytes())->toBe(15 * 1024 * 1024)
        ->and(AuditLog::query()->where('action', 'settings.updated')->sole()->changes)
        ->toBe(['release_min_lead_days' => ['old' => 2, 'new' => 7], 'cover_max_mb' => ['old' => 20, 'new' => 15]]);
});

it('keeps the cover limit under the temporary upload limit', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::SuperAdmin)->create(), 'admin');

    Livewire::test(ManageSettings::class)
        ->set('data.cover_max_mb', 80)
        ->call('save')
        ->assertHasErrors(['data.cover_max_mb']);
});

it('lets reviewers resolve duplicate audio flags', function () {
    $reviewer = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();
    $flag = DuplicateFlag::query()->create([
        'media_file_id' => MediaFile::factory()->audio()->create()->id,
        'matched_media_file_id' => MediaFile::factory()->audio()->create()->id,
    ]);

    expect($reviewer->can('update', $flag))->toBeTrue();

    $this->actingAs($reviewer, 'admin');
    expect(DuplicateFlagResource::getNavigationBadge())->toBe('1');

    $flag->forceFill(['status' => DuplicateFlagStatus::Dismissed, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()])->save();

    expect(DuplicateFlagResource::getNavigationBadge())->toBeNull();
});

it('lets reviewers download the files behind a flag', function () {
    Storage::fake('private');
    $media = MediaFile::factory()->audio()->create();
    Storage::disk('private')->put($media->path, wavBytes(100));

    $this->actingAs(Admin::factory()->withRole(AdminRole::ReviewEditor)->create(), 'admin')
        ->get(MediaUrl::temporary($media, download: true))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=parca.wav');

    $this->actingAs(Admin::factory()->withRole(AdminRole::Finance)->create(), 'admin')
        ->get(MediaUrl::temporary($media))
        ->assertForbidden();
});

it('renders the catalog admin pages', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::SuperAdmin)->create(), 'admin');
    Platform::query()->create(['name' => 'Spotify', 'is_active' => true]);
    $pop = Genre::query()->create(['name' => 'Pop', 'is_active' => true]);
    Genre::query()->create(['name' => 'Türkçe Pop', 'parent_id' => $pop->id, 'is_active' => true]);
    DuplicateFlag::query()->create([
        'media_file_id' => MediaFile::factory()->audio()->create()->id,
        'matched_media_file_id' => MediaFile::factory()->audio()->create()->id,
    ]);

    Livewire::test(ManagePlatforms::class)->assertSuccessful()->assertSee('Spotify');
    Livewire::test(ManageGenres::class)->assertSuccessful()->assertSee('Türkçe Pop');
    Livewire::test(ManageDuplicateFlags::class)->assertSuccessful()->assertSee('Açık');
    Livewire::test(ManageSettings::class)->assertSuccessful()->assertSee('Kapak dosyası üst sınırı');
});

it('creates a store from the admin panel', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::SuperAdmin)->create(), 'admin');

    Livewire::test(ManagePlatforms::class)
        ->callAction(TestAction::make('create')->table(), data: ['name' => 'Yeni Mağaza', 'is_active' => true])
        ->assertHasNoActionErrors();

    expect(Platform::query()->where('slug', 'yeni-magaza')->exists())->toBeTrue();
});
