<?php

namespace App\Http\Middleware;

use App\Domain\Users\Impersonation;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admin askıdaki ya da banlı hesabı da görüntüleyebilir; görüntüleme salt okunurdur.
        if ($user instanceof User && ! $user->canSignIn() && ! app(Impersonation::class)->active($request)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('auth.inactive'),
            ]);
        }

        return $next($request);
    }
}
