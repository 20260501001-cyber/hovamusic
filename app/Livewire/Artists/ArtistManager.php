<?php

namespace App\Livewire\Artists;

use App\Domain\Artists\AppleMusicLinkParser;
use App\Domain\Artists\ArtistFinder;
use App\Domain\Plans\GateResult;
use App\Domain\Plans\PlanGate;
use App\Models\Artist;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Sanatçı profilleri. Ad yazıldıkça Spotify ve Apple Music'te aranır; kullanıcı
 * listeden profili seçer. Profil listede yoksa link eklenir; mağazada profili
 * olmayan sanatçı için "yeni profil oluşturulsun" seçilir.
 */
class ArtistManager extends Component
{
    /**
     * 'new' ya da düzenlenen profilin ULID'si.
     */
    #[Locked]
    public ?string $editing = null;

    public string $name = '';

    /**
     * Sunucuda doğrulanmış Spotify profili; istemci değiştiremez.
     *
     * @var array{id: string, name: string, url: string, image_url: string|null}|null
     */
    #[Locked]
    public ?array $spotify = null;

    public bool $createNewSpotify = false;

    #[Locked]
    public bool $spotifyByLink = false;

    public string $spotifyLink = '';

    /**
     * @var list<array{id: string, name: string, url: string, image_url: string|null}>
     */
    #[Locked]
    public array $spotifyResults = [];

    #[Locked]
    public string $spotifyMessage = '';

    /**
     * Listeden seçilen Apple Music profili.
     *
     * @var array{id: string, name: string, url: string, image_url: null, genre?: string|null}|null
     */
    #[Locked]
    public ?array $apple = null;

    public bool $createNewApple = false;

    #[Locked]
    public bool $appleByLink = false;

    public string $appleInput = '';

    /**
     * @var list<array{id: string, name: string, url: string, image_url: null, genre: string|null}>
     */
    #[Locked]
    public array $appleResults = [];

    #[Locked]
    public string $appleMessage = '';

    #[Locked]
    public string $notice = '';

    #[Locked]
    public string $listError = '';

    public function create(PlanGate $plans): void
    {
        $gate = $plans->canAddArtist(auth()->user());

        if (! $gate->allowed) {
            $this->denied($gate);

            return;
        }

        $this->resetForm();
        $this->editing = 'new';
    }

    public function edit(string $ulid): void
    {
        $artist = $this->artist($ulid);
        $this->authorize('update', $artist);

        $this->resetForm();
        $this->editing = $artist->ulid;
        $this->name = $artist->name;
        $this->spotify = $artist->spotify_artist_id ? [
            'id' => $artist->spotify_artist_id,
            'name' => $artist->name,
            'url' => (string) ($artist->spotify_url ?: 'https://open.spotify.com/artist/'.$artist->spotify_artist_id),
            'image_url' => $artist->spotify_image_url,
        ] : null;
        $this->createNewSpotify = $artist->create_new_spotify;
        $this->apple = $artist->apple_music_id ? [
            'id' => $artist->apple_music_id,
            'name' => $artist->name,
            'url' => 'https://music.apple.com/artist/'.$artist->apple_music_id,
            'image_url' => null,
        ] : null;
        $this->createNewApple = $artist->create_new_apple;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function updatedName(): void
    {
        $this->searchSpotify();
        $this->searchApple();
    }

    public function selectSpotify(string $id): void
    {
        $match = collect($this->spotifyResults)->firstWhere('id', $id);

        if ($match !== null) {
            $this->useSpotify($match);
        }
    }

    public function clearSpotify(): void
    {
        $this->spotify = null;
        $this->searchSpotify();
    }

    public function spotifyLinkMode(bool $on = true): void
    {
        $this->spotifyByLink = $on;
        $this->spotifyLink = '';
        $this->spotifyResults = [];
        $this->spotifyMessage = '';

        if (! $on) {
            $this->searchSpotify();
        }
    }

    public function updatedSpotifyLink(): void
    {
        $found = app(ArtistFinder::class)->spotifyLink($this->spotifyLink, auth()->id());
        $this->spotifyMessage = $found['message'];

        if ($found['artist'] !== null) {
            $this->useSpotify($found['artist']);
        }
    }

    public function updatedCreateNewSpotify(): void
    {
        $this->spotify = null;
        $this->spotifyByLink = false;
        $this->spotifyLink = '';
        $this->spotifyResults = [];
        $this->spotifyMessage = '';
        $this->resetErrorBag('spotify');

        if (! $this->createNewSpotify) {
            $this->searchSpotify();
        }
    }

    public function selectApple(string $id): void
    {
        $match = collect($this->appleResults)->firstWhere('id', $id);

        if ($match === null) {
            return;
        }

        $this->apple = $match;
        $this->createNewApple = false;
        $this->appleResults = [];
        $this->appleMessage = '';
        $this->resetErrorBag('apple');
    }

    public function clearApple(): void
    {
        $this->apple = null;
        $this->searchApple();
    }

    public function appleLinkMode(bool $on = true): void
    {
        $this->appleByLink = $on;
        $this->appleInput = '';
        $this->appleResults = [];
        $this->appleMessage = '';

        if (! $on) {
            $this->searchApple();
        }
    }

    public function updatedCreateNewApple(): void
    {
        $this->apple = null;
        $this->appleByLink = false;
        $this->appleInput = '';
        $this->appleResults = [];
        $this->appleMessage = '';
        $this->resetErrorBag('apple');

        if (! $this->createNewApple) {
            $this->searchApple();
        }
    }

    public function save(): void
    {
        if ($this->editing === null) {
            return;
        }

        $user = auth()->user();
        $artist = $this->editing === 'new' ? null : $this->artist($this->editing);

        if ($artist) {
            $this->authorize('update', $artist);
        } elseif (! ($gate = app(PlanGate::class)->canAddArtist($user))->allowed) {
            $this->denied($gate);

            return;
        }

        $this->validate(
            ['name' => ['required', 'string', 'max:150']],
            ['name.required' => __('artist.name_required'), 'name.max' => __('artist.name_too_long')],
        );

        $appleId = $this->apple['id'] ?? ($this->appleByLink ? AppleMusicLinkParser::artistId($this->appleInput) : null);
        $errors = [];

        if ($this->spotify === null && ! $this->createNewSpotify) {
            $errors['spotify'] = __('artist.spotify_required');
        }

        if ($this->spotify !== null) {
            $duplicate = $user->artists()
                ->where('spotify_artist_id', $this->spotify['id'])
                ->when($artist, fn ($query) => $query->whereKeyNot($artist->id))
                ->value('name');

            if ($duplicate !== null) {
                $errors['spotify'] = __('artist.spotify_duplicate', ['name' => $duplicate]);
            }
        }

        if ($this->appleByLink && trim($this->appleInput) !== '' && $appleId === null) {
            $errors['apple'] = __('artist.apple_invalid');
        } elseif ($appleId === null && ! $this->createNewApple) {
            $errors['apple'] = __('artist.apple_required');
        }

        if ($errors !== []) {
            foreach ($errors as $field => $message) {
                $this->addError($field, $message);
            }

            return;
        }

        if ($artist === null) {
            $artist = new Artist;
            $artist->user()->associate($user);
        }

        $artist->fill([
            'name' => trim($this->name),
            'spotify_artist_id' => $this->spotify['id'] ?? null,
            'spotify_url' => $this->spotify['url'] ?? null,
            'spotify_image_url' => $this->spotify['image_url'] ?? null,
            'create_new_spotify' => $this->spotify === null,
            'apple_music_id' => $appleId,
            'create_new_apple' => $appleId === null,
        ])->save();

        $this->resetForm();
        $this->notice = __('artist.saved');
    }

    public function delete(string $ulid): void
    {
        $artist = $this->artist($ulid);
        $this->authorize('delete', $artist);
        $this->notice = '';
        $this->listError = '';

        if ($artist->isUsed()) {
            $this->listError = __('artist.in_use');

            return;
        }

        $artist->delete();

        if ($this->editing === $ulid) {
            $this->resetForm();
        }

        $this->notice = __('artist.deleted');
    }

    private function searchSpotify(): void
    {
        if ($this->editing === null || $this->spotify !== null || $this->createNewSpotify || $this->spotifyByLink) {
            return;
        }

        $found = app(ArtistFinder::class)->spotify($this->name, auth()->id());
        $this->spotifyResults = $found['results'];
        $this->spotifyMessage = $found['message'];
    }

    private function searchApple(): void
    {
        if ($this->editing === null || $this->apple !== null || $this->createNewApple || $this->appleByLink) {
            return;
        }

        $found = app(ArtistFinder::class)->apple($this->name, auth()->id());
        $this->appleResults = $found['results'];
        $this->appleMessage = $found['message'];
    }

    /**
     * Profil seçilince ad, mağazadaki yazımla aynı olur; Apple Music araması da bu adla
     * yenilenir.
     *
     * @param  array{id: string, name: string, url: string, image_url: string|null}  $artist
     */
    private function useSpotify(array $artist): void
    {
        $this->spotify = $artist;
        $this->createNewSpotify = false;
        $this->spotifyByLink = false;
        $this->spotifyLink = '';
        $this->spotifyResults = [];
        $this->spotifyMessage = '';
        $this->resetErrorBag('spotify');

        if (trim($this->name) !== $artist['name']) {
            $this->name = $artist['name'];
            $this->searchApple();
        }
    }

    private function artist(string $ulid): Artist
    {
        return auth()->user()->artists()->where('ulid', $ulid)->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editing', 'name', 'spotify', 'createNewSpotify', 'spotifyByLink', 'spotifyLink', 'spotifyResults', 'spotifyMessage',
            'apple', 'createNewApple', 'appleByLink', 'appleInput', 'appleResults', 'appleMessage', 'notice', 'listError',
        ]);
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.artists.artist-manager', [
            'artists' => auth()->user()->artists()->get(),
        ]);
    }

    /**
     * Plan yoksa ya da sanatçı limiti dolduysa kullanıcı plan sayfasına gider.
     */
    private function denied(GateResult $gate): void
    {
        if ($gate->redirectToPlans) {
            session()->flash('flash', (string) $gate->reason);
            $this->redirectRoute('panel.plans.index');

            return;
        }

        $this->listError = (string) $gate->reason;
    }
}
