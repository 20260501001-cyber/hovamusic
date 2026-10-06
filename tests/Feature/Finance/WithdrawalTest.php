<?php

use App\Domain\Finance\Ledger;
use App\Domain\Finance\WithdrawalNotAllowed;
use App\Domain\Finance\Withdrawals;
use App\Enums\AdminRole;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Enums\WithdrawalStatus;
use App\Models\Admin;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalStatusChanged;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->create();
    $this->admin = Admin::factory()->withRole(AdminRole::Finance)->create();
    $this->withdrawals = app(Withdrawals::class);
    $this->ledger = app(Ledger::class);
});

it('reserves the requested amount and keeps the total balance unchanged', function () {
    readyForWithdrawal($this->user);
    credit($this->user, '125.50');

    $withdrawal = $this->withdrawals->request($this->user, '100.25');
    $balances = $this->ledger->balances($this->user);

    expect($withdrawal->status)->toBe(WithdrawalStatus::Pending)
        ->and((string) $withdrawal->amount_usd)->toBe('100.25')
        ->and($withdrawal->payout_snapshot['last4'])->toBe('1326')
        ->and(DB::table('withdrawals')->value('payout_snapshot'))->not->toContain('TR33')
        ->and((string) $balances->available)->toBe('25.250000')
        ->and((string) $balances->reserved)->toBe('100.250000')
        ->and((string) $balances->total())->toBe('125.500000');

    Notification::assertSentTo($this->user, WithdrawalStatusChanged::class);
});

it('enforces the minimum amount, two decimals and the available balance', function (string $amount, string $error) {
    readyForWithdrawal($this->user);
    credit($this->user, '50');
    credit($this->user, '500', LedgerBucket::Blocked);

    expect(fn () => $this->withdrawals->request($this->user, $amount))->toThrow(WithdrawalNotAllowed::class, __($error, ['amount' => Withdrawals::MINIMUM_USD]))
        ->and(Withdrawal::query()->count())->toBe(0)
        ->and((string) $this->ledger->balance($this->user, LedgerBucket::Available))->toBe('50.000000');
})->with([
    'below minimum' => ['19.99', 'finance.withdrawals.errors.minimum'],
    'three decimals' => ['25.001', 'finance.withdrawals.errors.decimals'],
    'more than available (blocked does not count)' => ['50.01', 'finance.withdrawals.errors.balance'],
]);

it('requires billing details, a payout method and a valid tax form', function () {
    credit($this->user, '100');

    expect(fn () => $this->withdrawals->request($this->user, '50'))->toThrow(WithdrawalNotAllowed::class, __('finance.withdrawals.errors.profile'));

    readyForWithdrawal($this->user);
    $this->user->taxForms()->update(['expires_at' => now()->subDay()->toDateString()]);
    $this->user->refresh();

    expect($this->withdrawals->requirements($this->user))->toBe(['profile' => true, 'payout' => true, 'tax_form' => false])
        ->and(fn () => $this->withdrawals->request($this->user, '50'))->toThrow(WithdrawalNotAllowed::class, __('finance.withdrawals.errors.tax_form'));
});

it('allows only one open request at a time', function () {
    readyForWithdrawal($this->user);
    credit($this->user, '100');

    $this->withdrawals->request($this->user, '30');

    expect(fn () => $this->withdrawals->request($this->user, '30'))->toThrow(WithdrawalNotAllowed::class, __('finance.withdrawals.errors.open_exists'))
        ->and(Withdrawal::query()->count())->toBe(1);
});

it('returns the reserved amount when a request is rejected', function () {
    readyForWithdrawal($this->user);
    credit($this->user, '80');
    $withdrawal = $this->withdrawals->request($this->user, '60');

    expect(fn () => $this->withdrawals->reject($withdrawal, $this->admin, '  '))->toThrow(WithdrawalNotAllowed::class);

    $this->withdrawals->reject($withdrawal, $this->admin, 'IBAN sahibi ile ad uyuşmuyor');
    $balances = $this->ledger->balances($this->user);

    expect($withdrawal->refresh()->status)->toBe(WithdrawalStatus::Rejected)
        ->and($withdrawal->reject_reason)->toBe('IBAN sahibi ile ad uyuşmuyor')
        ->and((string) $balances->available)->toBe('80.000000')
        ->and((string) $balances->reserved)->toBe('0.000000')
        ->and(fn () => $this->withdrawals->reject($withdrawal, $this->admin, 'Tekrar'))->toThrow(WithdrawalNotAllowed::class, __('finance.withdrawals.errors.status'));
});

it('pays out the net amount and records the Wise fee separately', function () {
    app(Settings::class)->put(['wise_fee_fixed_usd' => '1.50', 'wise_fee_pct' => '0.5']);
    readyForWithdrawal($this->user);
    credit($this->user, '200');

    $withdrawal = $this->withdrawals->request($this->user, '150');

    expect((string) $withdrawal->estimated_fee_usd)->toBe('2.25')
        ->and(fn () => $this->withdrawals->markPaid($withdrawal, $this->admin, '2.10', '5100', 'W-1'))->toThrow(WithdrawalNotAllowed::class, __('finance.withdrawals.errors.status'));

    $this->withdrawals->approve($withdrawal, $this->admin);

    expect(fn () => $this->withdrawals->markPaid($withdrawal, $this->admin, '150', '5100', 'W-1'))->toThrow(WithdrawalNotAllowed::class, __('finance.withdrawals.errors.fee'));

    $paid = $this->withdrawals->markPaid($withdrawal, $this->admin, '2.10', '5100.40', ' TRANSFER-123 ');
    $balances = $this->ledger->balances($this->user);

    expect($paid->status)->toBe(WithdrawalStatus::Paid)
        ->and((string) $paid->net_usd)->toBe('147.90')
        ->and((string) $paid->fee_usd)->toBe('2.10')
        ->and($paid->wise_reference)->toBe('TRANSFER-123')
        ->and((string) $balances->reserved)->toBe('0.000000')
        ->and((string) $balances->available)->toBe('50.000000')
        ->and((string) LedgerEntry::query()->where('type', LedgerEntryType::WithdrawalPaid)->sole()->amount_usd)->toBe('-147.900000')
        ->and((string) LedgerEntry::query()->where('type', LedgerEntryType::WithdrawalFee)->sole()->amount_usd)->toBe('-2.100000')
        ->and(fn () => $this->withdrawals->reject($paid, $this->admin, 'Geç'))->toThrow(WithdrawalNotAllowed::class);
});

it('accepts a Turkish formatted amount from the panel form', function () {
    readyForWithdrawal($this->user);
    credit($this->user, '2000');

    $this->actingAs($this->user)
        ->post(route('panel.withdrawals.store'), ['amount' => '1.234,50'])
        ->assertRedirect(route('panel.withdrawals.index'))
        ->assertSessionHasNoErrors();

    expect((string) Withdrawal::query()->sole()->amount_usd)->toBe('1234.50');

    $this->actingAs($this->user)
        ->get(route('panel.withdrawals.index'))
        ->assertOk()
        ->assertSee('$1.234,50');
});

it('shows the panel error instead of creating a request without prerequisites', function () {
    credit($this->user, '100');

    $this->actingAs($this->user)
        ->from(route('panel.withdrawals.index'))
        ->post(route('panel.withdrawals.store'), ['amount' => '50'])
        ->assertRedirect(route('panel.withdrawals.index'))
        ->assertSessionHasErrors(['amount' => __('finance.withdrawals.errors.profile')]);

    expect(Withdrawal::query()->count())->toBe(0);
});
