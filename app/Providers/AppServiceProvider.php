<?php

namespace App\Providers;

use App\Domain\Billing\PolarClient;
use App\Domain\Media\AudioProbe;
use App\Domain\Media\FfprobeAudioProbe;
use App\Domain\Spotify\FakeSpotifyCatalog;
use App\Domain\Spotify\SpotifyCatalog;
use App\Domain\Spotify\SpotifyWebApiCatalog;
use App\Domain\Spotify\UnavailableSpotifyCatalog;
use App\Domain\Users\Impersonation;
use App\Models\Admin;
use App\Support\Audit\AuditLogger;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Horizon\Horizon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);

        $this->app->singleton(SpotifyCatalog::class, function (): SpotifyCatalog {
            if ($this->app->runningUnitTests()) {
                return new FakeSpotifyCatalog;
            }

            $id = (string) config('services.spotify.client_id');
            $secret = (string) config('services.spotify.client_secret');

            return $id !== '' && $secret !== ''
                ? new SpotifyWebApiCatalog($id, $secret)
                : new UnavailableSpotifyCatalog;
        });

        $this->app->singleton(PolarClient::class, fn (): PolarClient => PolarClient::fromConfig());

        $this->app->bind(AudioProbe::class, fn (): AudioProbe => new FfprobeAudioProbe((string) config('hova.media.ffprobe')));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Geliştirmede Resend anahtarı yoksa e-postalar log'a yazılır.
        if (! $this->app->isProduction() && config('mail.default') === 'resend' && blank(config('services.resend.key'))) {
            config(['mail.default' => 'log']);
        }

        Date::use(CarbonImmutable::class);

        // CSP satır içi script'e yalnızca nonce ile izin veriyor. Filament'in
        // düzen şablonlarındaki satır içi script'ler (tema, menü durumu) nonce
        // taşımadığı için engelleniyordu; nonce'u olmayan her <script> etiketine
        // derleme sırasında isteğin nonce'u eklenir.
        Blade::precompiler(static fn (string $template): string => preg_replace(
            '/<script(?=[\s>])(?![^>]*\bnonce=)/i',
            '<script nonce="{{ Vite::cspNonce() }}"',
            $template,
        ));

        // Ses yüklemesi 4 MB'lık parçalarla yapılır; 1 GB'lık dosya yaklaşık 256 istek.
        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perMinute(600)->by((string) ($request->user()?->id ?: $request->ip())));

        Password::defaults(function () {
            $rule = Password::min(10)->letters()->numbers()->max(128);

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // Kuyruk izleme ekranı (Horizon) yalnızca Süper Admin'e açık.
        Horizon::auth(fn (Request $request): bool => (bool) ($request->user('admin')?->isSuperAdmin()));

        Event::listen(Login::class, function (Login $event): void {
            // Admin kullanıcı olarak görüntülemeye başladığında kullanıcının son giriş bilgisi değişmez.
            $impersonating = $event->guard === 'web' && app(Impersonation::class)->pending();

            if (! $impersonating && method_exists($event->user, 'forceFill')) {
                $event->user->forceFill([
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                ])->saveQuietly();
            }

            if ($event->user instanceof Admin) {
                app(AuditLogger::class)->record('admin.login', $event->user, actor: $event->user);
            }
        });

        // Admin çıkış yaparsa açık görüntüleme kaydı kapatılır.
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->guard === 'admin' && app(Impersonation::class)->pending()) {
                app(Impersonation::class)->end(request());
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            if ($event->guard === 'admin') {
                app(AuditLogger::class)->record('admin.login_failed', changes: [
                    'email' => $event->credentials['email'] ?? null,
                ]);
            }
        });
    }
}
