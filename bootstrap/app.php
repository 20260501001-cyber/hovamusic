<?php

use App\Http\Middleware\BlockWhileImpersonating;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\GuardAuthForms;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\RestrictAdminIp;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottleFormSubmissions;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(HandleRedirects::class);
        $middleware->web(append: [SecurityHeaders::class, ThrottleFormSubmissions::class, EnsureAccountIsActive::class, BlockWhileImpersonating::class]);

        $middleware->alias([
            'auth.forms' => GuardAuthForms::class,
            'account.active' => EnsureAccountIsActive::class,
            'admin.ip' => RestrictAdminIp::class,
        ]);

        $proxies = env('TRUSTED_PROXIES');

        if (filled($proxies)) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('panel.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'iban', 'account_number', 'routing_number', 'tax_id']);
    })->create();
