<?php

use App\Domain\Artists\AppleMusicLinkParser;
use App\Domain\Spotify\SpotifyLinkParser;

it('reads a Spotify artist id from links, URIs and bare ids', function (string $input) {
    expect(SpotifyLinkParser::artistId($input))->toBe('0OdUWJ0sBjDrqHygGUXeCF');
})->with([
    'bare id' => '0OdUWJ0sBjDrqHygGUXeCF',
    'uri' => 'spotify:artist:0OdUWJ0sBjDrqHygGUXeCF',
    'link' => 'https://open.spotify.com/artist/0OdUWJ0sBjDrqHygGUXeCF',
    'localized link with share id' => 'https://open.spotify.com/intl-tr/artist/0OdUWJ0sBjDrqHygGUXeCF?si=abc123',
]);

it('rejects links that are not Spotify artist profiles', function (string $input) {
    expect(SpotifyLinkParser::artistId($input))->toBeNull();
})->with([
    'album' => 'https://open.spotify.com/album/0OdUWJ0sBjDrqHygGUXeCF',
    'other host' => 'https://example.com/artist/0OdUWJ0sBjDrqHygGUXeCF',
    'short id' => 'https://open.spotify.com/artist/abc',
    'empty' => '',
]);

it('reads an Apple Music artist id', function (string $input, ?string $expected) {
    expect(AppleMusicLinkParser::artistId($input))->toBe($expected);
})->with([
    ['80180812', '80180812'],
    ['https://music.apple.com/tr/artist/sezen-aksu/80180812', '80180812'],
    ['https://itunes.apple.com/us/artist/id80180812', '80180812'],
    ['https://music.apple.com/tr/album/x/123456789', null],
    ['abc', null],
]);
