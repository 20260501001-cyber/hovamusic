<?php

namespace App\Events;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Kullanıcının planı açıldı ya da değişti. Bloke kazançlar bu olayla serbest kalır.
 */
class PlanBecameActive
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly Subscription $subscription,
    ) {}
}
