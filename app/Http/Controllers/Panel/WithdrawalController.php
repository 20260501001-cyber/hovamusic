<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Finance\DisplayMoney;
use App\Domain\Finance\Ledger;
use App\Domain\Finance\TaxForms;
use App\Domain\Finance\WithdrawalNotAllowed;
use App\Domain\Finance\Withdrawals;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StoreWithdrawalRequest;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function index(Request $request, Withdrawals $withdrawals, Ledger $ledger, TaxForms $taxForms, Settings $settings): View
    {
        $user = $request->user();

        return view('panel.withdrawals.index', [
            'balances' => $ledger->balances($user),
            'money' => DisplayMoney::for($user),
            'requirements' => $withdrawals->requirements($user),
            'taxFormType' => $taxForms->typeFor($user),
            'payoutMethod' => $user->payoutMethod,
            'hasOpen' => $user->withdrawals()->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Approved])->exists(),
            'history' => $user->withdrawals()->latest('id')->paginate(20),
            'minimum' => Withdrawals::MINIMUM_USD,
            'feeFixed' => (string) $settings->get('wise_fee_fixed_usd'),
            'feePct' => (string) $settings->get('wise_fee_pct'),
        ]);
    }

    public function store(StoreWithdrawalRequest $request, Withdrawals $withdrawals): RedirectResponse
    {
        try {
            $withdrawals->request($request->user(), $request->validated('amount'));
        } catch (WithdrawalNotAllowed $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('panel.withdrawals.index')->with('flash', __('finance.withdrawals_page.submitted'));
    }
}
