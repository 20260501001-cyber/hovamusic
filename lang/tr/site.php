<?php

return [
    'nav' => [
        'label' => 'Site menüsü',
        'login' => 'Giriş yap',
        'register' => 'Kayıt ol',
        'panel' => 'Panele git',
        'pricing' => 'Fiyatlar',
        'how' => 'Nasıl çalışır',
        'platforms' => 'Platformlar',
        'blog' => 'Blog',
        'faq' => 'SSS',
        'about' => 'Hakkımızda',
        'contact' => 'İletişim',
        'open' => 'Menüyü aç',
        'close' => 'Menüyü kapat',
    ],

    'footer' => [
        'product' => 'Ürün',
        'resources' => 'Kaynaklar',
        'company' => 'Şirket',
        'legal' => 'Yasal',
        'cookies' => 'Çerez tercihleri',
        'rights' => '© :year Hova Music',
        'statement' => 'Bağımsız sanatçılar ve plak şirketleri için müzik dağıtımı.',
    ],

    'screenshot_note' => 'Ekran görüntüsü demo verilerle alınmıştır.',

    'home' => [
        'eyebrow' => 'Bağımsız müzik dağıtımı',
        'title' => 'Bağımsız müziğin dağıtım altyapısı.',
        'lead' => 'Single, EP ve albümünü panelden hazırlarsın. Kapak ve ses dosyaları yüklenirken kontrol edilir; ekibimiz yayını inceler ve dijital mağazalara iletir. Satış raporları geldikçe kazancın ve dinlenme istatistiklerin aynı panelde görünür.',
        'cta_primary' => 'Hesap aç',
        'cta_secondary' => 'Fiyatları gör',
        'hero_caption' => 'Yayın sihirbazı: bilgiler, kapak, parçalar ve gönderim öncesi son kontrol.',

        'process_eyebrow' => 'Süreç',
        'process_title' => 'Hesaptan ödemeye dört adım.',
        'process_link' => 'Ayrıntılı anlatım',

        'panel_eyebrow' => 'Panel',
        'panel_title' => 'Raporlar geldikçe ne kazandığını ve nerede dinlendiğini görürsün.',
        'panel_caption' => 'Kazançlar: bakiye, aylık gelir, platform, ülke ve parça kırılımı.',
        'panel_points' => [
            ['title' => 'Bakiye', 'body' => 'Toplam ve çekilebilir bakiye ayrı gösterilir. Tutarlar USD tutulur; istersen TL ya da euro karşılığını yaklaşık olarak görürsün.'],
            ['title' => 'Dinlenme', 'body' => 'Mağaza raporlarındaki adetlerden aylık dinlenme ve satış sayıları; platform, ülke ve parça bazında.'],
            ['title' => 'Dışa aktarma', 'body' => 'Seçtiğin dönemin dökümünü CSV olarak indirip kendi tablonda inceleyebilirsin.'],
        ],

        'plans_eyebrow' => 'Planlar',
        'plans_title' => 'Sanatçı ve plak şirketi için ayrı planlar.',
        'plans_link' => 'Tüm plan ayrıntıları',

        'platforms_eyebrow' => 'Platformlar',
        'platforms_title' => 'Yayınını göndereceğin mağazaları sen seçersin.',
        'platforms_link' => 'Platform listesi',

        'faq_eyebrow' => 'Sorular',
        'faq_title' => 'Sık sorulanlar',
        'faq_link' => 'Tüm sorular',

        'closing_title' => 'İlk yayınını hazırlamaya başla.',
        'closing_body' => 'Hesap açmak ücretsiz; yayın göndermek için bir plan seçmen gerekir.',
    ],

    'steps' => [
        [
            'title' => 'Hesap ve plan',
            'body' => 'Sanatçı ya da plak şirketi olarak hesap aç ve sana uygun planı seç. Plan; dönem başına yayın hakkını, ekleyebileceğin sanatçı sayısını ve gelir payını belirler.',
        ],
        [
            'title' => 'Yayın hazırlığı',
            'body' => 'Sihirbaz adım adım ilerler ve taslağını otomatik kaydeder. Kapak 3000×3000 piksel, ses dosyaları WAV ya da FLAC; 16 veya 24 bit, en az 44,1 kHz. Bir sorun varsa ölçülen değeri görürsün.',
        ],
        [
            'title' => 'İnceleme ve iletim',
            'body' => 'Gönderdiğin yayını ekibimiz inceler. Düzeltme gerekirse sebebiyle birlikte bildiririz; onaylanan yayın seçtiğin mağazalara iletilir.',
        ],
        [
            'title' => 'Kazanç ve ödeme',
            'body' => 'Mağaza raporları işlendiğinde payın bakiyene yazılır. Bakiyen en az 20 USD olduğunda Wise ile banka hesabına çekebilirsin.',
        ],
    ],

    'how' => [
        'eyebrow' => 'Nasıl çalışır',
        'title' => 'Hesap açmaktan ödeme almaya kadar.',
        'lead' => 'Hova Music ile bir yayının yolculuğu dört adımda ilerler. Her adımda ne olduğunu ve senden ne beklendiğini burada bulabilirsin.',
        'details' => [
            [
                'title' => 'Hesap ve plan',
                'items' => [
                    'Kayıt olurken hesap türünü seçersin: sanatçı ya da plak şirketi. İki hesap türü farklı planlar görür.',
                    'Ödeme, ön bilgilendirme formu ve mesafeli satış sözleşmesini onayladıktan sonra yapılır; abonelik otomatik yenilenir.',
                    'Plan süresi dolarsa yeni yükleme yapılamaz; mevcut yayınların yayında kalır.',
                ],
            ],
            [
                'title' => 'Yayın hazırlığı',
                'items' => [
                    'Sihirbaz: yayın bilgileri, sanatçılar, kapak, parçalar, mağazalar ve son kontrol. Her adım taslağını kaydeder.',
                    'Kapak: kare, 3000×3000 piksel, JPG ya da PNG, RGB.',
                    'Ses: WAV ya da FLAC, 16 veya 24 bit, en az 44,1 kHz. Büyük dosyalar parça parça yüklenir; bağlantı koparsa kaldığı yerden devam eder.',
                    'ISRC kodun yoksa parçalarına kod atanır; UPC atanması için [ONAY BEKLİYOR: UPC atama koşulları].',
                    'Yayın tarihi bugünden en az :days gün sonrası olabilir.',
                ],
            ],
            [
                'title' => 'İnceleme ve iletim',
                'items' => [
                    'Gönderilen her yayın ekibimiz tarafından incelenir; metadata, kapak ve ses dosyaları mağaza kurallarına göre kontrol edilir.',
                    'Düzeltme gerekirse sebebi ve ne yapman gerektiği bildirilir; yayını düzeltip yeniden gönderirsin.',
                    'Onaylanan yayın mağazalara iletilir. Mağazalarda görünme süresi: [ONAY BEKLİYOR: ortalama yayınlanma süresi].',
                    'Yayından sonra düzeltme ya da kaldırma talebini panelden açabilirsin.',
                ],
            ],
            [
                'title' => 'Kazanç ve ödeme',
                'items' => [
                    'Mağazaların satış raporları geldikçe işlenir; rapor dönemi ile kazancın panelde görünmesi arasında [ONAY BEKLİYOR: rapor gecikmesi] olabilir.',
                    'Payın, satış ayının son günü geçerli olan planının gelir payına göre hesaplanır.',
                    'Rapor işlendiğinde aktif planın yoksa kazancın bloke olarak yazılır; bir plan aldığında tamamı çekilebilir bakiyene geçer.',
                    'Para çekmek için en az 20 USD gerekir. İlk talepten önce fatura bilgilerini, ödeme bilgilerini ve vergi formunu (W-8BEN ya da W-8BEN-E) tamamlarsın.',
                    'Ödemeler Wise ile banka hesabına gönderilir; talep ekranında tahmini transfer ücretini görürsün.',
                ],
            ],
        ],
    ],

    'pricing' => [
        'eyebrow' => 'Fiyatlar',
        'title' => 'Planlar ve fiyatlar',
        'lead' => 'Fiyatlar USD. Abonelik otomatik yenilenir; planını panelden yönetebilir ya da iptal edebilirsin.',
        'artist' => 'Sanatçı planları',
        'label' => 'Plak şirketi planları',
        'col_plan' => 'Plan',
        'col_price' => 'Fiyat',
        'col_releases' => 'Yayın hakkı',
        'col_artists' => 'Sanatçı',
        'col_share' => 'Gelir payın',
        'per_period' => 'fatura dönemi başına :count',
        'unlimited' => 'Sınırsız',
        'choose' => 'Bu planla başla',
        'empty' => 'Plan bilgileri hazırlanıyor.',
        'notes_title' => 'Bilmen gerekenler',
        'notes' => [
            'Yayın hakkı her fatura döneminde yenilenir. Single, EP ve albüm birer yayın sayılır; reddedilen yayın da haktan düşer.',
            'Gelir payı, mağazalardan gelen net gelirin sana kalan kısmıdır.',
            'Vergiler: [ONAY BEKLİYOR: fiyatlara vergilerin dahil olup olmadığı].',
            'Para çekme alt sınırı 20 USD; transfer ücreti çekilen tutardan düşer.',
        ],
    ],

    'platforms' => [
        'eyebrow' => 'Platformlar',
        'title' => 'Yayınını gönderebileceğin platformlar',
        'lead' => 'Yayın sihirbazının mağaza adımında bu listeden seçim yaparsın. Liste, mağazaların koşullarına göre güncellenir.',
        'count' => ':count platform',
        'empty' => 'Platform listesi hazırlanıyor.',
    ],

    'faq' => [
        'eyebrow' => 'SSS',
        'title' => 'Sıkça sorulan sorular',
        'lead' => 'Aradığın cevabı bulamazsan bize yaz.',
        'contact' => 'İletişime geç',
        'general' => 'Genel',
        'empty' => 'Sorular hazırlanıyor.',
    ],

    'blog' => [
        'eyebrow' => 'Blog ve rehber',
        'title' => 'Blog ve rehber',
        'lead' => 'Yayın hazırlığı, metadata, telif ve dağıtım üzerine yazılar.',
        'all' => 'Tümü',
        'categories' => 'Kategoriler',
        'empty' => 'Henüz yazı yok.',
        'read' => 'Yazıyı oku',
        'minutes' => ':count dk okuma',
        'published' => 'Yayın tarihi',
        'related' => 'İlgili yazılar',
        'back' => 'Tüm yazılar',
        'by' => 'Yazan: :name',
    ],

    'about' => [
        'eyebrow' => 'Hakkımızda',
        'title' => 'Hova Music',
        'lead' => 'Hova Music, bağımsız sanatçılar ve plak şirketleri için bir müzik dağıtım platformudur.',
        'story_title' => 'Hikâye',
        'story' => '[ONAY BEKLİYOR: kuruluş hikâyesi ve ekip]',
        'principles_title' => 'Nasıl çalışıyoruz',
        'principles' => [
            ['title' => 'İnsan incelemesi', 'body' => 'Mağazalara giden her yayın ekibimizden biri tarafından incelenir.'],
            ['title' => 'Açık hesap', 'body' => 'Bakiyen, kayıtlarının toplamıdır. Her kazanç, düzeltme ve ödeme hesap hareketlerinde görünür.'],
            ['title' => 'Elle yapılan ödemeler', 'body' => 'Para çekme talepleri tek tek kontrol edilir ve Wise ile gönderilir.'],
        ],
        'company_title' => 'Şirket bilgileri',
        'company' => '[ONAY BEKLİYOR: şirket unvanı, adres, vergi dairesi ve numarası, MERSİS numarası]',
    ],

    'contact' => [
        'eyebrow' => 'İletişim',
        'title' => 'Bize yaz',
        'lead' => 'Formu doldur; mesajın ekibimize ulaşır ve e-posta adresinden dönüş yaparız.',
        'response_time' => 'Yanıt süresi: [ONAY BEKLİYOR: yanıt süresi].',
        'email_label' => 'E-posta',
        'name' => 'Adın',
        'email' => 'E-posta adresin',
        'topic' => 'Konu',
        'message' => 'Mesajın',
        'privacy' => 'Mesajındaki kişisel veriler, yalnızca sana dönüş yapmak için :link kapsamında işlenir.',
        'privacy_link' => 'KVKK aydınlatma metni',
        'submit' => 'Gönder',
        'sent' => 'Mesajın alındı. En kısa sürede dönüş yapacağız.',
        'topics' => [
            'general' => 'Genel',
            'distribution' => 'Dağıtım ve yayınlar',
            'payments' => 'Kazanç ve ödemeler',
            'partnership' => 'İş birliği',
            'press' => 'Basın',
            'privacy' => 'Kişisel veriler (KVKK)',
        ],
        'mail_subject' => 'İletişim formu: :topic',
    ],

    'legal' => [
        'version' => 'Sürüm :version · Yürürlük: :date',
        'eyebrow' => 'Yasal',
        'pending_title' => 'Bu metin henüz yayımlanmadı.',
        'pending_body' => 'Metin hazırlandığında bu sayfada yer alacak.',
    ],

    'not_found' => [
        'suggestions' => 'Belki bunlardan biri:',
    ],
];
