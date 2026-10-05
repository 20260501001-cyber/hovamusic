<?php

namespace App\Http\Controllers\Panel;

use App\Enums\DisplayCurrency;
use App\Enums\ThemePreference;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $twoFactorPending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;

        return view('panel.account', [
            'user' => $user,
            'twoFactorPending' => $twoFactorPending,
            'showRecoveryCodes' => $user->two_factor_secret !== null
                && in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true),
            'themes' => ThemePreference::cases(),
            'currencies' => DisplayCurrency::cases(),
            'dataRequests' => $user->dataRequests()->limit(10)->get(),
        ]);
    }
}
