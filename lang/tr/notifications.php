<?php

return [
    'greeting' => 'Merhaba :name,',
    'greeting_plain' => 'Merhaba,',
    'salutation' => 'Hova Music',

    'release' => [
        'in_review' => [
            'subject' => ':title incelemeye alındı',
            'line' => '":title" yayınını aldık ve inceliyoruz. Sonuçlanınca sana haber vereceğiz.',
            'note_label' => 'Not',
            'action' => 'Yayını görüntüle',
        ],
        'needs_changes' => [
            'subject' => ':title için düzeltme gerekiyor',
            'line' => '":title" yayınını mağazalara göndermeden önce birkaç düzeltme gerekiyor.',
            'note_label' => 'Yapman gerekenler',
            'action' => 'Yayını düzenle',
        ],
        'approved' => [
            'subject' => ':title onaylandı',
            'line' => '":title" yayınını onayladık; mağazalara gönderilmek üzere sıraya alındı.',
            'note_label' => 'Not',
            'action' => 'Yayını görüntüle',
        ],
        'rejected' => [
            'subject' => ':title reddedildi',
            'line' => '":title" yayınını bu haliyle mağazalara gönderemiyoruz.',
            'note_label' => 'Sebep',
            'action' => 'Yayını görüntüle',
        ],
        'delivered' => [
            'subject' => ':title mağazalara gönderildi',
            'line' => '":title" mağazalara gönderildi. Mağazalarda görünmesi biraz zaman alabilir; yayına girince sana haber vereceğiz.',
            'note_label' => 'Not',
            'action' => 'Yayını görüntüle',
        ],
        'live' => [
            'subject' => ':title yayında',
            'line' => '":title" mağazalarda yayında.',
            'note_label' => 'Not',
            'links' => 'Mağaza bağlantıları:',
            'action' => 'Yayını görüntüle',
        ],
        'takedown_requested' => [
            'subject' => ':title için kaldırma talebin alındı',
            'line' => '":title" yayınını mağazalardan kaldırma talebini aldık. Talebin sonuçlanınca sana haber vereceğiz.',
            'note_label' => 'Talebin',
            'action' => 'Yayını görüntüle',
        ],
        'taken_down' => [
            'subject' => ':title mağazalardan kaldırıldı',
            'line' => '":title" mağazalardan kaldırıldı.',
            'note_label' => 'Not',
            'action' => 'Yayını görüntüle',
        ],
        'takedown_rejected' => [
            'subject' => ':title için kaldırma talebin reddedildi',
            'line' => '":title" yayınını kaldırma talebini reddettik; yayın önceki durumunda kalıyor.',
            'note_label' => 'Sebep',
            'action' => 'Yayını görüntüle',
        ],
        'draft' => [
            'subject' => ':title taslağa alındı',
            'line' => '":title" taslak olarak düzenlenebilir.',
            'note_label' => 'Not',
            'action' => 'Yayını görüntüle',
        ],
    ],

    'request' => [
        'answered' => [
            'subject' => ':title için düzeltme talebin yanıtlandı',
            'line' => '":title" yayınıyla ilgili düzeltme talebini yanıtladık.',
            'note_label' => 'Yanıtımız',
            'action' => 'Yayını görüntüle',
        ],
    ],

    'plan' => [
        'activated' => [
            'subject' => ':plan planın açıldı',
            'line' => ':plan planın açıldı. Yayın gönderebilir ve dosya yükleyebilirsin. Dönem sonu: :date.',
            'action' => 'Planımı görüntüle',
        ],
        'renewing' => [
            'subject' => ':plan planın :date tarihinde yenilenecek',
            'line' => ':plan planın :date tarihinde otomatik olarak yenilenecek ve :amount tahsil edilecek. Değişiklik yapmak istersen plan sayfasından aboneliğini yönetebilirsin.',
            'action' => 'Aboneliği yönet',
        ],
        'ending' => [
            'subject' => ':plan planın :date tarihinde sona erecek',
            'line' => ':plan planın :date tarihinde sona erecek ve yenilenmeyecek. Plan bittiğinde yeni yükleme yapılamaz; yayındaki yayınların yayında kalır.',
            'action' => 'Planımı görüntüle',
        ],
        'ended' => [
            'subject' => ':plan planın sona erdi',
            'line' => ':plan planın sona erdi. Yeni yayın gönderemez ve dosya yükleyemezsin; yayındaki yayınların yayında kalır.',
            'blocked' => 'Planın yokken gelen kazançlar bloke olarak yazılır; yeni bir plan aldığında serbest kalır.',
            'action' => 'Plan seç',
        ],
    ],

    'earnings' => [
        'subject' => 'Hesabına :amount kazanç eklendi',
        'line' => ':periods dönemine ait raporlar işlendi; hesabına :amount eklendi.',
        'blocked' => 'Kazanç eklendiğinde aktif bir planın olmadığı için tutar bloke olarak yazıldı. Bir plan aldığında tamamı çekilebilir bakiyene geçer.',
        'action' => 'Kazançlarımı görüntüle',
    ],

    'withdrawal' => [
        'action' => 'Para çekme sayfasına git',
        'reason_label' => 'Sebep',
        'pending' => [
            'subject' => ':amount para çekme talebin alındı',
            'line' => ':amount tutarındaki para çekme talebin alındı. Tutar, talep sonuçlanana kadar rezerve bakiyende bekler.',
        ],
        'approved' => [
            'subject' => ':amount para çekme talebin onaylandı',
            'line' => ':amount tutarındaki talebin onaylandı; ödeme Wise ile gönderilecek.',
        ],
        'rejected' => [
            'subject' => ':amount para çekme talebin reddedildi',
            'line' => ':amount tutarındaki talebin reddedildi; tutar çekilebilir bakiyene geri eklendi.',
        ],
        'paid' => [
            'subject' => ':amount ödemen gönderildi',
            'line' => 'Talebin için Wise ile :paid gönderildi. Wise ücreti: :fee.',
        ],
    ],

    'privacy' => [
        'note_label' => 'Not',
        'export_ready' => [
            'subject' => 'Verilerinin kopyası hazır',
            'line' => 'İstediğin veri kopyası hazır. Bağlantı 7 gün geçerli; indirmek için giriş yapman gerekir.',
            'action' => 'Dosyayı indir',
        ],
        'deleted' => [
            'subject' => 'Hesabın silindi',
            'line' => 'Talebin üzerine Hova Music hesabın kapatıldı ve kişisel bilgilerin silindi. Yasal saklama yükümlülüğü olan kayıtlar mevzuattaki süre boyunca saklanır.',
        ],
        'corrected' => [
            'subject' => 'Bilgi düzeltme talebin tamamlandı',
            'line' => 'Bilgi düzeltme talebin tamamlandı.',
        ],
        'rejected' => [
            'subject' => 'Veri talebin sonuçlandı',
            'line' => 'Veri talebini bu haliyle gerçekleştiremedik.',
        ],
    ],

    'center' => [
        'title' => 'Bildirimler',
        'unread' => ':count okunmamış',
        'mark_all' => 'Tümünü okundu işaretle',
        'mark_read' => 'Okundu işaretle',
        'empty_title' => 'Bildirimin yok',
        'empty_body' => 'Yayınlarınla ilgili gelişmeler burada görünecek.',
        'marked' => 'Bildirimler okundu olarak işaretlendi.',
        'new' => 'Yeni',
        'open' => 'Aç',
    ],
];
