<?php

namespace App\Enums;

enum DataRequestStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('privacy.request_statuses.'.$this->value);
    }
}
