<?php

namespace App\Console\Commands;

use App\Domain\Releases\SpotifyReleaseTracker;
use App\Domain\Spotify\SpotifyCatalog;
use App\Domain\Spotify\SpotifyUnavailable;
use App\Enums\ReleaseStatus;
use App\Models\Release;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TrackSpotifyReleases extends Command
{
    protected $signature = 'hova:spotify-track';

    protected $description = 'Mağazalara gönderilen yayınları Spotify\'da UPC ve ISRC ile arar; bulunanları admin\'e öneri olarak ekler.';

    public function handle(SpotifyCatalog $catalog, SpotifyReleaseTracker $tracker): int
    {
        if (! $catalog->isAvailable()) {
            $this->components->warn('Spotify bağlantısı yapılandırılmadı; yayın takibi atlandı.');

            return self::SUCCESS;
        }

        $checked = 0;
        $suggestions = 0;
        $failed = 0;

        Release::query()
            ->where('status', ReleaseStatus::Delivered)
            ->with('tracks')
            ->orderBy('id')
            ->chunkById(50, function ($releases) use ($tracker, &$checked, &$suggestions, &$failed): void {
                foreach ($releases as $release) {
                    try {
                        $suggestions += $tracker->check($release);
                        $checked++;
                    } catch (SpotifyUnavailable $e) {
                        $failed++;
                        Log::warning('Spotify yayın takibi yapılamadı.', ['release' => $release->ulid, 'error' => $e->getMessage()]);
                    }
                }
            });

        $this->components->info("Kontrol edilen yayın: {$checked}, yeni öneri: {$suggestions}, hata: {$failed}");

        return self::SUCCESS;
    }
}
