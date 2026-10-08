<?php

namespace App\Domain\Artists;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Apple Music sanatçı araması, Apple'ın anahtarsız iTunes Search API'siyle.
 * API IP başına dakikada yaklaşık 20 istek kabul ettiği için sunucu genelinde
 * dakikada 18 istekle sınırlanır ve sonuçlar bir gün önbellekte tutulur.
 */
class ITunesSearchCatalog implements AppleMusicCatalog
{
    private const PER_MINUTE = 18;

    private const LIMIT = 6;

    public function __construct(private readonly string $country = 'TR') {}

    public function searchArtists(string $query): array
    {
        $term = trim(preg_replace('/\s+/u', ' ', $query) ?? '');

        if (mb_strlen($term) < 2) {
            return [];
        }

        $key = 'apple-artist-search:'.$this->country.':'.md5(mb_strtolower($term));
        $rows = Cache::get($key);

        if (! is_array($rows)) {
            $rows = $this->fetch($term);
            Cache::put($key, $rows, now()->addDay());
        }

        return array_map(fn (array $row): AppleMusicArtist => new AppleMusicArtist($row['id'], $row['name'], $row['url'], $row['genre']), $rows);
    }

    /**
     * @return list<array{id: string, name: string, url: string, genre: string|null}>
     *
     * @throws AppleMusicUnavailable
     */
    private function fetch(string $term): array
    {
        if (! RateLimiter::attempt('itunes-search', self::PER_MINUTE, fn (): bool => true, 60)) {
            throw new AppleMusicUnavailable('Arama sınırı doldu.');
        }

        try {
            $response = Http::timeout(8)->acceptJson()->get('https://itunes.apple.com/search', [
                'term' => $term,
                'entity' => 'musicArtist',
                'attribute' => 'artistTerm',
                'country' => $this->country,
                'limit' => self::LIMIT,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Apple Music araması yapılamadı.', ['error' => mb_substr($e->getMessage(), 0, 200)]);

            throw new AppleMusicUnavailable('Bağlantı kurulamadı.', previous: $e);
        }

        if (! $response->successful()) {
            throw new AppleMusicUnavailable('Apple Music araması başarısız: '.$response->status());
        }

        $results = json_decode($response->body(), true)['results'] ?? [];

        return collect(is_array($results) ? $results : [])
            ->filter(fn ($row): bool => is_array($row) && isset($row['artistId'], $row['artistName']))
            ->map(fn (array $row): array => [
                'id' => (string) $row['artistId'],
                'name' => (string) $row['artistName'],
                'url' => strtok((string) ($row['artistLinkUrl'] ?? 'https://music.apple.com/artist/'.$row['artistId']), '?'),
                'genre' => isset($row['primaryGenreName']) ? (string) $row['primaryGenreName'] : null,
            ])
            ->unique('id')
            ->values()
            ->all();
    }
}
