<?php

use App\Domain\Artists\AppleMusicCatalog;
use App\Livewire\Artists\ArtistManager;
use App\Models\Artist;
use App\Models\Release;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    activePlan($this->user, ['artist_limit' => null]);
    $this->actingAs($this->user);
});

it('shows the artists page with an empty state', function () {
    $this->get(route('panel.artists'))
        ->assertOk()
        ->assertSee('Henüz sanatçı profilin yok');
});

it('searches Spotify and Apple Music with the typed name and saves the chosen profiles', function () {
    Livewire::test(ArtistManager::class)
        ->call('create')
        ->set('name', 'deniz')
        ->assertCount('spotifyResults', 2)
        ->assertCount('appleResults', 1)
        ->call('selectSpotify', '4tZwfgrHOc3mvqYlEYSvVi')
        ->assertSet('name', 'Deniz Yılmaz')
        ->assertCount('spotifyResults', 0)
        ->assertSet('appleResults.0.id', '1234567890')
        ->call('selectApple', '1234567890')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', null);

    $artist = $this->user->artists()->sole();
    expect($artist->name)->toBe('Deniz Yılmaz')
        ->and($artist->spotify_artist_id)->toBe('4tZwfgrHOc3mvqYlEYSvVi')
        ->and($artist->spotify_image_url)->toBe('https://i.scdn.co/image/deniz')
        ->and($artist->create_new_spotify)->toBeFalse()
        ->and($artist->apple_music_id)->toBe('1234567890')
        ->and($artist->create_new_apple)->toBeFalse();
});

it('does not ask for the name twice and stops searching once a profile is chosen', function () {
    $component = Livewire::test(ArtistManager::class)
        ->call('create')
        ->set('name', 'Mavi')
        ->call('selectSpotify', '1Xyo4u8uXC1ZmMpatF05PJ')
        ->call('selectApple', '1098765432')
        ->set('name', 'Mavi Gece (Canlı)')
        ->assertCount('spotifyResults', 0)
        ->assertCount('appleResults', 0)
        ->assertSet('spotify.id', '1Xyo4u8uXC1ZmMpatF05PJ');

    $component->call('clearSpotify')->set('name', 'deniz')->assertCount('spotifyResults', 2);
});

it('offers a link field only when the profile is not listed', function () {
    Livewire::test(ArtistManager::class)
        ->call('create')
        ->set('name', 'Hiç Bilinmeyen')
        ->assertCount('spotifyResults', 0)
        ->assertSet('spotifyMessage', '"Hiç Bilinmeyen" için Spotify\'da sanatçı bulunamadı. Profilin varsa linkini ekle.')
        ->assertSee('Listede yok mu? Link ekle')
        ->call('spotifyLinkMode', true)
        ->assertSee('Spotify profil linki')
        ->set('spotifyLink', 'https://open.spotify.com/intl-tr/artist/1Xyo4u8uXC1ZmMpatF05PJ?si=x')
        ->assertSet('spotify.id', '1Xyo4u8uXC1ZmMpatF05PJ')
        ->assertSet('spotifyByLink', false)
        ->assertSet('name', 'Mavi Gece');
});

it('explains a wrong or unknown Spotify link', function () {
    Livewire::test(ArtistManager::class)
        ->call('create')
        ->call('spotifyLinkMode', true)
        ->set('spotifyLink', 'https://open.spotify.com/album/1Xyo4u8uXC1ZmMpatF05PJ')
        ->assertSet('spotifyMessage', 'Link bir Spotify sanatçı profili değil; open.spotify.com/artist/… biçiminde olmalı.')
        ->set('spotifyLink', 'https://open.spotify.com/artist/0000000000000000000000')
        ->assertSet('spotifyMessage', 'Bu linkteki sanatçı Spotify\'da bulunamadı.')
        ->assertSet('spotify', null);
});

it('accepts an Apple Music link or id when the profile is not listed', function () {
    Livewire::test(ArtistManager::class)
        ->call('create')
        ->set('name', 'Yeni Sanatçı')
        ->set('createNewSpotify', true)
        ->call('appleLinkMode', true)
        ->set('appleInput', 'https://music.apple.com/tr/artist/yeni-sanatci/1234567890')
        ->call('save')
        ->assertHasNoErrors();

    $artist = $this->user->artists()->sole();
    expect($artist->apple_music_id)->toBe('1234567890')
        ->and($artist->create_new_apple)->toBeFalse()
        ->and($artist->spotify_artist_id)->toBeNull()
        ->and($artist->create_new_spotify)->toBeTrue();
});

it('keeps working when Apple Music search is unavailable', function () {
    app(AppleMusicCatalog::class)->unavailable = true;

    Livewire::test(ArtistManager::class)
        ->call('create')
        ->set('name', 'Deniz')
        ->assertCount('spotifyResults', 2)
        ->assertSet('appleMessage', __('artist.apple_unavailable'));
});

it('asks for a store profile or the new profile option', function () {
    Livewire::test(ArtistManager::class)
        ->call('create')
        ->set('name', 'Deniz')
        ->call('appleLinkMode', true)
        ->set('appleInput', 'apple-degil')
        ->call('save')
        ->assertHasErrors(['spotify', 'apple']);

    expect($this->user->artists()->count())->toBe(0);
});

it('does not add the same Spotify profile twice', function () {
    Artist::factory()->for($this->user)->onSpotify('4tZwfgrHOc3mvqYlEYSvVi')->create(['name' => 'Deniz Yılmaz']);

    Livewire::test(ArtistManager::class)
        ->call('create')
        ->call('spotifyLinkMode', true)
        ->set('spotifyLink', 'https://open.spotify.com/artist/4tZwfgrHOc3mvqYlEYSvVi')
        ->set('createNewApple', true)
        ->call('save')
        ->assertHasErrors(['spotify']);
});

it('updates an existing profile', function () {
    $artist = Artist::factory()->for($this->user)->create(['name' => 'Eski Ad']);

    Livewire::test(ArtistManager::class)
        ->call('edit', $artist->ulid)
        ->assertSet('name', 'Eski Ad')
        ->set('name', 'Yeni Ad')
        ->call('save')
        ->assertHasNoErrors();

    expect($artist->fresh()->name)->toBe('Yeni Ad');
});

it('does not delete a profile used in a release', function () {
    $artist = Artist::factory()->for($this->user)->create();
    $release = Release::factory()->for($this->user)->create();
    $release->artists()->create(['artist_id' => $artist->id, 'name' => $artist->name, 'role' => 'primary']);
    $unused = Artist::factory()->for($this->user)->create();

    Livewire::test(ArtistManager::class)
        ->call('delete', $artist->ulid)
        ->assertSet('listError', 'Bu profil bir yayında kullanıldığı için silinemez.')
        ->call('delete', $unused->ulid)
        ->assertSet('notice', 'Sanatçı silindi.');

    expect($artist->fresh()->trashed())->toBeFalse()
        ->and($unused->fresh()->trashed())->toBeTrue();
});

it('does not touch another account\'s profiles', function () {
    $foreign = Artist::factory()->create();

    Livewire::test(ArtistManager::class)
        ->call('edit', $foreign->ulid)
        ->assertNotFound();

    Livewire::test(ArtistManager::class)
        ->call('delete', $foreign->ulid)
        ->assertNotFound();

    expect($foreign->fresh()->trashed())->toBeFalse();
});

it('sends a user without a plan to the plans page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ArtistManager::class)
        ->call('create')
        ->assertSet('editing', null)
        ->assertRedirect(route('panel.plans.index'));

    expect(session('flash'))->toBe(__('plans.gate.no_plan_artist'));
});

it('stops at the artist limit of the plan', function () {
    $user = User::factory()->create();
    activePlan($user, ['artist_limit' => 1]);
    Artist::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(ArtistManager::class)
        ->call('create')
        ->assertSet('editing', null)
        ->assertRedirect(route('panel.plans.index'));

    expect(session('flash'))->toBe(__('plans.gate.artist_limit', ['limit' => 1]))
        ->and($user->artists()->count())->toBe(1);
});
