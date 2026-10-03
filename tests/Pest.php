<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationData(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Deniz Yılmaz',
        'email' => 'Deniz@Example.com',
        'password' => 'guvenli-sifre-42',
        'password_confirmation' => 'guvenli-sifre-42',
        'account_type' => 'artist',
        'consents' => [
            'kvkk-aydinlatma' => '1',
            'uyelik-sozlesmesi' => '1',
        ],
    ], $overrides);
}
