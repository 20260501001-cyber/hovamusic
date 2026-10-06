<?php

use App\Domain\Finance\Ledger;
use App\Domain\Finance\ManualAdjustments;
use App\Domain\Finance\Money;
use App\Enums\AdminRole;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Models\Admin;
use App\Models\LedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;

it('keeps the balance as the exact sum of entries without floating point drift', function () {
    $user = User::factory()->create();

    foreach (range(1, 10) as $i) {
        credit($user, '0.1');
    }

    credit($user, '0.2', LedgerBucket::Blocked);

    $balances = app(Ledger::class)->balances($user);

    expect((string) $balances->available)->toBe('1.000000')
        ->and((string) $balances->blocked)->toBe('0.200000')
        ->and((string) $balances->reserved)->toBe('0.000000')
        ->and((string) $balances->total())->toBe('1.200000');
});

it('moves money between buckets with a balanced pair of entries', function () {
    $user = User::factory()->create();
    credit($user, '50');

    [$out, $in] = app(Ledger::class)->transfer($user, LedgerBucket::Available, LedgerBucket::Reserved, LedgerEntryType::WithdrawalReserve, BigDecimal::of('20'));

    $balances = app(Ledger::class)->balances($user);

    expect($out->group_id)->toBe($in->group_id)
        ->and((string) $out->amount_usd)->toBe('-20.000000')
        ->and((string) $balances->available)->toBe('30.000000')
        ->and((string) $balances->reserved)->toBe('20.000000')
        ->and((string) $balances->total())->toBe('50.000000');
});

it('never updates or deletes ledger entries', function () {
    $entry = credit(User::factory()->create(), '10');

    expect(fn () => $entry->forceFill(['amount_usd' => '99'])->save())->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and((string) $entry->fresh()->amount_usd)->toBe('10.000000');
});

it('corrects with a reversal entry instead of changing the original', function () {
    $user = User::factory()->create();
    $entry = credit($user, '12.345678');

    $reversal = app(Ledger::class)->reverse($entry, LedgerEntryType::EarningReversal, 'Hatalı kayıt');

    expect($reversal->reversal_of_id)->toBe($entry->id)
        ->and((string) $reversal->amount_usd)->toBe('-12.345678')
        ->and(LedgerEntry::query()->count())->toBe(2)
        ->and((string) app(Ledger::class)->balance($user, LedgerBucket::Available))->toBe('0.000000');
});

it('rounds money at the documented scales', function () {
    expect((string) Money::ledger('1.23456749'))->toBe('1.234567')
        ->and((string) Money::ledger('1.2345675'))->toBe('1.234568')
        ->and((string) Money::payout('20.005'))->toBe('20.01')
        ->and((string) Money::line('0.00000000005'))->toBe('0.0000000001');
});

it('records a manual adjustment with a required reason', function () {
    $user = User::factory()->create();
    $admin = Admin::factory()->withRole(AdminRole::Finance)->create();
    credit($user, '10');

    $adjustment = app(ManualAdjustments::class)->apply($user, LedgerBucket::Available, '-2.50', 'Mükerrer satır düzeltmesi', $admin);

    expect((string) $adjustment->amount_usd)->toBe('-2.500000')
        ->and((string) app(Ledger::class)->balance($user, LedgerBucket::Available))->toBe('7.500000')
        ->and(LedgerEntry::query()->where('type', LedgerEntryType::Adjustment)->first()->created_by_type)->toBe('admin')
        ->and(fn () => app(ManualAdjustments::class)->apply($user, LedgerBucket::Available, '5', '  ', $admin))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ManualAdjustments::class)->apply($user, LedgerBucket::Available, '0', 'Sebep', $admin))->toThrow(InvalidArgumentException::class);
});
