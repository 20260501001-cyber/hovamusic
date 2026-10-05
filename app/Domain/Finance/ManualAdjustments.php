<?php

namespace App\Domain\Finance;

use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Models\Admin;
use App\Models\ManualAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Admin'in sebep yazarak yaptığı bakiye düzeltmesi. Tutar artı ya da eksi olabilir;
 * deftere tek kayıt düşer ve düzeltme kaydı ayrıca saklanır.
 */
class ManualAdjustments
{
    public function __construct(private readonly Ledger $ledger) {}

    public function apply(User $user, LedgerBucket $bucket, string $amount, string $reason, Admin $admin): ManualAdjustment
    {
        $reason = trim($reason);
        $value = Money::of(trim($amount));

        if ($value->isZero() || $value->getScale() > Money::LEDGER_SCALE) {
            throw new InvalidArgumentException(__('finance.adjustments.errors.amount'));
        }

        if ($reason === '') {
            throw new InvalidArgumentException(__('finance.adjustments.errors.reason'));
        }

        return DB::transaction(function () use ($user, $bucket, $value, $reason, $admin): ManualAdjustment {
            $this->ledger->lock($user);

            $entry = $this->ledger->post($user, $bucket, LedgerEntryType::Adjustment, $value, null,
                __('finance.ledger.adjustment', ['reason' => $reason]), actor: $admin);

            $adjustment = ManualAdjustment::query()->create([
                'user_id' => $user->id,
                'amount_usd' => (string) Money::ledger($value),
                'bucket' => $bucket->value,
                'reason' => mb_substr($reason, 0, 2000),
                'ledger_entry_id' => $entry->id,
                'created_by' => $admin->id,
            ]);

            return $adjustment;
        });
    }
}
