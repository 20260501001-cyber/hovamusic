<?php

namespace App\Domain\Releases;

use App\Domain\Spotify\SpotifyCatalog;
use App\Domain\Spotify\SpotifyUnavailable;
use App\Enums\ReleaseStatus;
use App\Enums\SpotifyMatchStatus;
use App\Models\Admin;
use App\Models\Platform;
use App\Models\Release;
use App\Models\SpotifyMatch;
use Illuminate\Support\Facades\DB;

/**
 * "Mağazalara gönderildi" durumundaki yayınları Spotify'da arar: yayın UPC ile,
 * parçalar ISRC ile. Bulunan albüm admin'e öneri olarak düşer; admin onaylarsa
 * Spotify bağlantısı eklenir ve yayın "Yayında" olur. Reddedilen öneri tekrar
 * gösterilmez.
 */
class SpotifyReleaseTracker
{
    public function __construct(
        private readonly SpotifyCatalog $catalog,
        private readonly ReleaseWorkflow $workflow,
    ) {}

    /**
     * @return int Yeni öneri sayısı
     *
     * @throws SpotifyUnavailable
     */
    public function check(Release $release): int
    {
        $release->loadMissing('tracks');
        $created = 0;

        if (filled($release->upc) && ($album = $this->catalog->findAlbumByUpc((string) $release->upc)) !== null) {
            $created += (int) $this->remember($release, [
                'matched_by' => 'upc',
                'track_id' => null,
                'spotify_album_id' => $album->id,
            ], [
                'album_name' => mb_substr($album->name, 0, 300),
                'album_url' => mb_substr($album->url, 0, 300),
            ]);
        }

        foreach ($release->tracks as $track) {
            if (blank($track->isrc) || ($found = $this->catalog->findTrackByIsrc((string) $track->isrc)) === null || $found->albumId === null) {
                continue;
            }

            $created += (int) $this->remember($release, [
                'matched_by' => 'isrc',
                'track_id' => $track->id,
                'spotify_album_id' => $found->albumId,
            ], [
                'album_name' => mb_substr((string) $found->albumName, 0, 300),
                'album_url' => mb_substr((string) $found->albumUrl, 0, 300),
                'spotify_track_id' => $found->id,
            ]);
        }

        $release->forceFill(['spotify_checked_at' => now()])->saveQuietly();

        return $created;
    }

    /**
     * Öneriyi onaylar: Spotify bağlantısını ekler (varsa günceller), aynı albüme ait
     * parça eşleşmelerinden parça kimliklerini yazar ve yayını "Yayında" yapar.
     */
    public function accept(SpotifyMatch $match, Admin $admin): Release
    {
        return DB::transaction(function () use ($match, $admin): Release {
            $release = $match->release()->lockForUpdate()->firstOrFail();
            $platform = Platform::query()->where('slug', 'spotify')->firstOrFail();

            $release->storeLinks()
                ->firstOrNew(['platform_id' => $platform->id])
                ->fill(['url' => $match->album_url, 'source' => 'spotify', 'confirmed_by' => $admin->id])
                ->save();

            $release->forceFill(['spotify_album_id' => $match->spotify_album_id])->save();

            $sameAlbum = $release->spotifyMatches()->where('spotify_album_id', $match->spotify_album_id)->get();

            foreach ($sameAlbum as $item) {
                if ($item->track_id !== null && $item->spotify_track_id !== null) {
                    $item->track()->update(['spotify_track_id' => $item->spotify_track_id]);
                }
            }

            $handled = ['handled_by' => $admin->id, 'handled_at' => now()];
            $release->spotifyMatches()->where('status', SpotifyMatchStatus::Pending)
                ->where('spotify_album_id', $match->spotify_album_id)
                ->update(['status' => SpotifyMatchStatus::Accepted, ...$handled]);
            $release->spotifyMatches()->where('status', SpotifyMatchStatus::Pending)
                ->update(['status' => SpotifyMatchStatus::Dismissed, ...$handled]);

            if ($release->status === ReleaseStatus::Delivered) {
                $this->workflow->transition($release, ReleaseStatus::Live, $admin);
            }

            return $release;
        });
    }

    public function dismiss(SpotifyMatch $match, Admin $admin): void
    {
        $match->release->spotifyMatches()
            ->where('status', SpotifyMatchStatus::Pending)
            ->where('spotify_album_id', $match->spotify_album_id)
            ->update(['status' => SpotifyMatchStatus::Dismissed, 'handled_by' => $admin->id, 'handled_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    private function remember(Release $release, array $keys, array $values): bool
    {
        $match = $release->spotifyMatches()->getQuery()->where($keys)->first();

        if ($match !== null) {
            return false;
        }

        $match = new SpotifyMatch([...$keys, ...$values]);
        $match->release()->associate($release);
        $match->save();

        return true;
    }
}
