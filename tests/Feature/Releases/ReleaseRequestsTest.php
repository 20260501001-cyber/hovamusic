<?php

use App\Domain\Releases\NoteRequired;
use App\Domain\Releases\ReleaseRequests;
use App\Enums\AdminRole;
use App\Enums\ReleaseStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Admin;
use App\Models\Platform;
use App\Models\Release;
use App\Models\ReleaseRequest;
use App\Models\User;
use App\Notifications\ReleaseRequestAnswered;
use App\Notifications\ReleaseStatusChanged;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();
    $this->requests = app(ReleaseRequests::class);
});

it('lets the owner ask for a correction on an approved release without changing its status', function () {
    $release = Release::factory()->status(ReleaseStatus::Approved)->create();

    $this->actingAs($release->user)
        ->post(route('panel.releases.requests.store', $release), ['type' => 'correction', 'message' => 'Söz yazarının adı yanlış yazılmış.'])
        ->assertRedirect(route('panel.releases.show', $release))
        ->assertSessionHas('flash', 'Düzeltme talebin gönderildi.');

    $request = ReleaseRequest::query()->sole();
    expect($request->type)->toBe(RequestType::Correction)
        ->and($request->status)->toBe(RequestStatus::Open)
        ->and($release->fresh()->status)->toBe(ReleaseStatus::Approved);
});

it('moves the release to takedown requested when the owner asks for a takedown', function (ReleaseStatus $status) {
    Notification::fake();
    $release = Release::factory()->status($status)->create();

    $this->actingAs($release->user)
        ->post(route('panel.releases.requests.store', $release), ['type' => 'takedown', 'message' => 'Dağıtımı başka yerden yapacağım.'])
        ->assertRedirect();

    expect($release->fresh()->status)->toBe(ReleaseStatus::TakedownRequested)
        ->and(ReleaseRequest::query()->sole()->previous_status)->toBe($status);
    Notification::assertSentTo($release->user, ReleaseStatusChanged::class);
})->with([ReleaseStatus::Approved, ReleaseStatus::Delivered, ReleaseStatus::Live]);

it('does not accept requests for drafts, releases in review or someone else\'s release', function () {
    $draft = Release::factory()->create();
    $inReview = Release::factory()->status(ReleaseStatus::InReview)->create();
    $other = Release::factory()->status(ReleaseStatus::Live)->create();

    $this->actingAs($draft->user)
        ->post(route('panel.releases.requests.store', $draft), ['type' => 'correction', 'message' => 'Bir düzeltme lazım.'])
        ->assertSessionHasErrors('type', null, 'request');

    $this->actingAs($inReview->user)
        ->post(route('panel.releases.requests.store', $inReview), ['type' => 'takedown', 'message' => 'Kaldırılsın lütfen.'])
        ->assertSessionHasErrors('type', null, 'request');

    $this->actingAs(User::factory()->create())
        ->post(route('panel.releases.requests.store', $other), ['type' => 'correction', 'message' => 'Bir düzeltme lazım.'])
        ->assertForbidden();

    expect(ReleaseRequest::query()->count())->toBe(0);
});

it('requires a message and allows one open request of each type', function () {
    $release = Release::factory()->status(ReleaseStatus::Live)->create();
    $this->actingAs($release->user);

    $this->post(route('panel.releases.requests.store', $release), ['type' => 'correction', 'message' => ''])
        ->assertSessionHasErrors('message', null, 'request');

    $this->post(route('panel.releases.requests.store', $release), ['type' => 'correction', 'message' => 'İlk düzeltme talebim.']);
    $this->post(route('panel.releases.requests.store', $release), ['type' => 'correction', 'message' => 'İkinci düzeltme talebim.'])
        ->assertSessionHasErrors('type', null, 'request');

    expect(ReleaseRequest::query()->count())->toBe(1);
});

it('sends the admin answer to a correction request by e-mail and panel notification', function () {
    Notification::fake();
    $release = Release::factory()->status(ReleaseStatus::Live)->create(['title' => 'Kuzey']);
    $request = $this->requests->open($release, $release->user, RequestType::Correction, 'Sürüm adı eksik.');

    $this->requests->answer($request, $this->admin, 'Düzelttik, mağazalarda 2-3 gün içinde güncellenir.');

    expect($request->fresh())
        ->status->toBe(RequestStatus::Resolved)
        ->admin_note->toBe('Düzelttik, mağazalarda 2-3 gün içinde güncellenir.')
        ->handled_by->toBe($this->admin->id);

    Notification::assertSentTo($release->user, ReleaseRequestAnswered::class, function (ReleaseRequestAnswered $notification) use ($release) {
        return $notification->toMail($release->user)->subject === 'Kuzey için düzeltme talebin yanıtlandı'
            && $notification->toArray($release->user)['body'] === 'Düzelttik, mağazalarda 2-3 gün içinde güncellenir.';
    });
});

it('takes the release down when the admin approves a takedown', function () {
    $release = Release::factory()->status(ReleaseStatus::Live)->create();
    $request = $this->requests->open($release, $release->user, RequestType::Takedown, 'Kaldırılsın.');

    $this->requests->approveTakedown($request, $this->admin);

    expect($release->fresh()->status)->toBe(ReleaseStatus::TakenDown)
        ->and($request->fresh()->status)->toBe(RequestStatus::Resolved);
});

it('returns the release to its previous status when a takedown is rejected, with a required reason', function () {
    Notification::fake();
    $release = Release::factory()->status(ReleaseStatus::Delivered)->create(['title' => 'Liman']);
    $request = $this->requests->open($release, $release->user, RequestType::Takedown, 'Kaldırılsın.');

    expect(fn () => $this->requests->rejectTakedown($request, $this->admin, ''))->toThrow(NoteRequired::class);
    expect($release->fresh()->status)->toBe(ReleaseStatus::TakedownRequested);

    $this->requests->rejectTakedown($request->fresh(), $this->admin, 'Sözleşme süresi dolmadı.');

    expect($release->fresh()->status)->toBe(ReleaseStatus::Delivered)
        ->and($request->fresh()->status)->toBe(RequestStatus::Rejected)
        ->and($request->fresh()->admin_note)->toBe('Sözleşme süresi dolmadı.');

    Notification::assertSentTo($release->user, ReleaseStatusChanged::class, fn (ReleaseStatusChanged $n) => $n->toMail($release->user)->subject === 'Liman için kaldırma talebin reddedildi');
});

it('shows store links, request history and the request form on the release page', function () {
    $release = Release::factory()->status(ReleaseStatus::Live)->create();
    $platform = Platform::query()->create(['name' => 'Spotify', 'slug' => 'spotify', 'is_active' => true]);
    $release->storeLinks()->create(['platform_id' => $platform->id, 'url' => 'https://open.spotify.com/album/xyz']);
    $request = $this->requests->open($release, $release->user, RequestType::Correction, 'Kapak güncellensin.');
    $this->requests->answer($request, $this->admin, 'Kapak güncellenemez; yeni yayın oluştur.');

    $this->actingAs($release->user)
        ->get(route('panel.releases.show', $release))
        ->assertOk()
        ->assertSee('Mağaza bağlantıları')
        ->assertSee('https://open.spotify.com/album/xyz', false)
        ->assertSee('Kapak güncellensin.')
        ->assertSee('Kapak güncellenemez; yeni yayın oluştur.')
        ->assertSee('Yeni talep oluştur');
});

it('hides store links until the release is live', function () {
    $release = Release::factory()->status(ReleaseStatus::Delivered)->create();
    $platform = Platform::query()->create(['name' => 'Spotify', 'slug' => 'spotify', 'is_active' => true]);
    $release->storeLinks()->create(['platform_id' => $platform->id, 'url' => 'https://open.spotify.com/album/xyz']);

    $this->actingAs($release->user)
        ->get(route('panel.releases.show', $release))
        ->assertOk()
        ->assertDontSee('https://open.spotify.com/album/xyz', false);
});
