<?php

namespace App\Http\Middleware;

use App\Support\Security\Turnstile;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify'ın kendi rotalarına ek koruma: bot doğrulaması ve
 * kayıt / şifre sıfırlama formlarında saatlik deneme sınırı.
 */
class GuardAuthForms
{
    private const TURNSTILE_ROUTES = ['login.store', 'register.store', 'password.email'];

    public function __construct(
        private readonly Turnstile $turnstile,
        private readonly RateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('post')) {
            return $next($request);
        }

        $route = $request->route()?->getName();

        if (in_array($route, self::TURNSTILE_ROUTES, true)
            && ! $this->turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'turnstile' => __('auth.turnstile_failed'),
            ]);
        }

        [$key, $maxAttempts] = match ($route) {
            'register.store' => ['register|'.$request->ip(), config('hova.auth.register_per_hour')],
            'password.email' => ['password-email|'.$request->ip().'|'.Str::lower((string) $request->input('email')), config('hova.auth.password_reset_per_hour')],
            'password.update' => ['password-update|'.$request->ip(), config('hova.auth.password_reset_per_hour')],
            default => [null, null],
        };

        if ($key !== null) {
            if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
                throw ValidationException::withMessages([
                    'email' => __('auth.throttle', ['seconds' => $this->limiter->availableIn($key)]),
                ])->status(Response::HTTP_TOO_MANY_REQUESTS);
            }

            $this->limiter->hit($key, 3600);
        }

        return $next($request);
    }
}
