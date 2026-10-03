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

    /*
     * Fortify yolları config('fortify.paths.<rota adı>') ile okur. Rota adındaki
     * noktalar iç içe anahtar sayıldığı için "password.email" gibi adlar iç içe
     * dizi olarak yazılır.
     */
    'paths' => [
        'login' => 'giris',
        'logout' => 'cikis',
        'register' => 'kayit',
        'password' => [
            'request' => 'sifremi-unuttum',
            'email' => 'sifremi-unuttum',
            'reset' => 'sifre-yenile/{token}',
            'update' => 'sifre-yenile',
            'confirm' => 'sifre-onayla',
            'confirmation' => 'sifre-onayla/durum',
        ],
        'verification' => [
            'notice' => 'e-posta-dogrulama',
            'verify' => 'e-posta-dogrulama/{id}/{hash}',
            'send' => 'e-posta-dogrulama/yeniden-gonder',
        ],
        'user-profile-information' => [
            'update' => 'panel/hesap/profil',
        ],
        'user-password' => [
            'update' => 'panel/hesap/sifre',
        ],
        'two-factor' => [
            'login' => 'iki-adimli-dogrulama',
            'enable' => 'panel/hesap/iki-adimli-dogrulama',
            'confirm' => 'panel/hesap/iki-adimli-dogrulama/onayla',
            'disable' => 'panel/hesap/iki-adimli-dogrulama',
            'qr-code' => 'panel/hesap/iki-adimli-dogrulama/qr-kod',
            'secret-key' => 'panel/hesap/iki-adimli-dogrulama/anahtar',
            'recovery-codes' => 'panel/hesap/iki-adimli-dogrulama/kurtarma-kodlari',
        ],
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
