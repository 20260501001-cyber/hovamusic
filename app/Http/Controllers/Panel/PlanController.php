<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Billing\BillingUnavailable;
use App\Domain\Billing\CheckoutNotAllowed;
use App\Domain\Billing\CheckoutService;
use App\Domain\Billing\PolarClient;
use App\Domain\Legal\LegalDocuments;
use App\Domain\Plans\PlanGate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StartCheckoutRequest;
use App\Models\Checkout;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request, PlanGate $gate): View
    {
        $user = $request->user();
        $usage = $gate->usage($user);

        return view('panel.plans.index', [
            'usage' => $usage,
            'subscription' => $usage['subscription'],
            'plans' => Plan::query()->availableFor($user->account_type)->get(),
            'orders' => $user->orders()->limit(5)->get(),
            'portalAvailable' => $user->polarCustomer()->exists(),
        ]);
    }

    public function confirm(Request $request, Plan $plan, LegalDocuments $documents): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->activeSubscription() !== null) {
            return redirect()->route('panel.plans.index')->with('flash', __('plans.errors.already_subscribed'));
        }

        abort_unless($plan->is_active && $plan->audience === $user->account_type && $plan->polar_product_id !== null, 404);

        return view('panel.plans.confirm', [
            'plan' => $plan,
            'preInfo' => $documents->byConsentType('on-bilgilendirme'),
            'contract' => $documents->byConsentType('mesafeli-satis'),
        ]);
    }

    public function checkout(StartCheckoutRequest $request, Plan $plan, CheckoutService $checkouts): RedirectResponse
    {
        try {
            $url = $checkouts->start($request->user(), $plan);
        } catch (CheckoutNotAllowed|BillingUnavailable $e) {
            return redirect()->route('panel.plans.index')->with('flash', $e->getMessage());
        }

        return redirect()->away($url);
    }

    /**
     * Ödeme sonrası dönüş. Burada abonelik açılmaz; webhook gelene kadar sayfa
     * kendini yeniler.
     */
    public function processing(Request $request, Checkout $checkout): View|RedirectResponse
    {
        abort_unless($checkout->user_id === $request->user()->id, 404);

        if ($request->user()->activeSubscription() !== null) {
            return redirect()->route('panel.plans.index')->with('flash', __('plans.processing.done'));
        }

        return view('panel.plans.processing', [
            'checkout' => $checkout->load('plan'),
            'waitedLong' => $checkout->created_at->diffInMinutes(now()) >= 15,
            'failed' => in_array($checkout->status, ['failed', 'expired'], true),
        ]);
    }

    public function portal(Request $request, PolarClient $polar): RedirectResponse
    {
        try {
            return redirect()->away($polar->customerPortalUrl($request->user()));
        } catch (BillingUnavailable $e) {
            return redirect()->route('panel.plans.index')->with('flash', $e->getMessage());
        }
    }

    public function orders(Request $request): View
    {
        return view('panel.plans.orders', [
            'orders' => $request->user()->orders()->with('plan')->paginate(20),
            'portalAvailable' => $request->user()->polarCustomer()->exists(),
        ]);
    }
}
