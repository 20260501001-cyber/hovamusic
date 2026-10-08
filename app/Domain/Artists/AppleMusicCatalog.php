<?php

namespace App\Domain\Artists;

interface AppleMusicCatalog
{
    /**
     * En fazla 6 sonuç döner.
     *
     * @return list<AppleMusicArtist>
     *
     * @throws AppleMusicUnavailable
     */
    public function searchArtists(string $query): array;
}
