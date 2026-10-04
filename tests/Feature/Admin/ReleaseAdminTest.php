<?php

use App\Domain\Releases\ReleaseRequests;
use App\Enums\AdminRole;
use App\Enums\IsrcSource;
use App\Enums\ReleaseStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\TemplateType;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\IsrcCodes\IsrcCodeResource;
use App\Filament\Resources\ReleaseRequests\Pages\ManageReleaseRequests;
use App\Filament\Resources\ReleaseRequests\ReleaseRequestResource;
use App\Filament\Resources\Releases\Pages\EditRelease;
use App\Filament\Resources\Releases\Pages\ListReleases;
use App\Filament\Resources\Releases\Pages\ViewRelease;
use App\Filament\Resources\Releases\RelationManagers\StoreLinksRelationManager;
use App\Filament\Resources\Releases\RelationManagers\TracksRelationManager;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Filament\Resources\ReviewTemplates\Pages\ManageReviewTemplates;
use App\Filament\Resources\ReviewTemplates\ReviewTemplateResource;
use App\Filament\Resources\SpotifyMatches\SpotifyMatchResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\PendingReviews;
use App\Filament\Widgets\ReviewOverview;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\IsrcCode;
use App\Models\Platform;
use App\Models\Release;
use App\Models\ReleaseRequest;
use App\Models\ReviewTemplate;
use App\Notifications\ReleaseRequestAnswered;
use App\Notifications\ReleaseStatusChanged;
use App\Support\Settings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->reviewer = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();
});

function actAsAdmin(AdminRole $role): Admin
{
    $admin = Admin::factory()->withRole($role)->create();
    test()->actingAs($admin, 'admin');

    return $admin;
}

it('gives review editors and super admins the review screens and keeps finance out', function () {
    $release = Release::factory()->status(ReleaseStatus::InReview)->create();

    foreach ([AdminRole::ReviewEditor, AdminRole::SuperAdmin] as $role) {
        actAsAdmin($role);

        expect(ReleaseResource::canViewAny())->toBeTrue()
            ->and(ReleaseRequestResource::canViewAny())->toBeTrue()
            ->and(ReviewTemplateResource::canViewAny())->toBeTrue()
            ->and(SpotifyMatchResource::canViewAny())->toBeTrue()
            ->and(IsrcCodeResource::canViewAny())->toBeTrue()
            ->and(ReviewOverview::canView())->toBeTrue();
    }

    actAsAdmin(AdminRole::ReviewEditor);
    expect(UserResource::canViewAny())->toBeFalse();

    actAsAdmin(AdminRole::Finance);

    expect(ReleaseResource::canViewAny())->toBeFalse()
        ->and(ReleaseRequestResource::canViewAny())->toBeFalse()
        ->and(ReviewTemplateResource::canViewAny())->toBeFalse()
        ->and(SpotifyMatchResource::canViewAny())->toBeFalse()
        ->and(IsrcCodeResource::canViewAny())->toBeFalse()
        ->and(UserResource::canViewAny())->toBeFalse()
        ->and(ReviewOverview::canView())->toBeFalse();

    Livewire::test(ListReleases::class)->assertForbidden();
    Livewire::test(ViewRelease::class, ['record' => $release->ulid])->assertForbidden();
});

it('lists releases waiting for review first and searches by title, artist, ISRC and UPC', function () {
    actAsAdmin(AdminRole::ReviewEditor);
    $live = Release::factory()->status(ReleaseStatus::Live)->create(['title' => 'Eski Şarkı', 'upc' => '8721234567894']);
    $waiting = Release::factory()->status(ReleaseStatus::InReview)->create(['title' => 'Yeni Şarkı', 'submitted_at' => now()->subDay()]);
    $waiting->artists()->create(['name' => 'Mavi Gece', 'role' => 'primary', 'position' => 0]);
    $waiting->tracks()->create(['position' => 1, 'title' => 'Parça', 'isrc' => 'GXLM52600007']);

    Livewire::test(ListReleases::class)
        ->assertCanSeeTableRecords([$waiting, $live], inOrder: true)
        ->searchTable('GX-LM5-26-00007')
        ->assertCanSeeTableRecords([$waiting])
        ->assertCanNotSeeTableRecords([$live])
        ->searchTable('8721234567894')
        ->assertCanSeeTableRecords([$live])
        ->assertCanNotSeeTableRecords([$waiting])
        ->searchTable('Mavi Gece')
        ->assertCanSeeTableRecords([$waiting])
        ->searchTable(null)
        ->filterTable('status', [ReleaseStatus::Live->value])
        ->assertCanSeeTableRecords([$live])
        ->assertCanNotSeeTableRecords([$waiting]);
});

it('shows every field with a copy button, the cover and the tracks', function () {
    actAsAdmin(AdminRole::ReviewEditor);
    $release = Release::factory()->complete()->create(['title' => 'Gece Yarısı', 'upc' => '8721234567894']);
    $release->tracks()->update(['isrc' => 'GXLM52600001', 'title' => 'İlk Parça']);
    $release->forceFill(['status' => ReleaseStatus::InReview])->save();

    Livewire::test(ViewRelease::class, ['record' => $release->ulid])
        ->assertOk()
        ->assertSee('Gece Yarısı')
        ->assertSee('8721234567894')
        ->assertSee('GXLM52600001')
        ->assertSee('İlk Parça')
        ->assertSee('Dosyayı indir')
        ->assertSee('Kapağı indir')
        ->assertSeeHtml('<audio');
});

it('approves a release, logs it and notifies the owner', function () {
    Notification::fake();
    $admin = actAsAdmin(AdminRole::ReviewEditor);
    $release = Release::factory()->status(ReleaseStatus::InReview)->create();

    Livewire::test(ViewRelease::class, ['record' => $release->ulid])
        ->assertActionVisible('approve')
        ->assertActionVisible('requestChanges')
        ->assertActionVisible('reject')
        ->assertActionHidden('goLive')
        ->callAction('approve')
        ->assertHasNoActionErrors();

    $log = $release->statusLogs()->sole();
    expect($release->fresh()->status)->toBe(ReleaseStatus::Approved)
        ->and($log->actor_type)->toBe('admin')
        ->and($log->actor_id)->toBe($admin->id);
    Notification::assertSentTo($release->user, ReleaseStatusChanged::class);
});

it('requires a note to ask for changes and fills it from a template', function () {
    actAsAdmin(AdminRole::ReviewEditor);
    $release = Release::factory()->status(ReleaseStatus::InReview)->create();
    $template = ReviewTemplate::query()->create(['type' => TemplateType::NeedsChanges, 'title' => 'Kapak yazısı', 'body' => 'Kapakta site adresi olmamalı.']);
    ReviewTemplate::query()->create(['type' => TemplateType::Rejection, 'title' => 'Telif', 'body' => 'Telif sorunu.']);

    Livewire::test(ViewRelease::class, ['record' => $release->ulid])
        ->callAction('requestChanges', data: ['note' => ''])
        ->assertHasActionErrors(['note' => 'required']);

    expect($release->fresh()->status)->toBe(ReleaseStatus::InReview);

    Livewire::test(ViewRelease::class, ['record' => $release->ulid])
        ->mountAction('requestChanges')
        ->fillForm(['template_id' => $template->id])
        ->assertSchemaStateSet(['note' => 'Kapakta site adresi olmamalı.'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $log = $release->statusLogs()->sole();
    expect($release->fresh()->status)->toBe(ReleaseStatus::NeedsChanges)
        ->and($log->note)->toBe('Kapakta site adresi olmamalı.')
        ->and($log->template_id)->toBe($template->id);
});

it('requires a reason to reject', function () {
    actAsAdmin(AdminRole::ReviewEditor);
    $release = Release::factory()->status(ReleaseStatus::InReview)->create();

    Livewire::test(ViewRelease::class, ['record' => $release->ulid])
        ->callAction('reject', data: ['note' => ''])
        ->assertHasActionErrors(['note' => 'required'])
        ->fillForm(['note' => 'Başka bir sanatçının kaydı.'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($release->fresh()->status)->toBe(ReleaseStatus::Rejected);
});

it('moves a release through delivery to live', function () {
    actAsAdmin(AdminRole::SuperAdmin);
    $release = Release::factory()->status(ReleaseStatus::Approved)->create();

    Livewire::test(ViewRelease::class, ['record' => $release->ulid])
        ->assertActionHidden('approve')
        ->callAction('deliver')
        ->assertActionVisible('goLive')
        ->callAction('goLive');

    expect($release->fresh()->status)->toBe(ReleaseStatus::Live);
});

it('decides on a takedown request from the release page', function () {
    actAsAdmin(AdminRole::ReviewEditor);
    $release = Release::factory()->status(ReleaseStatus::Live)->create();
    app(ReleaseRequests::class)->open($release, $release->user, RequestType::Takedown, 'Kaldırın.');

    Livewire::test(ViewRelease::class, ['record' => $release->ulid])
        ->assertActionHidden('takeDown')
        ->callAction('rejectTakedown', data: ['note' => ''])
        ->assertHasActionErrors(['note' => 'required'])
        ->fillForm(['note' => 'Sözleşme süresi dolmadı.'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($release->fresh()->status)->toBe(ReleaseStatus::Live)
        ->and(ReleaseRequest::query()->sole()->status)->toBe(RequestStatus::Rejected);
});

it('answers requests from the request queue', function () {
    Notification::fake();
    actAsAdmin(AdminRole::ReviewEditor);
    $release = Release::factory()->status(ReleaseStatus::Live)->create();
    $correction = app(ReleaseRequests::class)->open($release, $release->user, RequestType::Correction, 'Tarih yanlış.');
    $takedown = app(ReleaseRequests::class)->open($release, $release->user, RequestType::Takedown, 'Kaldırın.');

    Livewire::test(ManageReleaseRequests::class)
        ->assertCanSeeTableRecords([$correction, $takedown])
        ->assertActionHidden(TestAction::make('answer')->table($takedown))
        ->callAction(TestAction::make('answer')->table($correction), data: ['note' => ''])
        ->assertHasActionErrors(['note' => 'required'])
        ->fillForm(['note' => 'Tarihi düzelttik.'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('approveTakedown')->table($takedown))
        ->assertHasNoActionErrors();

    expect($correction->fresh()->status)->toBe(RequestStatus::Resolved)
        ->and($takedown->fresh()->status)->toBe(RequestStatus::Resolved)
        ->and($release->fresh()->status)->toBe(ReleaseStatus::TakenDown);
    Notification::assertSentTo($release->user, ReleaseRequestAnswered::class);
});

it('lets admins edit metadata in any status and logs every change', function () {
    actAsAdmin(AdminRole::ReviewEditor);
    $release = Release::factory()->complete()->create(['title' => 'Eski Başlık']);
    $release->forceFill(['status' => ReleaseStatus::Live])->save();
    $other = Platform::query()->create(['name' => 'Deezer', 'slug' => 'deezer', 'is_active' => true]);

    Livewire::test(EditRelease::class, ['record' => $release->ulid])
        ->fillForm([
            'title' => 'Yeni Başlık',
            'upc' => '8721234567894',
            'platforms' => [$other->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $release->refresh();
    expect($release->title)->toBe('Yeni Başlık')
        ->and($release->upc)->toBe('8721234567894')
        ->and($release->platforms()->pluck('platforms.id')->all())->toBe([$other->id]);

    $update = AuditLog::query()->where('action', 'releases.updated')->latest('id')->firstOrFail();
    expect($update->changes['title'])->toBe(['old' => 'Eski Başlık', 'new' => 'Yeni Başlık'])
        ->and(AuditLog::query()->where('action', 'releases.platforms_updated')->exists())->toBeTrue();
});

it('validates the UPC check digit', function () {
    actAsAdmin(AdminRole::ReviewEditor);
    $release = Release::factory()->complete()->create();

    Livewire::test(EditRelease::class, ['record' => $release->ulid])
        ->fillForm(['upc' => '8721234567890'])
        ->call('save')
        ->assertHasFormErrors(['upc']);
});

it('lets admins enter an ISRC by hand and keeps Hova Music codes unique', function () {
    $admin = actAsAdmin(AdminRole::ReviewEditor);
    $release = Release::factory()->complete(tracks: 2)->create();
    [$first, $second] = $release->tracks()->get()->all();
    IsrcCode::query()->create(['isrc' => 'GXLM52600005', 'year' => 26, 'sequence' => 5, 'track_id' => $first->id, 'assigned_by_type' => 'system']);

    Livewire::test(TracksRelationManager::class, ['ownerRecord' => $release, 'pageClass' => EditRelease::class])
        ->callAction(TestAction::make('edit')->table($second), data: ['isrc' => 'GX-LM5-26-00005'])
        ->assertHasActionErrors(['isrc'])
        ->fillForm(['isrc' => 'TR-A1B-26-00009', 'title' => 'Düzeltilmiş Ad'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($second->fresh())
        ->isrc->toBe('TRA1B2600009')
        ->isrc_source->toBe(IsrcSource::Admin)
        ->title->toBe('Düzeltilmiş Ad');
    expect(AuditLog::query()->where('action', 'tracks.updated')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

it('assigns missing ISRCs from the release page', function () {
    actAsAdmin(AdminRole::ReviewEditor);
    $release = Release::factory()->complete()->create();
    $release->forceFill(['status' => ReleaseStatus::InReview])->save();

    Livewire::test(ViewRelease::class, ['record' => $release->ulid])
        ->callAction('assignIsrc')
        ->assertHasNoActionErrors();

    expect($release->tracks()->sole()->isrc)->toStartWith('GXLM5')
        ->and(IsrcCode::query()->sole()->assigned_by_type)->toBe('admin');
});

it('adds store links on the release page', function () {
    actAsAdmin(AdminRole::ReviewEditor);
    $release = Release::factory()->status(ReleaseStatus::Live)->create();
    $platform = Platform::query()->create(['name' => 'Apple Music', 'slug' => 'apple-music', 'is_active' => true]);

    Livewire::test(StoreLinksRelationManager::class, ['ownerRecord' => $release, 'pageClass' => ViewRelease::class])
        ->callAction(TestAction::make('create')->table(), data: ['platform_id' => $platform->id, 'url' => 'https://music.apple.com/album/1'])
        ->assertHasNoActionErrors();

    expect($release->storeLinks()->sole()->url)->toBe('https://music.apple.com/album/1')
        ->and(AuditLog::query()->where('action', 'release_store_links.created')->exists())->toBeTrue();
});

it('manages review templates', function () {
    actAsAdmin(AdminRole::ReviewEditor);

    Livewire::test(ManageReviewTemplates::class)
        ->callAction('create', data: ['type' => 'rejection', 'title' => 'Telif', 'body' => 'Kaydın hakları sende görünmüyor.', 'is_active' => true])
        ->assertHasNoActionErrors();

    expect(ReviewTemplate::query()->sole())->type->toBe(TemplateType::Rejection)->title->toBe('Telif');
});

it('shows pending reviews, open requests and new users on the dashboard', function () {
    actAsAdmin(AdminRole::ReviewEditor);
    Release::factory()->count(2)->status(ReleaseStatus::InReview)->create();
    $live = Release::factory()->status(ReleaseStatus::Live)->create();
    app(ReleaseRequests::class)->open($live, $live->user, RequestType::Correction, 'Bir düzeltme lazım.');

    Livewire::test(ReviewOverview::class)
        ->assertSee('Bekleyen inceleme')
        ->assertSee('Bekleyen talep')
        ->assertSee('Yeni kullanıcı (7 gün)');

    Livewire::test(PendingReviews::class)
        ->assertCanSeeTableRecords(Release::query()->where('status', ReleaseStatus::InReview)->get())
        ->assertCanNotSeeTableRecords([$live]);
});

it('lets super admins change the ISRC prefix in settings', function () {
    actAsAdmin(AdminRole::SuperAdmin);

    Livewire::test(ManageSettings::class)
        ->assertSet('data.isrc_registrant', 'GXLM5')
        ->set('data.isrc_registrant', 'G1')
        ->call('save')
        ->assertHasErrors(['data.isrc_registrant'])
        ->set('data.isrc_registrant', 'gxab1')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(Settings::class)->isrcRegistrant())->toBe('GXAB1');
});
