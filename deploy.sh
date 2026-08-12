#!/usr/bin/env bash
set -euo pipefail

# ============================================================================
#  hspm-app — однострочный деплой на сервер из готового Docker-образа (GHCR)
#
#  Использование:
#    curl -fsSL https://raw.githubusercontent.com/amper24/HSPM-App/main/deploy.sh | bash
#
#  С параметрами (env):
#    curl -fsSL ... | bash -s -- --port 8080 --admin-pass 'mysecret' --db-pass 'dbsecret'
#
#  Повторный запуск обновит приложение до последней версии (--pull always).
# ============================================================================

REPO="amper24/HSPM-App"
RAW_BASE="https://raw.githubusercontent.com/${REPO}/main"
GHCR_IMAGE="ghcr.io/amper24/hspm-app:latest"

# --- Значения по умолчанию (переопределяются переменными окружения или флагами) ---
APP_PORT="${APP_PORT:-8080}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin123}"
DB_NAME="${DB_NAME:-vshpm_edu}"
DB_USER="${DB_USER:-vshpm}"
DB_PASS="${DB_PASS:-vshpm_secret}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-root_secret}"

# --- Парсим аргументы командной строки ---
while [[ $# -gt 0 ]]; do
  case "$1" in
    --port)
      APP_PORT="$2"; shift 2 ;;
    --admin-pass)
      ADMIN_PASSWORD="$2"; shift 2 ;;
    --db-pass)
      DB_PASS="$2"; shift 2 ;;
    --db-user)
      DB_USER="$2"; shift 2 ;;
    --db-name)
      DB_NAME="$2"; shift 2 ;;
    --root-pass)
      MYSQL_ROOT_PASSWORD="$2"; shift 2 ;;
    -h|--help)
      cat <<'EOF'
Использование: deploy.sh [опции]

Опции:
  --port <port>          Порт доступа к приложению на хосте (по умолчанию 8080)
  --admin-pass <pass>    Пароль администратора admin (по умолчанию admin123)
  --db-pass <pass>       Пароль пользователя БД (по умолчанию vshpm_secret)
  --db-user <user>       Пользователь БД (по умолчанию vshpm)
  --db-name <name>       Имя базы данных (по умолчанию vshpm_edu)
  --root-pass <pass>     Пароль root MySQL (по умолчанию root_secret)

Так же можно задавать через переменные окружения:
  APP_PORT, ADMIN_PASSWORD, DB_PASS, DB_USER, DB_NAME, MYSQL_ROOT_PASSWORD
EOF
      exit 0 ;;
    *)
      echo "Неизвестный аргумент: $1" >&2
      echo "Запустите с --help для справки." >&2
      exit 1 ;;
  esac
done

echo "==> Проверка установки Docker и Docker Compose..."
if ! command -v docker >/dev/null 2>&1; then
  echo "ERROR: Docker не установлен." >&2
  exit 1
fi
if ! docker compose version >/dev/null 2>&1; then
  echo "ERROR: Docker Compose v2 не найден." >&2
  exit 1
fi

# Рабочая директория для конфигов (сохраняется между запусками)
APP_DIR="${APP_DIR:-$HOME/hspm-app}"
mkdir -p "$APP_DIR"
cd "$APP_DIR"

echo "==> Загрузка docker-compose.prod.yml..."
curl -fsSL "${RAW_BASE}/docker-compose.prod.yml" -o docker-compose.prod.yml

echo "==> Генерация .env с настройками..."
cat > .env <<EOF
APP_PORT=${APP_PORT}
ADMIN_PASSWORD=${ADMIN_PASSWORD}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
EOF
chmod 600 .env

echo "==> Авторизация в GitHub Container Registry (ghcr.io)..."
if [ -z "${GHCR_TOKEN:-}" ]; then
  echo "  GHCR_TOKEN не задан. Если образ публичный, авторизация не обязательна."
else
  echo "${GHCR_TOKEN}" | docker login ghcr.io -u amper24 --password-stdin
fi

echo "==> Загрузка и запуск контейнеров..."
APP_IMAGE="${GHCR_IMAGE}" docker compose -f docker-compose.prod.yml up -d --pull always

echo ""
echo "======================================================"
echo "  Приложение запущено!"
echo "  URL:    http://<server>:${APP_PORT}"
echo "  Логин:  admin"
echo "  Пароль: ${ADMIN_PASSWORD}"
echo "======================================================"
echo ""
echo "Полезные команды:"
echo "  Статус:  cd ${APP_DIR} && docker compose -f docker-compose.prod.yml ps"
echo "  Логи:    cd ${APP_DIR} && docker compose -f docker-compose.prod.yml logs -f app"
echo "  Обновить: повторите эту же команду."