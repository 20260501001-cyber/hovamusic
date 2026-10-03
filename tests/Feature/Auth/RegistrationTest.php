<?php

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

it('shows the registration page', function () {
    $this->get('/kayit')
        ->assertOk()
        ->assertSee('Hesap oluştur')
        ->assertSee('Plak şirketi');
});

it('registers an artist, records consents and sends the verification email', function () {
    Notification::fake();

    $this->post('/kayit', registrationData())->assertRedirect('/panel');

    $user = User::query()->where('email', 'deniz@example.com')->firstOrFail();

    expect($user->account_type)->toBe(AccountType::Artist)
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->ulid)->not->toBeEmpty()
        ->and(Hash::info($user->password)['algoName'])->toBe('argon2id')
        ->and($user->consents()->pluck('type')->sort()->values()->all())->toBe(['kvkk-aydinlatma', 'uyelik-sozlesmesi']);

    Notification::assertSentTo($user, VerifyEmail::class);
    $this->assertAuthenticatedAs($user);
});

it('registers a label account', function () {
    $this->post('/kayit', registrationData(['account_type' => 'label']))->assertRedirect('/panel');

    expect(User::query()->firstOrFail()->account_type)->toBe(AccountType::Label);
});

it('requires both legal consents', function () {
    $this->post('/kayit', registrationData(['consents' => ['kvkk-aydinlatma' => null, 'uyelik-sozlesmesi' => null]]))
        ->assertSessionHasErrors(['consents.kvkk-aydinlatma', 'consents.uyelik-sozlesmesi']);

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('rejects an unknown account type and a weak password', function () {
    $this->post('/kayit', registrationData([
        'account_type' => 'distributor',
        'password' => 'kisa1',
        'password_confirmation' => 'kisa1',
    ]))->assertSessionHasErrors(['account_type', 'password']);
});

it('ignores attempts to set the account status during registration', function () {
    $this->post('/kayit', registrationData(['status' => 'banned']));

    expect(User::query()->firstOrFail()->status)->toBe(UserStatus::Active);
});

it('blocks registration when the bot check fails', function () {
    config(['services.turnstile.secret_key' => 'test-secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $this->post('/kayit', registrationData(['cf-turnstile-response' => 'token']))
        ->assertSessionHasErrors('turnstile');

    $this->assertGuest();
});

it('accepts registration when the bot check passes', function () {
    config(['services.turnstile.secret_key' => 'test-secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $this->post('/kayit', registrationData(['cf-turnstile-response' => 'token']))->assertRedirect('/panel');

    Http::assertSent(fn ($request) => $request['response'] === 'token' && $request['secret'] === 'test-secret');
});

it('limits registration attempts per hour', function () {
    foreach (range(1, config('hova.auth.register_per_hour')) as $i) {
        $this->post('/kayit', registrationData(['consents' => ['kvkk-aydinlatma' => null]]));
    }

    $this->post('/kayit', registrationData())->assertSessionHasErrors('email');

    expect(User::count())->toBe(0);
});
