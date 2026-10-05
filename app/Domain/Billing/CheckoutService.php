<?php

namespace App\Domain\Billing;

use App\Domain\Legal\ConsentRecorder;
use App\Models\Checkout;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Plan satın alma: mesafeli satış sözleşmesi ve ön bilgilendirme formu onayı
 * kaydedilir, Polar ödeme oturumu açılır. Abonelik burada açılmaz; Polar'ın
 * imzalı webhook'u gelince açılır.
 */
class CheckoutService
{
    /**
     * Ödemeden önce onaylanması gereken metinler.
     *
     * @var list<string>
     */
    public const REQUIRED_CONSENTS = ['on-bilgilendirme', 'mesafeli-satis'];

    public function __construct(
        private readonly PolarClient $polar,
        private readonly ConsentRecorder $consents,
    ) {}

    /**
     * @throws CheckoutNotAllowed
     * @throws BillingUnavailable
     */
    public function start(User $user, Plan $plan): string
    {
        if (! $plan->is_active || $plan->polar_product_id === null || $plan->audience !== $user->account_type) {
            throw new CheckoutNotAllowed(__('plans.errors.plan_unavailable'));
        }

        if ($user->activeSubscription() !== null) {
            throw new CheckoutNotAllowed(__('plans.errors.already_subscribed'));
        }

        $checkout = DB::transaction(function () use ($user, $plan): Checkout {
            $checkout = new Checkout;
            $checkout->user()->associate($user);
            $checkout->plan()->associate($plan);
            $checkout->save();

            foreach (self::REQUIRED_CONSENTS as $type) {
                $this->consents->record($user, $type, $checkout, [
                    'plan' => $plan->ulid,
                    'plan_name' => $plan->name,
                    'price_usd' => (string) $plan->price_usd,
                    'interval' => $plan->interval->value,
                ]);
            }

            return $checkout;
        });

        $session = $this->polar->createCheckout(
            $plan,
            $user,
            route('panel.plans.processing', $checkout).'?checkout_id={CHECKOUT_ID}',
            ['user' => $user->ulid, 'plan' => $plan->ulid, 'checkout' => $checkout->ulid],
        );

        $checkout->forceFill(['provider_id' => $session['id']])->save();

        return $session['url'];
    }
}
