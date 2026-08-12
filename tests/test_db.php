<?php
/**
 * Тест базы данных
 * Проверяет: подключение, наличие таблиц, структуру, внешние ключи, индексы
 *
 * Запуск: php tests/test_db.php
 */

echo str_repeat('=', 60) . "\n";
echo "  ТЕСТ БАЗЫ ДАННЫХ — Учебный отдел ВШПМ\n";
echo str_repeat('=', 60) . "\n\n";

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void {
    global $passed, $failed;
    echo "  [TEST] {$name} ... ";
    try {
        $fn();
        echo "\033[32mOK\033[0m\n";
        $passed++;
    } catch (Exception $e) {
        echo "\033[31mFAIL: {$e->getMessage()}\033[0m\n";
        $failed++;
    }
}

// ===== Подключение к БД =====
require_once __DIR__ . '/../api/config.php';

echo "--- Подключение ---\n";

test('Подключение к MySQL', function () {
    $pdo = getDB();
    $pdo->query('SELECT 1');
});

test('База данных vshpm_edu существует', function () {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT DATABASE()");
    $db = $stmt->fetchColumn();
    if ($db !== 'vshpm_edu') {
        throw new Exception("Текущая БД: {$db}, ожидалась vshpm_edu");
    }
});

// ===== Проверка таблиц =====
echo "\n--- Таблицы ---\n";

$requiredTables = ['users', 'classrooms', 'teachers', 'schedule', 'software'];

test('Наличие всех таблиц', function () use ($requiredTables) {
    $pdo = getDB();
    $stmt = $pdo->query("SHOW TABLES");
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $missing = array_diff($requiredTables, $existing);
    if (!empty($missing)) {
        throw new Exception("Отсутствуют таблицы: " . implode(', ', $missing));
    }
});

// ===== Проверка структуры таблиц =====
echo "\n--- Структура ---\n";

$expectedColumns = [
    'users'      => ['id', 'username', 'password', 'role', 'full_name', 'created_at', 'updated_at'],
    'classrooms' => ['id', 'room_number', 'building', 'room_type', 'software_installed', 'seats', 'has_projector', 'has_speakers', 'computers_count', 'created_at', 'updated_at'],
    'teachers'   => ['id', 'last_name', 'first_name', 'middle_name', 'position', 'degree', 'title', 'department', 'employment_type', 'email', 'phone', 'notes', 'created_at', 'updated_at'],
    'schedule'   => ['id', 'dedup_key', 'classroom_id', 'teacher_id', 'numerator_denominator', 'date', 'day_of_week', 'discipline', 'group_department', 'group_code', 'teacher_department', 'teacher_position', 'examiner', 'exam_type', 'session_start', 'session_end', 'pair_number', 'time_start', 'time_end', 'is_nonstandard_time', 'lesson_type', 'is_occupied', 'transfer_cancel', 'created_at', 'updated_at'],
    'software'   => ['id', 'classroom_id', 'room_number', 'building', 'name', 'notes', 'created_at', 'updated_at'],
];

foreach ($expectedColumns as $table => $cols) {
    test("Колонки таблицы '{$table}'", function () use ($table, $cols) {
        $pdo = getDB();
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $missing = array_diff($cols, $existing);
        if (!empty($missing)) {
            throw new Exception("В таблице '{$table}' не хватает колонок: " . implode(', ', $missing));
        }
    });
}

// ===== Проверка ENUM значений =====
echo "\n--- ENUM валидация ---\n";

test("Тип колонки 'building' в classrooms", function () {
    $pdo = getDB();
    $stmt = $pdo->query("SHOW COLUMNS FROM classrooms LIKE 'building'");
    $col = $stmt->fetch();
    if ($col['Type'] !== 'varchar(5)') {
        throw new Exception("Тип {$col['Type']}, ожидался varchar(5)");
    }
});

test("Тип колонки 'department' в teachers", function () {
    $pdo = getDB();
    $stmt = $pdo->query("SHOW COLUMNS FROM teachers LIKE 'department'");
    $col = $stmt->fetch();
    if ($col['Type'] !== 'varchar(100)') {
        throw new Exception("Тип {$col['Type']}, ожидался varchar(100)");
    }
});

// ===== Проверка внешних ключей =====
echo "\n--- Внешние ключи ---\n";

test("FK schedule → classrooms", function () {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_NAME = 'schedule' AND COLUMN_NAME = 'classroom_id'
          AND REFERENCED_TABLE_NAME = 'classrooms'
    ");
    if (!$stmt->fetch()) {
        throw new Exception("FK schedule.classroom_id → classrooms.id не найден");
    }
});

test("FK schedule → teachers", function () {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_NAME = 'schedule' AND COLUMN_NAME = 'teacher_id'
          AND REFERENCED_TABLE_NAME = 'teachers'
    ");
    if (!$stmt->fetch()) {
        throw new Exception("FK schedule.teacher_id → teachers.id не найден");
    }
});

// ===== Проверка индексов =====
echo "\n--- Индексы ---\n";

$expectedIndexes = [
    'classrooms' => ['idx_building', 'idx_room_type'],
    'teachers'   => ['idx_last_name', 'idx_department', 'idx_employment_type', 'idx_unique_fio'],
    'schedule'   => ['idx_date', 'idx_transfer_cancel', 'idx_is_occupied', 'idx_unique_schedule_dedup'],
    'software'   => ['idx_building'],
];

foreach ($expectedIndexes as $table => $indexes) {
    foreach ($indexes as $idx) {
        test("Индекс '{$idx}' в таблице '{$table}'", function () use ($table, $idx) {
            $pdo = getDB();
            $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$idx}'");
            if (!$stmt->fetch()) {
                throw new Exception("Индекс '{$idx}' отсутствует в таблице '{$table}'");
            }
        });
    }
}

// ===== Проверка администратора по умолчанию =====
echo "\n--- Данные ---\n";

test('Администратор по умолчанию существует', function () {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, role FROM users WHERE username = 'admin'");
    $admin = $stmt->fetch();
    if (!$admin) {
        throw new Exception("Пользователь 'admin' не найден");
    }
    if ($admin['role'] !== 'admin') {
        throw new Exception("У пользователя 'admin' роль не admin: {$admin['role']}");
    }
});

// ===== Проверка целостности (CASCADE) =====
echo "\n--- Целостность данных ---\n";

test('Каскадное удаление: schedule → classrooms', function () {
    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        // Создаем тестовую аудиторию
        $pdo->exec("INSERT INTO classrooms (room_number, building, room_type) VALUES ('TEST999', 'Д', 'лекционная аудитория')");
        $classroomId = $pdo->lastInsertId();

        // Создаем запись расписания
        $pdo->prepare("INSERT INTO schedule (dedup_key, classroom_id, date) VALUES ('cascade-test', :cid, CURDATE())")
            ->execute(['cid' => $classroomId]);

        // Удаляем аудиторию
        $pdo->prepare("DELETE FROM classrooms WHERE id = :id")->execute(['id' => $classroomId]);

        // Проверяем, что запись расписания тоже удалена
        $stmt = $pdo->query("SELECT COUNT(*) FROM schedule WHERE classroom_id = {$classroomId}");
        $count = (int)$stmt->fetchColumn();

        if ($count > 0) {
            throw new Exception("Записей schedule осталось: {$count}, ожидалось 0 — CASCADE не сработал");
        }
    } finally {
        $pdo->rollBack();
    }
});

// ===== Итоги =====
echo "\n" . str_repeat('=', 60) . "\n";
echo "  ИТОГО: пройдено {$passed}, провалено {$failed}\n";
echo str_repeat('=', 60) . "\n";

if ($failed > 0) {
    echo "\033[31mТЕСТЫ ПРОВАЛЕНЫ: {$failed} ошибок\033[0m\n";
    exit(1);
} else {
    echo "\033[32mВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО ({$passed} тестов)\033[0m\n";
    exit(0);
}