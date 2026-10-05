<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Earning = 'earning';
    case EarningReversal = 'earning_reversal';
    case Unblock = 'unblock';
    case WithdrawalReserve = 'withdrawal_reserve';
    case WithdrawalRelease = 'withdrawal_release';
    case WithdrawalPaid = 'withdrawal_paid';
    case WithdrawalFee = 'withdrawal_fee';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return __('finance.entry_types.'.$this->value);
    }
}
