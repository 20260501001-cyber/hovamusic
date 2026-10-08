#!/usr/bin/env bash
#
# Hova Music — güncelleme betiği
#
# GitHub'daki son kodu sunucuya alır: bakım modu, yedek, git pull, composer,
# ön yüz derlemesi, migrate, önbellek, Horizon ve PHP-FPM yeniden yükleme.
#
# Kullanım (root olarak):
#   sudo bash /var/www/hovamusic/update.sh            # yedek alarak
#   sudo bash /var/www/hovamusic/update.sh --yedeksiz # yedek almadan
#
# Kod ya da derleme adımı hata verirse önceki sürüme dönülür ve site açılır.
# Migrate hata verirse site bakım modunda kalır; sorunu giderip betiği yeniden
# çalıştırmak yeterlidir (kod güncel olsa da yarım kalan adımlar tamamlanır).

set -Eeuo pipefail
umask 022

# Betik git pull ile değişebileceği için geçici bir kopyadan çalışır.
if [[ -z ${HM_SELF_COPY:-} && -f ${BASH_SOURCE[0]:-} ]]; then
    tmp_copy=$(mktemp /tmp/hovamusic-guncelleme.XXXXXX)
    cp "${BASH_SOURCE[0]}" "$tmp_copy"
    HM_SELF_COPY=1 exec bash "$tmp_copy" "$@"
fi
[[ -n ${HM_SELF_COPY:-} ]] && rm -f -- "${BASH_SOURCE[0]}"
cd /

readonly APP_DIR=/var/www/hovamusic
readonly APP_USER=deploy
readonly PHP_V=8.4
readonly LOG_FILE=/var/log/hovamusic-guncelleme.log
readonly DOWN_FILE=$APP_DIR/storage/framework/down

if [[ -t 1 ]]; then
    C_BLUE=$'\e[1;34m' C_GREEN=$'\e[1;32m' C_YELLOW=$'\e[1;33m' C_RED=$'\e[1;31m' C_OFF=$'\e[0m'
else
    C_BLUE='' C_GREEN='' C_YELLOW='' C_RED='' C_OFF=''
fi

step() { printf '\n%s==> %s%s\n' "$C_BLUE" "$1" "$C_OFF"; }
ok() { printf '    %s✓ %s%s\n' "$C_GREEN" "$*" "$C_OFF"; }
warn() { printf '    %s! %s%s\n' "$C_YELLOW" "$*" "$C_OFF"; }
die() { printf '\n%s✗ %s%s\n' "$C_RED" "$*" "$C_OFF" >&2; exit 1; }

as_app() {
    sudo -u "$APP_USER" -H -- bash -c 'cd "$0" && exec "$@"' "$APP_DIR" "$@"
}

artisan() {
    as_app php artisan "$@"
}

BACKUP=1
for arg in "$@"; do
    case $arg in
        --yedeksiz) BACKUP=0 ;;
        -h | --help)
            printf 'Kullanım: sudo bash %s/update.sh [--yedeksiz]\n' "$APP_DIR"
            exit 0
            ;;
        *) die "Bilinmeyen seçenek: $arg" ;;
    esac
done

[[ $EUID -eq 0 ]] || die 'Betiği root olarak çalıştır: sudo bash update.sh'
[[ -d $APP_DIR/.git && -f $APP_DIR/.env ]] || die "$APP_DIR altında kurulu uygulama yok; önce install.sh çalıştır."

touch "$LOG_FILE" && chmod 600 "$LOG_FILE"
exec > >(tee -a "$LOG_FILE") 2>&1
printf '\n===== Güncelleme %s =====\n' "$(date '+%F %T')" >>"$LOG_FILE"

BRANCH=$(as_app git rev-parse --abbrev-ref HEAD)
OLD=$(as_app git rev-parse HEAD)
WAS_DOWN=0
[[ -f $DOWN_FILE ]] && WAS_DOWN=1
WE_PUT_DOWN=0
PHASE=hazirlik
ASSETS_KEEP=''

# Yeni kod, PHP-FPM'in önbelleği (opcache) ve Horizon birlikte yenilenir.
reload_services() {
    artisan optimize
    artisan horizon:terminate
    systemctl reload "php$PHP_V-fpm"
}

# Hata anında: kod aşamasındaysa önceki sürüme dön ve siteyi aç; migrate
# aşamasındaysa siteyi bakımda bırak.
on_error() {
    local code=$? line=$1
    [[ $BASHPID == "$$" ]] || exit "$code"
    trap - ERR
    set +e
    printf '\n%s✗ Güncelleme hata verdi (satır %s, aşama: %s).%s\n' "$C_RED" "$line" "$PHASE" "$C_OFF" >&2
    case $PHASE in
        kod)
            warn "Önceki sürüme ($(printf '%.7s' "$OLD")) dönülüyor..."
            as_app git reset --quiet --hard "$OLD"
            as_app composer install --no-dev --optimize-autoloader --no-interaction --no-progress
            as_app npm ci --no-audit --no-fund && as_app npm run build
            reload_services
            ((WAS_DOWN)) || artisan up
            warn 'Önceki sürüme dönüldü.'
            ;;
        migrate)
            warn 'Veritabanı güncellemesi yarım kalmış olabilir; site bakım modunda bırakıldı.'
            warn "Hatayı $LOG_FILE ve $APP_DIR/storage/logs içinde incele."
            warn 'Sorunu giderdikten sonra betiği yeniden çalıştır: sudo bash /var/www/hovamusic/update.sh'
            warn "Yedekten dönmek gerekirse README'deki \"Geri yükleme\" adımlarını izle."
            ;;
        servis)
            reload_services
            artisan up
            ;;
        *)
            ((WE_PUT_DOWN && !WAS_DOWN)) && artisan up
            ;;
    esac
    [[ -n $ASSETS_KEEP ]] && rm -rf "$ASSETS_KEEP"
    printf '  Ayrıntılar: %s\n' "$LOG_FILE" >&2
    exit "$code"
}
trap 'on_error $LINENO' ERR

step 'Değişiklikler'
if [[ -n $(as_app git status --porcelain --untracked-files=no) ]]; then
    die "Sunucudaki kodda elle yapılmış değişiklikler var. Önce bunları geri al: sudo -u $APP_USER git -C $APP_DIR status"
fi
as_app git fetch --quiet origin "$BRANCH"
NEW=$(as_app git rev-parse "origin/$BRANCH")

if [[ $NEW == "$OLD" ]]; then
    if ((!WAS_DOWN)); then
        ok "Kod zaten güncel ($(printf '%.7s' "$OLD"))."
        exit 0
    fi
    ok 'Kod güncel; site bakımda olduğu için yarım kalan adımlar tamamlanıyor.'
else
    as_app git log --oneline --no-decorate "$OLD..$NEW" | sed 's/^/    /'
fi

if ((!WAS_DOWN)); then
    step 'Bakım modu'
    artisan down --render='errors::503' --retry=60
    WE_PUT_DOWN=1
    ok 'Site bakım sayfası gösteriyor'
fi

if ((BACKUP)); then
    step 'Yedek'
    PHASE=yedek
    artisan hova:backup
fi

if [[ $NEW != "$OLD" ]]; then
    step 'Kod ve derleme'
    PHASE=kod
    as_app git merge --quiet --ff-only "origin/$BRANCH"
    as_app composer install --no-dev --optimize-autoloader --no-interaction --no-progress

    # Bakım sayfası eski CSS dosyalarını kullanır; derleme bunları silmesin diye
    # eski dosyalar korunur, 30 günden eskileri temizlenir.
    ASSETS_KEEP=$(mktemp -d)
    [[ -d $APP_DIR/public/build/assets ]] && cp -a "$APP_DIR/public/build/assets/." "$ASSETS_KEEP/"
    as_app npm ci --no-audit --no-fund
    as_app npm run build
    cp -a --update=none "$ASSETS_KEEP/." "$APP_DIR/public/build/assets/"
    chown -R "$APP_USER:$APP_USER" "$APP_DIR/public/build"
    find "$APP_DIR/public/build/assets" -type f -mtime +30 -delete
    rm -rf "$ASSETS_KEEP"
    ASSETS_KEEP=''
    ok "Kod güncellendi: $(as_app git log -1 --format='%h %s')"
fi

step 'Veritabanı'
PHASE=migrate
artisan optimize:clear >/dev/null
artisan migrate --force
artisan db:seed --class=RoleSeeder --force

step 'Önbellek ve servisler'
PHASE=servis
reload_services
artisan up
ok 'Site açıldı; Horizon yeni kodla yeniden başlıyor'

printf '\n%s==> Güncelleme tamamlandı: %.7s → %.7s%s\n\n' "$C_GREEN" "$OLD" "$NEW" "$C_OFF"
