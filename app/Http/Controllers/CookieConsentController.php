<?php

namespace App\Http\Controllers;

use App\Domain\Legal\ConsentRecorder;
use App\Support\CookieConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Çerez tercihini bir yıl saklar ve onay kaydı olarak yazar (ziyaretçi kimliğiyle;
 * giriş yapılmışsa kullanıcıyla).
 */
class CookieConsentController extends Controller
{
    public function __invoke(Request $request, ConsentRecorder $consents): RedirectResponse
    {
        $validated = $request->validate([
            'choice' => ['required', 'in:all,essential,custom'],
            'analytics' => ['nullable', 'boolean'],
            'marketing' => ['nullable', 'boolean'],
        ]);

        $choices = collect(CookieConsent::CATEGORIES)->mapWithKeys(fn (string $category): array => [
            $category => match ($validated['choice']) {
                'all' => true,
                'essential' => false,
                default => (bool) ($validated[$category] ?? false),
            },
        ])->all();

        $id = CookieConsent::current($request)['id'] ?? (string) Str::uuid();
        $value = json_encode(['v' => CookieConsent::VERSION, 'id' => $id, ...$choices]);

        $consents->record($request->user(), 'cerez', choices: $choices, visitorId: $id);

        return back()
            ->withCookie(cookie(CookieConsent::COOKIE, (string) $value, 60 * 24 * 365, httpOnly: true, sameSite: 'lax'))
            ->with('flash', __('privacy.cookies.saved'));
    }
}
