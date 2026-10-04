<?php

namespace App\Console\Commands;

use App\Domain\Media\ChunkedUploads;
use Illuminate\Console\Command;

class PurgeExpiredUploads extends Command
{
    protected $signature = 'hova:purge-uploads';

    protected $description = '24 saattir tamamlanmayan parçalı yüklemeleri siler.';

    public function handle(ChunkedUploads $uploads): int
    {
        $count = $uploads->purgeExpired();

        $this->components->info("Silinen yarım yükleme: {$count}");

        return self::SUCCESS;
    }
}
