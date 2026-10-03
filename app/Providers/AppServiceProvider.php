<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use App\Models\Admin;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Date::use(CarbonImmutable::class);

        Password::defaults(function () {
            $rule = Password::min(10)->letters()->numbers()->max(128);

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        Event::listen(Login::class, function (Login $event): void {
            if (method_exists($event->user, 'forceFill')) {
                $event->user->forceFill([
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                ])->saveQuietly();
            }

            if ($event->user instanceof Admin) {
                app(AuditLogger::class)->record('admin.login', $event->user, actor: $event->user);
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
