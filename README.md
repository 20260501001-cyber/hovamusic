# Hova Music

Bağımsız sanatçılar ve plak şirketleri için müzik dağıtım platformu.

Laravel 13 · MySQL 8 · Redis + Horizon · Fortify · Filament · Livewire · Tailwind CSS 4 · Pest

## Geliştirme ortamı

Gerekenler: PHP 8.3+ (8.4 önerilir; `mbstring`, `intl`, `pdo_mysql`, `pdo_sqlite`, `redis`, `gd`, `zip`, `sodium` eklentileri), Composer 2, Node 22, MySQL 8, Redis, ffmpeg.

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

`migrate --seed` admin rollerini oluşturur. Local ortamda ayrıca demo hesaplar oluşur (şifre: `demo-sifre-2026`):

| Hesap | E-posta |
| --- | --- |
| Sanatçı | sanatci@demo.hovamusic.test |
| Plak şirketi | label@demo.hovamusic.test |
| Süper Admin | admin@demo.hovamusic.test |

Admin paneli `.env` içindeki `ADMIN_PATH` yolundadır. İlk girişte iki adımlı doğrulama kurulumu zorunludur.

Üretimde admin oluşturmak için:

```sh
php artisan hova:admin-create
```

## Testler ve kod stili

```sh
php artisan test
vendor/bin/pint
composer audit && npm audit --omit=dev
```

## Önemli `.env` alanları

| Alan | Açıklama |
| --- | --- |
| `ADMIN_PATH` | Admin panelinin yolu. Tahmin edilemeyen, en az 16 karakterlik bir değer. Boşsa uygulama anahtarından türetilir. |
| `ADMIN_ALLOWED_IPS` | Admin paneline erişebilecek IP/CIDR listesi (virgülle). Admin panelindeki listeyle birleşir; ikisi de boşsa kısıtlama yok. |
| `HASH_DRIVER` | `argon2id` |
| `PRIVATE_STORAGE_PATH` | Kapak, ses, rapor ve vergi formlarının tutulduğu, webroot dışındaki dizin. |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile. Üretimde boşsa giriş, kayıt ve şifre sıfırlama formları reddedilir. |
| `RESEND_API_KEY` | E-posta gönderimi (`MAIL_MAILER=resend`). |
| `TRUSTED_PROXIES` | Cloudflare veya yük dengeleyici arkasında güvenilen proxy IP'leri. |

Kurulum, kuyruk, cron, deploy (Ubuntu + Nginx + PHP-FPM + Supervisor + SSL) ve yedekten geri yükleme talimatları Faz 7'de bu dosyaya eklenecek.
