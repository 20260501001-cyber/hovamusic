<?php

namespace App\Domain\Spotify;

/**
 * Spotify sanatçı linkinden, URI'sinden ya da çıplak ID'den sanatçı kimliğini okur.
 * Örnek: https://open.spotify.com/intl-tr/artist/0OdUWJ0sBjDrqHygGUXeCF?si=...
 */
class SpotifyLinkParser
{
    private const ID = '[A-Za-z0-9]{22}';

    public static function artistId(?string $input): ?string
    {
        $value = trim((string) $input);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^'.self::ID.'$/', $value)) {
            return $value;
        }

        if (preg_match('/^spotify:artist:('.self::ID.')$/', $value, $match)) {
            return $match[1];
        }

        if (preg_match('#^https?://open\.spotify\.com/(?:intl-[a-z]{2}(?:-[A-Za-z]{2})?/)?artist/('.self::ID.')(?:[/?\#].*)?$#', $value, $match)) {
            return $match[1];
        }

        return null;
    }

    public static function looksLikeLink(?string $input): bool
    {
        $value = trim((string) $input);

        return str_starts_with($value, 'http') || str_starts_with($value, 'spotify:') || str_contains($value, 'open.spotify.com');
    }
}
