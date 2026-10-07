#!/usr/bin/env bash
# =============================================================================
#  HSPM-App — установщик для Linux
#
#  Запуск одной строкой:
#    curl -fsSL https://raw.githubusercontent.com/amper24/HSPM-App/main/install.sh | bash
#
#  Параметры (опционально, через переменные окружения):
#    HSPM_INSTALL_DIR=/opt/hspm   — куда ставить (по умолчанию ./HSPM-App)
#    HSPM_PORT=8080               — порт приложения
#    HSPM_MODE=docker|local       — способ установки (по умолчанию docker)
#    HSPM_ADMIN_USERNAME=admin    — логин администратора
#    HSPM_ADMIN_PASSWORD=xxx      — пароль админа (минимум 12 символов)
#    HSPM_NONINTERACTIVE=1        — не задавать вопросов (пароль сгенерируется)
# =============================================================================

set -euo pipefail

# ---------- Оформление ----------
if [[ -t 1 ]]; then
    BOLD=$'\033[1m'; DIM=$'\033[2m'
    RED=$'\033[0;31m'; GRN=$'\033[0;32m'; YLW=$'\033[0;33m'
    BLU=$'\033[0;34m'; CYN=$'\033[0;36m'; NC=$'\033[0m'
else
    BOLD=""; DIM=""; RED=""; GRN=""; YLW=""; BLU=""; CYN=""; NC=""
fi

step()  { printf "\n${BOLD}${BLU}[*] %s${NC}\n" "$*"; }
info()  { printf "  ${DIM}%s${NC}\n" "$*"; }
ok()    { printf "  ${GRN}[ok]${NC} %s\n" "$*"; }
warn()  { printf "  ${YLW}[!]${NC} %s\n" "$*"; }
die()   { printf "\n${RED}[x] %s${NC}\n" "$*" >&2; exit 1; }

banner() {
    printf "${BOLD}${CYN}"
    cat <<'EOF'
  ==============================================
    HSPM-App - установка одной командой
  ==============================================
EOF
    printf "${NC}"
}

# ---------- Параметры ----------
REPO_URL="${HSPM_REPO_URL:-https://github.com/amper24/HSPM-App.git}"
INSTALL_DIR="${HSPM_INSTALL_DIR:-HSPM-App}"
MODE="${HSPM_MODE:-docker}"
APP_PORT="${HSPM_PORT:-8080}"
ADMIN_USER="${HSPM_ADMIN_USERNAME:-admin}"
ADMIN_PASS="${HSPM_ADMIN_PASSWORD:-}"
NONINTERACTIVE="${HSPM_NONINTERACTIVE:-0}"

banner

# ---------- Проверка зависимостей ----------
step "Проверяю зависимости"

have() { command -v "$1" >/dev/null 2>&1; }

have git || die "Не найден 'git'. Установите: sudo apt install git   (или dnf/pacman)"

DC=""
if [[ "$MODE" == "docker" ]]; then
    have docker || die "Не найден 'docker'. Установите Docker: https://docs.docker.com/engine/install/"
    if docker compose version >/dev/null 2>&1; then
        DC="docker compose"
    elif have docker-compose; then
        DC="docker-compose"
    else
        die "Не найден Docker Compose. Установите плагин: sudo apt install docker-compose-plugin"
    fi
    if ! docker info >/dev/null 2>&1; then
        die "Docker-демон недоступен. Запустите его и проверьте, что ваш пользователь в группе 'docker':
        sudo systemctl start docker
        sudo usermod -aG docker \$USER && newgrp docker"
    fi
    ok "docker + compose"
else
    for c in php composer node npm; do
        have "$c" || die "Не найдено: $c (нужно для локального режима)"
    done
    ok "php composer node npm"
fi
ok "git"

# ---------- Клонирование ----------
step "Получаю исходники"
if [[ -d "$INSTALL_DIR/.git" ]]; then
    warn "Каталог '$INSTALL_DIR' уже существует — обновляю его"
    cd "$INSTALL_DIR"
    git pull --ff-only >/dev/null 2>&1 || warn "git pull не удался, продолжаю с текущим состоянием"
else
    info "git clone -> $INSTALL_DIR"
    git clone --depth 1 "$REPO_URL" "$INSTALL_DIR" >/dev/null
    cd "$INSTALL_DIR"
fi
ok "Проект: $(pwd)"

# ---------- .env ----------
step "Настраиваю окружение"
if [[ -f .env ]]; then
    warn ".env уже есть — оставляю без изменений"
else
    [[ -f .env.example ]] || die "В проекте нет .env.example — не от чего отталкиваться"
    cp .env.example .env
    ok "Создан .env"
fi

# Генератор случайных строк
rand_hex() {
    if have openssl; then
        openssl rand -hex "$1"
    else
        head -c "$1" /dev/urandom | od -An -tx1 | tr -d ' \n'
    fi
}

set_env() {
    local key="$1" val="$2"
    if grep -qE "^${key}=" .env; then
        local esc; esc=$(printf '%s' "$val" | sed -e 's/[\/&|]/\\&/g')
        sed -i.bak -E "s|^${key}=.*|${key}=${esc}|" .env && rm -f .env.bak
    else
        printf '%s=%s\n' "$key" "$val" >> .env
    fi
}

# --- Запрос пароля администратора ---
ask_password() {
    # Если NONINTERACTIVE и пароль не задан — генерируем
    if [[ "$NONINTERACTIVE" == "1" && -z "$ADMIN_PASS" ]]; then
        ADMIN_PASS="$(rand_hex 8)"   # 16 hex-символов, >= 12
        warn "Non-interactive режим: пароль администратора сгенерирован автоматически"
        return
    fi

    # Если уже задан через HSPM_ADMIN_PASSWORD — используем его
    if [[ -n "$ADMIN_PASS" ]]; then
        if [[ ${#ADMIN_PASS} -lt 12 ]]; then
            die "HSPM_ADMIN_PASSWORD короче 12 символов — приложение его не примет"
        fi
        return
    fi

    # Если терминала нет — генерируем
    if ! [[ -r /dev/tty && -w /dev/tty ]]; then
        ADMIN_PASS="$(rand_hex 8)"
        warn "Терминал недоступен: пароль администратора сгенерирован автоматически"
        return
    fi

    local p1 p2
    while true; do
        printf "  Введите пароль для администратора (%s) [минимум 12 символов]: " "$ADMIN_USER"
        IFS= read -r -s p1 < /dev/tty
        printf "\n"
        if [[ -z "$p1" ]]; then
            printf "  ${YLW}Пароль не может быть пустым, попробуйте снова.${NC}\n"
            continue
        fi
        if [[ ${#p1} -lt 12 ]]; then
            printf "  ${YLW}Пароль слишком короткий (требуется минимум 12 символов, введено ${#p1}).${NC}\n"
            continue
        fi
        printf "  Повторите пароль: "
        IFS= read -r -s p2 < /dev/tty
        printf "\n"
        if [[ "$p1" != "$p2" ]]; then
            printf "  ${YLW}Пароли не совпадают, попробуйте снова.${NC}\n"
            continue
        fi
        ADMIN_PASS="$p1"
        ok "Пароль администратора принят"
        break
    done
}

step "Пароли"
DB_PASS="$(rand_hex 24)"
ROOT_PASS="$(rand_hex 24)"
ok "Пароль БД и root-пароль MySQL сгенерированы"

ask_password

set_env DB_PASSWORD          "$DB_PASS"
set_env MYSQL_ROOT_PASSWORD  "$ROOT_PASS"
set_env ADMIN_USERNAME       "$ADMIN_USER"
set_env ADMIN_PASSWORD       "$ADMIN_PASS"
set_env APP_ENV              "production"
set_env APP_DEBUG            "false"
set_env APP_PORT             "$APP_PORT"
set_env APP_URL              "http://localhost:${APP_PORT}"
ok ".env заполнен"

# ---------- Установка ----------
if [[ "$MODE" == "docker" ]]; then

    step "Собираю Docker-образы"
    info "первый запуск может занять 3-5 минут (компилируются PHP-расширения)"
    $DC build

    step "Генерирую APP_KEY"
    APP_KEY_VAL="$($DC run --rm --no-deps app php artisan key:generate --show 2>/dev/null | tr -d '\r' | tail -n1)"
    [[ -n "$APP_KEY_VAL" ]] || die "Не удалось получить APP_KEY из контейнера"
    set_env APP_KEY "$APP_KEY_VAL"
    ok "APP_KEY установлен"

    step "Запускаю контейнеры"
    $DC up -d

    step "Ожидаю готовности базы данных"
    for i in $(seq 1 30); do
        if $DC exec -T app php artisan db:show >/dev/null 2>&1; then
            ok "БД готова"
            break
        fi
        printf "."
        sleep 2
        [[ $i -eq 30 ]] && warn "БД так и не ответила, пробую миграции всё равно"
    done
    echo

    step "Применяю миграции"
    $DC exec -T app php artisan migrate --force || warn "Миграции не применились — проверьте логи: $DC logs app"

    step "Создаю администратора"
    if $DC exec -T app php artisan list 2>/dev/null | grep -q "hspm:admin"; then
        $DC exec -T app php artisan hspm:admin "$ADMIN_USER" --create --password="$ADMIN_PASS" \
            || warn "Команда hspm:admin завершилась с ошибкой — создайте админа вручную"
        ok "Администратор готов"
    else
        warn "Команда hspm:admin не найдена — создайте админа вручную"
    fi

    FINAL_URL="http://localhost:${APP_PORT}"
    RUN_HINT="$DC ps"

else
    step "Устанавливаю PHP-зависимости"
    composer install --no-interaction --prefer-dist --no-scripts
    php artisan package:discover --ansi

    step "Устанавливаю JS-зависимости"
    npm ci

    step "Генерирую APP_KEY"
    php artisan key:generate --force
    ok "APP_KEY установлен"

    step "Собираю фронтенд"
    npm run build

    step "Настраиваю базу данных"
    if grep -qE '^DB_CONNECTION=mysql' .env; then
        warn "В .env указан MySQL — убедитесь, что БД создана, и запустите: php artisan migrate"
    else
        mkdir -p database
        [[ -f database/database.sqlite ]] || touch database/database.sqlite
        ABS="$(cd database && pwd)/database.sqlite"
        set_env DB_CONNECTION "sqlite"
        set_env DB_DATABASE   "$ABS"
        php artisan migrate --force
        ok "SQLite: $ABS"
    fi

    step "Создаю администратора"
    if php artisan list 2>/dev/null | grep -q "hspm:admin"; then
        php artisan hspm:admin "$ADMIN_USER" --create --password="$ADMIN_PASS" \
            || warn "Команда hspm:admin завершилась с ошибкой — создайте админа вручную"
        ok "Администратор готов"
    else
        warn "Команда hspm:admin не найдена — создайте админа вручную"
    fi

    FINAL_URL="http://127.0.0.1:8000"
    RUN_HINT="php artisan serve"
fi

# ---------- Итог ----------
printf "\n${BOLD}${GRN}"
cat <<'EOF'
  ==============================================
               Установка завершена
  ==============================================
EOF
printf "${NC}"

printf "  ${BOLD}URL:${NC}      %s\n" "$FINAL_URL"
printf "  ${BOLD}Запуск:${NC}   %s\n" "$RUN_HINT"
printf "  ${BOLD}Логин:${NC}    %s\n" "$ADMIN_USER"
printf "  ${BOLD}Проект:${NC}   %s\n" "$(pwd)"
echo
printf "  ${DIM}Пароль администратора сохранён в .env (ADMIN_PASSWORD).${NC}\n"
printf "  ${DIM}Остановить: %s down   |   Логи: %s logs -f app${NC}\n\n" "$DC" "$DC"