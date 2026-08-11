#!/bin/bash
# ================================================================
#  Запуск всех тестов — Учебный отдел ВШПМ
#
#  Использование:
#    ./tests/run_tests.sh              # все тесты (БД + API + debug)
#    ./tests/run_tests.sh --db         # только тест БД
#    ./tests/run_tests.sh --api        # только тест API
#    ./tests/run_tests.sh --debug      # только диагностика
# ================================================================
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$DIR")"
cd "$PROJECT_DIR"

APP_PORT=$(grep APP_PORT .env 2>/dev/null | cut -d= -f2 || echo "8080")
APP_URL="http://localhost:${APP_PORT}"

echo -e "${BLUE}============================================================${NC}"
echo -e "${BLUE}  ТЕСТИРОВАНИЕ — Учебный отдел ВШПМ${NC}"
echo -e "${BLUE}  Проект: ${PROJECT_DIR}${NC}"
echo -e "${BLUE}  API URL: ${APP_URL}${NC}"
echo -e "${BLUE}============================================================${NC}"
echo ""

# Проверка наличия PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}[ОШИБКА] PHP не найден в PATH.${NC}"
    echo "  Для тестов БД и API требуется PHP с модулями curl, pdo_mysql."
    exit 1
fi

# Проверка модулей PHP
MISSING_MODULES=()
for mod in curl pdo_mysql mbstring; do
    if ! php -m | grep -q "^${mod}$"; then
        MISSING_MODULES+=("$mod")
    fi
done
if [ ${#MISSING_MODULES[@]} -gt 0 ]; then
    echo -e "${RED}[ОШИБКА] Отсутствуют модули PHP: ${MISSING_MODULES[*]}${NC}"
    echo "  Установите: sudo apt install php-curl php-mysql php-mbstring"
    exit 1
fi

echo -e "${GREEN}[OK] PHP готов: $(php -v | head -1)${NC}"
echo ""

RUN_DB=false
RUN_API=false
RUN_DEBUG=false

if [ $# -eq 0 ]; then
    RUN_DB=true
    RUN_API=true
    RUN_DEBUG=true
else
    for arg in "$@"; do
        case $arg in
            --db) RUN_DB=true ;;
            --api) RUN_API=true ;;
            --debug) RUN_DEBUG=true ;;
            *) echo "Неизвестный аргумент: $arg"; exit 1 ;;
        esac
    done
fi

TOTAL_PASS=0
TOTAL_FAIL=0

# --- Тест БД ---
if $RUN_DB; then
    echo -e "${YELLOW}>>> Тест базы данных${NC}"
    echo ""

    if php "$DIR/test_db.php"; then
        echo ""
    else
        echo -e "${RED}Тест БД завершился с ошибкой${NC}"
        ((TOTAL_FAIL++))
    fi
    echo ""
fi

# --- Диагностика ---
if $RUN_DEBUG; then
    echo -e "${YELLOW}>>> Диагностика системы${NC}"
    echo ""

    if bash "$DIR/debug.sh"; then
        echo ""
    else
        echo -e "${RED}Диагностика завершилась с ошибкой${NC}"
        ((TOTAL_FAIL++))
    fi
    echo ""
fi

# --- Тест API ---
if $RUN_API; then
    echo -e "${YELLOW}>>> Тест REST API${NC}"
    echo ""

    # Проверяем доступность сервера
    echo -n "  Проверка доступности ${APP_URL} ... "
    if curl -s -o /dev/null -w "%{http_code}" "${APP_URL}/api/auth/me" 2>/dev/null | grep -q "200"; then
        echo -e "${GREEN}OK${NC}"
    else
        echo -e "${RED}СЕРВЕР НЕ ДОСТУПЕН${NC}"
        echo "  Убедитесь, что контейнеры запущены:"
        echo "    docker-compose up -d"
        echo "  Или укажите другой URL:"
        echo "    php tests/test_api.php http://your-host:port"
        echo ""
        echo -e "${RED}Тест API пропущен${NC}"
        ((TOTAL_FAIL++))
    fi

    if php "$DIR/test_api.php" "$APP_URL"; then
        echo ""
    else
        echo -e "${RED}Тест API завершился с ошибкой${NC}"
        ((TOTAL_FAIL++))
    fi
    echo ""
fi

# --- Итоги ---
echo -e "${BLUE}============================================================${NC}"
if [ $TOTAL_FAIL -eq 0 ]; then
    echo -e "${GREEN}  ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО${NC}"
else
    echo -e "${RED}  ТЕСТЫ ЗАВЕРШЕНЫ С ОШИБКАМИ (${TOTAL_FAIL})${NC}"
fi
echo -e "${BLUE}============================================================${NC}"

exit $TOTAL_FAIL