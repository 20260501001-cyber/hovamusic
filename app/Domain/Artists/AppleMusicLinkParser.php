<?php

namespace App\Domain\Artists;

/**
 * Apple Music sanatçı linkinden ya da çıplak sayısal ID'den sanatçı kimliğini okur.
 * Örnek: https://music.apple.com/tr/artist/sezen-aksu/80180812
 */
class AppleMusicLinkParser
{
    public static function artistId(?string $input): ?string
    {
        $value = trim((string) $input);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{5,12}$/', $value)) {
            return $value;
        }

        if (preg_match('#^https?://(?:music|itunes)\.apple\.com/(?:[a-z]{2}/)?artist/(?:[^/?\#]+/)?(?:id)?(\d{5,12})(?:[/?\#].*)?$#i', $value, $match)) {
            return $match[1];
        }

        return null;
    }
}
