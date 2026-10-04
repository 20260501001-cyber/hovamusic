<?php

use App\Livewire\Artists\ArtistManager;
use App\Models\Artist;
use App\Models\Release;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows the artists page with an empty state', function () {
    $this->get(route('panel.artists'))
        ->assertOk()
        ->assertSee('Henüz sanatçı profilin yok');
});

it('finds an artist on Spotify by name and saves the profile', function () {
    Livewire::test(ArtistManager::class)
        ->call('create')
        ->set('spotifyQuery', 'deniz')
        ->assertCount('results', 2)
        ->call('selectSpotify', '4tZwfgrHOc3mvqYlEYSvVi')
        ->assertSet('name', 'Deniz Yılmaz')
        ->set('createNewApple', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', null);

    $artist = $this->user->artists()->sole();
    expect($artist->name)->toBe('Deniz Yılmaz')
        ->and($artist->spotify_artist_id)->toBe('4tZwfgrHOc3mvqYlEYSvVi')
        ->and($artist->spotify_image_url)->toBe('https://i.scdn.co/image/deniz')
        ->and($artist->create_new_spotify)->toBeFalse()
        ->and($artist->apple_music_id)->toBeNull()
        ->and($artist->create_new_apple)->toBeTrue();
});

it('reads and verifies a pasted Spotify link', function () {
    Livewire::test(ArtistManager::class)
        ->call('create')
        ->set('spotifyQuery', 'https://open.spotify.com/intl-tr/artist/1Xyo4u8uXC1ZmMpatF05PJ?si=x')
        ->assertSet('spotify.id', '1Xyo4u8uXC1ZmMpatF05PJ')
        ->assertSet('name', 'Mavi Gece')
        ->set('spotifyQuery', 'https://open.spotify.com/album/1Xyo4u8uXC1ZmMpatF05PJ')
        ->assertSet('searchMessage', 'Link bir Spotify sanatçı profili değil; open.spotify.com/artist/… biçiminde olmalı.')
        ->set('spotifyQuery', 'https://open.spotify.com/artist/0000000000000000000000')
        ->assertSet('searchMessage', 'Bu linkteki sanatçı Spotify\'da bulunamadı.');
});

it('accepts an Apple Music link or id', function () {
    Livewire::test(ArtistManager::class)
        ->call('create')
        ->set('name', 'Yeni Sanatçı')
        ->set('createNewSpotify', true)
        ->set('appleInput', 'https://music.apple.com/tr/artist/yeni-sanatci/1234567890')
        ->call('save')
        ->assertHasNoErrors();

    $artist = $this->user->artists()->sole();
    expect($artist->apple_music_id)->toBe('1234567890')
        ->and($artist->create_new_apple)->toBeFalse()
        ->and($artist->spotify_artist_id)->toBeNull()
        ->and($artist->create_new_spotify)->toBeTrue();
});

it('asks for a store profile or the new profile option', function () {
    Livewire::test(ArtistManager::class)
        ->call('create')
        ->set('name', 'Deniz')
        ->set('appleInput', 'apple-degil')
        ->call('save')
        ->assertHasErrors(['spotify', 'apple']);

    expect($this->user->artists()->count())->toBe(0);
});

it('does not add the same Spotify profile twice', function () {
    Artist::factory()->for($this->user)->onSpotify('4tZwfgrHOc3mvqYlEYSvVi')->create(['name' => 'Deniz Yılmaz']);

    Livewire::test(ArtistManager::class)
        ->call('create')
        ->set('spotifyQuery', 'https://open.spotify.com/artist/4tZwfgrHOc3mvqYlEYSvVi')
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

it('routes the artist limit through the plan check', function () {
    config(['hova.plans.enforce' => true]);

    Livewire::test(ArtistManager::class)
        ->call('create')
        ->assertSet('editing', null)
        ->assertSet('listError', 'Bu işlem planlar devreye girdiğinde açılacak.');
});
