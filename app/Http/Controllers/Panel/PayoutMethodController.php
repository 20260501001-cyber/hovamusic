<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\UpdatePayoutMethodRequest;
use App\Models\PayoutMethod;
use App\Support\Locale\Countries;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayoutMethodController extends Controller
{
    public function edit(Request $request): View
    {
        return view('panel.finance.payout', [
            'method' => $request->user()->payoutMethod,
            'countries' => Countries::options(),
            'currencies' => array_combine(PayoutMethod::CURRENCIES, PayoutMethod::CURRENCIES),
        ]);
    }

    public function update(UpdatePayoutMethodRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->except('current_password');
        $method = $user->payoutMethod ?? new PayoutMethod;

        // Yeni hesap numarası girilmediyse kayıtlı olan korunur.
        if (blank($data['iban'] ?? null) && blank($data['account_number'] ?? null)) {
            unset($data['iban'], $data['account_number']);

            if (blank($data['routing_number'] ?? null)) {
                unset($data['routing_number']);
            }
        } else {
            $data['last4'] = substr((string) (($data['iban'] ?? null) ?: $data['account_number']), -4);
        }

        $method->fill($data);
        $method->user()->associate($user);
        $method->save();

        return redirect()->route('panel.finance.payout')->with('flash', __('finance.payout_page.saved'));
    }
}
