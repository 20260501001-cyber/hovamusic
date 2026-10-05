<?php

return [
    'failed' => 'E-posta adresi veya şifre hatalı.',
    'password' => 'Şifre hatalı.',
    'throttle' => 'Çok fazla deneme yaptın. :seconds saniye sonra tekrar dene.',
    'inactive' => 'Hesabın şu an kullanıma kapalı. Ayrıntı için destek ekibine yaz.',
    'turnstile_failed' => 'Güvenlik doğrulaması tamamlanamadı. Sayfayı yenileyip tekrar dene.',
    'current_password_mismatch' => 'Mevcut şifre hatalı.',
    'aside_statement' => 'Yayınlarını gönder, durumunu ve kazancını tek panelden takip et.',

    'fields' => [
        'email' => 'E-posta',
        'password' => 'Şifre',
        'password_confirmation' => 'Şifre tekrarı',
        'new_password' => 'Yeni şifre',
        'current_password' => 'Mevcut şifre',
    ],

    'login' => [
        'title' => 'Giriş yap',
        'no_account' => 'Hesabın yok mu?',
        'register_link' => 'Kayıt ol',
        'remember' => 'Beni hatırla',
        'forgot' => 'Şifremi unuttum',
        'submit' => 'Giriş yap',
        'submitting' => 'Giriş yapılıyor',
    ],

    'register' => [
        'title' => 'Hesap oluştur',
        'has_account' => 'Zaten hesabın var mı?',
        'login_link' => 'Giriş yap',
        'account_type' => 'Hesap türü',
        'artist_hint' => 'Kendi müziğini yayınlıyorsan.',
        'label_hint' => 'Birden fazla sanatçının yayınlarını yönetiyorsan.',
        'name' => 'Ad soyad veya şirket adı',
        'name_hint' => 'Sanatçı adın burada değil; sanatçı profillerini sonra ekleyeceksin.',
        'password_hint' => 'En az 10 karakter; harf ve rakam içermeli.',
        'consent_kvkk_before' => '',
        'consent_kvkk_link' => 'KVKK Aydınlatma Metni\'ni',
        'consent_kvkk_after' => 'okudum.',
        'consent_terms_link' => 'Üyelik Sözleşmesi\'ni',
        'consent_terms_after' => 'okudum ve kabul ediyorum.',
        'consent_explicit_link' => 'Açık Rıza Metni\'ni',
        'consent_explicit_after' => 'okudum, kişisel verilerimin bu metinde belirtilen amaçlarla işlenmesine açık rıza veriyorum.',
        'consent_explicit_help' => 'İsteğe bağlı; vermesen de hesap açabilirsin.',
        'consent_required' => 'Devam etmek için bu kutuyu işaretlemelisin.',
        'submit' => 'Hesap oluştur',
        'submitting' => 'Hesap oluşturuluyor',
    ],

    'forgot' => [
        'title' => 'Şifreni sıfırla',
        'lead' => 'Hesabına kayıtlı e-posta adresini yaz, şifre sıfırlama bağlantısını gönderelim.',
        'submit' => 'Bağlantıyı gönder',
        'back' => 'Giriş sayfasına dön',
    ],

    'reset' => [
        'title' => 'Yeni şifre belirle',
        'submit' => 'Şifreyi kaydet',
    ],

    'verify' => [
        'title' => 'E-posta adresini doğrula',
        'lead' => ':email adresine bir doğrulama bağlantısı gönderdik. Bağlantıya tıkladıktan sonra panele geçebilirsin.',
        'link_sent' => 'Yeni doğrulama bağlantısı gönderildi.',
        'resend' => 'Bağlantıyı yeniden gönder',
    ],

    'confirm' => [
        'title' => 'Şifreni onayla',
        'lead' => 'Bu işlem için şifreni bir kez daha girmen gerekiyor.',
        'submit' => 'Onayla',
    ],

    'two_factor' => [
        'title' => 'İki adımlı doğrulama',
        'lead' => 'Doğrulama uygulamasındaki 6 haneli kodu gir.',
        'code' => 'Doğrulama kodu',
        'submit' => 'Doğrula',
        'use_recovery' => 'Uygulamaya erişemiyorum, kurtarma kodu kullanacağım',
        'recovery_code' => 'Kurtarma kodu',
    ],
];
