#!/usr/bin/env bash
#
# Hova Music — Ubuntu 24.04 kurulum betiği
#
# Boş bir Ubuntu 24.04 sunucuya Hova Music'i kurar: PHP 8.4, MySQL 8, Redis,
# Nginx + SSL, Horizon (Supervisor), cron, gece yedeği, güvenlik duvarı.
#
# Kullanım (root olarak):
#   sudo bash install.sh
#
# Betik tekrar çalıştırılabilir. Mevcut .env'deki şifreler ve APP_KEY korunur,
# yapılmış adımlar atlanır; DNS ya da SSL sonradan düzeldiyse yeniden çalıştırmak
# yeterlidir. Tüm çıktı /var/log/hovamusic-kurulum.log dosyasına da yazılır
# (şifreler yazılmaz).

set -Eeuo pipefail
umask 022

# Betik git pull ile değişebileceği için geçici bir kopyadan çalışır.
if [[ -z ${HM_SELF_COPY:-} && -f ${BASH_SOURCE[0]:-} ]]; then
    tmp_copy=$(mktemp /tmp/hovamusic-kurulum.XXXXXX)
    cp "${BASH_SOURCE[0]}" "$tmp_copy"
    HM_SELF_COPY=1 exec bash "$tmp_copy" "$@"
fi
[[ -n ${HM_SELF_COPY:-} ]] && rm -f -- "${BASH_SOURCE[0]}"
cd /

# ---------------------------------------------------------------------------
# Sabitler
# ---------------------------------------------------------------------------

readonly APP_DIR=/var/www/hovamusic
readonly DATA_DIR=/var/hovamusic
readonly PRIVATE_DIR=$DATA_DIR/private
readonly BACKUP_DIR=$DATA_DIR/backups
readonly APP_USER=deploy
readonly APP_HOME=/home/$APP_USER
readonly ENV_FILE=$APP_DIR/.env
readonly PHP_V=8.4
readonly NODE_MAJOR=22
readonly DB_NAME=hovamusic
readonly DB_USER=hovamusic
readonly REPO_DEFAULT=git@github.com:20260501001-cyber/hovamusic.git
readonly BRANCH_DEFAULT=main
readonly LOG_FILE=/var/log/hovamusic-kurulum.log
readonly ANSWERS_FILE=/root/.hovamusic-kurulum
readonly SUMMARY_FILE=/root/hovamusic-kurulum.txt
readonly NGINX_SITE=/etc/nginx/sites-available/hovamusic
readonly GITHUB_ED25519_FP='SHA256:+DiY3wvvV6TuJJhbpZisF/zLDA0zPMSvHdkr4UvCOqU'

export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
export COMPOSER_ALLOW_SUPERUSER=1

CURRENT_STEP='başlangıç'

# ---------------------------------------------------------------------------
# Yardımcılar
# ---------------------------------------------------------------------------

if [[ -t 1 ]]; then
    C_BLUE=$'\e[1;34m' C_GREEN=$'\e[1;32m' C_YELLOW=$'\e[1;33m' C_RED=$'\e[1;31m' C_DIM=$'\e[2m' C_OFF=$'\e[0m'
else
    C_BLUE='' C_GREEN='' C_YELLOW='' C_RED='' C_DIM='' C_OFF=''
fi

step() { CURRENT_STEP=$1; printf '\n%s==> %s%s\n' "$C_BLUE" "$1" "$C_OFF"; }
info() { printf '    %s\n' "$*"; }
ok() { printf '    %s✓ %s%s\n' "$C_GREEN" "$*" "$C_OFF"; }
warn() { printf '    %s! %s%s\n' "$C_YELLOW" "$*" "$C_OFF"; }
die() { printf '\n%s✗ %s%s\n' "$C_RED" "$*" "$C_OFF" >&2; exit 1; }

on_error() {
    local code=$? line=$1
    # Komut ikamesi gibi alt kabuklardaki hatalar ana kabukta raporlanır.
    [[ $BASHPID == "$$" ]] || exit "$code"
    printf '\n%s✗ "%s" adımında hata (satır %s, çıkış kodu %s).%s\n' "$C_RED" "$CURRENT_STEP" "$line" "$code" "$C_OFF" >&2
    printf '  Ayrıntılar: %s\n  Sorunu giderip betiği yeniden çalıştırabilirsin; tamamlanan adımlar atlanır.\n' "$LOG_FILE" >&2
    exit "$code"
}
trap 'on_error $LINENO' ERR

# Kullanıcıdan cevap: ask DEĞİŞKEN "Soru" "varsayılan" "regex" "hata mesajı"
ask() {
    local __var=$1 prompt=$2 default=${3:-} pattern=${4:-} message=${5:-'Geçersiz değer.'} answer
    while true; do
        if [[ -n $default ]]; then
            read -r -p "  $prompt [$default]: " answer </dev/tty || die 'Giriş okunamadı.'
            answer=${answer:-$default}
        else
            read -r -p "  $prompt: " answer </dev/tty || die 'Giriş okunamadı.'
        fi
        answer=$(printf '%s' "$answer" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
        if [[ -z $pattern || $answer =~ $pattern ]]; then
            printf -v "$__var" '%s' "$answer"
            return
        fi
        warn "$message"
    done
}

# Gizli değer: ask_secret DEĞİŞKEN "Soru" "mevcut değer"
# Boş = mevcut değeri koru, "-" = sil. Ekrana yazılmaz, loga düşmez.
ask_secret() {
    local __var=$1 prompt=$2 current=${3:-} hint answer
    if [[ -n $current ]]; then
        hint='kayıtlı; boş = koru, - = sil'
    else
        hint='boş = atla'
    fi
    while true; do
        read -r -s -p "  $prompt ($hint): " answer </dev/tty || die 'Giriş okunamadı.'
        printf '\n'
        if [[ -z $answer ]]; then
            answer=$current
        elif [[ $answer == '-' ]]; then
            answer=''
        fi
        if [[ $answer =~ [[:space:]\"\\\$\`\'] ]]; then
            warn 'Değerde boşluk, tırnak, ters bölü, $ ya da ` olamaz.'
            continue
        fi
        printf -v "$__var" '%s' "$answer"
        return
    done
}

# Evet/hayır: yesno "Soru" e|h
yesno() {
    local prompt=$1 default=${2:-e} answer
    local shown='E/h'
    [[ $default == h ]] && shown='e/H'
    while true; do
        read -r -p "  $prompt ($shown): " answer </dev/tty || die 'Giriş okunamadı.'
        answer=$(printf '%s' "${answer:-$default}" | tr '[:upper:]' '[:lower:]')
        case $answer in
            e | evet | y | yes) return 0 ;;
            h | hayir | hayır | n | no) return 1 ;;
        esac
    done
}

# Uygulama kullanıcısıyla, uygulama dizininde komut çalıştırır.
as_app() {
    sudo -u "$APP_USER" -H -- bash -c 'cd "$0" && exec "$@"' "$APP_DIR" "$@"
}

artisan() {
    as_app php artisan "$@"
}

# .env okuma ve yazma. Değerler komut satırına değil ortam değişkenine konur.
env_get() {
    [[ -f $ENV_FILE ]] || return 0
    HM_KEY=$1 python3 - "$ENV_FILE" <<'PY'
import os, sys
key = os.environ["HM_KEY"]
value = ""
with open(sys.argv[1], encoding="utf-8") as fh:
    for line in fh:
        line = line.rstrip("\n")
        if line.startswith(key + "="):
            value = line[len(key) + 1:].strip()
if len(value) >= 2 and value[0] == value[-1] and value[0] in "\"'":
    value = value[1:-1]
if value == "null":
    value = ""
print(value)
PY
}

env_set() {
    HM_KEY=$1 HM_VALUE=$2 python3 - "$ENV_FILE" <<'PY'
import os, re, sys
path, key, value = sys.argv[1], os.environ["HM_KEY"], os.environ["HM_VALUE"]
if not re.fullmatch(r"[A-Za-z0-9_./:@+=,*\-]*", value):
    value = '"' + value + '"'
line = f"{key}={value}"
with open(path, encoding="utf-8") as fh:
    lines = fh.read().splitlines()
for i, current in enumerate(lines):
    if current.startswith(key + "="):
        lines[i] = line
        break
else:
    lines.append(line)
with open(path, "w", encoding="utf-8") as fh:
    fh.write("\n".join(lines) + "\n")
PY
}

random_hex() { openssl rand -hex "${1:-24}"; }

mysql_scalar() { mysql -N -B -e "$1" 2>/dev/null || true; }

answer_get() {
    [[ -f $ANSWERS_FILE ]] || return 0
    grep -E "^$1=" "$ANSWERS_FILE" | tail -1 | cut -d= -f2- || true
}

# ---------------------------------------------------------------------------
# 1. Ön kontrol
# ---------------------------------------------------------------------------

preflight() {
    [[ $EUID -eq 0 ]] || die 'Betiği root olarak çalıştır: sudo bash install.sh'
    { : </dev/tty; } 2>/dev/null || die 'Betik soru soracağı için bir terminalde çalıştırılmalı.'

    touch "$LOG_FILE" && chmod 600 "$LOG_FILE"
    exec > >(tee -a "$LOG_FILE") 2>&1
    printf '\n===== Kurulum %s =====\n' "$(date '+%F %T')" >>"$LOG_FILE"

    step 'Ön kontrol'
    # shellcheck disable=SC1091
    . /etc/os-release
    if [[ ${ID:-} != ubuntu || ${VERSION_ID:-} != 24.04 ]]; then
        warn "Bu betik Ubuntu 24.04 için yazıldı; bu sunucu: ${PRETTY_NAME:-bilinmiyor}."
        yesno 'Yine de devam edilsin mi?' h || die 'Kurulum durduruldu.'
    fi
    ok "${PRETTY_NAME:-Ubuntu}"

    if ! command -v curl >/dev/null || ! command -v python3 >/dev/null; then
        apt-get update -q && apt-get install -y -q curl python3
    fi
    curl -fsS --max-time 10 -o /dev/null https://github.com || die 'İnternete (github.com) erişilemiyor.'
    ok 'İnternet bağlantısı var'

    local mem_mb
    mem_mb=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
    info "Bellek: ${mem_mb} MB"
    if ((mem_mb < 1800)) && [[ -z $(swapon --noheadings 2>/dev/null) ]]; then
        info '2 GB takas alanı (swap) oluşturuluyor; derleme adımları için gerekli.'
        fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048 status=none
        chmod 600 /swapfile
        mkswap /swapfile >/dev/null
        swapon /swapfile
        grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >>/etc/fstab
        ok 'Takas alanı açıldı'
    fi
}

# ---------------------------------------------------------------------------
# 2. Sorular
# ---------------------------------------------------------------------------

gather_answers() {
    step 'Kurulum bilgileri'
    info 'Köşeli parantez içindeki değer varsayılandır; Enter ile kabul edilir.'
    printf '\n'

    local default_domain
    default_domain=$(answer_get DOMAIN)
    if [[ -z $default_domain ]]; then
        default_domain=$(env_get APP_URL | sed -E 's#^https?://##; s#/.*$##')
        [[ $default_domain == localhost* ]] && default_domain=''
    fi
    ask DOMAIN 'Alan adı (ör. hovamusic.com)' "$default_domain" \
        '^([A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}$' 'Alan adını http:// ve / olmadan yaz (ör. hovamusic.com).'
    DOMAIN=${DOMAIN,,}
    DOMAIN=${DOMAIN#www.}

    WITH_WWW=$(answer_get WITH_WWW)
    if yesno "www.$DOMAIN da bu siteye yönlensin mi?" "${WITH_WWW:-e}"; then WITH_WWW=e; else WITH_WWW=h; fi

    ask LE_EMAIL 'SSL sertifikası bildirimleri için e-posta' "$(answer_get LE_EMAIL)" \
        '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$' 'Geçerli bir e-posta yaz.'

    CLOUDFLARE=$(answer_get CLOUDFLARE)
    if yesno 'Alan adı Cloudflare proxy (turuncu bulut) arkasında mı?' "${CLOUDFLARE:-h}"; then CLOUDFLARE=e; else CLOUDFLARE=h; fi

    local repo_default branch_default
    repo_default=$(answer_get REPO)
    branch_default=$(answer_get BRANCH)
    ask REPO 'GitHub deposu (SSH adresi)' "${repo_default:-$REPO_DEFAULT}" '^git@github\.com:[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\.git$' \
        'git@github.com:hesap/depo.git biçiminde yaz.'
    ask BRANCH 'Dal' "${branch_default:-$BRANCH_DEFAULT}" '^[A-Za-z0-9._/-]+$' 'Geçersiz dal adı.'

    local default_ips
    default_ips=$(env_get ADMIN_ALLOWED_IPS)
    ask ADMIN_IPS 'Admin paneline yalnızca şu IP/CIDR adreslerinden girilsin (virgülle; boş = kısıtlama yok)' "$default_ips" \
        '^[0-9A-Fa-f:.,/ ]*$' 'Yalnızca IP ya da CIDR yaz (ör. 203.0.113.10,198.51.100.0/24).'
    ADMIN_IPS=${ADMIN_IPS// /}

    printf '\n'
    info 'Servis anahtarları. Şimdi yoksa boş bırak; sonra .env dosyasına ekleyip betiği yeniden çalıştırabilirsin.'
    printf '\n'

    ask_secret TURNSTILE_SITE_KEY 'Cloudflare Turnstile site anahtarı' "$(env_get TURNSTILE_SITE_KEY)"
    ask_secret TURNSTILE_SECRET_KEY 'Cloudflare Turnstile gizli anahtarı' "$(env_get TURNSTILE_SECRET_KEY)"
    if [[ -z $TURNSTILE_SITE_KEY || -z $TURNSTILE_SECRET_KEY ]]; then
        warn 'Turnstile anahtarları olmadan canlıda giriş, kayıt, şifre sıfırlama ve iletişim formları reddedilir.'
        yesno 'Anahtarlar olmadan devam edilsin mi?' h || die 'Turnstile anahtarlarını Cloudflare panelinden alıp betiği yeniden çalıştır.'
    fi

    ask_secret RESEND_API_KEY 'Resend API anahtarı (e-posta)' "$(env_get RESEND_API_KEY)"
    local default_from
    default_from=$(env_get MAIL_FROM_ADDRESS)
    [[ -z $default_from || $default_from == 'bildirim@hovamusic.com' ]] && default_from="bildirim@$DOMAIN"
    ask MAIL_FROM 'Gönderen e-posta adresi' "$default_from" '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$' 'Geçerli bir e-posta yaz.'

    ask_secret POLAR_ACCESS_TOKEN 'Polar erişim anahtarı (access token)' "$(env_get POLAR_ACCESS_TOKEN)"
    ask_secret POLAR_WEBHOOK_SECRET 'Polar webhook gizli anahtarı' "$(env_get POLAR_WEBHOOK_SECRET)"
    local polar_default
    polar_default=$(env_get POLAR_SERVER)
    ask POLAR_SERVER 'Polar ortamı (production / sandbox)' "${polar_default:-production}" '^(production|sandbox)$' 'production ya da sandbox yaz.'

    ask_secret SPOTIFY_CLIENT_ID 'Spotify client ID' "$(env_get SPOTIFY_CLIENT_ID)"
    ask_secret SPOTIFY_CLIENT_SECRET 'Spotify client secret' "$(env_get SPOTIFY_CLIENT_SECRET)"

    (
        umask 077
        cat >"$ANSWERS_FILE" <<EOF
DOMAIN=$DOMAIN
WITH_WWW=$WITH_WWW
LE_EMAIL=$LE_EMAIL
CLOUDFLARE=$CLOUDFLARE
REPO=$REPO
BRANCH=$BRANCH
EOF
    )

    printf '\n'
    info "Alan adı   : $DOMAIN$([[ $WITH_WWW == e ]] && printf ' + www.%s' "$DOMAIN")"
    info "Cloudflare : $([[ $CLOUDFLARE == e ]] && echo evet || echo hayır)"
    info "Depo       : $REPO ($BRANCH)"
    info "Uygulama   : $APP_DIR  (kullanıcı: $APP_USER)"
    info "Özel dosya : $PRIVATE_DIR"
    printf '\n'
    yesno 'Kurulum başlasın mı?' e || die 'Kurulum durduruldu.'
}

# ---------------------------------------------------------------------------
# 3. Paketler
# ---------------------------------------------------------------------------

install_packages() {
    step 'Sistem paketleri'
    timedatectl set-timezone Europe/Istanbul 2>/dev/null || true

    local apt_opts=(-y -q -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold)
    apt-get update -q
    apt-get install "${apt_opts[@]}" software-properties-common ca-certificates curl gnupg unzip git openssl python3 ufw

    if ! grep -rqs 'ondrej/php' /etc/apt/sources.list /etc/apt/sources.list.d/; then
        add-apt-repository -y ppa:ondrej/php
    fi

    if ! command -v node >/dev/null || [[ $(node -v 2>/dev/null | sed -E 's/^v([0-9]+).*/\1/') != "$NODE_MAJOR" ]]; then
        install -d -m 0755 /etc/apt/keyrings
        curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor --yes -o /etc/apt/keyrings/nodesource.gpg
        echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_MAJOR}.x nodistro main" >/etc/apt/sources.list.d/nodesource.list
    fi

    apt-get update -q
    apt-get install "${apt_opts[@]}" \
        nginx mysql-server redis-server supervisor ffmpeg certbot nodejs \
        "php$PHP_V-fpm" "php$PHP_V-cli" "php$PHP_V-common" "php$PHP_V-opcache" "php$PHP_V-readline" \
        "php$PHP_V-mysql" "php$PHP_V-redis" "php$PHP_V-mbstring" "php$PHP_V-intl" "php$PHP_V-xml" \
        "php$PHP_V-curl" "php$PHP_V-gd" "php$PHP_V-zip" "php$PHP_V-bcmath"
    ok "PHP $(php -r 'echo PHP_VERSION;'), Node $(node -v), $(nginx -v 2>&1 | cut -d/ -f2 | sed 's/^/Nginx /')"

    if ! command -v composer >/dev/null; then
        local expected actual installer
        installer=$(mktemp)
        expected=$(curl -fsS https://composer.github.io/installer.sig)
        curl -fsS https://getcomposer.org/installer -o "$installer"
        actual=$(php -r "echo hash_file('sha384', '$installer');")
        [[ $expected == "$actual" ]] || die 'Composer kurulum dosyasının imzası tutmuyor.'
        php "$installer" --quiet --install-dir=/usr/local/bin --filename=composer
        rm -f "$installer"
    fi
    ok "$(composer --version 2>/dev/null | head -1)"
}

# ---------------------------------------------------------------------------
# 4. Güvenlik duvarı ve kullanıcı
# ---------------------------------------------------------------------------

setup_firewall() {
    step 'Güvenlik duvarı'
    local ports port
    # sshd ayarı ve Ubuntu 24.04'teki ssh.socket dinleme adresleri; ikisi de
    # okunamazsa 22. Yanlış port kapatılırsa sunucuya bir daha bağlanılamaz.
    ports=$(
        {
            sshd -T 2>/dev/null | awk '$1 == "port" {print $2}'
            systemctl show ssh.socket -p Listen --value 2>/dev/null | grep -oE ':[0-9]+' | tr -d ':'
            ss -Hltnp 2>/dev/null | awk '/"sshd"/ {n = split($4, a, ":"); print a[n]}'
        } | grep -E '^[0-9]+$' | sort -un || true
    )
    [[ -n $ports ]] || ports=22
    for port in $ports; do
        ufw allow "$port/tcp" >/dev/null
    done
    ufw allow 80/tcp >/dev/null
    ufw allow 443/tcp >/dev/null
    ufw --force enable >/dev/null
    ok "Açık portlar: SSH ($(echo "$ports" | tr '\n' ' ' | sed 's/ $//')), 80, 443"
}

setup_user() {
    step 'Uygulama kullanıcısı'
    if ! id "$APP_USER" >/dev/null 2>&1; then
        adduser --disabled-password --gecos '' "$APP_USER" >/dev/null
        ok "$APP_USER kullanıcısı oluşturuldu (şifresiz, sudo yetkisi yok)"
    else
        ok "$APP_USER kullanıcısı var"
    fi

    install -d -o "$APP_USER" -g "$APP_USER" -m 0755 "$APP_DIR"
    install -d -o "$APP_USER" -g "$APP_USER" -m 0750 "$DATA_DIR" "$PRIVATE_DIR" "$BACKUP_DIR"
    install -d -o "$APP_USER" -g "$APP_USER" -m 0700 "$APP_HOME/.ssh"
}

# ---------------------------------------------------------------------------
# 5. MySQL ve Redis
# ---------------------------------------------------------------------------

setup_mysql() {
    step 'MySQL'
    systemctl enable --now mysql >/dev/null

    # Bakiye defterini koruyan tetikleyiciler ikili log açıkken SUPER yetkisi
    # olmadan oluşturulamaz; ayar yeniden başlatmada da kalır.
    mysql -e 'SET PERSIST log_bin_trust_function_creators = 1;'

    DB_PASSWORD=$(env_get DB_PASSWORD)
    [[ -n $DB_PASSWORD ]] || DB_PASSWORD=$(random_hex 24)

    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT PROCESS ON *.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    ok "Veritabanı $DB_NAME ve kullanıcı $DB_USER hazır"
}

setup_redis() {
    step 'Redis'
    local conf=/etc/redis/redis.conf
    REDIS_PASSWORD=$(env_get REDIS_PASSWORD)
    [[ -n $REDIS_PASSWORD ]] || REDIS_PASSWORD=$(random_hex 24)

    sed -i -E '/^[[:space:]]*requirepass[[:space:]]/d' "$conf"
    printf 'requirepass %s\n' "$REDIS_PASSWORD" >>"$conf"
    if ! grep -qE '^[[:space:]]*bind[[:space:]]' "$conf"; then
        echo 'bind 127.0.0.1 -::1' >>"$conf"
    fi

    systemctl enable redis-server >/dev/null
    systemctl restart redis-server
    sleep 1
    [[ $(REDISCLI_AUTH=$REDIS_PASSWORD redis-cli --no-auth-warning ping 2>/dev/null) == PONG ]] || die 'Redis şifreyle yanıt vermiyor.'
    ok 'Redis yalnızca yerelde, şifreli çalışıyor'
}

# ---------------------------------------------------------------------------
# 6. Kod
# ---------------------------------------------------------------------------

github_ready() {
    local out
    out=$(sudo -u "$APP_USER" -H ssh -o BatchMode=yes -o ConnectTimeout=15 -T git@github.com 2>&1 || true)
    [[ $out == *'successfully authenticated'* ]]
}

setup_github_access() {
    step 'GitHub erişimi'
    local key=$APP_HOME/.ssh/id_ed25519 known=$APP_HOME/.ssh/known_hosts scanned fingerprint

    if [[ ! -f $key ]]; then
        sudo -u "$APP_USER" -H ssh-keygen -q -t ed25519 -N '' -C "hovamusic@$(hostname)" -f "$key"
    fi

    if ! sudo -u "$APP_USER" -H ssh-keygen -F github.com -f "$known" >/dev/null 2>&1; then
        scanned=$(ssh-keyscan -t ed25519 github.com 2>/dev/null)
        fingerprint=$(printf '%s\n' "$scanned" | ssh-keygen -lf - 2>/dev/null | awk '{print $2}')
        if [[ $fingerprint != "$GITHUB_ED25519_FP" ]]; then
            warn "GitHub sunucu anahtarı beklenenden farklı: $fingerprint"
            warn 'https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/githubs-ssh-key-fingerprints adresindeki değerle karşılaştır.'
            yesno 'Bu anahtara güvenilsin mi?' h || die 'GitHub anahtarı doğrulanamadı.'
        fi
        printf '%s\n' "$scanned" >>"$known"
        chown "$APP_USER:$APP_USER" "$known"
        chmod 644 "$known"
    fi

    while ! github_ready; do
        printf '\n'
        warn 'Sunucunun GitHub deposunu okuyabilmesi için aşağıdaki anahtarı depoya ekle:'
        info "GitHub > ${REPO#git@github.com:} > Settings > Deploy keys > Add deploy key"
        info 'Başlık: hovamusic-sunucu   "Allow write access" işaretleme.'
        printf '\n%s%s%s\n\n' "$C_DIM" "$(cat "$key.pub")" "$C_OFF"
        read -r -p '  Ekledikten sonra Enter: ' _ </dev/tty || true
    done
    ok 'GitHub deposuna erişim var'
}

fetch_code() {
    step 'Kod'
    if [[ -d $APP_DIR/.git ]]; then
        if [[ -n $(as_app git status --porcelain --untracked-files=no) ]]; then
            warn 'Sunucudaki kodda elle yapılmış değişiklikler var; güncelleme atlandı.'
        else
            as_app git fetch --quiet origin "$BRANCH"
            as_app git checkout --quiet "$BRANCH"
            as_app git merge --quiet --ff-only "origin/$BRANCH"
            ok "Kod güncellendi: $(as_app git log -1 --format='%h %s')"
        fi
    else
        if [[ -n $(ls -A "$APP_DIR") ]]; then
            die "$APP_DIR boş değil ve bir git deposu değil. İçeriğini taşıyıp betiği yeniden çalıştır."
        fi
        sudo -u "$APP_USER" -H git clone --quiet --branch "$BRANCH" "$REPO" "$APP_DIR"
        ok "Kod indirildi: $(as_app git log -1 --format='%h %s')"
    fi
}

# ---------------------------------------------------------------------------
# 7. .env
# ---------------------------------------------------------------------------

cloudflare_ranges() {
    local v4 v6
    v4=$(curl -fsS --max-time 10 https://www.cloudflare.com/ips-v4 2>/dev/null || true)
    v6=$(curl -fsS --max-time 10 https://www.cloudflare.com/ips-v6 2>/dev/null || true)
    if [[ -z $v4 || -z $v6 ]]; then
        warn 'Cloudflare IP listesi indirilemedi; betikteki liste kullanılıyor.'
        v4='173.245.48.0/20 103.21.244.0/22 103.22.200.0/22 103.31.4.0/22 141.101.64.0/18 108.162.192.0/18 190.93.240.0/20 188.114.96.0/20 197.234.240.0/22 198.41.128.0/17 162.158.0.0/15 104.16.0.0/13 104.24.0.0/14 172.64.0.0/13 131.0.72.0/22'
        v6='2400:cb00::/32 2606:4700::/32 2803:f800::/32 2405:b500::/32 2405:8100::/32 2a06:98c0::/29 2c0f:f248::/32'
    fi
    printf '%s %s' "$v4" "$v6" | tr -s '[:space:]' ',' | sed 's/^,//; s/,$//'
}

write_env() {
    step 'Ortam dosyası (.env)'
    local fresh=0
    if [[ ! -f $ENV_FILE ]]; then
        install -m 640 -o "$APP_USER" -g "$APP_USER" "$APP_DIR/.env.example" "$ENV_FILE"
        fresh=1
    fi

    local admin_path proxies mailer
    admin_path=$(env_get ADMIN_PATH)
    [[ ${#admin_path} -ge 16 ]] || admin_path="yonetim-$(random_hex 12)"
    proxies=''
    [[ $CLOUDFLARE == e ]] && proxies=$(cloudflare_ranges)
    mailer=log
    [[ -n $RESEND_API_KEY ]] && mailer=resend

    env_set APP_ENV production
    env_set APP_DEBUG false
    env_set APP_URL "$(current_scheme)://$DOMAIN"
    env_set ADMIN_PATH "$admin_path"
    env_set ADMIN_ALLOWED_IPS "$ADMIN_IPS"
    env_set HORIZON_PATH "$admin_path/kuyruklar"
    env_set LOG_STACK daily
    env_set LOG_LEVEL warning
    env_set DB_CONNECTION mysql
    env_set DB_HOST 127.0.0.1
    env_set DB_PORT 3306
    env_set DB_DATABASE "$DB_NAME"
    env_set DB_USERNAME "$DB_USER"
    env_set DB_PASSWORD "$DB_PASSWORD"
    env_set SESSION_DRIVER redis
    env_set SESSION_ENCRYPT true
    env_set SESSION_SECURE_COOKIE "$([[ $(current_scheme) == https ]] && echo true || echo false)"
    env_set QUEUE_CONNECTION redis
    env_set CACHE_STORE redis
    env_set REDIS_CLIENT phpredis
    env_set REDIS_HOST 127.0.0.1
    env_set REDIS_PASSWORD "$REDIS_PASSWORD"
    env_set PRIVATE_STORAGE_PATH "$PRIVATE_DIR"
    env_set MAIL_MAILER "$mailer"
    env_set MAIL_FROM_ADDRESS "$MAIL_FROM"
    env_set RESEND_API_KEY "$RESEND_API_KEY"
    env_set TURNSTILE_SITE_KEY "$TURNSTILE_SITE_KEY"
    env_set TURNSTILE_SECRET_KEY "$TURNSTILE_SECRET_KEY"
    env_set POLAR_ACCESS_TOKEN "$POLAR_ACCESS_TOKEN"
    env_set POLAR_WEBHOOK_SECRET "$POLAR_WEBHOOK_SECRET"
    env_set POLAR_SERVER "$POLAR_SERVER"
    env_set SPOTIFY_CLIENT_ID "$SPOTIFY_CLIENT_ID"
    env_set SPOTIFY_CLIENT_SECRET "$SPOTIFY_CLIENT_SECRET"
    env_set FFPROBE_PATH /usr/bin/ffprobe
    env_set BACKUP_PATH "$BACKUP_DIR"
    env_set BACKUP_MYSQLDUMP /usr/bin/mysqldump
    env_set TRUSTED_PROXIES "$proxies"

    chown "$APP_USER:$APP_USER" "$ENV_FILE"
    chmod 640 "$ENV_FILE"
    ADMIN_PATH=$admin_path

    [[ $mailer == log ]] && warn 'Resend anahtarı yok: e-postalar gönderilmez, loga yazılır.'
    [[ -z $POLAR_ACCESS_TOKEN ]] && warn 'Polar anahtarı yok: plan satın alma kapalı kalır.'
    [[ -z $SPOTIFY_CLIENT_ID ]] && warn 'Spotify anahtarı yok: sanatçı araması çalışmaz.'
    if ((fresh)); then ok '.env oluşturuldu'; else ok '.env güncellendi (mevcut şifreler ve APP_KEY korundu)'; fi
}

current_scheme() {
    if [[ -f /etc/letsencrypt/live/$DOMAIN/fullchain.pem ]]; then echo https; else echo http; fi
}

# ---------------------------------------------------------------------------
# 8. PHP-FPM
# ---------------------------------------------------------------------------

setup_php() {
    step 'PHP-FPM'
    local pool=/etc/php/$PHP_V/fpm/pool.d/www.conf mem_mb children spare
    mem_mb=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
    children=$(((mem_mb - 1024) / 80))
    ((children < 5)) && children=5
    ((children > 40)) && children=40
    spare=$((children / 4))
    ((spare < 4)) && spare=4

    # Web istekleri, kuyruk ve cron aynı kullanıcıyla çalışır: özel diskteki
    # dosyalar yalnızca sahibine açık (0600) yazılır.
    sed -i -E \
        -e "s/^user = .*/user = $APP_USER/" \
        -e "s/^group = .*/group = $APP_USER/" \
        -e 's/^listen.owner = .*/listen.owner = www-data/' \
        -e 's/^listen.group = .*/listen.group = www-data/' \
        -e 's/^pm = .*/pm = dynamic/' \
        -e "s/^pm.max_children = .*/pm.max_children = $children/" \
        -e 's/^pm.start_servers = .*/pm.start_servers = 2/' \
        -e 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/' \
        -e "s/^pm.max_spare_servers = .*/pm.max_spare_servers = $spare/" \
        "$pool"

    cat >"/etc/php/$PHP_V/fpm/conf.d/99-hovamusic.ini" <<'INI'
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 512M
max_execution_time = 120
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
INI

    systemctl enable "php$PHP_V-fpm" >/dev/null
    systemctl restart "php$PHP_V-fpm"
    ok "PHP-FPM $APP_USER kullanıcısıyla, en fazla $children işlemle çalışıyor"
}

# ---------------------------------------------------------------------------
# 9. Uygulama
# ---------------------------------------------------------------------------

build_app() {
    step 'Bağımlılıklar ve derleme'
    as_app composer install --no-dev --optimize-autoloader --no-interaction --no-progress
    as_app npm ci --no-audit --no-fund
    as_app npm run build
    ok 'PHP bağımlılıkları kuruldu, ön yüz derlendi'
}

setup_app() {
    step 'Uygulama'
    if [[ -z $(env_get APP_KEY) ]]; then
        artisan key:generate --force
        warn "APP_KEY oluşturuldu. $ENV_FILE dosyasının bir kopyasını sunucu dışında sakla; anahtar kaybolursa şifreli veriler okunamaz."
    fi

    artisan optimize:clear >/dev/null
    artisan migrate --force

    # Başlangıç verileri (roller, mağazalar, SSS) yalnızca ilk kurulumda; sonra
    # admin panelinden silinenler geri gelmesin diye yalnızca roller eşitlenir.
    if [[ $(mysql_scalar "SELECT COUNT(*) FROM \`$DB_NAME\`.roles") == 0 ]]; then
        artisan db:seed --force
    else
        artisan db:seed --class=RoleSeeder --force
    fi

    if [[ $(mysql_scalar "SELECT COUNT(*) FROM \`$DB_NAME\`.genres") == 0 ]]; then
        if yesno 'Tür listesi boş. Örnek tür listesi yüklensin mi? (Admin panelinden düzenlenebilir)' e; then
            artisan db:seed --class=GenreSeeder --force
        fi
    fi

    [[ -e $APP_DIR/public/storage ]] || artisan storage:link
    ok 'Veritabanı tabloları ve başlangıç verileri hazır'
}

# ---------------------------------------------------------------------------
# 10. Nginx ve SSL
# ---------------------------------------------------------------------------

server_names() {
    if [[ $WITH_WWW == e ]]; then echo "$DOMAIN www.$DOMAIN"; else echo "$DOMAIN"; fi
}

nginx_app_block() {
    cat <<NGINX
    root $APP_DIR/public;
    index index.php;
    charset utf-8;
    client_max_body_size 64M;
    server_tokens off;

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml application/xml text/plain;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Vite çıktıları içerik özetli adlarla gelir; uzun süre önbelleklenir.
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ~* \.(?:webp|avif|png|jpg|jpeg|svg|ico|woff2)\$ {
        expires 30d;
        add_header Cache-Control "public";
        access_log off;
        try_files \$uri /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php$PHP_V-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
NGINX
}

write_nginx() {
    local names cert=/etc/letsencrypt/live/$DOMAIN v6_80='' v6_443=''
    names=$(server_names)
    # IPv6 kapalı sunucularda [::] dinlemesi Nginx'in açılmasını engeller.
    if [[ -e /proc/net/if_inet6 ]]; then
        v6_80='listen [::]:80;'
        v6_443='listen [::]:443 ssl http2;'
    fi

    if [[ -f $cert/fullchain.pem ]]; then
        cat >"$NGINX_SITE" <<NGINX
# Hova Music — install.sh tarafından üretildi; elle değiştirirsen betik yeniden yazar.
server {
    listen 80;
    $v6_80
    server_name $names;

    location /.well-known/acme-challenge/ {
        root $APP_DIR/public;
    }

    location / {
        return 301 https://$DOMAIN\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    $v6_443
    server_name $names;

    ssl_certificate $cert/fullchain.pem;
    ssl_certificate_key $cert/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

$(if [[ $WITH_WWW == e ]]; then printf '    if ($host = www.%s) {\n        return 301 https://%s$request_uri;\n    }\n' "$DOMAIN" "$DOMAIN"; fi)

$(nginx_app_block)
}
NGINX
    else
        cat >"$NGINX_SITE" <<NGINX
# Hova Music — install.sh tarafından üretildi; elle değiştirirsen betik yeniden yazar.
server {
    listen 80;
    $v6_80
    server_name $names;

    location /.well-known/acme-challenge/ {
        root $APP_DIR/public;
    }

$(nginx_app_block)
}
NGINX
    fi

    ln -sf "$NGINX_SITE" /etc/nginx/sites-enabled/hovamusic
    rm -f /etc/nginx/sites-enabled/default
    nginx -t 2>&1 | sed 's/^/    /'
    systemctl enable nginx >/dev/null
    systemctl reload nginx || systemctl restart nginx
}

# Sertifika istenen tüm adları (alan adı, www) kapsıyor mu?
cert_covers_names() {
    local cert=/etc/letsencrypt/live/$DOMAIN/fullchain.pem sans name
    [[ -f $cert ]] || return 1
    sans=$(openssl x509 -in "$cert" -noout -ext subjectAltName 2>/dev/null | grep -oE 'DNS:[^,[:space:]]+' | cut -d: -f2 | tr '\n' ' ' || true)
    for name in $(server_names); do
        [[ " $sans " == *" $name "* ]] || return 1
    done
    return 0
}

dns_points_here() {
    local server_ip name resolved
    server_ip=$(curl -4 -fsS --max-time 10 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')
    for name in $(server_names); do
        resolved=$(getent ahostsv4 "$name" | awk '{print $1}' | sort -u | tr '\n' ' ')
        if [[ " $resolved " != *" $server_ip "* ]]; then
            warn "$name bu sunucuyu ($server_ip) göstermiyor: ${resolved:-kayıt yok}"
            return 1
        fi
    done
    return 0
}

setup_nginx_ssl() {
    step 'Nginx ve SSL'
    write_nginx
    ok 'Nginx yapılandırıldı'

    if cert_covers_names; then
        ok 'SSL sertifikası zaten var; yenileme otomatik'
    else
        local can_issue=1
        if [[ $CLOUDFLARE == e ]]; then
            info 'Cloudflare kullanıldığı için DNS kontrolü atlandı. Cloudflare SSL modunu "Full (strict)" yap.'
        elif ! dns_points_here; then
            can_issue=0
        fi

        if ((can_issue)); then
            local domains=(-d "$DOMAIN")
            [[ $WITH_WWW == e ]] && domains+=(-d "www.$DOMAIN")
            if certbot certonly --webroot -w "$APP_DIR/public" --cert-name "$DOMAIN" --expand "${domains[@]}" \
                --email "$LE_EMAIL" --agree-tos --no-eff-email --non-interactive \
                --deploy-hook 'systemctl reload nginx'; then
                write_nginx
                ok "SSL sertifikası alındı; HTTP istekleri HTTPS'e yönleniyor"
            else
                warn 'SSL sertifikası alınamadı; site şimdilik HTTP ile çalışıyor.'
                [[ $CLOUDFLARE == e ]] && warn 'Cloudflare kullanıyorsan DNS kayıtlarını geçici olarak gri buluta (DNS only) alıp betiği yeniden çalıştır.'
            fi
        else
            warn 'DNS bu sunucuyu gösterene kadar SSL alınamaz. DNS yayıldıktan sonra betiği yeniden çalıştır.'
        fi
    fi

    # Sertifika durumuna göre adres ve çerez ayarı.
    env_set APP_URL "$(current_scheme)://$DOMAIN"
    env_set SESSION_SECURE_COOKIE "$([[ $(current_scheme) == https ]] && echo true || echo false)"
}

# ---------------------------------------------------------------------------
# 11. Kuyruklar, zamanlanmış görevler
# ---------------------------------------------------------------------------

setup_workers() {
    step 'Horizon ve zamanlanmış görevler'
    cat >/etc/supervisor/conf.d/hovamusic-horizon.conf <<CONF
[program:hovamusic-horizon]
process_name=%(program_name)s
command=php $APP_DIR/artisan horizon
directory=$APP_DIR
user=$APP_USER
environment=HOME="$APP_HOME"
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3700
redirect_stderr=true
stdout_logfile=/var/log/hovamusic-horizon.log
stdout_logfile_maxbytes=0
CONF

    # Supervisor log dosyasını root olarak yazar; döndürme logrotate ile.
    cat >/etc/logrotate.d/hovamusic <<CONF
/var/log/hovamusic-horizon.log {
    weekly
    rotate 4
    compress
    missingok
    notifempty
    copytruncate
}
CONF

    systemctl enable --now supervisor >/dev/null
    supervisorctl reread >/dev/null
    supervisorctl update >/dev/null
    # Çalışıyorsa yeni kodla finalize adımında (horizon:terminate) yeniden başlar.
    supervisorctl start hovamusic-horizon >/dev/null 2>&1 || true
    ok 'Horizon Supervisor altında çalışıyor'

    local line="* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1" current
    current=$(crontab -u "$APP_USER" -l 2>/dev/null || true)
    if ! grep -qF "artisan schedule:run" <<<"$current"; then
        printf '%s\n%s\n' "$current" "$line" | sed '/^$/d' | crontab -u "$APP_USER" -
    fi
    ok 'Zamanlayıcı her dakika çalışıyor (gece 03:30 yedek dahil)'
}

# ---------------------------------------------------------------------------
# 12. Son adımlar
# ---------------------------------------------------------------------------

finalize() {
    step 'Önbellek ve izinler'
    chown -R "$APP_USER:$APP_USER" "$APP_DIR" "$DATA_DIR"
    chmod 750 "$DATA_DIR" "$PRIVATE_DIR" "$BACKUP_DIR"
    chmod 640 "$ENV_FILE"
    artisan optimize
    artisan horizon:terminate >/dev/null 2>&1 || true
    systemctl reload "php$PHP_V-fpm"
    ok 'Yapılandırma önbelleğe alındı'
}

create_admin() {
    step 'Yönetici hesabı'
    local count
    count=$(mysql_scalar "SELECT COUNT(*) FROM \`$DB_NAME\`.admins")
    if [[ -n $count && $count != 0 ]]; then
        ok "$count yönetici hesabı var"
        return
    fi
    info 'İlk yöneticiyi oluştur (rol olarak Süper Admin seç).'
    if ! sudo -u "$APP_USER" -H -- bash -c 'cd "$0" && exec php artisan hova:admin-create' "$APP_DIR" </dev/tty >/dev/tty 2>&1; then
        warn "Yönetici oluşturulamadı. Sonra çalıştır: sudo -u $APP_USER php $APP_DIR/artisan hova:admin-create"
    fi
}

health_check() {
    step 'Kontrol'
    local scheme code
    scheme=$(current_scheme)
    if [[ $scheme == https ]]; then
        code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/" || true)
    else
        code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 -H "Host: $DOMAIN" http://127.0.0.1/ || true)
    fi
    if [[ $code == 200 ]]; then ok "Ana sayfa yanıt veriyor ($scheme, HTTP $code)"; else warn "Ana sayfa HTTP $code döndü; $APP_DIR/storage/logs dosyalarına bak."; fi

    local i running=0
    for i in 1 2 3 4 5 6 7 8 9 10; do
        if artisan horizon:status 2>/dev/null | grep -qi running; then running=1; break; fi
        sleep 2
    done
    if ((running)); then ok 'Kuyruklar çalışıyor'; else warn 'Horizon çalışmıyor görünüyor: supervisorctl status hovamusic-horizon'; fi

    if artisan hova:backup >/dev/null 2>&1; then ok "İlk yedek alındı ($BACKUP_DIR)"; else warn "Yedek alınamadı: sudo -u $APP_USER php $APP_DIR/artisan hova:backup"; fi
}

write_summary() {
    local scheme
    scheme=$(current_scheme)
    (
        umask 077
        cat >"$SUMMARY_FILE" <<EOF
Hova Music kurulumu — $(date '+%F %T')

Site            : $scheme://$DOMAIN
Admin paneli    : $scheme://$DOMAIN/$ADMIN_PATH
Kuyruk ekranı   : $scheme://$DOMAIN/$ADMIN_PATH/kuyruklar
Polar webhook   : $scheme://$DOMAIN/webhooks/polar  (olaylar: subscription.*, order.*, checkout.updated)

Uygulama        : $APP_DIR  (kullanıcı: $APP_USER)
Ortam dosyası   : $ENV_FILE  (şifreler ve APP_KEY burada; bir kopyasını sunucu dışında sakla)
Özel dosyalar   : $PRIVATE_DIR
Yedekler        : $BACKUP_DIR  (her gece 03:30; 7 günlük, 4 haftalık)
Kurulum logu    : $LOG_FILE
Kuyruk logu     : /var/log/hovamusic-horizon.log

Güncelleme      : sudo bash $APP_DIR/update.sh

Yayına almadan önce:
- Admin panelinde yasal metinlerin birer sürümünü yayımla.
- Polar'da ürünleri oluştur, ürün kimliklerini Planlar'a gir, webhook'u ekle.
- Resend'de alan adını doğrula (SPF, DKIM).
- Finans ayarlarında tahmini Wise ücretini, Kurlar'da ilk rapor dönemlerinin kurlarını gir.
- Sitedeki [ONAY BEKLİYOR] işaretli metinleri gerçek bilgilerle değiştir.
- Google Search Console doğrulama kodunu Sistem > Ayarlar'a gir, sitemap'i gönder.
EOF
    )

    printf '\n%s==> Kurulum tamamlandı%s\n\n' "$C_GREEN" "$C_OFF"
    sed 's/^/    /' "$SUMMARY_FILE"
    printf '\n    Bu özet %s dosyasına kaydedildi.\n\n' "$SUMMARY_FILE"
}

# ---------------------------------------------------------------------------

main() {
    preflight
    gather_answers
    install_packages
    setup_firewall
    setup_user
    setup_mysql
    setup_redis
    setup_github_access
    fetch_code
    write_env
    setup_php
    build_app
    setup_app
    setup_nginx_ssl
    setup_workers
    finalize
    create_admin
    health_check
    write_summary
}

main "$@"
