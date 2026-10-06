<?php

use App\Domain\Billing\WebhookSignature;
use App\Domain\Finance\Ledger;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlanHistory;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\SubscriptionActivated;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Notification::fake();
    config(['services.polar.webhook_secret' => 'whsec_'.base64_encode('polar-test-anahtari')]);

    $this->user = User::factory()->create();
    $this->plan = Plan::query()->create([
        'name' => 'Sanatçı Yıllık',
        'audience' => $this->user->account_type,
        'interval' => 'year',
        'price_usd' => '49.00',
        'release_limit' => 10,
        'artist_limit' => 1,
        'revenue_share_pct' => '85.00',
        'polar_product_id' => 'prod_sanatci',
        'is_active' => true,
    ]);
});

/**
 * @param  array<string, mixed>  $payload
 */
function polarWebhook(array $payload, ?string $id = null, ?int $timestamp = null, ?string $signature = null): TestResponse
{
    $id ??= 'msg_'.Str::random(20);
    $timestamp ??= time();
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature ??= 'v1,'.(new WebhookSignature((string) config('services.polar.webhook_secret')))->sign($id, (string) $timestamp, $body);

    return test()->call('POST', route('webhooks.polar'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_WEBHOOK_ID' => $id,
        'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
        'HTTP_WEBHOOK_SIGNATURE' => $signature,
    ], $body);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function subscriptionEvent(User $user, array $overrides = [], string $type = 'subscription.active'): array
{
    return [
        'type' => $type,
        'data' => array_merge([
            'id' => 'sub_123',
            'status' => 'active',
            'product_id' => 'prod_sanatci',
            'customer_id' => 'cus_123',
            'customer' => ['id' => 'cus_123', 'external_id' => $user->ulid, 'email' => $user->email],
            'amount' => 4900,
            'currency' => 'usd',
            'started_at' => now()->subMinute()->toIso8601String(),
            'current_period_start' => now()->subMinute()->toIso8601String(),
            'current_period_end' => now()->addYear()->toIso8601String(),
            'cancel_at_period_end' => false,
            'modified_at' => now()->toIso8601String(),
        ], $overrides),
    ];
}

it('rejects requests without a valid signature and stores nothing', function () {
    $payload = subscriptionEvent($this->user);

    polarWebhook($payload, signature: 'v1,'.base64_encode('sahte'))->assertForbidden();
    polarWebhook($payload, timestamp: time() - WebhookSignature::TOLERANCE_SECONDS - 10)->assertForbidden();

    config(['services.polar.webhook_secret' => '']);
    polarWebhook($payload)->assertForbidden();

    expect(WebhookEvent::query()->count())->toBe(0)
        ->and(Subscription::query()->count())->toBe(0);
});

it('activates the plan, records plan history and releases blocked earnings', function () {
    credit($this->user, '30', LedgerBucket::Blocked);

    polarWebhook(subscriptionEvent($this->user))->assertOk()->assertJson(['result' => 'processed']);

    $subscription = Subscription::query()->sole();
    $balances = app(Ledger::class)->balances($this->user);

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan_id)->toBe($this->plan->id)
        ->and((string) $subscription->amount)->toBe('49.00')
        ->and($this->user->activeSubscription()?->is($subscription))->toBeTrue()
        ->and(PlanHistory::query()->where('user_id', $this->user->id)->sole()->revenue_share_pct)->toBe('85.00')
        ->and((string) $balances->blocked)->toBe('0.000000')
        ->and((string) $balances->available)->toBe('30.000000');

    Notification::assertSentTo($this->user, SubscriptionActivated::class);
});

it('processes the same event only once', function () {
    credit($this->user, '30', LedgerBucket::Blocked);
    $payload = subscriptionEvent($this->user);

    polarWebhook($payload, 'msg_ayni')->assertJson(['result' => 'processed']);
    polarWebhook($payload, 'msg_ayni')->assertOk()->assertJson(['result' => 'duplicate']);

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and(Subscription::query()->count())->toBe(1)
        ->and(LedgerEntry::query()->where('type', LedgerEntryType::Unblock)->count())->toBe(2);

    Notification::assertSentToTimes($this->user, SubscriptionActivated::class, 1);
});

it('ignores an older event that arrives after a newer one', function () {
    polarWebhook(subscriptionEvent($this->user, ['status' => 'canceled', 'canceled_at' => now()->toIso8601String(), 'modified_at' => now()->toIso8601String()], 'subscription.canceled'));
    polarWebhook(subscriptionEvent($this->user, ['status' => 'active', 'modified_at' => now()->subHour()->toIso8601String()], 'subscription.updated'));

    expect(Subscription::query()->sole()->status)->toBe(SubscriptionStatus::Canceled);
});

it('keeps a canceled plan usable until the end of the paid period', function () {
    polarWebhook(subscriptionEvent($this->user))->assertOk()->assertJson(['result' => 'processed']);
    polarWebhook(subscriptionEvent($this->user, ['status' => 'canceled', 'cancel_at_period_end' => true, 'modified_at' => now()->addSecond()->toIso8601String()], 'subscription.canceled'))
        ->assertOk()->assertJson(['result' => 'processed']);

    expect($this->user->activeSubscription())->not->toBeNull();

    polarWebhook(subscriptionEvent($this->user, [
        'status' => 'canceled',
        'current_period_end' => now()->subDay()->toIso8601String(),
        'ended_at' => now()->subDay()->toIso8601String(),
        'modified_at' => now()->addSeconds(2)->toIso8601String(),
    ], 'subscription.revoked'))->assertOk()->assertJson(['result' => 'processed']);

    expect($this->user->activeSubscription())->toBeNull()
        ->and(PlanHistory::query()->where('user_id', $this->user->id)->sole()->ends_at)->not->toBeNull();
});

it('records orders and their refunds in cents-accurate amounts', function () {
    $order = [
        'id' => 'ord_1',
        'status' => 'paid',
        'customer_id' => 'cus_123',
        'customer' => ['id' => 'cus_123', 'external_id' => $this->user->ulid],
        'product_id' => 'prod_sanatci',
        'product' => ['name' => 'Sanatçı Yıllık'],
        'billing_reason' => 'purchase',
        'subtotal_amount' => 4900,
        'tax_amount' => 980,
        'total_amount' => 5880,
        'currency' => 'usd',
        'created_at' => now()->toIso8601String(),
    ];

    polarWebhook(['type' => 'order.paid', 'data' => $order])->assertJson(['result' => 'processed']);
    polarWebhook(['type' => 'order.refunded', 'data' => [...$order, 'status' => 'refunded', 'refunded_amount' => 5880]]);

    $stored = Order::query()->sole();

    expect($stored->user_id)->toBe($this->user->id)
        ->and($stored->plan_id)->toBe($this->plan->id)
        ->and($stored->status)->toBe(OrderStatus::Refunded)
        ->and((string) $stored->total)->toBe('58.80')
        ->and((string) $stored->tax)->toBe('9.80')
        ->and((string) $stored->refunded)->toBe('58.80');
});

it('marks events for unknown customers as unresolved without failing the delivery', function () {
    polarWebhook(subscriptionEvent($this->user, ['customer' => ['id' => 'cus_yok', 'external_id' => 'bilinmeyen'], 'customer_id' => 'cus_yok']))
        ->assertOk()
        ->assertJson(['result' => 'unresolved']);

    expect(Subscription::query()->count())->toBe(0)
        ->and(WebhookEvent::query()->sole()->processed_at)->not->toBeNull();
});

it('ignores event types it does not handle', function () {
    polarWebhook(['type' => 'benefit.created', 'data' => ['id' => 'ben_1']])->assertOk()->assertJson(['result' => 'ignored']);
});
