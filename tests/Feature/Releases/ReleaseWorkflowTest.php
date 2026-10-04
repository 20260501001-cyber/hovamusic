<?php

use App\Domain\Releases\InvalidTransition;
use App\Domain\Releases\NoteRequired;
use App\Domain\Releases\ReleaseWorkflow;
use App\Enums\AdminRole;
use App\Enums\ReleaseStatus;
use App\Enums\TemplateType;
use App\Models\Admin;
use App\Models\Platform;
use App\Models\Release;
use App\Models\ReleaseStoreLink;
use App\Models\ReviewTemplate;
use App\Notifications\ReleaseStatusChanged;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();
    $this->workflow = app(ReleaseWorkflow::class);
});

it('follows the review flow and logs who changed what, when, with the note', function () {
    $release = Release::factory()->status(ReleaseStatus::InReview)->create();
    $template = ReviewTemplate::query()->create(['type' => TemplateType::NeedsChanges, 'title' => 'Kapak', 'body' => 'Kapakta yazı var.']);

    $this->workflow->transition($release, ReleaseStatus::NeedsChanges, $this->admin, 'Kapakta yazı var.', $template);
    $this->workflow->transition($release->fresh(), ReleaseStatus::InReview, $release->user);
    $this->workflow->transition($release->fresh(), ReleaseStatus::Approved, $this->admin);
    $this->workflow->transition($release->fresh(), ReleaseStatus::Delivered, $this->admin);
    $this->workflow->transition($release->fresh(), ReleaseStatus::Live, $this->admin);

    $logs = $release->statusLogs()->reorder('id')->get();

    expect($release->fresh()->status)->toBe(ReleaseStatus::Live)
        ->and($logs->map(fn ($log) => [$log->from_status->value, $log->to_status->value, $log->actor_type])->all())->toBe([
            ['in_review', 'needs_changes', 'admin'],
            ['needs_changes', 'in_review', 'user'],
            ['in_review', 'approved', 'admin'],
            ['approved', 'delivered', 'admin'],
            ['delivered', 'live', 'admin'],
        ])
        ->and($logs->first()->note)->toBe('Kapakta yazı var.')
        ->and($logs->first()->template_id)->toBe($template->id)
        ->and($logs->first()->actor_id)->toBe($this->admin->id)
        ->and($logs->first()->created_at)->not->toBeNull();
});

it('rejects transitions that are not allowed', function (ReleaseStatus $from, ReleaseStatus $to, string $actor) {
    $release = Release::factory()->status($from)->create();
    $by = $actor === 'admin' ? $this->admin : $release->user;

    expect(fn () => $this->workflow->transition($release, $to, $by, 'Not'))->toThrow(InvalidTransition::class);
    expect($release->fresh()->status)->toBe($from)
        ->and($release->statusLogs()->count())->toBe(0);
})->with([
    'taslak doğrudan onaylanamaz' => [ReleaseStatus::Draft, ReleaseStatus::Approved, 'admin'],
    'incelemedeki yayın yayına alınamaz' => [ReleaseStatus::InReview, ReleaseStatus::Live, 'admin'],
    'kullanıcı kendi yayınını onaylayamaz' => [ReleaseStatus::InReview, ReleaseStatus::Approved, 'user'],
    'kullanıcı reddedileni yeniden gönderemez' => [ReleaseStatus::Rejected, ReleaseStatus::InReview, 'user'],
    'onaylanan doğrudan yayına alınamaz' => [ReleaseStatus::Approved, ReleaseStatus::Live, 'admin'],
    'kaldırılan yayın geri alınamaz' => [ReleaseStatus::TakenDown, ReleaseStatus::Live, 'admin'],
    'taslak için kaldırma talebi açılamaz' => [ReleaseStatus::Draft, ReleaseStatus::TakedownRequested, 'user'],
    'admin kaldırma talebi açamaz' => [ReleaseStatus::Live, ReleaseStatus::TakedownRequested, 'admin'],
]);

it('requires a note for changes requested, rejection and takedown requests', function (ReleaseStatus $from, ReleaseStatus $to, string $actor) {
    $release = Release::factory()->status($from)->create();
    $by = $actor === 'admin' ? $this->admin : $release->user;

    expect(fn () => $this->workflow->transition($release, $to, $by, '   '))->toThrow(NoteRequired::class);
    expect($release->fresh()->status)->toBe($from);

    $this->workflow->transition($release, $to, $by, 'Sebep yazıldı.');
    expect($release->fresh()->status)->toBe($to);
})->with([
    'düzeltme' => [ReleaseStatus::InReview, ReleaseStatus::NeedsChanges, 'admin'],
    'ret' => [ReleaseStatus::InReview, ReleaseStatus::Rejected, 'admin'],
    'kaldırma talebi' => [ReleaseStatus::Live, ReleaseStatus::TakedownRequested, 'user'],
]);

it('sends the owner an e-mail and a panel notification on every status change', function () {
    Notification::fake();
    $release = Release::factory()->status(ReleaseStatus::InReview)->create(['title' => 'Gece Yarısı']);

    $this->workflow->transition($release, ReleaseStatus::Rejected, $this->admin, 'Telif sahibi belirsiz.');

    Notification::assertSentTo($release->user, ReleaseStatusChanged::class, function (ReleaseStatusChanged $notification, array $channels) use ($release) {
        $mail = $notification->toMail($release->user);
        $data = $notification->toArray($release->user);

        return $channels === ['database', 'mail']
            && $mail->subject === 'Gece Yarısı reddedildi'
            && in_array('Telif sahibi belirsiz.', $mail->introLines, true)
            && $data['status'] === 'rejected'
            && $data['body'] === 'Telif sahibi belirsiz.'
            && $data['url'] === route('panel.releases.show', $release);
    });
});

it('stores the panel notification and renders the Turkish e-mail', function () {
    Mail::fake();
    $release = Release::factory()->status(ReleaseStatus::Delivered)->create(['title' => 'Mavi']);
    $platform = Platform::query()->create(['name' => 'Spotify', 'slug' => 'spotify', 'is_active' => true]);
    ReleaseStoreLink::query()->forceCreate(['release_id' => $release->id, 'platform_id' => $platform->id, 'url' => 'https://open.spotify.com/album/abc']);

    $this->workflow->transition($release, ReleaseStatus::Live, $this->admin);

    $notification = $release->user->notifications()->sole();
    expect($notification->data['title'])->toBe('Mavi yayında')
        ->and($notification->read_at)->toBeNull();

    $html = (string) (new ReleaseStatusChanged($release->fresh(), $release->statusLogs()->first()))->toMail($release->user)->render();
    expect($html)->toContain('Merhaba')
        ->toContain('Mağaza bağlantıları')
        ->toContain('https://open.spotify.com/album/abc')
        ->toContain('Hova Music');
});

it('points a needs-changes notification at the edit wizard', function () {
    $release = Release::factory()->status(ReleaseStatus::InReview)->create();

    $this->workflow->transition($release, ReleaseStatus::NeedsChanges, $this->admin, 'Parça adını düzelt.');

    expect($release->user->notifications()->sole()->data['url'])
        ->toBe(route('panel.releases.edit', ['release' => $release->ulid, 'step' => 5]));
});
