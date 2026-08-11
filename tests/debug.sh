#!/bin/bash
# ================================================================
#  Диагностика системы — Учебный отдел ВШПМ
#  Проверяет: Docker, контейнеры, БД, PHP, доступность API
# ================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}=== ДИАГНОСТИКА — Учебный отдел ВШПМ ===${NC}"
echo ""

PASS=0
FAIL=0

check() {
    local label="$1"
    shift
    echo -n "  [$label] ... "
    if "$@" &>/dev/null; then
        echo -e "${GREEN}OK${NC}"
        ((PASS++))
    else
        echo -e "${RED}ОШИБКА${NC}"
        ((FAIL++))
    fi
}

# --- Docker ---
echo "--- Docker ---"
check "docker installed" command -v docker
check "docker compose available" docker compose version
check "docker daemon running" docker info

# --- Контейнеры ---
echo ""
echo "--- Контейнеры ---"
check "vshpm-db running" docker ps --format '{{.Names}}' | grep -q vshpm-db
check "vshpm-app running" docker ps --format '{{.Names}}' | grep -q vshpm-app

# --- MySQL ---
echo ""
echo "--- MySQL ---"
check "MySQL port 3306 (внутри Docker)" docker exec vshpm-db mysqladmin ping -h localhost --silent 2>/dev/null
check "MySQL: БД vshpm_edu существует" docker exec vshpm-db mysql -u root -proot_secret -e "USE vshpm_edu; SELECT 1;" 2>/dev/null
check "MySQL: таблица users" docker exec vshpm-db mysql -u root -proot_secret vshpm_edu -e "SELECT COUNT(*) FROM users;" 2>/dev/null
check "MySQL: таблица classrooms" docker exec vshpm-db mysql -u root -proot_secret vshpm_edu -e "SELECT COUNT(*) FROM classrooms;" 2>/dev/null
check "MySQL: таблица teachers" docker exec vshpm-db mysql -u root -proot_secret vshpm_edu -e "SELECT COUNT(*) FROM teachers;" 2>/dev/null
check "MySQL: таблица schedule" docker exec vshpm-db mysql -u root -proot_secret vshpm_edu -e "SELECT COUNT(*) FROM schedule;" 2>/dev/null
check "MySQL: таблица software" docker exec vshpm-db mysql -u root -proot_secret vshpm_edu -e "SELECT COUNT(*) FROM software;" 2>/dev/null

# --- PHP / Apache ---
echo ""
echo "--- PHP / Apache ---"
check "PHP 7.2" docker exec vshpm-app php -v 2>/dev/null | grep -q "7.2"
check "PHP extensions: pdo_mysql" docker exec vshpm-app php -m 2>/dev/null | grep -q pdo_mysql
check "PHP extensions: mbstring" docker exec vshpm-app php -m 2>/dev/null | grep -q mbstring
check "PHP extensions: zip" docker exec vshpm-app php -m 2>/dev/null | grep -q zip
check "Apache mod_rewrite" docker exec vshpm-app apache2ctl -M 2>/dev/null | grep -q rewrite
check "Vendor (PhpSpreadsheet)" docker exec vshpm-app test -f /var/www/html/vendor/autoload.php

# --- API ---
echo ""
echo "--- API ---"

APP_PORT=$(grep APP_PORT .env 2>/dev/null | cut -d= -f2 || echo "8080")
APP_URL="http://localhost:${APP_PORT}"

check "API /api/auth/me" curl -s -o /dev/null -w "%{http_code}" "${APP_URL}/api/auth/me" 2>/dev/null | grep -q "200"
check "API /api/auth/login (401)" curl -s -o /dev/null -w "%{http_code}" -X POST "${APP_URL}/api/auth/login" -H "Content-Type: application/json" -d '{"username":"admin","password":"wrong"}' 2>/dev/null | grep -q "401"

# Попытка логина
LOGIN_RESP=$(curl -s -c /tmp/vshpm_cookie.txt -X POST "${APP_URL}/api/auth/login" -H "Content-Type: application/json" -d '{"username":"admin","password":"admin123"}' 2>/dev/null)
if echo "$LOGIN_RESP" | grep -q '"success":true'; then
    echo -e "  [API login admin] ... ${GREEN}OK${NC}"
    ((PASS++))
else
    echo -e "  [API login admin] ... ${RED}ОШИБКА (пароль изменен?)${NC}"
    ((FAIL++))
fi

check "API /api/teachers" curl -s -b /tmp/vshpm_cookie.txt "${APP_URL}/api/teachers?per_page=1" 2>/dev/null | grep -q '"success":true'
check "API /api/classrooms" curl -s -b /tmp/vshpm_cookie.txt "${APP_URL}/api/classrooms?per_page=1" 2>/dev/null | grep -q '"success":true'
check "API /api/schedule" curl -s -b /tmp/vshpm_cookie.txt "${APP_URL}/api/schedule?per_page=1" 2>/dev/null | grep -q '"success":true'
check "API /api/software" curl -s -b /tmp/vshpm_cookie.txt "${APP_URL}/api/software?per_page=1" 2>/dev/null | grep -q '"success":true'

# --- Файлы ---
echo ""
echo "--- Файлы приложения ---"
check "index.html" docker exec vshpm-app test -f /var/www/html/index.html
check ".htaccess" docker exec vshpm-app test -f /var/www/html/.htaccess
check "database/schema.sql" docker exec vshpm-app test -f /var/www/html/database/schema.sql
check "uploads/ exists" docker exec vshpm-app test -d /var/www/html/uploads
check "exports/ exists" docker exec vshpm-app test -d /var/www/html/exports

# --- Итоги ---
echo ""
echo -e "${YELLOW}============================================${NC}"
echo -e "  Пройдено: ${GREEN}${PASS}${NC} | Ошибок: ${RED}${FAIL}${NC}"
echo -e "${YELLOW}============================================${NC}"

if [ $FAIL -gt 0 ]; then
    echo -e "${RED}Есть проблемы. Проверьте вывод выше.${NC}"
    exit 1
else
    echo -e "${GREEN}Все системы работают нормально.${NC}"
    exit 0
fi