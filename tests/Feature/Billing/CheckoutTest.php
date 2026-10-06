<?php

use App\Enums\AccountType;
use App\Models\Checkout;
use App\Models\Consent;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.polar.access_token' => 'polar-test-token', 'services.polar.server' => 'sandbox']);
    Http::preventStrayRequests();

    $this->user = User::factory()->create();
    $this->plan = Plan::query()->create([
        'name' => 'Sanatçı Yıllık',
        'audience' => AccountType::Artist,
        'interval' => 'year',
        'price_usd' => '49.00',
        'release_limit' => 10,
        'artist_limit' => 1,
        'revenue_share_pct' => '85.00',
        'polar_product_id' => 'prod_sanatci',
        'is_active' => true,
    ]);
});

function consentsAccepted(): array
{
    return ['consents' => ['on-bilgilendirme' => '1', 'mesafeli-satis' => '1']];
}

it('requires the pre-information form and the distance sales contract', function () {
    $this->actingAs($this->user)
        ->post(route('panel.plans.checkout', $this->plan), ['consents' => ['on-bilgilendirme' => '1']])
        ->assertSessionHasErrors(['consents.mesafeli-satis' => __('plans.confirm.contract_required')]);

    expect(Checkout::query()->count())->toBe(0)
        ->and(Consent::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('records both consents and sends the user to the Polar checkout', function () {
    Http::fake(['sandbox-api.polar.sh/v1/checkouts/' => Http::response(['id' => 'chk_1', 'url' => 'https://sandbox.polar.sh/checkout/chk_1'], 201)]);

    $this->actingAs($this->user)
        ->post(route('panel.plans.checkout', $this->plan), consentsAccepted())
        ->assertRedirect('https://sandbox.polar.sh/checkout/chk_1');

    $checkout = Checkout::query()->sole();

    expect($checkout->provider_id)->toBe('chk_1')
        ->and(Consent::query()->where('context_id', $checkout->id)->pluck('type')->sort()->values()->all())->toBe(['mesafeli-satis', 'on-bilgilendirme'])
        ->and(Consent::query()->first()->choices['price_usd'])->toBe('49.00');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer polar-test-token')
        && $request['products'] === ['prod_sanatci']
        && $request['external_customer_id'] === $this->user->ulid
        && str_contains($request['success_url'], $checkout->ulid));
});

it('does not open a second subscription or sell a plan for another account type', function () {
    activePlan($this->user);

    $this->actingAs($this->user)
        ->post(route('panel.plans.checkout', $this->plan), consentsAccepted())
        ->assertRedirect(route('panel.plans.index'))
        ->assertSessionHas('flash', __('plans.errors.already_subscribed'));

    $label = User::factory()->label()->create();

    $this->actingAs($label)
        ->post(route('panel.plans.checkout', $this->plan), consentsAccepted())
        ->assertRedirect(route('panel.plans.index'))
        ->assertSessionHas('flash', __('plans.errors.plan_unavailable'));

    Http::assertNothingSent();
});

it('shows a friendly message when Polar is unavailable', function () {
    Http::fake(['sandbox-api.polar.sh/*' => Http::response(['detail' => 'error'], 500)]);

    $this->actingAs($this->user)
        ->post(route('panel.plans.checkout', $this->plan), consentsAccepted())
        ->assertRedirect(route('panel.plans.index'))
        ->assertSessionHas('flash', __('plans.errors.provider'));
});

it('waits on the processing page until the webhook activates the plan', function () {
    $checkout = new Checkout;
    $checkout->user()->associate($this->user);
    $checkout->plan()->associate($this->plan);
    $checkout->save();

    $this->actingAs($this->user)->get(route('panel.plans.processing', $checkout))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('panel.plans.processing', $checkout))->assertNotFound();

    activePlan($this->user);

    $this->actingAs($this->user)->get(route('panel.plans.processing', $checkout))->assertRedirect(route('panel.plans.index'));
});

it('shows the plans with usage to a subscribed user', function () {
    activePlan($this->user, ['name' => 'Kullanımda Plan', 'release_limit' => 4]);

    $this->actingAs($this->user)
        ->get(route('panel.plans.index'))
        ->assertOk()
        ->assertSee('Kullanımda Plan');
});
