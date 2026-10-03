<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('sends a reset link', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/sifremi-unuttum', ['email' => $user->email])->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('gives the same message for an unknown address', function () {
    $this->post('/sifremi-unuttum', ['email' => 'yok@example.com']);

    expect(__('passwords.user'))->toBe(__('passwords.sent'));
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/sifremi-unuttum', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post('/sifre-yenile', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'yeni-guvenli-sifre-7',
            'password_confirmation' => 'yeni-guvenli-sifre-7',
        ])->assertSessionHasNoErrors()->assertRedirect('/giris');

        return Hash::check('yeni-guvenli-sifre-7', $user->fresh()->password);
    });
});
