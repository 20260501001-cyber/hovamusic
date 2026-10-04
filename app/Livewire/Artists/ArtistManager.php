<?php

namespace App\Livewire\Artists;

use App\Domain\Artists\AppleMusicLinkParser;
use App\Domain\Plans\PlanGate;
use App\Domain\Spotify\SpotifyArtist;
use App\Domain\Spotify\SpotifyCatalog;
use App\Domain\Spotify\SpotifyLinkParser;
use App\Domain\Spotify\SpotifyUnavailable;
use App\Models\Artist;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Sanatçı profilleri. Spotify profili adla aranıp seçilir ya da linki yapıştırılıp
 * doğrulanır; Apple Music için sanatçı ID'si ya da linki girilir. Mağazada profili
 * olmayan sanatçı için "yeni profil oluşturulsun" seçilir.
 */
class ArtistManager extends Component
{
    private const SEARCHES_PER_MINUTE = 30;

    /**
     * 'new' ya da düzenlenen profilin ULID'si.
     */
    #[Locked]
    public ?string $editing = null;

    public string $name = '';

    public string $spotifyQuery = '';

    /**
     * Sunucuda doğrulanmış Spotify profili; istemci değiştiremez.
     *
     * @var array{id: string, name: string, url: string, image_url: string|null}|null
     */
    #[Locked]
    public ?array $spotify = null;

    public bool $createNewSpotify = false;

    public string $appleInput = '';

    public bool $createNewApple = false;

    /**
     * @var list<array{id: string, name: string, url: string, image_url: string|null}>
     */
    #[Locked]
    public array $results = [];

    #[Locked]
    public string $searchMessage = '';

    #[Locked]
    public string $notice = '';

    #[Locked]
    public string $listError = '';

    public function create(PlanGate $plans): void
    {
        $gate = $plans->canAddArtist(auth()->user());

        if (! $gate->allowed) {
            $this->listError = (string) $gate->reason;

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
        $this->appleInput = (string) $artist->apple_music_id;
        $this->createNewApple = $artist->create_new_apple;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function updatedSpotifyQuery(): void
    {
        $catalog = app(SpotifyCatalog::class);
        $this->results = [];
        $this->searchMessage = '';
        $query = trim($this->spotifyQuery);

        if (mb_strlen($query) < 2) {
            return;
        }

        $key = 'spotify-search:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, self::SEARCHES_PER_MINUTE)) {
            $this->searchMessage = __('artist.spotify_throttled');

            return;
        }

        RateLimiter::hit($key, 60);

        try {
            if (SpotifyLinkParser::looksLikeLink($query)) {
                $this->lookupLink($catalog, $query);

                return;
            }

            $this->results = array_map(fn (SpotifyArtist $artist): array => $artist->toArray(), $catalog->searchArtists($query));

            if ($this->results === []) {
                $this->searchMessage = __('artist.spotify_no_results', ['query' => $query]);
            }
        } catch (SpotifyUnavailable) {
            $this->searchMessage = __('artist.spotify_unavailable');
        }
    }

    public function selectSpotify(string $id): void
    {
        $match = collect($this->results)->firstWhere('id', $id);

        if ($match === null) {
            return;
        }

        $this->useSpotify($match);
    }

    public function clearSpotify(): void
    {
        $this->spotify = null;
    }

    public function updatedCreateNewSpotify(): void
    {
        if ($this->createNewSpotify) {
            $this->spotify = null;
            $this->results = [];
            $this->spotifyQuery = '';
            $this->searchMessage = '';
        }

        $this->resetErrorBag('spotify');
    }

    public function updatedCreateNewApple(): void
    {
        if ($this->createNewApple) {
            $this->appleInput = '';
        }

        $this->resetErrorBag('apple');
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
            $this->listError = (string) $gate->reason;

            return;
        }

        $this->validate(
            ['name' => ['required', 'string', 'max:150']],
            ['name.required' => __('artist.name_required'), 'name.max' => __('artist.name_too_long')],
        );

        $appleId = AppleMusicLinkParser::artistId($this->appleInput);
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

        if (trim($this->appleInput) !== '' && $appleId === null) {
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

    private function lookupLink(SpotifyCatalog $catalog, string $query): void
    {
        $id = SpotifyLinkParser::artistId($query);

        if ($id === null) {
            $this->searchMessage = __('artist.spotify_link_invalid');

            return;
        }

        $artist = $catalog->findArtist($id);

        if ($artist === null) {
            $this->searchMessage = __('artist.spotify_not_found');

            return;
        }

        $this->useSpotify($artist->toArray());
    }

    /**
     * @param  array{id: string, name: string, url: string, image_url: string|null}  $artist
     */
    private function useSpotify(array $artist): void
    {
        $this->spotify = $artist;
        $this->createNewSpotify = false;
        $this->results = [];
        $this->spotifyQuery = '';
        $this->searchMessage = '';
        $this->resetErrorBag('spotify');

        if (trim($this->name) === '') {
            $this->name = $artist['name'];
        }
    }

    private function artist(string $ulid): Artist
    {
        return auth()->user()->artists()->where('ulid', $ulid)->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->reset(['editing', 'name', 'spotifyQuery', 'spotify', 'createNewSpotify', 'appleInput', 'createNewApple', 'results', 'searchMessage', 'notice', 'listError']);
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.artists.artist-manager', [
            'artists' => auth()->user()->artists()->get(),
        ]);
    }
}
