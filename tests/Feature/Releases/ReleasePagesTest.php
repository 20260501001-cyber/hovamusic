<?php

use App\Domain\Media\MediaUrl;
use App\Enums\ReleaseStatus;
use App\Models\MediaFile;
use App\Models\Release;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('shows an empty state before the first release', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('panel.releases.index'))
        ->assertOk()
        ->assertSee('Henüz yayının yok')
        ->assertSee('Yayın oluştur');
});

it('creates a draft with the default label and opens the wizard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('panel.releases.store'));

    $release = $user->releases()->sole();
    $response->assertRedirect(route('panel.releases.edit', ['release' => $release->ulid, 'step' => 1]));
    expect($release->status)->toBe(ReleaseStatus::Draft)
        ->and($release->label_name)->toBe('Hova Music');

    $this->get(route('panel.releases.edit', ['release' => $release->ulid, 'step' => 1]))
        ->assertOk()
        ->assertSee('Yayın bilgileri')
        ->assertSee('Adım <strong>1</strong> / 5', false);
});

it('lists releases with their status', function () {
    $user = User::factory()->create();
    Release::factory()->for($user)->create(['title' => 'Gece Yarısı']);
    Release::factory()->for($user)->status(ReleaseStatus::InReview)->create(['title' => 'Sabah']);

    $this->actingAs($user)
        ->get(route('panel.releases.index'))
        ->assertOk()
        ->assertSeeInOrder(['Sabah', 'İncelemede'])
        ->assertSee('Gece Yarısı')
        ->assertSee('UPC atanacak');
});

it('does not let anyone else see a release', function () {
    $release = Release::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('panel.releases.show', $release))
        ->assertForbidden();

    $this->get(route('panel.releases.edit', ['release' => $release->ulid, 'step' => 1]))->assertForbidden();
});

it('does not jump past the furthest step reached', function () {
    $release = Release::factory()->create(['wizard_step' => 2]);

    $this->actingAs($release->user)
        ->get(route('panel.releases.edit', ['release' => $release->ulid, 'step' => 4]))
        ->assertRedirect(route('panel.releases.edit', ['release' => $release->ulid, 'step' => 2]));
});

it('sends a locked release to its detail page instead of the wizard', function () {
    $release = Release::factory()->status(ReleaseStatus::Approved)->create();

    $this->actingAs($release->user)
        ->get(route('panel.releases.edit', ['release' => $release->ulid, 'step' => 1]))
        ->assertRedirect(route('panel.releases.show', $release));

    $this->get(route('panel.releases.show', $release))
        ->assertOk()
        ->assertSee('Onaylandı')
        ->assertDontSee('Düzenle');
});

it('shows the status history on the detail page', function () {
    $release = Release::factory()->status(ReleaseStatus::NeedsChanges)->create();
    $release->statusLogs()->create(['from_status' => 'in_review', 'to_status' => 'needs_changes', 'actor_type' => 'admin', 'note' => 'Kapaktaki linki kaldır.']);

    $this->actingAs($release->user)
        ->get(route('panel.releases.show', $release))
        ->assertOk()
        ->assertSee('Durum geçmişi')
        ->assertSee('Kapaktaki linki kaldır.');

    $this->get(route('panel.releases.edit', ['release' => $release->ulid, 'step' => 1]))
        ->assertOk()
        ->assertSee('Bu yayın için düzeltme istendi.')
        ->assertSee('Kapaktaki linki kaldır.');
});

it('deletes a draft together with its files', function () {
    Storage::fake('private');
    $release = Release::factory()->complete()->create();
    $cover = $release->cover;
    Storage::disk('private')->put($cover->path, 'kapak');

    $this->actingAs($release->user)
        ->delete(route('panel.releases.destroy', $release))
        ->assertRedirect(route('panel.releases.index'));

    expect(Release::withTrashed()->find($release->id))->toBeNull()
        ->and(MediaFile::query()->find($cover->id))->toBeNull();
    Storage::disk('private')->assertMissing($cover->path);
});

it('does not delete a submitted release', function () {
    $release = Release::factory()->status(ReleaseStatus::InReview)->create();

    $this->actingAs($release->user)
        ->delete(route('panel.releases.destroy', $release))
        ->assertForbidden();
});

it('serves private files only through a signed link to their owner', function () {
    Storage::fake('private');
    $media = MediaFile::factory()->create();
    Storage::disk('private')->put($media->path, 'kapak-verisi');
    $url = MediaUrl::temporary($media);

    $this->get($url)->assertRedirect();

    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

    $this->actingAs($media->user)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    $this->get(route('media.show', $media))->assertForbidden();
});
