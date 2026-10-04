<?php

namespace App\Domain\Spotify;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Spotify Web API (client credentials). Development Mode'da arama en fazla 10 sonuç
 * döndürür; takipçi ve popülerlik alanları kullanılmaz. Arama sonuçları kısa süre
 * önbellekte tutulur, hız sınırına (429) takılınca Retry-After kadar beklenip bir kez
 * yeniden denenir.
 */
class SpotifyWebApiCatalog implements SpotifyCatalog
{
    private const API = 'https://api.spotify.com/v1';

    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';

    private const CONNECT_ATTEMPTS = 4;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly int $maxRetryWait = 3,
    ) {}

    public function isAvailable(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function searchArtists(string $query): array
    {
        $query = Str::squish($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $cached = Cache::remember('spotify.search.'.md5(mb_strtolower($query)), now()->addMinutes(10), function () use ($query): array {
            $response = $this->get('/search', ['q' => $query, 'type' => 'artist', 'limit' => 10]);

            return collect($response->json('artists.items', []))
                ->filter(fn ($item): bool => is_array($item) && isset($item['id'], $item['name']))
                ->take(10)
                ->map(fn (array $item): array => SpotifyArtist::fromApi($item)->toArray())
                ->values()
                ->all();
        });

        return array_map(fn (array $item): SpotifyArtist => new SpotifyArtist($item['id'], $item['name'], $item['url'], $item['image_url']), $cached);
    }

    public function findArtist(string $id): ?SpotifyArtist
    {
        $response = $this->get('/artists/'.rawurlencode($id), allowNotFound: true);

        if ($response->notFound() || $response->status() === 400) {
            return null;
        }

        return SpotifyArtist::fromApi($response->json());
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query = [], bool $allowNotFound = false): Response
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->http()
                    ->withToken($this->token())
                    ->acceptJson()
                    ->get(self::API.$path, $query);
            } catch (ConnectionException $e) {
                Log::warning('Spotify\'a bağlanılamadı.', ['error' => mb_substr($e->getMessage(), 0, 300)]);

                throw new SpotifyUnavailable('Spotify\'a bağlanılamadı.', previous: $e);
            }

            if ($response->status() === 401 && $attempt === 1) {
                Cache::forget($this->tokenCacheKey());

                continue;
            }

            if ($response->status() === 429 && $attempt === 1) {
                $wait = (int) $response->header('Retry-After');

                if ($wait <= $this->maxRetryWait) {
                    sleep(max(1, $wait));

                    continue;
                }
            }

            if ($response->successful() || ($allowNotFound && in_array($response->status(), [400, 404], true))) {
                return $response;
            }

            Log::warning('Spotify isteği başarısız.', ['path' => $path, 'status' => $response->status()]);

            throw new SpotifyUnavailable('Spotify şu an yanıt vermiyor.');
        }
    }

    private function token(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $this->http()
                ->asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post(self::TOKEN_URL, ['grant_type' => 'client_credentials']);
        } catch (ConnectionException $e) {
            Log::warning('Spotify\'a bağlanılamadı.', ['error' => mb_substr($e->getMessage(), 0, 300)]);

            throw new SpotifyUnavailable('Spotify\'a bağlanılamadı.', previous: $e);
        }

        $token = (string) $response->json('access_token');

        if (! $response->successful() || $token === '') {
            Log::warning('Spotify erişim anahtarı alınamadı.', ['status' => $response->status()]);

            throw new SpotifyUnavailable('Spotify erişim anahtarı alınamadı.');
        }

        Cache::put($this->tokenCacheKey(), $token, now()->addSeconds(max(60, (int) $response->json('expires_in', 3600) - 60)));

        return $token;
    }

    /**
     * Bağlantı kurulamazsa (TLS el sıkışması, ağ kesintisi) istek kısa aralıkla
     * yeniden denenir; HTTP hata yanıtları burada yeniden denenmez.
     */
    private function http(): PendingRequest
    {
        return Http::timeout(8)
            ->retry(self::CONNECT_ATTEMPTS, 150, fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false);
    }

    private function tokenCacheKey(): string
    {
        return 'spotify.token.'.md5($this->clientId);
    }
}
