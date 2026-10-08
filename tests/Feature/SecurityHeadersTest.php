<?php

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Models\Admin;
use App\Models\User;
use Filament\Facades\Filament;

it('sends security headers with a nonce based policy on the public site', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/")
        ->not->toContain('unsafe-eval');
});

it('allows script evaluation only inside the panel', function () {
    $csp = $this->actingAs(User::factory()->create())->get('/panel')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("'unsafe-eval'");
});

it('lets panel forms continue to the Polar checkout but keeps the public site same-origin', function () {
    $panel = $this->actingAs(User::factory()->create())->get('/panel')->headers->get('Content-Security-Policy');
    $public = $this->get('/')->headers->get('Content-Security-Policy');

    expect($panel)->toContain("form-action 'self' https://polar.sh https://*.polar.sh")
        ->and($public)->toContain("form-action 'self';");
});

it('sends HSTS over HTTPS', function () {
    $this->get('https://localhost/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('marks auth and panel pages as noindex', function () {
    $this->get('/giris')->assertSee('noindex, nofollow', false);
});

it('gives every script on the admin login page the request nonce', function () {
    $response = $this->get('/'.trim(config('hova.admin.path'), '/').'/login')->assertOk();

    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);
    preg_match_all('/<script\b[^>]*>/i', $response->getContent(), $scripts);

    expect($match[1] ?? null)->not->toBeNull()
        ->and($scripts[0])->not->toBeEmpty()
        ->each->toContain('nonce="'.$match[1].'"');
});

it('does not load avatars from a third party', function () {
    $admin = Admin::factory()->create(['name' => 'Deniz Yılmaz']);

    expect(Filament::getPanel('admin')->getDefaultAvatarProvider())->toBe(InitialsAvatarProvider::class)
        ->and(app(InitialsAvatarProvider::class)->get($admin))->toStartWith('data:image/svg+xml;base64,');
});
