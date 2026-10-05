<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';

    public function label(): string
    {
        return __('finance.withdrawal_statuses.'.$this->value);
    }
}
