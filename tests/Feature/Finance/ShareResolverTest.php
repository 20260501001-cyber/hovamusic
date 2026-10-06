<?php

use App\Domain\Finance\ShareResolver;
use App\Models\User;
use Illuminate\Support\Carbon;

it('uses the plan active on the last day of the sales month', function () {
    $user = User::factory()->create();
    planHistory($user, '70.00', '2026-01-01', '2026-03-15');
    planHistory($user, '85.00', '2026-03-15');

    $resolver = app(ShareResolver::class);

    expect($resolver->shareFor($user->id, Carbon::parse('2026-02-01')))->toBe('70.00')
        ->and($resolver->shareFor($user->id, Carbon::parse('2026-03-01')))->toBe('85.00')
        ->and($resolver->shareFor($user->id, Carbon::parse('2026-05-01')))->toBe('85.00');
});

it('falls back to the last active plan when there was no plan that day', function () {
    $user = User::factory()->create();
    planHistory($user, '80.00', '2025-06-01', '2025-12-01');

    expect(app(ShareResolver::class)->shareFor($user->id, Carbon::parse('2026-04-01')))->toBe('80.00');
});

it('uses the first plan for sales before the user ever had one, and null without history', function () {
    $user = User::factory()->create();
    planHistory($user, '75.00', '2026-05-01');

    expect(app(ShareResolver::class)->shareFor($user->id, Carbon::parse('2026-01-01')))->toBe('75.00')
        ->and(app(ShareResolver::class)->shareFor(User::factory()->create()->id, Carbon::parse('2026-01-01')))->toBeNull();
});
