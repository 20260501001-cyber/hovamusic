<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CookieConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Yalnızca local ortamda ve imzalı adresle: demo kullanıcıyla oturum açıp verilen
 * panel sayfasına gider. Ekran görüntüsü komutu (hova:screenshots) kullanır.
 */
class DevLoginController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $to = (string) $request->query('to', '/panel');
        $to = str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : '/panel';
        $consent = json_encode(['v' => CookieConsent::VERSION, 'id' => 'demo', 'analytics' => false, 'marketing' => false]);

        return redirect($to)->withCookie(cookie(CookieConsent::COOKIE, (string) $consent, 60, httpOnly: true, sameSite: 'lax'));
    }
}
