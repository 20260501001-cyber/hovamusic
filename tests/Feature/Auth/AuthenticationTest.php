<?php

use App\Models\User;

it('shows the login page', function () {
    $this->get('/giris')->assertOk()->assertSee('Giriş yap');
});

it('logs in with valid credentials and records the login', function () {
    $user = User::factory()->create();

    $this->post('/giris', ['email' => strtoupper($user->email), 'password' => 'password'])
        ->assertRedirect('/panel');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong password', function () {
    $user = User::factory()->create();

    $this->post('/giris', ['email' => $user->email, 'password' => 'yanlis-sifre'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('does not let a suspended or banned user sign in', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->post('/giris', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.inactive')]);

    $this->assertGuest();
})->with(['suspended', 'banned']);

it('signs out a user who is suspended while logged in', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->forceFill(['status' => 'suspended'])->save();

    $this->get('/panel')->assertRedirect('/giris');
    $this->assertGuest();
});

it('locks the login after repeated failures', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->post('/giris', ['email' => $user->email, 'password' => 'yanlis-sifre']);
    }

    $this->post('/giris', ['email' => $user->email, 'password' => 'password']);

    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/cikis')
        ->assertRedirect('/');

    $this->assertGuest();
});
