<?php

namespace App\Console\Commands;

use App\Enums\ReleaseStatus;
use App\Models\User;
use App\Support\Images\Screenshots;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Herkese açık site için demo verili panel ekran görüntüleri: yerel sunucuda demo
 * kullanıcıyla oturum açılır, Chrome/Edge headless ile sayfa çekilir ve
 * public/images/panel altına WebP ve AVIF olarak yazılır. Yalnızca local.
 */
class TakeScreenshots extends Command
{
    protected $signature = 'hova:screenshots {--base=http://127.0.0.1:8000} {--only=} {--browser=}';

    protected $description = 'Demo verili panel ekran görüntülerini alır (yalnızca local).';

    private const WIDTH = 1440;

    private const HEIGHT = 900;

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Bu komut yalnızca local ortamda çalışır.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', 'sanatci@demo.hovamusic.test')->first();
        $browser = $this->browser();

        if ($user === null) {
            $this->error('Demo kullanıcı yok: önce "php artisan db:seed --class=DemoSeeder" çalıştır.');

            return self::FAILURE;
        }

        if ($browser === null) {
            $this->error('Chrome ya da Edge bulunamadı; --browser= ile yolunu ver.');

            return self::FAILURE;
        }

        $draft = $user->releases()->where('status', ReleaseStatus::Draft)->latest('id')->first();
        $targets = array_filter([
            'pano' => '/panel',
            'yayinlar' => '/panel/yayinlar',
            'kazanclar' => '/panel/kazanclar',
            'sihirbaz' => $draft ? '/panel/yayinlar/'.$draft->ulid.'/duzenle/1' : null,
        ]);

        if ($only = $this->option('only')) {
            $targets = array_intersect_key($targets, array_flip(explode(',', (string) $only)));
        }

        $base = rtrim((string) $this->option('base'), '/');
        URL::forceRootUrl($base);
        $directory = public_path(Screenshots::DIRECTORY);
        File::ensureDirectoryExists($directory);
        $manifestPath = $directory.'/manifest.json';
        $manifest = is_file($manifestPath) ? (json_decode((string) file_get_contents($manifestPath), true) ?: []) : [];
        $failed = false;

        foreach ($targets as $name => $path) {
            $png = storage_path("app/screenshots/{$name}.png");
            File::ensureDirectoryExists(dirname($png));
            File::delete($png);
            $url = URL::temporarySignedRoute('dev.login', now()->addMinutes(5), ['user' => $user, 'to' => $path]);
            $profile = storage_path('app/screenshots/profile-'.$name);

            $process = new Process([
                $browser, '--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
                '--user-data-dir='.$profile, '--window-size='.self::WIDTH.','.self::HEIGHT, '--force-device-scale-factor=2',
                '--virtual-time-budget=8000', '--screenshot='.$png, $url,
            ]);
            $process->setTimeout(90);
            $process->run();
            File::deleteDirectory($profile);

            if (! is_file($png)) {
                $this->error("{$name}: ekran görüntüsü alınamadı. ".trim($process->getErrorOutput()));
                $failed = true;

                continue;
            }

            $manifest[$name] = $this->encode($png, $directory, $name);
            File::delete($png);
            $this->info("{$name}: {$path} -> ".Screenshots::DIRECTORY."/{$name}-*.webp");
        }

        if (! $this->option('only') || in_array('og', explode(',', (string) $this->option('only')), true)) {
            $failed = ! $this->openGraphImage($browser) || $failed;
        }

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT)."\n");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Varsayılan paylaşım görseli (public/images/og-default.png), site fontlarıyla.
     */
    private function openGraphImage(string $browser): bool
    {
        $png = public_path('images/og-default.png');
        $profile = storage_path('app/screenshots/profile-og');
        $process = new Process([
            $browser, '--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
            '--user-data-dir='.$profile, '--window-size=1200,630', '--force-device-scale-factor=1',
            '--virtual-time-budget=5000', '--screenshot='.$png, route('dev.og'),
        ]);
        $process->setTimeout(60);
        $process->run();
        File::deleteDirectory($profile);
        $this->info('og: images/og-default.png');

        return is_file($png);
    }

    /**
     * @return array{width: int, height: int}
     */
    private function encode(string $png, string $directory, string $name): array
    {
        $manager = ImageManager::usingDriver(GdDriver::class);
        $size = ['width' => 0, 'height' => 0];

        foreach (Screenshots::WIDTHS as $width) {
            $image = $manager->decodePath($png)->scaleDown(width: $width);
            $size = ['width' => $image->width(), 'height' => $image->height()];
            file_put_contents("{$directory}/{$name}-{$width}.webp", (string) $image->encode(new WebpEncoder(quality: 82, strip: true)));

            if (function_exists('imageavif')) {
                try {
                    file_put_contents("{$directory}/{$name}-{$width}.avif", (string) $image->encode(new AvifEncoder(quality: 55, strip: true)));
                } catch (Throwable) {
                    // AVIF desteklenmiyorsa yalnızca WebP kalır.
                }
            }
        }

        return $size;
    }

    private function browser(): ?string
    {
        $candidates = array_filter([
            $this->option('browser'),
            getenv('ProgramFiles') ? getenv('ProgramFiles').'\\Google\\Chrome\\Application\\chrome.exe' : null,
            getenv('ProgramFiles(x86)') ? getenv('ProgramFiles(x86)').'\\Google\\Chrome\\Application\\chrome.exe' : null,
            getenv('LOCALAPPDATA') ? getenv('LOCALAPPDATA').'\\Google\\Chrome\\Application\\chrome.exe' : null,
            getenv('ProgramFiles(x86)') ? getenv('ProgramFiles(x86)').'\\Microsoft\\Edge\\Application\\msedge.exe' : null,
            getenv('ProgramFiles') ? getenv('ProgramFiles').'\\Microsoft\\Edge\\Application\\msedge.exe' : null,
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
