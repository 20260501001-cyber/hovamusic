<?php

return [

    'admin' => [
        'path' => env('ADMIN_PATH'),
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_ALLOWED_IPS', ''))))),
    ],

    'auth' => [
        'login_attempts_per_minute' => 5,
        'lockout_attempts' => 10,
        'lockout_minutes' => 15,
        'register_per_hour' => 10,
        'password_reset_per_hour' => 5,
    ],

    'consents' => [
        'registration' => [
            'kvkk-aydinlatma' => '1',
            'uyelik-sozlesmesi' => '1',
        ],
    ],

    'legal_pages' => [
        'kvkk-aydinlatma-metni' => 'KVKK Aydınlatma Metni',
        'uyelik-sozlesmesi' => 'Üyelik Sözleşmesi',
        'gizlilik-politikasi' => 'Gizlilik Politikası',
        'cerez-politikasi' => 'Çerez Politikası',
        'mesafeli-satis-sozlesmesi' => 'Mesafeli Satış Sözleşmesi',
        'on-bilgilendirme-formu' => 'Ön Bilgilendirme Formu',
    ],

    'trusted_proxies' => env('TRUSTED_PROXIES'),

];
