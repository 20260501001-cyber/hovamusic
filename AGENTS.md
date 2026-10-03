# Hova Music — çalışma kuralları

Müzik dağıtım platformu. Laravel 13, MySQL 8, Redis + Horizon, Fortify (kullanıcı kimlik doğrulama), Filament (admin), Livewire + Alpine (kullanıcı paneli), Blade + Tailwind CSS 4 (herkese açık site), Pest (testler).

Mimari, veri modeli ve kararlar: `docs/` klasörü ve Faz 0 belgesi. Tasarım sistemi: `resources/css/tokens.css` ve `resources/views/components/ui`.

## Kurallar

- Ürün sahibi onaylamadan özellik ekleme; öneriyi not et, sor.
- Para: asla float. Veritabanında DECIMAL, PHP'de brick/math. Bakiye her zaman `ledger_entries` toplamıdır; kayıtlar değiştirilmez, düzeltme ters kayıtla yapılır.
- Her kaynakta sahiplik kontrolü Policy ile; URL'lerde `ulid`, sıralı id yok.
- Modellerde `#[Fillable]` açıkça yazılır; her form isteği FormRequest ile doğrulanır.
- Hassas alanlar (`iban`, `tax_id`, 2FA sırları) `encrypted` cast ile saklanır ve loglanmaz.
- Kullanıcıya görünen metin Türkçe ve `lang/tr` üzerinden; hitap "sen", yasal metinler resmi. Rakam veya iddia içeren metni onaysız yazma: `[ONAY BEKLİYOR: ...]` bırak.
- Renk, boşluk ve köşe yalnızca tasarım tokenlarıyla; hex değeri yazma. İkonlar yalnızca Lucide.
- Kullanıcı guard'ı `web` (users), admin guard'ı `admin` (admins). Admin rolleri: super_admin, review_editor, finance.
- Gereksiz yorum yazma; karmaşık iş kuralını açıkla.
- Her değişiklikten sonra `php artisan test` ve `vendor/bin/pint --test`.
