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

    // Kayıtta zorunlu onaylar; sürüm numarası admin panelindeki yasal metinden okunur.
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
        // PPL'nin verdiği ilk beş karakter: ülke kodu + kayıt sahibi kodu.
        'isrc_registrant' => 'GXLM5',
        // Para çekme talebinde gösterilen tahmini Wise ücreti: sabit (USD) + yüzde.
        // Gerçek ücret ödeme yapılınca admin tarafından girilir.
        'wise_fee_fixed_usd' => '0',
        'wise_fee_pct' => '0',
        // Google Search Console doğrulama kodu (meta etiketi içeriği).
        'google_site_verification' => '',
        // İletişim formu mesajlarının gönderileceği adres; boşsa MAIL_FROM_ADDRESS.
        'contact_email' => '',
    ],

    // Site dilleri. İngilizce açıldığında içerik /en altında yayınlanır ve
    // sayfalara hreflang karşılıkları eklenir.
    'locales' => [
        'tr' => ['enabled' => true, 'hreflang' => 'tr', 'prefix' => ''],
        'en' => ['enabled' => false, 'hreflang' => 'en', 'prefix' => 'en'],
    ],

    'media' => [
        'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),
    ],

    // Gece yedeği: veritabanı dökümü, kapaklar ve vergi formları (ses dosyaları hariç).
    'backup' => [
        'path' => env('BACKUP_PATH', '/var/hovamusic/backups'),
        'keep_daily' => (int) env('BACKUP_KEEP_DAILY', 7),
        'keep_weekly' => (int) env('BACKUP_KEEP_WEEKLY', 4),
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
        'directories' => ['covers', 'tax-forms'],
    ],

    'default_label' => 'Hova Music',

    // Veritabanı UTC tutar; kullanıcıya gösterilen saatler bu dilimdedir.
    'display_timezone' => 'Europe/Istanbul',

    'trusted_proxies' => env('TRUSTED_PROXIES'),

];
