<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Gece yedeği: veritabanı dökümü (gzip) ve özel diskteki kapaklar ile vergi
 * formları (tar.gz) BACKUP_PATH altına yazılır. Son 7 günlük ve 4 haftalık yedek
 * tutulur; haftalık yedek pazar günü (ya da o hafta ilk kez) günlüğün kopyasıdır.
 * Geri yükleme adımları README'de.
 */
class BackupCommand extends Command
{
    protected $signature = 'hova:backup {--dry-run : Ne yapılacağını göster, yedek alma}';

    protected $description = 'Veritabanı ve kapakları yedekler; eski yedekleri temizler.';

    public function handle(): int
    {
        $root = rtrim((string) config('hova.backup.path'), '/\\');
        $stamp = now()->format('Y-m-d_His');
        $daily = $root.DIRECTORY_SEPARATOR.'daily'.DIRECTORY_SEPARATOR.$stamp;
        $weekKey = now()->format('o-\WW');
        $weekly = $root.DIRECTORY_SEPARATOR.'weekly'.DIRECTORY_SEPARATOR.$weekKey;
        $makeWeekly = now()->isSunday() || ! is_dir($weekly);

        if ($this->option('dry-run')) {
            $this->line("Hedef: {$daily}");
            $this->line('Veritabanı: '.config('database.default').' -> database.sql.gz');
            $this->line('Dosyalar: '.implode(', ', config('hova.backup.directories')).' -> files.tar.gz');
            $this->line($makeWeekly ? "Haftalık kopya: {$weekly}" : 'Haftalık kopya bu hafta zaten var.');
            $this->line('Saklama: '.config('hova.backup.keep_daily').' günlük, '.config('hova.backup.keep_weekly').' haftalık');

            return self::SUCCESS;
        }

        File::ensureDirectoryExists($daily, 0750);

        try {
            $this->dumpDatabase($daily.DIRECTORY_SEPARATOR.'database.sql.gz');
            $this->archiveFiles($daily.DIRECTORY_SEPARATOR.'files.tar.gz');
        } catch (RuntimeException $e) {
            File::deleteDirectory($daily);
            $this->error('Yedek alınamadı: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->writeChecksums($daily);

        if ($makeWeekly) {
            File::deleteDirectory($weekly);
            File::copyDirectory($daily, $weekly);
        }

        $this->prune($root.DIRECTORY_SEPARATOR.'daily', (int) config('hova.backup.keep_daily'));
        $this->prune($root.DIRECTORY_SEPARATOR.'weekly', (int) config('hova.backup.keep_weekly'));

        $this->info("Yedek alındı: {$daily}");

        return self::SUCCESS;
    }

    private function dumpDatabase(string $target): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) === 'sqlite') {
            $source = $config['database'];

            if (! is_file($source)) {
                throw new RuntimeException('SQLite dosyası bulunamadı.');
            }

            file_put_contents($target, gzencode((string) file_get_contents($source), 9));

            return;
        }

        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Desteklenmeyen veritabanı sürücüsü: '.($config['driver'] ?? '?'));
        }

        // Şifre komut satırında görünmesin diye ortam değişkeniyle verilir.
        $command = sprintf(
            '%s --single-transaction --quick --routines --triggers --no-tablespaces --default-character-set=utf8mb4 -h %s -P %s -u %s %s | gzip -9 > %s',
            escapeshellarg((string) config('hova.backup.mysqldump')),
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg((string) $config['username']),
            escapeshellarg((string) $config['database']),
            escapeshellarg($target),
        );

        $process = new Process(['bash', '-o', 'pipefail', '-c', $command], null, ['MYSQL_PWD' => (string) $config['password']], null, 3600);
        $process->run();

        if (! $process->isSuccessful() || filesize($target) < 100) {
            throw new RuntimeException('mysqldump başarısız: '.trim($process->getErrorOutput()));
        }
    }

    private function archiveFiles(string $target): void
    {
        $base = Storage::disk('private')->path('');
        $directories = array_values(array_filter(
            config('hova.backup.directories'),
            fn (string $dir): bool => is_dir($base.$dir),
        ));

        if ($directories === []) {
            file_put_contents($target, gzencode('', 9));

            return;
        }

        $process = new Process(['tar', '-czf', $target, '-C', $base, ...$directories], null, null, null, 3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('tar başarısız: '.trim($process->getErrorOutput()));
        }
    }

    private function writeChecksums(string $directory): void
    {
        $lines = [];

        foreach (['database.sql.gz', 'files.tar.gz'] as $file) {
            $lines[] = hash_file('sha256', $directory.DIRECTORY_SEPARATOR.$file).'  '.$file;
        }

        file_put_contents($directory.DIRECTORY_SEPARATOR.'SHA256SUMS', implode("\n", $lines)."\n");
    }

    private function prune(string $directory, int $keep): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = collect(File::directories($directory))->sort()->values();

        foreach ($entries->slice(0, max(0, $entries->count() - $keep)) as $old) {
            File::deleteDirectory($old);
        }
    }
}
