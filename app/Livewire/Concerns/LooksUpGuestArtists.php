<?php

namespace App\Livewire\Concerns;

use App\Domain\Artists\ArtistFinder;
use App\Domain\Releases\ArtistCredits;
use Livewire\Attributes\Locked;

/**
 * Featuring'deki konuk sanatçı için Spotify ve Apple Music araması: ad yazıldıkça
 * profiller listelenir, seçilen profilin linki kayda yazılır. Listede yoksa link
 * alanı açılır. Profil zorunlu değildir; boş kalırsa mağazada yeni profil açılır.
 */
trait LooksUpGuestArtists
{
    public const GUEST_STORES = ['spotify', 'apple'];

    /**
     * Konuk satırı başına sonuçlar: [indeks => ['spotify' => [...], 'spotify_message' => '', 'apple' => [...], 'apple_message' => '']].
     *
     * @var array<int, array<string, mixed>>
     */
    #[Locked]
    public array $guestLookup = [];

    /**
     * @return list<array<string, mixed>>
     */
    abstract protected function guestEntries(): array;

    /**
     * Satırları bileşene yazar ve kaydeder.
     *
     * @param  list<array<string, mixed>>  $entries
     */
    abstract protected function storeGuestEntries(array $entries): void;

    public function selectGuestProfile(int $index, string $store, string $id): void
    {
        $entries = $this->guestEntries();
        $match = in_array($store, self::GUEST_STORES, true)
            ? collect($this->guestLookup[$index][$store] ?? [])->firstWhere('id', $id)
            : null;

        if ($match === null || ! $this->isGuest($entries[$index] ?? null)) {
            return;
        }

        $entries[$index][$store] = $match['url'];
        $entries[$index][$store.'_name'] = $match['name'];
        $entries[$index][$store.'_image'] = $match['image_url'] ?? null;
        $entries[$index][$store.'_genre'] = $match['genre'] ?? null;
        $entries[$index][$store.'_link'] = false;

        // Ad, Spotify'daki yazımla aynı olur; Apple Music araması bu adla yenilenir.
        if ($store === 'spotify') {
            $entries[$index]['name'] = $match['name'];
        }

        $this->storeGuestEntries($entries);
        $this->lookupGuest($index);
    }

    public function clearGuestProfile(int $index, string $store): void
    {
        $this->resetGuestStore($index, $store, link: false);
    }

    public function guestLinkMode(int $index, string $store, bool $on = true): void
    {
        $this->resetGuestStore($index, $store, link: $on);
    }

    protected function lookupGuest(int $index): void
    {
        $entry = $this->guestEntries()[$index] ?? null;

        if (! $this->isGuest($entry)) {
            unset($this->guestLookup[$index]);

            return;
        }

        $finder = app(ArtistFinder::class);
        $lookup = [];

        foreach (self::GUEST_STORES as $store) {
            $searching = blank($entry[$store] ?? null) && empty($entry[$store.'_link']);
            $found = $searching ? $finder->{$store}((string) ($entry['name'] ?? ''), auth()->id()) : ['results' => [], 'message' => ''];
            $lookup[$store] = $found['results'];
            $lookup[$store.'_message'] = $found['message'];
        }

        $this->guestLookup[$index] = $lookup;
    }

    protected function guestNameIndex(string $property): ?int
    {
        return preg_match('/^(?:form\.)?featuring\.(\d+)\.name$/', $property, $match) ? (int) $match[1] : null;
    }

    private function resetGuestStore(int $index, string $store, bool $link): void
    {
        $entries = $this->guestEntries();

        if (! in_array($store, self::GUEST_STORES, true) || ! $this->isGuest($entries[$index] ?? null)) {
            return;
        }

        $entries[$index][$store] = '';
        $entries[$index][$store.'_name'] = null;
        $entries[$index][$store.'_image'] = null;
        $entries[$index][$store.'_genre'] = null;
        $entries[$index][$store.'_link'] = $link;

        $this->storeGuestEntries($entries);
        $this->lookupGuest($index);
    }

    private function isGuest(mixed $entry): bool
    {
        return is_array($entry) && ($entry['kind'] ?? null) === ArtistCredits::KIND_GUEST;
    }
}
