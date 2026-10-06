<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tüm form gönderimleri (POST/PUT/PATCH/DELETE) için genel hız sınırı: kullanıcı ya
 * da IP başına dakikada 60 istek. Livewire güncellemeleri, parçalı dosya yükleme ve
 * webhook'lar kendi sınırlarıyla çalışır. Hassas formlar (giriş, para çekme, iletişim
 * vb.) rotalarında ayrıca daha sıkı sınır taşır.
 */
class ThrottleFormSubmissions
{
    public const PER_MINUTE = 60;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->is('livewire/*', 'livewire-*', 'panel/yuklemeler', 'panel/yuklemeler/*', 'webhooks/*')) {
            return $next($request);
        }

        $user = $request->user();
        $key = 'forms|'.($user !== null ? class_basename($user).':'.$user->getAuthIdentifier() : $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            abort(Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
