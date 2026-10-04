<?php

use App\Domain\Releases\InvalidTransition;
use App\Domain\Releases\ReleaseSubmission;
use App\Domain\Releases\ReleaseWorkflow;
use App\Domain\Releases\SubmissionFailed;
use App\Enums\AdminRole;
use App\Enums\ReleaseStatus;
use App\Models\Admin;
use App\Models\Consent;
use App\Models\Release;

function declarations(bool $accepted = true): array
{
    return collect(config('hova.consents.release'))->keys()->mapWithKeys(fn ($key) => [$key => $accepted])->all();
}

it('submits a complete release for review and stores the declarations', function () {
    $release = Release::factory()->complete()->create();

    app(ReleaseSubmission::class)->submit($release, $release->user, declarations());

    $release->refresh();
    expect($release->status)->toBe(ReleaseStatus::InReview)
        ->and($release->submitted_at)->not->toBeNull()
        ->and($release->statusLogs()->count())->toBe(1)
        ->and(Consent::query()->where('context_id', $release->id)->pluck('type')->sort()->values()->all())
        ->toBe(['hak-beyani-bilgiler', 'hak-beyani-haklar', 'hak-beyani-icerik']);
});

it('refuses to submit without all three declarations', function () {
    $release = Release::factory()->complete()->create();
    $partial = declarations();
    $partial['hak-beyani-icerik'] = false;

    expect(fn () => app(ReleaseSubmission::class)->submit($release, $release->user, $partial))
        ->toThrow(SubmissionFailed::class);

    expect($release->fresh()->status)->toBe(ReleaseStatus::Draft)
        ->and(Consent::query()->count())->toBe(0);
});

it('refuses to submit an incomplete release', function () {
    $release = Release::factory()->create();

    try {
        app(ReleaseSubmission::class)->submit($release, $release->user, declarations());
        $this->fail('Gönderim reddedilmeliydi.');
    } catch (SubmissionFailed $failed) {
        expect($failed->errors)->toHaveKeys([1, 2, 3, 4]);
    }
});

it('keeps the plan check in one place and blocks submission when plans are enforced', function () {
    config(['hova.plans.enforce' => true]);
    $release = Release::factory()->complete()->create();

    try {
        app(ReleaseSubmission::class)->submit($release, $release->user, declarations());
        $this->fail('Plan kontrolü gönderimi engellemeliydi.');
    } catch (SubmissionFailed $failed) {
        expect($failed->errors)->toHaveKey('plan');
    }
});

it('lets the owner resubmit a release that needs changes, and locks it once approved', function () {
    $release = Release::factory()->complete()->create();
    $admin = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();
    $workflow = app(ReleaseWorkflow::class);

    app(ReleaseSubmission::class)->submit($release, $release->user, declarations());
    expect($release->user->can('update', $release->fresh()))->toBeFalse();

    $workflow->transition($release->fresh(), ReleaseStatus::NeedsChanges, $admin, 'Kapakta link var.');
    expect($release->user->can('update', $release->fresh()))->toBeTrue();

    app(ReleaseSubmission::class)->submit($release->fresh(), $release->user, declarations());
    $workflow->transition($release->fresh(), ReleaseStatus::Approved, $admin);

    $release->refresh();
    expect($release->status)->toBe(ReleaseStatus::Approved)
        ->and($release->locked_at)->not->toBeNull()
        ->and($release->user->can('update', $release))->toBeFalse()
        ->and($release->statusLogs()->count())->toBe(4);
});

it('does not let a user approve their own release', function () {
    $release = Release::factory()->complete()->create();
    app(ReleaseSubmission::class)->submit($release, $release->user, declarations());

    expect(fn () => app(ReleaseWorkflow::class)->transition($release->fresh(), ReleaseStatus::Approved, $release->user))
        ->toThrow(InvalidTransition::class);
});
