<?php

use Laravel\Fortify\Features;

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    'home' => '/panel',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web', 'auth.forms'],

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
        'verification' => '6,1',
    ],

    'paths' => [
        'login' => 'giris',
        'logout' => 'cikis',
        'password.request' => 'sifremi-unuttum',
        'password.email' => 'sifremi-unuttum',
        'password.reset' => 'sifre-yenile/{token}',
        'password.update' => 'sifre-yenile',
        'register' => 'kayit',
        'verification.notice' => 'e-posta-dogrulama',
        'verification.verify' => 'e-posta-dogrulama/{id}/{hash}',
        'verification.send' => 'e-posta-dogrulama/yeniden-gonder',
        'user-profile-information.update' => 'panel/hesap/profil',
        'user-password.update' => 'panel/hesap/sifre',
        'password.confirm' => 'sifre-onayla',
        'password.confirmation' => 'sifre-onayla/durum',
        'two-factor.login' => 'iki-adimli-dogrulama',
        'two-factor.enable' => 'panel/hesap/iki-adimli-dogrulama',
        'two-factor.confirm' => 'panel/hesap/iki-adimli-dogrulama/onayla',
        'two-factor.disable' => 'panel/hesap/iki-adimli-dogrulama',
        'two-factor.qr-code' => 'panel/hesap/iki-adimli-dogrulama/qr-kod',
        'two-factor.secret-key' => 'panel/hesap/iki-adimli-dogrulama/anahtar',
        'two-factor.recovery-codes' => 'panel/hesap/iki-adimli-dogrulama/kurtarma-kodlari',
    ],

    'redirects' => [
        'login' => null,
        'logout' => '/',
        'password-confirmation' => null,
        'register' => null,
        'email-verification' => null,
        'password-reset' => null,
    ],

    'views' => true,

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],

];
