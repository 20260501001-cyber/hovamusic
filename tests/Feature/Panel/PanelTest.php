<?php

use App\Enums\ThemePreference;
use App\Models\User;

it('redirects guests to the login page', function () {
    $this->get('/panel')->assertRedirect('/giris');
});

it('shows the dashboard to a verified user', function () {
    $user = User::factory()->create(['name' => 'Deniz']);

    $this->actingAs($user)
        ->get('/panel')
        ->assertOk()
        ->assertSee('Merhaba, Deniz')
        ->assertSee('Henüz yayının yok');
});

it('shows the account page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/panel/hesap')
        ->assertOk()
        ->assertSee('İki adımlı doğrulama')
        ->assertSee('Tercihler');
});

it('saves theme and currency preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/panel/hesap/tercihler', ['theme' => 'light', 'display_currency' => 'EUR'])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->theme)->toBe(ThemePreference::Light)
        ->and($user->display_currency->value)->toBe('EUR');

    $this->get('/panel')->assertSee('data-theme="light"', false);
});

it('rejects an unknown theme', function () {
    $this->actingAs(User::factory()->create())
        ->put('/panel/hesap/tercihler', ['theme' => 'neon', 'display_currency' => 'USD'])
        ->assertSessionHasErrors('theme');
});

it('updates the profile name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/panel/hesap/profil', ['name' => 'Yeni Ad', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Yeni Ad');
});

it('shows legal template pages and 404s unknown ones', function () {
    $this->get('/yasal/kvkk-aydinlatma-metni')->assertOk()->assertSee('KVKK Aydınlatma Metni');
    $this->get('/yasal/olmayan-sayfa')->assertNotFound();
});
