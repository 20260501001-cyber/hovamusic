<?php

use App\Domain\Isrc\IsrcAllocator;
use App\Domain\Isrc\IsrcAlreadyUsed;
use App\Domain\Releases\ReleaseSubmission;
use App\Domain\Releases\ReleaseValidator;
use App\Enums\AdminRole;
use App\Enums\IsrcSource;
use App\Livewire\Releases\Wizard\TracksStep;
use App\Models\Admin;
use App\Models\IsrcCode;
use App\Models\Release;
use App\Models\Track;
use App\Support\Settings;
use Livewire\Livewire;

function acceptedDeclarations(): array
{
    return collect(config('hova.consents.release'))->keys()->mapWithKeys(fn ($key) => [$key => true])->all();
}

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 5));
});

it('assigns Hova Music ISRCs in order when a release without codes is submitted', function () {
    $release = planned(Release::factory()->complete(tracks: 2)->create(['type' => 'single']));

    app(ReleaseSubmission::class)->submit($release, $release->user, acceptedDeclarations());

    $tracks = $release->tracks()->get();
    expect($tracks->pluck('isrc')->all())->toBe(['GXLM52600001', 'GXLM52600002'])
        ->and($tracks->pluck('isrc_source')->all())->toBe([IsrcSource::Hova, IsrcSource::Hova])
        ->and($tracks->first()->formattedIsrc())->toBe('GX-LM5-26-00001')
        ->and(IsrcCode::query()->orderBy('sequence')->pluck('track_id')->all())->toBe($tracks->pluck('id')->all())
        ->and(IsrcCode::query()->first()->assigned_by_type)->toBe('system');

    $next = planned(Release::factory()->complete()->create());
    app(ReleaseSubmission::class)->submit($next, $next->user, acceptedDeclarations());

    expect($next->tracks()->sole()->isrc)->toBe('GXLM52600003');
});

it('keeps the user\'s own ISRC and does not assign one', function () {
    $release = planned(Release::factory()->complete()->create());
    $release->tracks()->update(['isrc' => 'TRA1B2600001', 'has_own_isrc' => true, 'isrc_source' => 'user']);

    app(ReleaseSubmission::class)->submit($release, $release->user, acceptedDeclarations());

    expect($release->tracks()->sole()->isrc)->toBe('TRA1B2600001')
        ->and(IsrcCode::query()->count())->toBe(0);
});

it('never hands out a code again, even after the track is deleted', function () {
    $release = planned(Release::factory()->complete()->create());
    $allocator = app(IsrcAllocator::class);
    $track = $release->tracks()->sole();

    expect($allocator->assign($track, $release->user))->toBe('GXLM52600001');

    $track->delete();
    $other = Track::factory()->for($release)->create(['position' => 2]);

    expect($allocator->assign($other, $release->user))->toBe('GXLM52600002')
        ->and(IsrcCode::query()->where('isrc', 'GXLM52600001')->exists())->toBeTrue();
});

it('starts a new sequence each year', function () {
    $release = planned(Release::factory()->complete()->create());
    $allocator = app(IsrcAllocator::class);
    $allocator->assign($release->tracks()->sole(), $release->user);

    $this->travelTo(now()->setDate(2027, 1, 2));
    $track = Track::factory()->for($release)->create(['position' => 2]);

    expect($allocator->assign($track, $release->user))->toBe('GXLM52700001')
        ->and($allocator->nextCode())->toBe('GXLM52700002');
});

it('uses the prefix set in the admin settings', function () {
    app(Settings::class)->put(['isrc_registrant' => 'gxab1']);
    $release = planned(Release::factory()->complete()->create());

    expect(app(IsrcAllocator::class)->assign($release->tracks()->sole(), $release->user))->toBe('GXAB12600001');
});

it('does not let users enter a code with the Hova Music prefix', function () {
    $release = Release::factory()->create();
    $track = Track::factory()->for($release)->create();
    $this->actingAs($release->user);

    Livewire::test(TracksStep::class, ['release' => $release])
        ->call('edit', $track->ulid)
        ->set('form.has_own_isrc', '1')
        ->set('form.isrc', 'GX-LM5-26-00042')
        ->assertHasErrors(['form.isrc']);

    expect($track->fresh()->isrc)->toBeNull();

    $track->forceFill(['isrc' => 'GXLM52600042', 'isrc_source' => IsrcSource::User, 'has_own_isrc' => true])->save();

    $messages = collect(app(ReleaseValidator::class)->issues($release->fresh())[3] ?? []);

    expect($messages->contains(fn (string $message): bool => str_contains($message, 'Hova Music önekiyle (GXLM5)')))->toBeTrue();
});

it('lets the user switch between "I have a code" and "assign one for me"', function () {
    $release = Release::factory()->create();
    $track = Track::factory()->for($release)->create();
    $this->actingAs($release->user);

    $component = Livewire::test(TracksStep::class, ['release' => $release])
        ->call('edit', $track->ulid)
        ->assertSet('form.has_own_isrc', '0')
        ->set('form.has_own_isrc', '1')
        ->set('form.isrc', 'TR-A1B-26-00001');

    expect($track->fresh())->isrc->toBe('TRA1B2600001')->has_own_isrc->toBeTrue();

    $component->set('form.has_own_isrc', '0');

    expect($track->fresh())->isrc->toBeNull()->has_own_isrc->toBeFalse();
});

it('registers a Hova Music code an admin enters by hand and refuses to reuse it', function () {
    $admin = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();
    $release = planned(Release::factory()->complete()->create());
    $first = $release->tracks()->sole();
    $second = Track::factory()->for($release)->create(['position' => 2]);
    $allocator = app(IsrcAllocator::class);

    $allocator->claimManual($first, 'GXLM52600010', $admin);

    expect(IsrcCode::query()->where('isrc', 'GXLM52600010')->value('track_id'))->toBe($first->id)
        ->and(fn () => $allocator->claimManual($second, 'GXLM52600010', $admin))->toThrow(IsrcAlreadyUsed::class)
        ->and($allocator->nextCode())->toBe('GXLM52600011');
});
