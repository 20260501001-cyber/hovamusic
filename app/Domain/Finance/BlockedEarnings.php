<?php

namespace App\Domain\Finance;

use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Events\PlanBecameActive;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Plan yenilenince ya da yeni plan alınınca bloke kazancın tamamı kullanılabilir
 * kovaya aktarılır.
 */
class BlockedEarnings
{
    public function __construct(private readonly Ledger $ledger) {}

    public function handle(PlanBecameActive $event): void
    {
        $this->release($event->user);
    }

    public function release(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->ledger->lock($user);
            $blocked = $this->ledger->balance($user, LedgerBucket::Blocked);

            if ($blocked->isPositive()) {
                $this->ledger->transfer($user, LedgerBucket::Blocked, LedgerBucket::Available, LedgerEntryType::Unblock, $blocked,
                    description: __('finance.ledger.unblock'));
            }
        });
    }
}
