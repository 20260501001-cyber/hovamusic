<?php

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

it('asks for the password before enabling two factor authentication', function () {
    $this->actingAs(User::factory()->create())
        ->post('/panel/hesap/iki-adimli-dogrulama')
        ->assertRedirect('/sifre-onayla');
});

it('enables and confirms two factor authentication', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/panel/hesap/iki-adimli-dogrulama')
        ->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    $this->get('/panel/hesap')->assertOk()->assertSee('Kurulum anahtarı');

    $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->post('/panel/hesap/iki-adimli-dogrulama/onayla', ['code' => $code])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('challenges users with two factor authentication at login', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();

    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['kod-1-ornek', 'kod-2-ornek'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post('/giris', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/iki-adimli-dogrulama');

    $this->assertGuest();

    $this->post('/iki-adimli-dogrulama', ['code' => $google2fa->getCurrentOtp($secret)])
        ->assertRedirect('/panel');

    $this->assertAuthenticatedAs($user);
});
