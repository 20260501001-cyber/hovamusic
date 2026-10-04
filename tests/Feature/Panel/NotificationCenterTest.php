<?php

use App\Domain\Releases\ReleaseWorkflow;
use App\Enums\AdminRole;
use App\Enums\ReleaseStatus;
use App\Models\Admin;
use App\Models\Release;
use App\Models\User;

function notifyOwner(Release $release, ReleaseStatus $to, ?string $note = null): void
{
    app(ReleaseWorkflow::class)->transition($release->fresh(), $to, Admin::factory()->withRole(AdminRole::ReviewEditor)->create(), $note);
}

it('lists notifications with the unread count in the menu', function () {
    $release = Release::factory()->status(ReleaseStatus::InReview)->create(['title' => 'Deniz Feneri']);
    notifyOwner($release, ReleaseStatus::NeedsChanges, 'Kapakta logo var.');

    $this->actingAs($release->user)
        ->get(route('panel.notifications.index'))
        ->assertOk()
        ->assertSee('Deniz Feneri için düzeltme gerekiyor')
        ->assertSee('Kapakta logo var.')
        ->assertSee('1 okunmamış bildirim')
        ->assertSee('Tümünü okundu işaretle');
});

it('marks a notification as read when it is opened and goes to the release', function () {
    $release = Release::factory()->status(ReleaseStatus::InReview)->create();
    notifyOwner($release, ReleaseStatus::Approved);
    $notification = $release->user->notifications()->sole();

    $this->actingAs($release->user)
        ->get(route('panel.notifications.open', $notification->id))
        ->assertRedirect(route('panel.releases.show', $release));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks one or all notifications as read', function () {
    $release = Release::factory()->status(ReleaseStatus::InReview)->create();
    notifyOwner($release, ReleaseStatus::Approved);
    notifyOwner($release, ReleaseStatus::Delivered);
    [$first, $second] = $release->user->notifications()->get()->all();
    $this->actingAs($release->user);

    $this->post(route('panel.notifications.read', $first->id))->assertRedirect();
    expect($release->user->unreadNotifications()->count())->toBe(1);

    $this->post(route('panel.notifications.read-all'))->assertRedirect();
    expect($release->user->unreadNotifications()->count())->toBe(0);

    $this->get(route('panel.notifications.index'))->assertDontSee('okunmamış bildirim');
});

it('does not show or change another user\'s notifications', function () {
    $release = Release::factory()->status(ReleaseStatus::InReview)->create();
    notifyOwner($release, ReleaseStatus::Approved);
    $notification = $release->user->notifications()->sole();

    $this->actingAs(User::factory()->create());

    $this->get(route('panel.notifications.open', $notification->id))->assertNotFound();
    $this->post(route('panel.notifications.read', $notification->id))->assertNotFound();
    expect($notification->fresh()->read_at)->toBeNull();
});

it('shows an empty state without notifications', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('panel.notifications.index'))
        ->assertOk()
        ->assertSee('Bildirimin yok');
});
