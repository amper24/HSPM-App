#!/bin/bash
# ================================================================
#  Скрипт первоначальной настройки
#  Система сбора и поиска данных для учебного отдела ВШПМ СПбГУПТД
# ================================================================
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}============================================================${NC}"
echo -e "${BLUE}  Учебный отдел ВШПМ СПбГУПТД — Мастер настройки${NC}"
echo -e "${BLUE}  Кодовая база: PHP 7.2.34 / MySQL 5.7.21 / Vue 2.7.14${NC}"
echo -e "${BLUE}============================================================${NC}"
echo ""

# Проверка Docker
if ! command -v docker &> /dev/null; then
    echo -e "${RED}[ОШИБКА] Docker не установлен.${NC}"
    echo "  Установите Docker: https://docs.docker.com/engine/install/"
    exit 1
fi

if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo -e "${RED}[ОШИБКА] Docker Compose не найден.${NC}"
    echo "  Установите Docker Compose: https://docs.docker.com/compose/install/"
    exit 1
fi

DOCKER_COMPOSE=$(command -v docker-compose || echo "docker compose")

echo -e "${GREEN}[OK] Docker установлен${NC}"
echo ""

# ============================================
#  Шаг 1: Проверка/создание .env файла
# ============================================
echo -e "${YELLOW}--- Шаг 1: Настройка подключения к БД ---${NC}"
echo ""

if [ -f .env ]; then
    echo "Файл .env уже существует. Пропускаем."
    source .env
else
    echo "Создаю файл .env с настройками по умолчанию..."
    echo ""
    echo "Вы можете изменить параметры или оставить значения по умолчанию."

    read -p "Пароль root MySQL [root_secret]: " MYSQL_ROOT_PASSWORD
    MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-root_secret}

    read -p "Имя базы данных [vshpm_edu]: " DB_NAME
    DB_NAME=${DB_NAME:-vshpm_edu}

    read -p "Пользователь БД [vshpm]: " DB_USER
    DB_USER=${DB_USER:-vshpm}

    read -p "Пароль пользователя БД [vshpm_secret]: " DB_PASS
    DB_PASS=${DB_PASS:-vshpm_secret}

    read -p "Порт БД на хосте (внешний) [3307]: " DB_PORT
    DB_PORT=${DB_PORT:-3307}

    read -p "Порт приложения на хосте [8080]: " APP_PORT
    APP_PORT=${APP_PORT:-8080}

    cat > .env <<EOF
# ===================================================
#  Конфигурация Учебный отдел ВШПМ
#  Сгенерировано $(date '+%Y-%m-%d %H:%M')
# ===================================================

# MySQL root
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}

# База данных
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}

# Порты
DB_PORT=${DB_PORT}
APP_PORT=${APP_PORT}
EOF

    echo ""
    echo -e "${GREEN}[OK] Файл .env создан${NC}"
fi

echo ""

# ============================================
#  Шаг 2: Запуск контейнеров
# ============================================
echo -e "${YELLOW}--- Шаг 2: Запуск Docker-контейнеров ---${NC}"
echo ""

echo "Остановка предыдущих контейнеров (если есть)..."
$DOCKER_COMPOSE down 2>/dev/null || true

echo "Сборка и запуск..."
$DOCKER_COMPOSE up -d --build

echo ""
echo -e "${GREEN}[OK] Контейнеры запущены${NC}"
echo ""

# ============================================
#  Шаг 3: Ожидание готовности
# ============================================
echo -e "${YELLOW}--- Шаг 3: Ожидание готовности сервисов ---${NC}"
echo ""

echo "Ожидание запуска приложения (до 90 секунд)..."
for i in $(seq 1 45); do
    if curl -s -o /dev/null -w "%{http_code}" "http://localhost:${APP_PORT:-8080}/api/auth/me" 2>/dev/null | grep -q "200"; then
        echo -e "${GREEN}[OK] Приложение готово!${NC}"
        break
    fi
    if [ $i -eq 45 ]; then
        echo -e "${YELLOW}[!] Не удалось дождаться ответа. Проверьте логи:${NC}"
        echo "  $DOCKER_COMPOSE logs app"
        echo ""
        echo "  Возможно, приложение все еще инициализируется. Попробуйте через минуту."
    fi
    sleep 2
done

echo ""

# ============================================
#  Шаг 4: Вывод итоговой информации
# ============================================
echo -e "${BLUE}============================================================${NC}"
echo -e "${GREEN}  НАСТРОЙКА ЗАВЕРШЕНА!${NC}"
echo -e "${BLUE}============================================================${NC}"
echo ""
echo -e "  Приложение доступно по адресу:"
echo -e "    ${BLUE}http://localhost:${APP_PORT:-8080}/${NC}"
echo ""
echo -e "  Учетные данные для входа:"
echo -e "    Логин:  ${YELLOW}admin${NC}"
echo -e "    Пароль: ${YELLOW}admin123${NC}"
echo ""
echo -e "  ${RED}ВАЖНО: Сразу после входа смените пароль администратора!${NC}"
echo -e "  (Раздел «Пользователи» → Изменить администратора)"
echo ""
echo -e "  База данных MySQL доступна:"
echo -e "    Хост: localhost"
echo -e "    Порт: ${DB_PORT:-3307}"
echo -e "    БД:   ${DB_NAME:-vshpm_edu}"
echo -e "    Пользователь: ${DB_USER:-vshpm}"
echo ""
echo -e "  Управление контейнерами:"
echo -e "    ${BLUE}$DOCKER_COMPOSE ps${NC}          — статус"
echo -e "    ${BLUE}$DOCKER_COMPOSE logs -f app${NC} — логи приложения"
echo -e "    ${BLUE}$DOCKER_COMPOSE down${NC}         — остановка"
echo -e "    ${BLUE}$DOCKER_COMPOSE up -d${NC}        — запуск"
echo ""
echo -e "  Документация: app/docs/README.md"
echo ""
echo -e "${BLUE}============================================================${NC}"