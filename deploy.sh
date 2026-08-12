#!/usr/bin/env bash
set -euo pipefail

# Однострочный деплой приложения на сервер из готового Docker-образа (GHCR).
#
# Использование:
#   curl -fsSL https://raw.githubusercontent.com/amper24/HSPM-App/main/deploy.sh | bash
#
# Или локально:
#   bash deploy.sh
#
# Образ собирается и публикуется автоматически GitHub Actions (workflow docker-publish.yml).

REPO="amper24/HSPM-App"
RAW_BASE="https://raw.githubusercontent.com/${REPO}/main"
GHCR_IMAGE="ghcr.io/amper24/hspm-app:latest"

echo "==> Проверка установки Docker и Docker Compose..."
if ! command -v docker >/dev/null 2>&1; then
  echo "ERROR: Docker не установлен. Установите Docker и Docker Compose." >&2
  exit 1
fi
if ! docker compose version >/dev/null 2>&1; then
  echo "ERROR: Docker Compose (v2) не найден." >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

echo "==> Загрузка docker-compose.prod.yml..."
curl -fsSL "${RAW_BASE}/docker-compose.prod.yml" -o "${TMP_DIR}/docker-compose.prod.yml"

echo "==> Авторизация в GitHub Container Registry (ghcr.io)..."
# Имя пользователя может быть любым; токен должен иметь доступ на чтение пакетов.
if [ -z "${GHCR_TOKEN:-}" ]; then
  echo "  GHCR_TOKEN не задан. Если образ публичный, авторизация не обязательна."
else
  echo "${GHCR_TOKEN}" | docker login ghcr.io -u amper24 --password-stdin
fi

echo "==> Загрузка и запуск контейнеров..."
cd "${TMP_DIR}"
APP_IMAGE="${GHCR_IMAGE}" docker compose -f docker-compose.prod.yml up -d --pull always

echo ""
echo "Готово! Приложение доступно на http://<server>:8080 (admin / admin123)"
echo "Проверить статус: docker compose -f docker-compose.prod.yml ps"
echo "Посмотреть логи:   docker compose -f docker-compose.prod.yml logs -f app"