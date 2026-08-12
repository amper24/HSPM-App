#!/bin/bash
set -e

# Установка локали UTF-8 (важно для корректной работы с кириллицей)
export LANG=ru_RU.UTF-8
export LC_ALL=ru_RU.UTF-8

# Ждем готовности MySQL
echo "Ожидание MySQL ($DB_HOST:$DB_PORT)..."
timeout=60
while ! mysqladmin ping -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" --silent --default-character-set=utf8 2>/dev/null; do
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
mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;" 2>/dev/null

# Импортируем схему (только если таблиц еще нет)
TABLE_COUNT=$(mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'" 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" -eq "0" ]; then
    echo "Первичная инициализация базы данных..."
    mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < /var/www/html/database/schema.sql
    echo "База данных инициализирована."

    # Устанавливаем пароль администратора (по умолчанию admin123)
    export ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin123}"
    php -r '
        $dsn = "mysql:host=" . getenv("DB_HOST") . ";dbname=" . getenv("DB_NAME") . ";charset=utf8";
        $pdo = new PDO($dsn, getenv("DB_USER"), getenv("DB_PASS"));
        $hash = password_hash(getenv("ADMIN_PASSWORD") ?: "admin123", PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE username = ?")->execute([$hash, "admin"]);
    '
    echo "Пароль администратора установлен."
else
    echo "База данных уже содержит таблицы ($TABLE_COUNT шт.), пропускаем импорт схемы."
fi

# Миграции: добавляем новые колонки, если их еще нет
echo "Проверка миграций..."
for col in "dedup_key" "classrooms_raw" "discipline" "group_department" "group_code" "teacher_department" "teacher_position" "examiner" "exam_type" "session_start" "session_end"; do
  EXISTS=$(mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='schedule' AND COLUMN_NAME='$col'" 2>/dev/null || echo "0")
  if [ "$EXISTS" -eq "0" ]; then
    case $col in
      dedup_key) AFTER="id"; TYPE="VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'Уникальный ключ записи для защиты от дублей'" ;;
      classrooms_raw) AFTER="classroom_id"; TYPE="VARCHAR(255) DEFAULT NULL COMMENT 'Исходная строка аудиторий'" ;;
      discipline) AFTER="day_of_week"; TYPE="VARCHAR(255) DEFAULT NULL COMMENT 'Дисциплина'" ;;
      group_department) AFTER="discipline"; TYPE="VARCHAR(100) DEFAULT NULL COMMENT 'Кафедра группы'" ;;
      group_code) AFTER="group_department"; TYPE="VARCHAR(50) DEFAULT NULL COMMENT 'Шифр группы'" ;;
      teacher_department) AFTER="group_code"; TYPE="VARCHAR(100) DEFAULT NULL COMMENT 'Кафедра преподавателя'" ;;
      teacher_position) AFTER="teacher_department"; TYPE="VARCHAR(100) DEFAULT NULL COMMENT 'Должность преподавателя'" ;;
      examiner) AFTER="teacher_position"; TYPE="VARCHAR(255) DEFAULT NULL COMMENT 'Экзаменатор'" ;;
      exam_type) AFTER="examiner"; TYPE="VARCHAR(20) DEFAULT NULL COMMENT 'Экзамен/консультация'" ;;
      session_start) AFTER="exam_type"; TYPE="DATE DEFAULT NULL COMMENT 'Начало сессии'" ;;
      session_end) AFTER="session_start"; TYPE="DATE DEFAULT NULL COMMENT 'Конец сессии'" ;;
    esac
    echo "  Добавление колонки $col..."
    mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE schedule ADD COLUMN $col $TYPE AFTER $AFTER;" 2>/dev/null
  fi
done
# Добавление уникальных ключей
for idx in "schedule idx_unique_schedule_dedup" "software idx_unique_software" "teachers idx_unique_fio"; do
  tbl="${idx%% *}"
  idxname="${idx##* }"
  EXISTS=$(mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='$tbl' AND INDEX_NAME='$idxname'" 2>/dev/null || echo "0")
  if [ "$EXISTS" -eq "0" ]; then
    echo "  Добавление уникального ключа $idxname в $tbl..."
    case $tbl in
      schedule)
        case $idxname in
          idx_unique_schedule_dedup)
            # Заполняем dedup_key для существующих записей (по содержимому)
            mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "UPDATE schedule SET dedup_key = SUBSTRING(MD5(CONCAT_WS('|', IFNULL(date, ''), IFNULL(time_start, ''), IFNULL(discipline, ''), IFNULL(group_code, ''), IFNULL(examiner, ''), IFNULL(classroom_id, ''))), 1, 64);" 2>/dev/null
            # Удаляем дубликаты по dedup_key
            mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DELETE s1 FROM schedule s1 INNER JOIN schedule s2 ON s1.dedup_key = s2.dedup_key AND s1.id > s2.id;" 2>/dev/null
            mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE schedule ADD UNIQUE KEY idx_unique_schedule_dedup (dedup_key);" 2>/dev/null
            ;;
        esac
        ;;
      software)
        mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DELETE s1 FROM software s1 INNER JOIN software s2 ON s1.room_number = s2.room_number AND s1.building = s2.building AND s1.name = s2.name AND s1.id > s2.id;" 2>/dev/null
        mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE software ADD UNIQUE KEY idx_unique_software (room_number, building, name);" 2>/dev/null
        ;;
      teachers)
        mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DELETE t1 FROM teachers t1 INNER JOIN teachers t2 ON t1.last_name = t2.last_name AND t1.first_name = t2.first_name AND t1.middle_name <=> t2.middle_name AND t1.id > t2.id;" 2>/dev/null
        mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE teachers ADD UNIQUE KEY idx_unique_fio (last_name, first_name, middle_name);" 2>/dev/null
        ;;
    esac
  fi
done

# Таблица связи расписания с несколькими аудиториями
SC_EXISTS=$(mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='schedule_classrooms'" 2>/dev/null || echo "0")
if [ "$SC_EXISTS" -eq "0" ]; then
    echo "  Создание таблицы schedule_classrooms..."
    mysql --default-character-set=utf8 -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "CREATE TABLE IF NOT EXISTS schedule_classrooms (
      id INT(11) NOT NULL AUTO_INCREMENT,
      schedule_id INT(11) NOT NULL,
      classroom_id INT(11) NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY idx_sched_classroom (schedule_id, classroom_id),
      KEY idx_classroom (classroom_id),
      CONSTRAINT fk_sc_schedule FOREIGN KEY (schedule_id) REFERENCES schedule(id) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT fk_sc_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" 2>/dev/null
fi

echo "Миграции проверены."

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

// CORS и заголовки (только для веб-запросов, не CLI)
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    session_start();
}

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
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
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
echo "  Кодировка: UTF-8 (ru_RU.UTF-8)"
echo "========================================"
echo ""
echo "  Доступ:"
echo "    URL:    http://localhost:${APP_PORT:-8080}"
echo "    Логин:  admin"
echo "    Пароль: ${ADMIN_PASSWORD:-admin123}"
echo ""
echo "  Управление из консоли:"
echo "    docker exec vshpm-app php /var/www/html/hspm-admin help"
echo "    docker exec vshpm-app php /var/www/html/hspm-admin info"
echo "    docker exec vshpm-app php /var/www/html/hspm-admin stats"
echo "    docker exec vshpm-app php /var/www/html/hspm-admin reset-password"
echo "========================================"

exec "$@"