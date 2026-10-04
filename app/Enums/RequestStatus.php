<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('release.request_statuses.'.$this->value);
    }
}
