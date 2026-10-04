<?php

namespace App\Http\Middleware;

use App\Domain\Users\Impersonation;
use App\Http\Controllers\ImpersonationController;
use App\Support\Admin\AdminContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin kullanıcı olarak görüntülerken kullanıcı tarafında yalnızca okuma yapılır:
 * GET ve HEAD dışındaki istekler reddedilir. Admin paneli istekleri etkilenmez.
 * Çıkış isteği görüntülemeyi bitirir; admin oturumu açık kalır.
 */
class BlockWhileImpersonating
{
    public function __construct(private readonly Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->impersonation->pending($request)) {
            return $next($request);
        }

        if (! $this->impersonation->active($request)) {
            $this->impersonation->end($request);

            return $next($request);
        }

        if (AdminContext::isAdminRequest($request) || $request->isMethodSafe() || $request->routeIs('impersonation.end')) {
            return $next($request);
        }

        if ($request->routeIs('logout')) {
            return app(ImpersonationController::class)($request, $this->impersonation);
        }

        $message = __('panel.impersonation.blocked');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return response($message, 403)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
