<?php

namespace App\Domain\Artists;

/**
 * Testlerde kullanılan sahte Apple Music kataloğu. Ağ isteği yapmaz.
 */
class FakeAppleMusicCatalog implements AppleMusicCatalog
{
    /**
     * @var array<string, AppleMusicArtist>
     */
    private array $artists = [];

    public bool $unavailable = false;

    public function __construct()
    {
        $this->add(new AppleMusicArtist('1234567890', 'Deniz Yılmaz', 'https://music.apple.com/tr/artist/deniz-yilmaz/1234567890', 'Pop'));
        $this->add(new AppleMusicArtist('1098765432', 'Mavi Gece', 'https://music.apple.com/tr/artist/mavi-gece/1098765432', 'Alternatif'));
    }

    public function add(AppleMusicArtist $artist): static
    {
        $this->artists[$artist->id] = $artist;

        return $this;
    }

    public function searchArtists(string $query): array
    {
        if ($this->unavailable) {
            throw new AppleMusicUnavailable('Sahte katalog kapalı.');
        }

        $needle = mb_strtolower(trim($query));

        if (mb_strlen($needle) < 2) {
            return [];
        }

        return array_values(array_filter(
            $this->artists,
            fn (AppleMusicArtist $artist): bool => str_contains(mb_strtolower($artist->name), $needle),
        ));
    }
}
