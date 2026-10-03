<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

it('sends unverified users to the verification notice', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get('/panel')
        ->assertRedirect('/e-posta-dogrulama');
});

it('verifies the email through a signed link', function () {
    Event::fake([Verified::class]);
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect('/panel?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a verification link with the wrong hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('baska@example.com'),
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
