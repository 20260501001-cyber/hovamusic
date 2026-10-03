<?php

use App\Models\User;

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

it('sends HSTS over HTTPS', function () {
    $this->get('https://localhost/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('marks auth and panel pages as noindex', function () {
    $this->get('/giris')->assertSee('noindex, nofollow', false);
});
