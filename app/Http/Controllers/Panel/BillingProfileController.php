<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Finance\Withdrawals;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\UpdateBillingProfileRequest;
use App\Models\UserProfile;
use App\Support\Locale\Countries;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('panel.finance.profile', [
            'profile' => $request->user()->profile ?? new UserProfile,
            'countries' => Countries::options(),
        ]);
    }

    public function update(UpdateBillingProfileRequest $request, Withdrawals $withdrawals): RedirectResponse
    {
        $user = $request->user();
        $hadTaxForm = $withdrawals->validTaxForm($user) !== null;
        $data = $request->validated();

        if ($data['entity_type'] !== 'company') {
            $data['company_name'] = null;
        }

        $user->profile()->updateOrCreate([], $data);
        $user->unsetRelation('profile');

        $message = __('finance.profile.saved');

        if ($hadTaxForm && $withdrawals->validTaxForm($user) === null) {
            $message .= ' '.__('finance.profile.tax_form_notice');
        }

        return redirect()->route('panel.finance.profile')->with('flash', $message);
    }
}
