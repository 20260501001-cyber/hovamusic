<?php

namespace App\Enums;

/**
 * Polar abonelik durumları. "active" ve "trialing" kullanımdadır; "past_due" ve
 * "canceled" dönem sonuna kadar kullanımda sayılır.
 */
enum SubscriptionStatus: string
{
    case Incomplete = 'incomplete';
    case IncompleteExpired = 'incomplete_expired';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Unpaid = 'unpaid';
    case Revoked = 'revoked';

    public function label(): string
    {
        return __('plans.subscription_statuses.'.$this->value);
    }
}
