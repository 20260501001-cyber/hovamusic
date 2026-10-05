<?php

return [
    'deleted_user' => 'Silinmiş kullanıcı',
    'request_types' => [
        'export' => 'Verilerimin kopyası',
        'deletion' => 'Hesap silme',
        'correction' => 'Bilgi düzeltme',
    ],
    'request_statuses' => [
        'pending' => 'İşleniyor',
        'completed' => 'Tamamlandı',
        'rejected' => 'Reddedildi',
    ],
    'section' => [
        'title' => 'Verilerin ve gizlilik',
        'help' => 'Kişisel verilerinle ilgili taleplerin KVKK kapsamında Hova Music ekibi tarafından sonuçlandırılır ve sonuç sana bildirilir.',
        'notice' => 'KVKK Aydınlatma Metni',
        'policy' => 'Gizlilik Politikası',
        'cookies' => 'Çerez tercihleri',
    ],
    'fields' => [
        'message' => 'Açıklama',
    ],
    'export' => [
        'title' => 'Verilerimin kopyası',
        'help' => 'Hesabın, yayınların, onayların ve ödemelerinle ilgili kayıtların bir dosya olarak hazırlanır; hazır olunca indirme bağlantısı gönderilir.',
        'submit' => 'Kopya iste',
    ],
    'correction' => [
        'title' => 'Bilgi düzeltme',
        'help' => 'Hangi bilginin yanlış olduğunu ve doğrusunu yaz.',
        'submit' => 'Talep gönder',
    ],
    'deletion' => [
        'title' => 'Hesabımı sil',
        'help' => 'Talebin incelendikten sonra kişisel bilgilerin silinir ve hesabın kapatılır. Yasal saklama süresi olan kayıtlar (onaylar, ödemeler, bakiye hareketleri, vergi formları) süre boyunca saklanır. Yayında olan yayınların için ayrıca kaldırma talebi açman gerekir; bekleyen bakiyen varsa önce çekmen önerilir.',
        'reason' => 'Neden ayrılıyorsun?',
        'submit' => 'Silme talebi gönder',
        'confirm' => 'Hesabının silinmesi için talep gönderilsin mi? Talep onaylanınca geri alınamaz.',
    ],
    'sent' => [
        'export' => 'Talebin alındı; dosya hazır olunca sana haber vereceğiz.',
        'correction' => 'Düzeltme talebin alındı.',
        'deletion' => 'Hesap silme talebin alındı.',
    ],
    'errors' => [
        'pending_exists' => 'Bu türde işlenmekte olan bir talebin var.',
        'not_pending' => 'Bu talep zaten sonuçlandırılmış.',
    ],
    'cookies' => [
        'title' => 'Çerezler',
        'body' => 'Sitenin çalışması için zorunlu çerezleri kullanıyoruz. Analiz ve pazarlama çerezleri yalnızca izin verirsen yüklenir.',
        'policy' => 'Çerez Politikası',
        'accept_all' => 'Tümünü kabul et',
        'essential_only' => 'Yalnızca zorunlu',
        'customize' => 'Tercihler',
        'save' => 'Tercihleri kaydet',
        'categories' => [
            'essential' => 'Zorunlu',
            'essential_help' => 'Oturum, güvenlik ve tercihlerinin hatırlanması için gerekir; kapatılamaz.',
            'analytics' => 'Analiz',
            'analytics_help' => 'Sitenin nasıl kullanıldığını anonim olarak ölçmemize yardım eder.',
            'marketing' => 'Pazarlama',
            'marketing_help' => 'Reklam ve kampanya ölçümü için kullanılır.',
        ],
        'saved' => 'Çerez tercihlerin kaydedildi.',
    ],
];
