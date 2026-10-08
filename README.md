# Hova Music

Bağımsız sanatçılar ve plak şirketleri için müzik dağıtım platformu: yayın hazırlama ve inceleme, Polar.sh abonelikleri, satış raporu içe aktarma, bakiye defteri, para çekme ve herkese açık site.

Laravel 13 · PHP 8.4 · MySQL 8 · Redis + Horizon · Fortify · Filament 5 · Livewire 4 · Tailwind CSS 4 · Pest

İçindekiler: [Geliştirme ortamı](#geliştirme-ortamı) · [Testler](#testler-ve-kod-stili) · [.env alanları](#env-alanları) · [Kuyruklar ve zamanlanmış görevler](#kuyruklar-ve-zamanlanmış-görevler) · [Üretime kurulum](#üretime-kurulum-ubuntu-2404) · [Güncelleme](#güncelleme-deploy) · [Yedekleme ve geri yükleme](#yedekleme-ve-geri-yükleme) · [Güvenlik](#güvenlik-notları)

## Geliştirme ortamı

Gerekenler: PHP 8.4 (`mbstring`, `intl`, `pdo_mysql`, `pdo_sqlite`, `redis`, `gd`, `zip`, `sodium`, `bcmath`), Composer 2, Node 22, MySQL 8 (ya da geliştirmede SQLite), Redis, ffmpeg (ffprobe).

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install
npm run dev
php artisan serve
```

`migrate --seed` admin rollerini, başlangıç mağaza listesini ve başlangıç SSS içeriğini oluşturur. Local ortamda ayrıca demo hesaplar, demo yayınlar ve demo kazanç verisi oluşur (şifre: `demo-sifre-2026`):

| Hesap | E-posta |
| --- | --- |
| Sanatçı | sanatci@demo.hovamusic.test |
| Plak şirketi | label@demo.hovamusic.test |
| Süper Admin | admin@demo.hovamusic.test |

Admin paneli `.env` içindeki `ADMIN_PATH` yolundadır. İlk girişte iki adımlı doğrulama kurulumu zorunludur. Üretimde admin oluşturmak için:

```sh
php artisan hova:admin-create
```

Geliştirmede kuyruk `sync` çalışabilir (`QUEUE_CONNECTION=sync`); Redis kullanıyorsan `php artisan horizon` ya da `php artisan queue:work --queue=imports,media,default`.

### Sitedeki panel ekran görüntüleri

Herkese açık sitedeki panel görüntüleri gerçek panelden, demo verilerle alınır. Yerel sunucu çalışırken:

```sh
php artisan db:seed --class=DemoSeeder
php artisan serve
php artisan hova:screenshots --base=http://127.0.0.1:8000
```

Komut Chrome ya da Edge'i headless açar (`--browser=` ile yol verilebilir), görüntüleri `public/images/panel` altına WebP ve AVIF olarak, paylaşım görselini `public/images/og-default.png` olarak yazar. Yalnızca `APP_ENV=local` iken çalışır.

## Testler ve kod stili

```sh
php artisan test
vendor/bin/pint --test
composer audit
npm audit --omit=dev
```

Testler SQLite bellek veritabanıyla çalışır (`phpunit.xml`). GitHub Actions her push'ta stil, test ve bağımlılık güvenliği kontrollerini çalıştırır (`.github/workflows/ci.yml`).

## .env alanları

| Alan | Açıklama |
| --- | --- |
| `APP_NAME`, `APP_URL` | Site adı ve tam adresi (`https://hovamusic.com`). Sitemap, canonical ve e-posta bağlantıları bu adresten üretilir. |
| `APP_ENV`, `APP_DEBUG` | Üretimde `production` ve `false`. |
| `APP_KEY` | `php artisan key:generate`. Şifreli alanlar (IBAN, vergi numarası, 2FA) bu anahtarla şifrelenir; **değiştirilirse eski veriler okunamaz**, yedeğini ayrıca sakla. |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | `tr`, `tr`, `tr_TR`. |
| `ADMIN_PATH` | Admin panelinin yolu. Tahmin edilemeyen, en az 16 karakterlik değer (`php -r "echo bin2hex(random_bytes(12));"`). Boşsa uygulama anahtarından türetilir. |
| `ADMIN_ALLOWED_IPS` | Admin paneline erişebilecek IP/CIDR listesi (virgülle). Admin panelindeki listeyle birleşir; ikisi de boşsa kısıtlama yok. |
| `HASH_DRIVER` | `argon2id`. |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL`, `LOG_DAILY_DAYS` | Günlük log dosyaları (`storage/logs`). Hassas alanlar loglarda maskelenir. Üretimde `LOG_LEVEL=warning` önerilir. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL 8 bağlantısı (`utf8mb4`). |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `SESSION_DOMAIN` | Oturum Redis'te, şifreli. Üretimde `SESSION_SECURE_COOKIE=true`. |
| `QUEUE_CONNECTION` | `redis` (Horizon). |
| `REDIS_QUEUE_RETRY_AFTER` | Varsayılan kuyrukta bir işin yeniden denenmeden önce bekleme süresi (sn). |
| `HORIZON_PATH` | Kuyruk izleme ekranının yolu; yalnızca Süper Admin ve admin IP kısıtlamasıyla açılır. Ör. `<ADMIN_PATH>/kuyruklar`. |
| `CACHE_STORE`, `CACHE_PREFIX` | `redis`, `hovamusic_`. |
| `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` | Redis bağlantısı. |
| `FILESYSTEM_DISK` | `local`. |
| `PRIVATE_STORAGE_PATH` | Kapak, ses, rapor, vergi formu ve veri kopyalarının tutulduğu, webroot dışındaki dizin (ör. `/var/hovamusic/private`). İndirmeler imzalı ve süreli adresle yapılır. |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `RESEND_API_KEY` | E-posta (`MAIL_MAILER=resend`). Geliştirmede anahtar yoksa e-postalar loga yazılır. |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile: giriş, kayıt, şifre sıfırlama ve iletişim formu. Üretimde boşsa bu formlar reddedilir. |
| `POLAR_ACCESS_TOKEN`, `POLAR_WEBHOOK_SECRET`, `POLAR_SERVER` | Polar.sh ödeme. `POLAR_SERVER=sandbox` ya da `production`. Webhook adresi `https://alanadi/webhooks/polar`. |
| `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET` | Spotify Web API (sanatçı arama, yayın takibi). |
| `FFPROBE_PATH` | ffprobe PATH'te değilse tam yolu. |
| `BACKUP_PATH`, `BACKUP_KEEP_DAILY`, `BACKUP_KEEP_WEEKLY`, `BACKUP_MYSQLDUMP` | Gece yedeğinin dizini ve saklama sayıları (7 günlük, 4 haftalık), mysqldump yolu. |
| `TRUSTED_PROXIES` | Cloudflare veya yük dengeleyici arkasında güvenilen proxy IP'leri (virgülle). Sunucuya doğrudan da erişilebiliyorsa `*` kullanma: istemci IP'si taklit edilebilir, admin IP kısıtı ve hız sınırları aşılır. |
| `VITE_APP_NAME` | Ön yüz derlemesinde kullanılan ad. |

Admin panelinden değiştirilen ayarlar (en erken yayın tarihi, dosya sınırları, ISRC öneki, tahmini Wise ücreti, Search Console doğrulama kodu, iletişim e-postası) `.env`'de değil veritabanında tutulur: Sistem > Ayarlar ve Finans > Finans ayarları.

## Kuyruklar ve zamanlanmış görevler

Kuyruklar Horizon ile çalışır (`config/horizon.php`):

| Kuyruk | İş | Bağlantı |
| --- | --- | --- |
| `default` | E-posta ve bildirimler, genel işler | `redis` |
| `media` | Ses dosyası analizi (ffprobe) | `redis-long` (uzun süreli) |
| `imports` | Satış raporu okuma ve hesaplama | `redis-long` (tek işlemci, 1 saate kadar) |

Zamanlanmış görevler (`routes/console.php`, saatler İstanbul):

| Komut | Zaman | İş |
| --- | --- | --- |
| `hova:purge-uploads` | Saatte bir | Yarım kalmış ve süresi dolmuş yüklemeleri siler. |
| `hova:spotify-track` | Her gün 06:00 | Mağazalara gönderilen yayınları Spotify'da arar, bağlantı önerir. |
| `hova:subscriptions` | Her gün 09:00 | Yenileme hatırlatması ve plan bitiş bildirimi. |
| `hova:backup` | Her gün 03:30 | Veritabanı ve kapak yedeği, eski yedeklerin temizlenmesi. |

Cron'a tek satır yeterlidir (aşağıda).

## Üretime kurulum (Ubuntu 24.04)

Aşağıdaki adımlar root yetkili bir Ubuntu 24.04 sunucu, `hovamusic.com` alan adı ve `deploy` kullanıcısı varsayar.

### 0. Sunucu hazırlığı

Alan adının DNS'inde `hovamusic.com` ve `www` için sunucunun IP'sine A kaydı aç. Sonra root olarak:

```sh
timedatectl set-timezone Europe/Istanbul
adduser deploy && usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh && cp ~/.ssh/authorized_keys /home/deploy/.ssh/ && chown -R deploy:deploy /home/deploy/.ssh
ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw enable
```

Depo özel olduğu için sunucuya salt okunur bir GitHub deploy anahtarı ekle (`deploy` kullanıcısıyla):

```sh
ssh-keygen -t ed25519 -C "hovamusic-sunucu" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub   # GitHub > depo > Settings > Deploy keys > Add (yazma izni verme)
ssh -T git@github.com       # bağlantıyı doğrula
```

### 1. Paketler

```sh
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y nginx mysql-server redis-server supervisor ffmpeg git unzip certbot python3-certbot-nginx \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis php8.4-mbstring php8.4-intl php8.4-xml php8.4-curl \
  php8.4-gd php8.4-zip php8.4-bcmath php8.4-sqlite3
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

### 2. Veritabanı ve Redis

```sh
sudo mysql_secure_installation
sudo mysql -e "CREATE DATABASE hovamusic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'hovamusic'@'localhost' IDENTIFIED BY 'GÜÇLÜ-BİR-ŞİFRE';"
sudo mysql -e "GRANT ALL PRIVILEGES ON hovamusic.* TO 'hovamusic'@'localhost'; GRANT PROCESS ON *.* TO 'hovamusic'@'localhost'; FLUSH PRIVILEGES;"
```

`PROCESS` yetkisi yedekleme sırasında `mysqldump` için gerekir. Bakiye defterini koruyan tetikleyiciler (trigger) ikili log açıkken SUPER yetkisi olmadan oluşturulamaz; migrate'ten önce bir kez çalıştır:

```sh
sudo mysql -e "SET PERSIST log_bin_trust_function_creators = 1;"
```

Redis için `/etc/redis/redis.conf` içinde `bind 127.0.0.1` ve bir `requirepass` tanımla; şifreyi `REDIS_PASSWORD` olarak yaz.

### 3. Uygulama

```sh
sudo mkdir -p /var/www/hovamusic /var/hovamusic/private /var/hovamusic/backups
sudo chown -R deploy:www-data /var/www/hovamusic /var/hovamusic
sudo chmod 750 /var/hovamusic/private /var/hovamusic/backups

cd /var/www/hovamusic
git clone git@github.com:<hesap>/hovamusic.git .
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
cp .env.example .env   # alanları yukarıdaki tabloya göre doldur
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # roller, mağaza listesi, başlangıç SSS
# Tür listesi admin panelinden girilir; örnek listeyle başlamak için:
# php artisan db:seed --class=GenreSeeder --force
php artisan storage:link
php artisan hova:admin-create
php artisan optimize
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

### 4. PHP-FPM

`/etc/php/8.4/fpm/conf.d/99-hovamusic.ini`:

```ini
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 512M
max_execution_time = 120
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 256
opcache.validate_timestamps = 0
```

PHP-FPM, Horizon ve cron aynı kullanıcıyla (`deploy`) çalışmalı: özel diskteki dosyalar yalnızca sahibine açık (0600) yazılır; web isteğinin yüklediği sesi kuyruktaki analiz işi de okuyabilmeli. Soket `www-data`'ya açık kalır, Nginx bağlanmaya devam eder:

```sh
sudo sed -i 's/^user = www-data/user = deploy/; s/^group = www-data/group = deploy/' /etc/php/8.4/fpm/pool.d/www.conf
```

Ses dosyaları 4 MB'lık parçalarla yüklenir; rapor dosyaları admin panelinden en fazla 50 MB. `opcache.validate_timestamps=0` olduğu için her deploy'da `php-fpm` yeniden yüklenir (aşağıda).

```sh
sudo systemctl restart php8.4-fpm
```

### 5. Nginx

`/etc/nginx/sites-available/hovamusic`:

```nginx
server {
    listen 80;
    server_name hovamusic.com www.hovamusic.com;
    root /var/www/hovamusic/public;
    index index.php;

    client_max_body_size 64M;
    server_tokens off;

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml application/xml text/plain;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Vite çıktıları içerik özetli adlarla gelir; uzun süre önbelleklenir.
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ~* \.(?:webp|avif|png|jpg|jpeg|svg|ico|woff2)$ {
        expires 30d;
        add_header Cache-Control "public";
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```sh
sudo ln -s /etc/nginx/sites-available/hovamusic /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Güvenlik başlıkları (CSP, HSTS, X-Frame-Options vb.) uygulama tarafından eklenir; Nginx'te tekrar eklenmez. `robots.txt` ve `sitemap.xml` uygulama tarafından üretilir; `public/` altında bu adlarla dosya olmamalı.

### 6. SSL

```sh
sudo certbot --nginx -d hovamusic.com -d www.hovamusic.com --redirect
```

Sertifika yenileme certbot'un systemd zamanlayıcısıyla otomatik yapılır (`sudo certbot renew --dry-run` ile denenebilir). SSL açıldıktan sonra `.env`: `APP_URL=https://hovamusic.com`, `SESSION_SECURE_COOKIE=true`; ardından `php artisan optimize`. Cloudflare kullanılıyorsa SSL modu "Full (strict)" ve `TRUSTED_PROXIES` Cloudflare aralıkları olmalı.

### 7. Horizon (Supervisor)

`/etc/supervisor/conf.d/hovamusic-horizon.conf`:

```ini
[program:hovamusic-horizon]
process_name=%(program_name)s
command=php /var/www/hovamusic/artisan horizon
user=deploy
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3700
redirect_stderr=true
stdout_logfile=/var/www/hovamusic/storage/logs/horizon.log
```

```sh
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start hovamusic-horizon
```

### 8. Cron

`crontab -e -u deploy`:

```cron
* * * * * cd /var/www/hovamusic && php artisan schedule:run >> /dev/null 2>&1
```

### 9. Dış servisler

- **Polar.sh:** Ürünleri oluştur, ürün kimliklerini admin panelinde Satış > Planlar'a gir. Webhook adresi `https://hovamusic.com/webhooks/polar`; olaylar: `subscription.*`, `order.*`, `checkout.updated`. Gizli anahtarı `POLAR_WEBHOOK_SECRET`'a yaz.
- **Resend:** Alan adını doğrula (SPF, DKIM), `RESEND_API_KEY`.
- **Turnstile:** Site ve gizli anahtar.
- **Google Search Console:** HTML etiketi yöntemindeki kodu admin panelinde Sistem > Ayarlar'a gir; ardından `https://hovamusic.com/sitemap.xml` adresini gönder.

### 10. Yayına almadan önce

- Admin panelinde İçerik > Yasal metinler: tüm metinlerin bir sürümünü yayımla (KVKK aydınlatma, açık rıza, üyelik sözleşmesi, gizlilik ve çerez politikası, mesafeli satış, ön bilgilendirme).
- Sitedeki `[ONAY BEKLİYOR: ...]` işaretli cümleleri (`lang/tr/site.php`, SSS) gerçek bilgilerle değiştir.
- Finans > Finans ayarları: tahmini Wise ücreti.
- Finans > Sütun eşleştirme: Believe raporunun başlıklarını kontrol et.
- Finans > Kurlar: ilk raporun dönemleri için kur gir.

## Güncelleme (deploy)

```sh
cd /var/www/hovamusic
php artisan down --render="errors::503"
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
php artisan horizon:terminate     # Supervisor yeni kodla yeniden başlatır
sudo systemctl reload php8.4-fpm
php artisan up
```

## Yedekleme ve geri yükleme

`hova:backup` her gece 03:30'da çalışır ve `BACKUP_PATH` altına yazar:

```
/var/hovamusic/backups/
  daily/2026-10-06_033000/   database.sql.gz  files.tar.gz  SHA256SUMS
  weekly/2026-W41/           (pazar günü ya da haftanın ilk yedeğinin kopyası)
```

- `database.sql.gz`: tüm veritabanının `mysqldump` çıktısı (tek işlem, tutarlı anlık görüntü).
- `files.tar.gz`: özel diskteki kapaklar (`covers/`) ve imzalı vergi formları (`tax-forms/`). Ses dosyaları yedeklenmez.
- Son 7 günlük ve 4 haftalık yedek tutulur; eskiler silinir.

Elle yedek ve kontrol:

```sh
php artisan hova:backup --dry-run   # ne yapılacağını gösterir
php artisan hova:backup
cd /var/hovamusic/backups/daily/<tarih> && sha256sum -c SHA256SUMS
```

Yedekler sunucuyla aynı diskte durur; disk arızasına karşı en azından veritabanı yedeğinin düzenli olarak sunucu dışına kopyalanması önerilir.

### Geri yükleme

1. Siteyi bakıma al ve kuyrukları durdur:

   ```sh
   cd /var/www/hovamusic
   php artisan down
   sudo supervisorctl stop hovamusic-horizon
   ```

2. Yedeği doğrula ve veritabanını geri yükle (mevcut veritabanının üzerine yazar):

   ```sh
   B=/var/hovamusic/backups/daily/<tarih>
   cd $B && sha256sum -c SHA256SUMS
   gunzip < $B/database.sql.gz | mysql -u hovamusic -p hovamusic
   ```

3. Kapakları ve vergi formlarını geri yükle:

   ```sh
   tar -xzf $B/files.tar.gz -C /var/hovamusic/private
   sudo chown -R deploy:www-data /var/hovamusic/private
   ```

4. Önbellekleri temizle, kuyrukları ve siteyi aç:

   ```sh
   php artisan optimize:clear && php artisan optimize
   sudo supervisorctl start hovamusic-horizon
   php artisan up
   ```

Şifreli alanların okunabilmesi için geri yüklenen ortamda **aynı `APP_KEY`** kullanılmalıdır.

## Güvenlik notları

- Kullanıcılar (`web`) ve adminler (`admin`) ayrı tablolar ve oturum korumalarıyla çalışır. Admin paneli tahmin edilemez yolda, zorunlu TOTP ve isteğe bağlı IP listesiyle.
- Her kaynakta sahiplik Policy ile kontrol edilir; adreslerde sıralı id yerine ULID kullanılır.
- Tüm form gönderimlerinde genel hız sınırı (kullanıcı/IP başına dakikada 60), hassas formlarda (giriş, kayıt, para çekme, ödeme bilgisi, iletişim) daha sıkı sınırlar.
- Dosya yüklemelerinde MIME ve magic byte kontrolü; dosya adları yeniden üretilir, dosyalar webroot dışında tutulur, indirmeler imzalı ve süreli adreslerle.
- Nonce'lu Content-Security-Policy, HSTS ve diğer güvenlik başlıkları; panel, admin ve giriş sayfaları `noindex`.
- IBAN, hesap ve vergi numarası, 2FA sırları şifreli saklanır; audit log ve uygulama loglarında maskelenir.
- Bakiye defteri yalnızca eklenir: MySQL tetikleyicisi kayıtların değiştirilmesini ve silinmesini engeller; düzeltmeler ters kayıtla yapılır.
