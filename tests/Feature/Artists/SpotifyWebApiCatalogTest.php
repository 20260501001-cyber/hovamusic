<?php

use App\Domain\Spotify\SpotifyUnavailable;
use App\Domain\Spotify\SpotifyWebApiCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function spotifyArtistPayload(string $id, string $name): array
{
    return [
        'id' => $id,
        'name' => $name,
        'external_urls' => ['spotify' => "https://open.spotify.com/artist/{$id}"],
        'images' => [
            ['url' => "https://i.scdn.co/image/{$id}-640", 'width' => 640, 'height' => 640],
            ['url' => "https://i.scdn.co/image/{$id}-160", 'width' => 160, 'height' => 160],
            ['url' => "https://i.scdn.co/image/{$id}-32", 'width' => 32, 'height' => 32],
        ],
    ];
}

it('searches artists with a client credentials token and at most ten results', function () {
    Http::fake([
        'accounts.spotify.com/*' => Http::response(['access_token' => 'erisim', 'expires_in' => 3600]),
        'api.spotify.com/v1/search*' => Http::response(['artists' => ['items' => collect(range(1, 12))
            ->map(fn ($i) => spotifyArtistPayload(str_pad((string) $i, 22, 'A', STR_PAD_LEFT), "Sanatçı {$i}"))->all()]]),
    ]);

    $results = (new SpotifyWebApiCatalog('kimlik', 'gizli'))->searchArtists('Sanatçı');

    expect($results)->toHaveCount(10)
        ->and($results[0]->name)->toBe('Sanatçı 1')
        ->and($results[0]->imageUrl)->toEndWith('-160');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/search')
        && $request['limit'] === 10
        && $request['type'] === 'artist'
        && $request->hasHeader('Authorization', 'Bearer erisim'));
});

it('renews an expired token once', function () {
    Http::fake([
        'accounts.spotify.com/*' => Http::sequence()
            ->push(['access_token' => 'eski', 'expires_in' => 3600])
            ->push(['access_token' => 'yeni', 'expires_in' => 3600]),
        'api.spotify.com/v1/artists/*' => Http::sequence()
            ->push(['error' => ['status' => 401]], 401)
            ->push(spotifyArtistPayload('0OdUWJ0sBjDrqHygGUXeCF', 'Deniz')),
    ]);

    $artist = (new SpotifyWebApiCatalog('kimlik', 'gizli'))->findArtist('0OdUWJ0sBjDrqHygGUXeCF');

    expect($artist?->name)->toBe('Deniz');
    Http::assertSentCount(4);
});

it('returns null for an unknown artist', function () {
    Http::fake([
        'accounts.spotify.com/*' => Http::response(['access_token' => 'erisim', 'expires_in' => 3600]),
        'api.spotify.com/*' => Http::response(['error' => ['status' => 404]], 404),
    ]);

    expect((new SpotifyWebApiCatalog('kimlik', 'gizli'))->findArtist('0OdUWJ0sBjDrqHygGUXeCF'))->toBeNull();
});

it('reports Spotify as unavailable when the service fails', function () {
    Http::fake([
        'accounts.spotify.com/*' => Http::response(['access_token' => 'erisim', 'expires_in' => 3600]),
        'api.spotify.com/*' => Http::response('', 503),
    ]);

    (new SpotifyWebApiCatalog('kimlik', 'gizli'))->searchArtists('Deniz');
})->throws(SpotifyUnavailable::class);

it('reports Spotify as unavailable when the credentials are rejected', function () {
    Http::fake(['accounts.spotify.com/*' => Http::response(['error' => 'invalid_client'], 400)]);

    (new SpotifyWebApiCatalog('kimlik', 'yanlis'))->searchArtists('Deniz');
})->throws(SpotifyUnavailable::class);

it('retries a request when the connection fails', function () {
    Http::fake([
        'accounts.spotify.com/*' => Http::response(['access_token' => 'erisim', 'expires_in' => 3600]),
        'api.spotify.com/*' => Http::sequence()
            ->pushFailedConnection()
            ->pushFailedConnection()
            ->push(spotifyArtistPayload('0OdUWJ0sBjDrqHygGUXeCF', 'Deniz')),
    ]);

    expect((new SpotifyWebApiCatalog('kimlik', 'gizli'))->findArtist('0OdUWJ0sBjDrqHygGUXeCF')?->name)->toBe('Deniz');
});

it('gives up after repeated connection failures', function () {
    Http::fake([
        'accounts.spotify.com/*' => Http::response(['access_token' => 'erisim', 'expires_in' => 3600]),
        'api.spotify.com/*' => Http::failedConnection(),
    ]);

    (new SpotifyWebApiCatalog('kimlik', 'gizli'))->searchArtists('Deniz');
})->throws(SpotifyUnavailable::class);
