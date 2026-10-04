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
        // Yayın gönderiminde onaylanan hak beyanları. Metin değişirse sürüm artırılır.
        'release' => [
            'hak-beyani-haklar' => '1',
            'hak-beyani-icerik' => '1',
            'hak-beyani-bilgiler' => '1',
        ],
    ],

    // Admin panelindeki Ayarlar sayfasından değiştirilebilen değerlerin varsayılanları.
    'settings' => [
        'release_min_lead_days' => 2,
        'cover_max_mb' => 20,
        'audio_max_mb' => 1024,
    ],

    'plans' => [
        // Faz 4'e kadar üretimde yayın gönderimi ve sanatçı ekleme kapalı kalır.
        'enforce' => env('PLANS_ENFORCE', env('APP_ENV') === 'production'),
    ],

    'media' => [
        'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),
    ],

    'default_label' => 'Hova Music',

    // Veritabanı UTC tutar; kullanıcıya gösterilen saatler bu dilimdedir.
    'display_timezone' => 'Europe/Istanbul',

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
