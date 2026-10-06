<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Başlangıç SSS içeriği. Tekrar çalıştırılabilir; var olan soruya dokunmaz.
 * [ONAY BEKLİYOR: ...] işaretli cevaplar yayın öncesi admin panelden tamamlanmalı.
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['Genel', 'Hova Music nedir?', 'Bağımsız sanatçılar ve plak şirketleri için bir müzik dağıtım platformu. Yayınını panelden hazırlarsın, ekibimiz inceler ve seçtiğin dijital mağazalara iletir. Satış raporları geldikçe kazancın ve dinlenme istatistiklerin panelde görünür.', true],
            ['Genel', 'Sanatçı ve plak şirketi hesabı arasındaki fark nedir?', 'İki hesap türü farklı planlar görür. Plak şirketi hesabında birden çok sanatçı yönetebilir ve yayınlarda kendi plak şirketi adını kullanabilirsin. Sanatçı planında yayınlar "Hova Music" plak şirketi adıyla çıkar.', true],
            ['Yayın', 'Kapak görseli hangi koşulları karşılamalı?', 'Kare ve 3000×3000 piksel olmalı; JPG ya da PNG, RGB renk uzayında. Dosya boyutu sınırı yükleme ekranında yazar. Görsel bu koşulları karşılamazsa ölçülen değeri görürsün.', false],
            ['Yayın', 'Hangi ses dosyalarını kabul ediyorsunuz?', 'WAV ya da FLAC; 16 veya 24 bit, en az 44,1 kHz. Büyük dosyalar parça parça yüklenir; bağlantın koparsa yükleme kaldığı yerden devam eder.', true],
            ['Yayın', 'Yayın tarihini nasıl seçerim?', 'Sihirbazın ilk adımında seçersin. Seçebileceğin en erken tarih ekranda yazar; mağazaların yayını işleyebilmesi için bugünden birkaç gün sonrası olmalı.', false],
            ['Yayın', 'ISRC ya da UPC kodum yoksa ne olur?', 'Parçalarda "ISRC kodum yok" seçeneğini işaretlersen kod atanır. UPC: [ONAY BEKLİYOR: UPC atama koşulları].', false],
            ['Yayın', 'Yayınım onaylandıktan sonra değişiklik yapabilir miyim?', 'Yayın panelinden düzeltme ya da kaldırma talebi açabilirsin. Talebin ekibimize düşer ve sonucu sana bildirilir.', false],
            ['Plan ve ödeme', 'Plan süresi dolarsa yayınlarıma ne olur?', 'Yayınların yayında kalır; yalnızca yeni yükleme ve gönderim yapılamaz. Plan yokken işlenen raporların kazancı bloke olarak yazılır ve yeniden plan aldığında tamamı çekilebilir bakiyene geçer.', true],
            ['Plan ve ödeme', 'Reddedilen yayın, yayın hakkımdan düşer mi?', 'Evet. Gönderdiğin her yayın, sonucu ne olursa olsun o fatura döneminin hakkından düşer. Düzeltip yeniden gönderdiğin yayın ikinci kez düşmez.', false],
            ['Kazanç', 'Kazancımı ne zaman görürüm?', 'Mağazaların satış raporları geldikçe işlenir ve payın bakiyene yazılır. Rapor dönemi ile kazancın panelde görünmesi arasındaki süre: [ONAY BEKLİYOR: rapor gecikmesi].', false],
            ['Kazanç', 'Para çekmek için ne gerekir?', 'Çekilebilir bakiyenin en az 20 USD olması, fatura bilgilerin, ödeme bilgilerin ve vergi formunun (W-8BEN ya da W-8BEN-E) tamamlanmış olması gerekir. Ödemeler Wise ile banka hesabına gönderilir; talep ekranında tahmini transfer ücretini görürsün.', true],
            ['Kazanç', 'Neden ABD vergi formu istiyorsunuz?', 'Ödemelerde ABD vergi mevzuatına uyum için bireysel hesaplarda W-8BEN, şirketlerde W-8BEN-E formu gerekir. Form fatura bilgilerinle doldurulur; sen kontrol edip elektronik olarak imzalarsın.', false],
        ];

        foreach ($items as $sort => [$group, $question, $answer, $home]) {
            Faq::query()->firstOrCreate(
                ['locale' => 'tr', 'question' => $question],
                ['group' => $group, 'answer' => $answer, 'is_published' => true, 'show_on_home' => $home, 'sort' => $sort],
            );
        }
    }
}
