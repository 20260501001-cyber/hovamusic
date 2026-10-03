<?php

use App\Enums\UserStatus;

it('only lets active accounts sign in', function () {
    expect(UserStatus::Active->canSignIn())->toBeTrue()
        ->and(UserStatus::Suspended->canSignIn())->toBeFalse()
        ->and(UserStatus::Banned->canSignIn())->toBeFalse();
});
