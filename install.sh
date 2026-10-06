#!/usr/bin/env bash

set -Eeuo pipefail

# ============================================================
# HOVA MUSIC - FULL PRODUCTION INSTALLER
# ============================================================
#
# Repository:
# https://github.com/20260501001-cyber/hovamusic
#
# Target:
# Ubuntu 24.04
#
# Stack:
# PHP 8.4
# Laravel 13
# MySQL 8
# Redis
# Horizon
# Node.js 22
# Nginx
# Supervisor
# FFmpeg
# Let's Encrypt
#
# ============================================================

APP_NAME="Hova Music"
APP_DIR="/var/www/hovamusic"

PRIVATE_DIR="/var/hovamusic/private"
BACKUP_DIR="/var/hovamusic/backups"

REPO="https://github.com/20260501001-cyber/hovamusic.git"
BRANCH="main"

DEPLOY_USER="deploy"

DB_NAME="hovamusic"
DB_USER="hovamusic"

PHP_VERSION="8.4"
NODE_VERSION="22"

DOMAIN=""
ADMIN_EMAIL=""
ADMIN_PASSWORD=""

RESEND_API_KEY=""
TURNSTILE_SITE_KEY=""
TURNSTILE_SECRET_KEY=""

POLAR_ACCESS_TOKEN=""
POLAR_WEBHOOK_SECRET=""
POLAR_SERVER="production"

SPOTIFY_CLIENT_ID=""
SPOTIFY_CLIENT_SECRET=""

TRUSTED_PROXIES=""

ENABLE_CLOUDFLARE="no"

DB_PASSWORD=""
REDIS_PASSWORD=""
ADMIN_PATH=""

INSTALL_LOG="/root/hovamusic-install.log"
INFO_FILE="/root/hovamusic-install-info.txt"

# ============================================================
# COLORS
# ============================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# ============================================================
# LOGGING
# ============================================================

exec > >(tee -a "$INSTALL_LOG") 2>&1

info() {
    echo -e "${CYAN}[INFO]${NC} $1"
}

success() {
    echo -e "${GREEN}[OK]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[UYARI]${NC} $1"
}

error() {
    echo -e "${RED}[HATA]${NC} $1"
}

section() {
    echo
    echo "============================================================"
    echo "$1"
    echo "============================================================"
}

fail() {
    error "$1"
    exit 1
}

trap 'error "Kurulum başarısız oldu. Satır: $LINENO"; error "Log: $INSTALL_LOG"' ERR

# ============================================================
# ROOT
# ============================================================

if [[ "$EUID" -ne 0 ]]; then
    fail "Script root olarak çalıştırılmalıdır."
fi

# ============================================================
# OS
# ============================================================

source /etc/os-release

if [[ "${ID:-}" != "ubuntu" ]]; then
    fail "Bu installer yalnızca Ubuntu için hazırlanmıştır."
fi

if [[ "${VERSION_ID:-}" != "24.04" ]]; then
    warning "Bu script Ubuntu 24.04 için hazırlanmıştır."
    warning "Mevcut Ubuntu sürümü: ${VERSION_ID:-unknown}"

    read -rp "Devam etmek istiyor musunuz? [y/N]: " ANSWER

    if [[ ! "$ANSWER" =~ ^[Yy]$ ]]; then
        exit 0
    fi
fi

# ============================================================
# BASIC INPUT
# ============================================================

section "HOVA MUSIC KURULUM AYARLARI"

read -rp "Domain (örn: hovamusic.com): " DOMAIN

DOMAIN="${DOMAIN#http://}"
DOMAIN="${DOMAIN#https://}"
DOMAIN="${DOMAIN%%/*}"

if [[ -z "$DOMAIN" ]]; then
    fail "Domain boş bırakılamaz."
fi

read -rp "Admin e-posta adresi: " ADMIN_EMAIL

if [[ -z "$ADMIN_EMAIL" ]]; then
    fail "Admin e-posta boş bırakılamaz."
fi

echo
echo "Admin şifresini kendiniz belirleyebilirsiniz."
echo "En az 12 karakter kullanmanız önerilir."
echo

read -rsp "Admin şifresi: " ADMIN_PASSWORD
echo

if [[ ${#ADMIN_PASSWORD} -lt 12 ]]; then
    fail "Admin şifresi en az 12 karakter olmalıdır."
fi

# ============================================================
# CLOUDFLARE
# ============================================================

echo
read -rp "Cloudflare kullanıyor musunuz? [y/N]: " ENABLE_CLOUDFLARE

if [[ "$ENABLE_CLOUDFLARE" =~ ^[Yy]$ ]]; then

    ENABLE_CLOUDFLARE="yes"

    echo
    echo "Cloudflare kullanıyorsanız SSL modu:"
    echo
    echo "Cloudflare > SSL/TLS > Overview > Full (strict)"
    echo

    read -rp "Cloudflare proxy kullanılsın mı? [Y/n]: " CF_PROXY

    if [[ ! "$CF_PROXY" =~ ^[Nn]$ ]]; then
        ENABLE_CLOUDFLARE="yes"
    fi

else

    ENABLE_CLOUDFLARE="no"

fi

# ============================================================
# RESEND
# ============================================================

section "RESEND"

echo
echo "Resend kullanmak istemiyorsanız Enter'a basabilirsiniz."
echo

read -rsp "RESEND_API_KEY: " RESEND_API_KEY
echo

# ============================================================
# TURNSTILE
# ============================================================

section "CLOUDFLARE TURNSTILE"

echo
echo "Turnstile Site Key boş bırakılırsa CAPTCHA devre dışı kalır."
echo

read -rp "TURNSTILE_SITE_KEY: " TURNSTILE_SITE_KEY
read -rsp "TURNSTILE_SECRET_KEY: " TURNSTILE_SECRET_KEY
echo

# ============================================================
# POLAR
# ============================================================

section "POLAR.SH"

echo
echo "Polar kullanıyorsanız production bilgilerini girin."
echo "Kullanmıyorsanız alanları boş bırakabilirsiniz."
echo

read -rsp "POLAR_ACCESS_TOKEN: " POLAR_ACCESS_TOKEN
echo

read -rsp "POLAR_WEBHOOK_SECRET: " POLAR_WEBHOOK_SECRET
echo

if [[ -n "$POLAR_ACCESS_TOKEN" ]]; then

    read -rp "Polar ortamı [production/sandbox] (production): " POLAR_SERVER

    if [[ -z "$POLAR_SERVER" ]]; then
        POLAR_SERVER="production"
    fi

fi

# ============================================================
# SPOTIFY
# ============================================================

section "SPOTIFY"

echo
echo "Spotify API bilgileri boş bırakılabilir."
echo

read -rp "SPOTIFY_CLIENT_ID: " SPOTIFY_CLIENT_ID

read -rsp "SPOTIFY_CLIENT_SECRET: " SPOTIFY_CLIENT_SECRET
echo

# ============================================================
# RANDOM SECRETS
# ============================================================

section "GÜVENLİK ANAHTARLARI OLUŞTURULUYOR"

DB_PASSWORD="$(openssl rand -hex 32)"

REDIS_PASSWORD="$(openssl rand -hex 32)"

ADMIN_PATH="$(openssl rand -hex 16)"

success "Database şifresi oluşturuldu."
success "Redis şifresi oluşturuldu."
success "Admin path oluşturuldu."

# ============================================================
# DNS CHECK
# ============================================================

section "DNS KONTROLÜ"

SERVER_IP="$(curl -4 -s https://api.ipify.org || true)"

echo
echo "Sunucu IPv4:"
echo "$SERVER_IP"
echo
echo "Domain:"
echo "$DOMAIN"
echo

if command -v dig >/dev/null 2>&1; then

    DOMAIN_IP="$(dig +short "$DOMAIN" A | tail -n 1 || true)"

    if [[ -n "$DOMAIN_IP" ]]; then

        echo "DNS IPv4:"
        echo "$DOMAIN_IP"

        if [[ "$DOMAIN_IP" != "$SERVER_IP" ]]; then
            warning "Domain IP adresi bu sunucunun IP'si ile eşleşmiyor."
            warning "SSL kurulumu başarısız olabilir."

            read -rp "Devam edilsin mi? [y/N]: " DNS_CONTINUE

            if [[ ! "$DNS_CONTINUE" =~ ^[Yy]$ ]]; then
                exit 1
            fi
        else
            success "DNS sunucu IP'si ile eşleşiyor."
        fi

    fi

fi

# ============================================================
# APT
# ============================================================

section "SİSTEM PAKETLERİ"

export DEBIAN_FRONTEND=noninteractive

apt-get update

apt-get install -y \
    software-properties-common \
    ca-certificates \
    curl \
    wget \
    gnupg \
    lsb-release \
    apt-transport-https \
    unzip \
    zip \
    git \
    nginx \
    mysql-server \
    redis-server \
    supervisor \
    ffmpeg \
    certbot \
    python3-certbot-nginx \
    openssl \
    dnsutils

success "Temel paketler kuruldu."

# ============================================================
# PHP
# ============================================================

section "PHP 8.4"

add-apt-repository ppa:ondrej/php -y

apt-get update

apt-get install -y \
    php8.4-fpm \
    php8.4-cli \
    php8.4-common \
    php8.4-mysql \
    php8.4-redis \
    php8.4-mbstring \
    php8.4-intl \
    php8.4-xml \
    php8.4-curl \
    php8.4-gd \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-sqlite3 \
    php8.4-opcache

php -v

success "PHP 8.4 kuruldu."

# ============================================================
# NODE
# ============================================================

section "NODE.JS 22"

curl -fsSL https://deb.nodesource.com/setup_22.x | bash -

apt-get install -y nodejs

node -v
npm -v

success "Node.js kuruldu."

# ============================================================
# COMPOSER
# ============================================================

section "COMPOSER"

if command -v composer >/dev/null 2>&1; then

    success "Composer zaten kurulu."

else

    curl -sS https://getcomposer.org/installer \
        -o /tmp/composer-setup.php

    php /tmp/composer-setup.php \
        --install-dir=/usr/local/bin \
        --filename=composer

    rm -f /tmp/composer-setup.php

fi

composer --version

# ============================================================
# DEPLOY USER
# ============================================================

section "DEPLOY USER"

if ! id "$DEPLOY_USER" >/dev/null 2>&1; then

    useradd \
        --system \
        --create-home \
        --shell /bin/bash \
        "$DEPLOY_USER"

fi

usermod -aG www-data "$DEPLOY_USER"

success "deploy kullanıcısı hazır."

# ============================================================
# MYSQL
# ============================================================

section "MYSQL"

systemctl enable mysql
systemctl start mysql

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost'
IDENTIFIED BY '${DB_PASSWORD}';

ALTER USER '${DB_USER}'@'localhost'
IDENTIFIED BY '${DB_PASSWORD}';

GRANT ALL PRIVILEGES
ON \`${DB_NAME}\`.*
TO '${DB_USER}'@'localhost';

GRANT PROCESS
ON *.*
TO '${DB_USER}'@'localhost';

FLUSH PRIVILEGES;
SQL

success "MySQL database oluşturuldu."

# ============================================================
# REDIS
# ============================================================

section "REDIS"

REDIS_CONFIG="/etc/redis/redis.conf"

cp "$REDIS_CONFIG" "${REDIS_CONFIG}.backup-$(date +%Y%m%d-%H%M%S)"

sed -i \
    's/^#\?bind .*/bind 127.0.0.1 ::1/' \
    "$REDIS_CONFIG"

if grep -q '^requirepass ' "$REDIS_CONFIG"; then

    sed -i \
        "s/^requirepass .*/requirepass ${REDIS_PASSWORD}/" \
        "$REDIS_CONFIG"

else

    echo "requirepass ${REDIS_PASSWORD}" >> "$REDIS_CONFIG"

fi

systemctl enable redis-server
systemctl restart redis-server

redis-cli -a "$REDIS_PASSWORD" ping

success "Redis çalışıyor."

# ============================================================
# DIRECTORIES
# ============================================================

section "DİZİNLER"

mkdir -p "$APP_DIR"
mkdir -p "$PRIVATE_DIR"
mkdir -p "$BACKUP_DIR"

chown -R "$DEPLOY_USER":www-data \
    /var/hovamusic

chmod 750 "$PRIVATE_DIR"
chmod 750 "$BACKUP_DIR"

# ============================================================
# GITHUB
# ============================================================

section "HOVA MUSIC GITHUB"

if [[ -d "$APP_DIR/.git" ]]; then

    info "Mevcut repository bulundu."

    cd "$APP_DIR"

    git fetch origin

    git checkout "$BRANCH"

    git reset --hard "origin/$BRANCH"

else

    info "Repository indiriliyor."

    rm -rf "$APP_DIR"

    git clone \
        --branch "$BRANCH" \
        --depth 1 \
        "$REPO" \
        "$APP_DIR"

fi

cd "$APP_DIR"

success "Hova Music indirildi."

# ============================================================
# ENV
# ============================================================

section ".ENV"

cp .env.example .env

# Basic
sed -i "s|^APP_NAME=.*|APP_NAME=\"${APP_NAME}\"|" .env
sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env

# Locale
sed -i "s|^APP_LOCALE=.*|APP_LOCALE=tr|" .env
sed -i "s|^APP_FALLBACK_LOCALE=.*|APP_FALLBACK_LOCALE=tr|" .env
sed -i "s|^APP_FAKER_LOCALE=.*|APP_FAKER_LOCALE=tr_TR|" .env

# Admin
sed -i "s|^ADMIN_PATH=.*|ADMIN_PATH=${ADMIN_PATH}|" .env

# Security
sed -i "s|^HASH_DRIVER=.*|HASH_DRIVER=argon2id|" .env

# Logging
sed -i "s|^LOG_LEVEL=.*|LOG_LEVEL=warning|" .env

# Database
sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sed -i "s|^DB_PORT=.*|DB_PORT=3306|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env

# Session
sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=redis|" .env
sed -i "s|^SESSION_ENCRYPT=.*|SESSION_ENCRYPT=true|" .env
sed -i "s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|" .env
sed -i "s|^SESSION_SAME_SITE=.*|SESSION_SAME_SITE=lax|" .env

# Queue
sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=redis|" .env

# Horizon
sed -i "s|^HORIZON_PATH=.*|HORIZON_PATH=${ADMIN_PATH}/kuyruklar|" .env

# Redis
sed -i "s|^CACHE_STORE=.*|CACHE_STORE=redis|" .env
sed -i "s|^CACHE_PREFIX=.*|CACHE_PREFIX=hovamusic_|" .env
sed -i "s|^REDIS_CLIENT=.*|REDIS_CLIENT=phpredis|" .env
sed -i "s|^REDIS_HOST=.*|REDIS_HOST=127.0.0.1|" .env
sed -i "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=${REDIS_PASSWORD}|" .env
sed -i "s|^REDIS_PORT=.*|REDIS_PORT=6379|" .env

# Storage
sed -i "s|^PRIVATE_STORAGE_PATH=.*|PRIVATE_STORAGE_PATH=${PRIVATE_DIR}|" .env

# Mail
sed -i "s|^MAIL_MAILER=.*|MAIL_MAILER=resend|" .env
sed -i "s|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=\"bildirim@${DOMAIN}\"|" .env
sed -i "s|^MAIL_FROM_NAME=.*|MAIL_FROM_NAME=\"${APP_NAME}\"|" .env
sed -i "s|^RESEND_API_KEY=.*|RESEND_API_KEY=${RESEND_API_KEY}|" .env

# Turnstile
sed -i "s|^TURNSTILE_SITE_KEY=.*|TURNSTILE_SITE_KEY=${TURNSTILE_SITE_KEY}|" .env
sed -i "s|^TURNSTILE_SECRET_KEY=.*|TURNSTILE_SECRET_KEY=${TURNSTILE_SECRET_KEY}|" .env

# Polar
sed -i "s|^POLAR_ACCESS_TOKEN=.*|POLAR_ACCESS_TOKEN=${POLAR_ACCESS_TOKEN}|" .env
sed -i "s|^POLAR_WEBHOOK_SECRET=.*|POLAR_WEBHOOK_SECRET=${POLAR_WEBHOOK_SECRET}|" .env
sed -i "s|^POLAR_SERVER=.*|POLAR_SERVER=${POLAR_SERVER}|" .env

# Spotify
sed -i "s|^SPOTIFY_CLIENT_ID=.*|SPOTIFY_CLIENT_ID=${SPOTIFY_CLIENT_ID}|" .env
sed -i "s|^SPOTIFY_CLIENT_SECRET=.*|SPOTIFY_CLIENT_SECRET=${SPOTIFY_CLIENT_SECRET}|" .env

# FFmpeg
sed -i "s|^FFPROBE_PATH=.*|FFPROBE_PATH=/usr/bin/ffprobe|" .env

# Backup
sed -i "s|^BACKUP_PATH=.*|BACKUP_PATH=${BACKUP_DIR}|" .env
sed -i "s|^BACKUP_KEEP_DAILY=.*|BACKUP_KEEP_DAILY=7|" .env
sed -i "s|^BACKUP_KEEP_WEEKLY=.*|BACKUP_KEEP_WEEKLY=4|" .env
sed -i "s|^BACKUP_MYSQLDUMP=.*|BACKUP_MYSQLDUMP=/usr/bin/mysqldump|" .env

# Vite
sed -i "s|^VITE_APP_NAME=.*|VITE_APP_NAME=\"${APP_NAME}\"|" .env

# Cloudflare
if [[ "$ENABLE_CLOUDFLARE" == "yes" ]]; then

    # Cloudflare proxy IP'leri için uygulama tarafında
    # trusted proxy kullanımını açıyoruz.
    #
    # Uygulama Cloudflare arkasındaysa gerçek Cloudflare
    # CIDR listesi gerektiğinde ayrıca ayarlanabilir.

    TRUSTED_PROXIES="*"

fi

sed -i "s|^TRUSTED_PROXIES=.*|TRUSTED_PROXIES=${TRUSTED_PROXIES}|" .env

chmod 640 .env

success ".env oluşturuldu."

# ============================================================
# PHP CONFIG
# ============================================================

section "PHP PRODUCTION"

cat > /etc/php/8.4/fpm/conf.d/99-hovamusic.ini <<'EOF'
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 512M
max_execution_time = 120

expose_php = Off

opcache.enable = 1
opcache.memory_consumption = 256
opcache.validate_timestamps = 0
EOF

# ============================================================
# COMPOSER
# ============================================================

section "COMPOSER DEPENDENCIES"

cd "$APP_DIR"

export COMPOSER_ALLOW_SUPERUSER=1

composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

success "Composer tamamlandı."

# ============================================================
# NODE
# ============================================================

section "NPM BUILD"

npm ci

npm run build

success "Frontend build tamamlandı."

# ============================================================
# LARAVEL KEY
# ============================================================

section "LARAVEL"

php artisan key:generate --force

# ============================================================
# DATABASE
# ============================================================

php artisan migrate --force

php artisan db:seed --force

# ============================================================
# STORAGE
# ============================================================

php artisan storage:link || true

# ============================================================
# PERMISSIONS
# ============================================================

chown -R "$DEPLOY_USER":www-data "$APP_DIR"

chown -R "$DEPLOY_USER":www-data "$PRIVATE_DIR"
chown -R "$DEPLOY_USER":www-data "$BACKUP_DIR"

chmod -R ug+rwX "$APP_DIR/storage"
chmod -R ug+rwX "$APP_DIR/bootstrap/cache"

chmod 640 "$APP_DIR/.env"

# ============================================================
# NGINX
# ============================================================

section "NGINX"

cat > /etc/nginx/sites-available/hovamusic <<EOF
server {

    listen 80;
    listen [::]:80;

    server_name ${DOMAIN} www.${DOMAIN};

    root ${APP_DIR}/public;

    index index.php;

    client_max_body_size 64M;

    server_tokens off;

    gzip on;

    gzip_types
        text/css
        application/javascript
        application/json
        image/svg+xml
        application/xml
        text/plain;

    location / {

        try_files \$uri \$uri/ /index.php?\$query_string;

    }

    location /build/ {

        expires 1y;

        add_header Cache-Control "public, immutable";

        access_log off;

    }

    location ~* \.(?:webp|avif|png|jpg|jpeg|svg|ico|woff2)$ {

        expires 30d;

        add_header Cache-Control "public";

        access_log off;

        try_files \$uri /index.php?\$query_string;

    }

    location ~ \.php$ {

        fastcgi_pass unix:/run/php/php8.4-fpm.sock;

        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;

        include fastcgi_params;

        fastcgi_read_timeout 120;

    }

    location ~ /\.(?!well-known).* {

        deny all;

    }

}
EOF

ln -sfn \
    /etc/nginx/sites-available/hovamusic \
    /etc/nginx/sites-enabled/hovamusic

rm -f /etc/nginx/sites-enabled/default

nginx -t

systemctl enable nginx
systemctl restart nginx

success "Nginx hazır."

# ============================================================
# PHP-FPM
# ============================================================

systemctl enable php8.4-fpm
systemctl restart php8.4-fpm

# ============================================================
# SSL
# ============================================================

section "LET'S ENCRYPT SSL"

echo
echo "SSL kuruluyor:"
echo "https://${DOMAIN}"
echo

certbot \
    --nginx \
    --non-interactive \
    --agree-tos \
    --redirect \
    --email "$ADMIN_EMAIL" \
    -d "$DOMAIN" \
    -d "www.${DOMAIN}"

success "SSL başarıyla kuruldu."

# ============================================================
# CERTBOT AUTO RENEW
# ============================================================

systemctl enable certbot.timer 2>/dev/null || true
systemctl start certbot.timer 2>/dev/null || true

certbot renew --dry-run || warning "SSL yenileme testi başarısız olabilir."

# ============================================================
# LARAVEL URL
# ============================================================

cd "$APP_DIR"

sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
sed -i "s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|" .env

php artisan optimize

# ============================================================
# HORIZON
# ============================================================

section "LARAVEL HORIZON"

cat > /etc/supervisor/conf.d/hovamusic-horizon.conf <<EOF
[program:hovamusic-horizon]

process_name=%(program_name)s

command=php ${APP_DIR}/artisan horizon

directory=${APP_DIR}

user=${DEPLOY_USER}

autostart=true

autorestart=true

stopasgroup=true

killasgroup=true

stopwaitsecs=3700

redirect_stderr=true

stdout_logfile=${APP_DIR}/storage/logs/horizon.log

stdout_logfile_maxbytes=50MB

stdout_logfile_backups=5

environment=HOME="/home/${DEPLOY_USER}",USER="${DEPLOY_USER}"
EOF

supervisorctl reread

supervisorctl update

supervisorctl restart hovamusic-horizon || \
supervisorctl start hovamusic-horizon

success "Horizon çalışıyor."

# ============================================================
# CRON
# ============================================================

section "LARAVEL SCHEDULER"

CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"

CURRENT_CRON="$(crontab -u "$DEPLOY_USER" -l 2>/dev/null || true)"

if ! echo "$CURRENT_CRON" | grep -Fq "$CRON_LINE"; then

    (
        echo "$CURRENT_CRON"
        echo "$CRON_LINE"
    ) | crontab -u "$DEPLOY_USER" -

fi

success "Cron eklendi."

# ============================================================
# ADMIN
# ============================================================

section "ADMIN HESABI"

echo
echo "Admin hesabı oluşturuluyor."
echo "E-posta: $ADMIN_EMAIL"
echo

export HOVA_ADMIN_EMAIL="$ADMIN_EMAIL"
export HOVA_ADMIN_PASSWORD="$ADMIN_PASSWORD"

# ------------------------------------------------------------
# Try Laravel command with stdin/environment
# ------------------------------------------------------------

ADMIN_CREATED="no"

if php artisan hova:admin-create \
    --help >/dev/null 2>&1; then

    # Command'ın interaktif olması durumunda expect yoksa
    # otomatik stdin kullanmayı deniyoruz.

    if printf "%s\n%s\n%s\n" \
        "$ADMIN_EMAIL" \
        "$ADMIN_PASSWORD" \
        "$ADMIN_PASSWORD" \
        | php artisan hova:admin-create; then

        ADMIN_CREATED="yes"

    fi

fi

if [[ "$ADMIN_CREATED" != "yes" ]]; then

    warning "hova:admin-create komutu interaktif olarak çalışıyor olabilir."

    echo
    echo "Admin oluşturmak için şu komutu çalıştırın:"
    echo
    echo "cd ${APP_DIR}"
    echo "php artisan hova:admin-create"
    echo
fi

# ============================================================
# FINAL OPTIMIZATION
# ============================================================

section "LARAVEL OPTIMIZATION"

php artisan optimize:clear

php artisan optimize

php artisan config:cache
php artisan route:cache
php artisan view:cache

# ============================================================
# SERVICES
# ============================================================

section "SERVİSLER"

systemctl restart php8.4-fpm
systemctl restart nginx
systemctl restart redis-server

supervisorctl restart hovamusic-horizon || true

# ============================================================
# HEALTH CHECK
# ============================================================

section "HEALTH CHECK"

if systemctl is-active --quiet nginx; then
    success "Nginx: OK"
else
    error "Nginx: FAILED"
fi

if systemctl is-active --quiet php8.4-fpm; then
    success "PHP-FPM: OK"
else
    error "PHP-FPM: FAILED"
fi

if systemctl is-active --quiet mysql; then
    success "MySQL: OK"
else
    error "MySQL: FAILED"
fi

if systemctl is-active --quiet redis-server; then
    success "Redis: OK"
else
    error "Redis: FAILED"
fi

if supervisorctl status hovamusic-horizon | grep -q RUNNING; then
    success "Horizon: OK"
else
    warning "Horizon çalışmıyor olabilir."
fi

# ============================================================
# HTTP TEST
# ============================================================

section "SITE TESTİ"

HTTP_STATUS="$(curl \
    -k \
    -L \
    -s \
    -o /dev/null \
    -w "%{http_code}" \
    "https://${DOMAIN}" || true)"

echo "HTTPS HTTP Status: ${HTTP_STATUS}"

if [[ "$HTTP_STATUS" == "200" ||
      "$HTTP_STATUS" == "301" ||
      "$HTTP_STATUS" == "302" ||
      "$HTTP_STATUS" == "403" ]]; then

    success "Web sunucusu cevap veriyor."

else

    warning "Site HTTP ${HTTP_STATUS} döndürdü."

fi

# ============================================================
# BACKUP TEST
# ============================================================

section "YEDEKLEME TESTİ"

if php artisan hova:backup --dry-run; then

    success "Backup komutu çalışıyor."

else

    warning "Backup dry-run başarısız."
fi

# ============================================================
# INSTALL INFORMATION
# ============================================================

section "KURULUM BİLGİLERİ"

cat > "$INFO_FILE" <<EOF
============================================================
HOVA MUSIC INSTALLATION
============================================================

Installation date:
$(date)

Domain:
https://${DOMAIN}

Admin:
https://${DOMAIN}/${ADMIN_PATH}

Admin Email:
${ADMIN_EMAIL}

Admin Password:
${ADMIN_PASSWORD}

============================================================
DATABASE
============================================================

Database:
${DB_NAME}

Username:
${DB_USER}

Password:
${DB_PASSWORD}

============================================================
REDIS
============================================================

Host:
127.0.0.1

Port:
6379

Password:
${REDIS_PASSWORD}

============================================================
PATHS
============================================================

Application:
${APP_DIR}

Private Storage:
${PRIVATE_DIR}

Backups:
${BACKUP_DIR}

Install Log:
${INSTALL_LOG}

============================================================
POLAR
============================================================

Webhook:
https://${DOMAIN}/webhooks/polar

============================================================
HORIZON
============================================================

Horizon:
${APP_DIR}/artisan horizon

Horizon Path:
https://${DOMAIN}/${ADMIN_PATH}/kuyruklar

============================================================
ADMIN
============================================================

Admin URL:
https://${DOMAIN}/${ADMIN_PATH}

IMPORTANT:
İlk girişte 2FA/TOTP kurulumu istenebilir.

============================================================
SSL
============================================================

https://${DOMAIN}

https://www.${DOMAIN}

============================================================
EOF

chmod 600 "$INFO_FILE"

# ============================================================
# SECURITY
# ============================================================

section "GÜVENLİK"

chmod 600 "$APP_DIR/.env"

chmod 600 "$INFO_FILE"

chown "$DEPLOY_USER":www-data "$APP_DIR/.env"

# ============================================================
# SUCCESS
# ============================================================

section "KURULUM TAMAMLANDI"

echo
echo -e "${GREEN}"
echo "============================================================"
echo "             HOVA MUSIC HAZIR"
echo "============================================================"
echo -e "${NC}"

echo
echo "🌐 Site:"
echo "https://${DOMAIN}"

echo
echo "🔐 Admin:"
echo "https://${DOMAIN}/${ADMIN_PATH}"

echo
echo "📧 Admin:"
echo "${ADMIN_EMAIL}"

echo
echo "🗄️ Database:"
echo "${DB_NAME}"

echo
echo "🔴 Redis:"
echo "127.0.0.1:6379"

echo
echo "⚙️ Horizon:"
supervisorctl status hovamusic-horizon || true

echo
echo "💾 Backup:"
echo "$BACKUP_DIR"

echo
echo "🔑 Tüm önemli bilgiler:"
echo "$INFO_FILE"

echo
echo "📜 Kurulum logu:"
echo "$INSTALL_LOG"

echo
echo "Polar Webhook:"
echo "https://${DOMAIN}/webhooks/polar"

echo
echo "============================================================"
echo
echo "ÖNEMLİ:"
echo "1. Admin hesabında 2FA'yı tamamla."
echo "2. Polar panelinden webhook adresini ekle."
echo "3. Resend domain doğrulamasını tamamla."
echo "4. Cloudflare kullanıyorsan SSL = Full (strict) yap."
echo "5. Turnstile anahtarlarının doğru olduğundan emin ol."
echo "6. Spotify Redirect URI ayarlarını kontrol et."
echo
echo "Kurulum bilgileri:"
echo "$INFO_FILE"
echo
echo "============================================================"
