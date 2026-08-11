#!/bin/bash
set -e

# Ждем готовности MySQL
echo "Ожидание MySQL ($DB_HOST:$DB_PORT)..."
timeout=60
while ! mysqladmin ping -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" --silent 2>/dev/null; do
    sleep 2
    timeout=$((timeout - 2))
    if [ $timeout -le 0 ]; then
        echo "ОШИБКА: MySQL не доступен. Проверьте настройки подключения."
        echo "  DB_HOST=$DB_HOST"
        echo "  DB_PORT=${DB_PORT:-3306}"
        echo "  DB_USER=$DB_USER"
        exit 1
    fi
done
echo "MySQL готов."

# Создаем БД, если не существует
mysql -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;" 2>/dev/null

# Импортируем схему (только если таблиц еще нет)
TABLE_COUNT=$(mysql -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'" 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" -eq "0" ]; then
    echo "Первичная инициализация базы данных..."
    mysql -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < /var/www/html/database/schema.sql
    echo "База данных инициализирована."
else
    echo "База данных уже содержит таблицы ($TABLE_COUNT шт.), пропускаем импорт схемы."
fi

# Обновляем конфиг подключения к БД из переменных окружения
cat > /var/www/html/api/config.php <<'PHPEOF'
<?php
/**
 * Конфигурация подключения к БД и общие настройки
 * Значения подставляются из переменных окружения Docker
 */

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'vshpm_edu');
define('DB_USER', getenv('DB_USER') ?: 'vshpm');
define('DB_PASS', getenv('DB_PASS') ?: 'vshpm_secret');
define('DB_CHARSET', 'utf8');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('EXPORT_DIR', __DIR__ . '/../exports/');

// Часовой пояс
date_default_timezone_set('Europe/Moscow');

// CORS и заголовки
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

/**
 * Получить PDO соединение
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

/**
 * Проверка авторизации
 */
function requireAuth(): void {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Требуется авторизация']);
        exit();
    }
}

/**
 * Проверка роли администратора
 */
function requireAdmin(): void {
    requireAuth();
    if ($_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Доступ запрещен. Требуются права администратора.']);
        exit();
    }
}

/**
 * Получить JSON тело запроса
 */
function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Успешный ответ
 */
function jsonSuccess($data = null, string $message = 'OK'): void {
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Ответ с ошибкой
 */
function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Валидация обязательных полей
 */
function validateRequired(array $data, array $fields): void {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            jsonError("Поле '{$field}' обязательно для заполнения");
        }
    }
}
PHPEOF

echo "========================================"
echo "  Учебный отдел ВШПМ СПбГУПТД"
echo "  Сервер запущен"
echo "  БД: $DB_NAME@$DB_HOST:${DB_PORT:-3306}"
echo "  Учетные данные по умолчанию:"
echo "    Логин:  admin"
echo "    Пароль: admin123"
echo "========================================"

exec "$@"