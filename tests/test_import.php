<?php
/**
 * Тест импорта: парсинг ФИО, поиск преподавателей, аудиторий, защита от дублирования
 *
 * Запуск: php tests/test_import.php
 */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/import.php';

echo str_repeat('=', 60) . "\n";
echo "  ТЕСТ ИМПОРТА — Учебный отдел ВШПМ\n";
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

// ===== 1. Парсинг ФИО =====
echo "--- Парсинг ФИО ---\n";

test('parseFIO: полное ФИО (три слова)', function () {
    $result = parseFIO('Воробьев Дмитрий Дмитриевич');
    if ($result[0] !== 'Воробьев') throw new Exception("Фамилия: {$result[0]}, ожидалась 'Воробьев'");
    if ($result[1] !== 'Дмитрий') throw new Exception("Имя: {$result[1]}, ожидалось 'Дмитрий'");
    if ($result[2] !== 'Дмитриевич') throw new Exception("Отчество: {$result[2]}, ожидалось 'Дмитриевич'");
});

test('parseFIO: только фамилия и имя', function () {
    $result = parseFIO('Иванов Иван');
    if ($result[0] !== 'Иванов') throw new Exception("Фамилия: {$result[0]}, ожидалась 'Иванов'");
    if ($result[1] !== 'Иван') throw new Exception("Имя: {$result[1]}, ожидалось 'Иван'");
    if ($result[2] !== '') throw new Exception("Отчество должно быть пустым, получено '{$result[2]}'");
});

test('parseFIO: двойная фамилия', function () {
    $result = parseFIO('Салтыков-Щедрин Михаил Евграфович');
    if ($result[0] !== 'Салтыков-Щедрин') throw new Exception("Фамилия: {$result[0]}");
    if ($result[1] !== 'Михаил') throw new Exception("Имя: {$result[1]}");
    if ($result[2] !== 'Евграфович') throw new Exception("Отчество: {$result[2]}");
});

test('parseFIO: с лишними пробелами', function () {
    $result = parseFIO('  Петров   Петр  Петрович  ');
    if ($result[0] !== 'Петров') throw new Exception("Фамилия: {$result[0]}");
    if ($result[1] !== 'Петр') throw new Exception("Имя: {$result[1]}");
    if ($result[2] !== 'Петрович') throw new Exception("Отчество: {$result[2]}");
});

// ===== 2. Нормализация =====
echo "\n--- Нормализация ---\n";

test('normalizeEmploymentType: штатный', function () {
    $result = normalizeEmploymentType('штат.');
    if ($result !== 'штатный') throw new Exception("Получено '{$result}', ожидалось 'штатный'");
});

test('normalizeEmploymentType: внешний совместитель', function () {
    $result = normalizeEmploymentType('внеш. совм.');
    if ($result !== 'внешний совместитель') throw new Exception("Получено '{$result}', ожидалось 'внешний совместитель'");
});

test('normalizeEmploymentType: внутренний совместитель', function () {
    $result = normalizeEmploymentType('внут. совм.');
    if ($result !== 'внутренний совместитель') throw new Exception("Получено '{$result}', ожидалось 'внутренний совместитель'");
});

test('normalizeEmploymentType: ГПХ', function () {
    $result = normalizeEmploymentType('гпх');
    if ($result !== 'ГПХ') throw new Exception("Получено '{$result}', ожидалось 'ГПХ'");
});

test('normalizeEmploymentType: почасовая', function () {
    $result = normalizeEmploymentType('почасовая');
    if ($result !== 'ГПХ') throw new Exception("Получено '{$result}', ожидалось 'ГПХ'");
});

test('normalizeDepartment: ЖиМ СМИ', function () {
    $result = normalizeDepartment('ЖиМ СМИ');
    if ($result !== 'ЖиМ СМИ') throw new Exception("Получено '{$result}'");
});

test('normalizeDepartment: с посторонним текстом', function () {
    $result = normalizeDepartment('каф. КиКТ');
    if ($result !== 'КиКТ') throw new Exception("Получено '{$result}', ожидалось 'КиКТ'");
});

test('normalizeKey: приведение к нижнему регистру', function () {
    $result = normalizeKey('Воробьев Дмитрий Дмитриевич');
    if ($result !== 'воробьев дмитрий дмитриевич') throw new Exception("Получено '{$result}'");
});

// ===== 2.1 Распознавание заголовков расписания =====
echo "\n--- Распознавание заголовков ---\n";

test('mapScheduleHeader: Экзаменатор → examiner', function () {
    if (mapScheduleHeader('Экзаменатор') !== 'examiner') throw new Exception("Неверно распознан заголовок");
});
test('mapScheduleHeader: Преподаватель → examiner', function () {
    if (mapScheduleHeader('Преподаватель') !== 'examiner') throw new Exception("Неверно распознан заголовок");
});
test('mapScheduleHeader: ФИО преподавателя → examiner', function () {
    if (mapScheduleHeader('ФИО преподавателя') !== 'examiner') throw new Exception("Неверно распознан заголовок");
});
test('mapScheduleHeader: Экзамен/консультация → exam_type', function () {
    if (mapScheduleHeader('Экзамен/консультация') !== 'exam_type') throw new Exception("Неверно распознан заголовок: " . mapScheduleHeader('Экзамен/консультация'));
});
test('mapScheduleHeader: Вид занятия → lesson_type', function () {
    if (mapScheduleHeader('Вид занятия') !== 'lesson_type') throw new Exception("Неверно распознан заголовок: " . mapScheduleHeader('Вид занятия'));
});
test('mapScheduleHeader: Тип занятия → lesson_type', function () {
    if (mapScheduleHeader('Тип занятия') !== 'lesson_type') throw new Exception("Неверно распознан заголовок: " . mapScheduleHeader('Тип занятия'));
});
test('mapScheduleHeader: Дисциплина → discipline', function () {
    if (mapScheduleHeader('Дисциплина') !== 'discipline') throw new Exception("Неверно распознан заголовок");
});
test('mapScheduleHeader: Аудитория → classroom', function () {
    if (mapScheduleHeader('Аудитория') !== 'classroom') throw new Exception("Неверно распознан заголовок");
});
test('mapScheduleHeader: Дата → date', function () {
    if (mapScheduleHeader('Дата') !== 'date') throw new Exception("Неверно распознан заголовок");
});
test('mapScheduleHeader: Группа → group_code', function () {
    if (mapScheduleHeader('Группа') !== 'group_code') throw new Exception("Неверно распознан заголовок");
});
test('mapScheduleHeader: пустой → null', function () {
    if (mapScheduleHeader('') !== null) throw new Exception("Пустой заголовок должен быть null");
});

// ===== 3. Поиск преподавателя (требует данных в БД) =====
echo "\n--- Поиск преподавателей (БД) ---\n";

test('Подготовка: добавление тестового преподавателя', function () {
    $pdo = getDB();
    $pdo->exec("INSERT IGNORE INTO teachers (last_name, first_name, middle_name, department) 
        VALUES ('Воробьев', 'Дмитрий', 'Дмитриевич', 'КиКТ')");
});

test('findTeacherByShortName: Воробьев Д.Д. (инициалы)', function () {
    $pdo = getDB();
    $id = findTeacherByShortName($pdo, 'Воробьев Д.Д.');
    if ($id === null) throw new Exception("Не найден преподаватель 'Воробьев Д.Д.'");
    // Проверяем, что это действительно Воробьев
    $st = $pdo->prepare("SELECT last_name, first_name, middle_name FROM teachers WHERE id = :id");
    $st->execute(['id' => $id]);
    $t = $st->fetch();
    if ($t['last_name'] !== 'Воробьев') throw new Exception("Найден {$t['last_name']}, а не Воробьев");
});

test('findTeacherByShortName: Воробьев Д (только первая буква имени)', function () {
    $pdo = getDB();
    $id = findTeacherByShortName($pdo, 'Воробьев Д');
    if ($id === null) throw new Exception("Не найден преподаватель 'Воробьев Д'");
});

test('findTeacherByShortName: Воробьев Дмитрий Дмитриевич (полное)', function () {
    $pdo = getDB();
    $id = findTeacherByShortName($pdo, 'Воробьев Дмитрий Дмитриевич');
    if ($id === null) throw new Exception("Не найден преподаватель с полным ФИО");
});

test('findTeacherByShortName: несуществующий', function () {
    $pdo = getDB();
    $id = findTeacherByShortName($pdo, 'Ктулху З.О.');
    if ($id !== null) throw new Exception("Несуществующий преподаватель вернул id={$id}");
});

test('findTeacherByShortName: пустая строка', function () {
    $pdo = getDB();
    $id = findTeacherByShortName($pdo, '');
    if ($id !== null) throw new Exception("Пустая строка вернула id={$id}");
});

// ===== 4. Поиск аудиторий =====
echo "\n--- Поиск аудиторий (БД) ---\n";

test('Подготовка: добавление тестовой аудитории', function () {
    $pdo = getDB();
    $pdo->exec("INSERT IGNORE INTO classrooms (room_number, building, room_type) 
        VALUES ('331', 'Д', 'лекционная аудитория'),
               ('202', 'В', 'лаборатория')");
});

test('findClassroomByRoom: Д331', function () {
    $pdo = getDB();
    $id = findClassroomByRoom($pdo, 'Д331');
    if ($id === null) throw new Exception("Аудитория Д331 не найдена");
});

test('findClassroomByRoom: д331 (нижний регистр)', function () {
    $pdo = getDB();
    $id = findClassroomByRoom($pdo, 'д331');
    if ($id === null) throw new Exception("Аудитория д331 не найдена (нижний регистр)");
});

test('findClassroomByRoom: 331 (без корпуса)', function () {
    $pdo = getDB();
    $id = findClassroomByRoom($pdo, '331');
    if ($id === null) throw new Exception("Аудитория 331 не найдена (без корпуса)");
});

test('findClassroomByRoom: В202', function () {
    $pdo = getDB();
    $id = findClassroomByRoom($pdo, 'В202');
    if ($id === null) throw new Exception("Аудитория В202 не найдена");
});

test('findClassroomByRoom: "нет" → null', function () {
    $pdo = getDB();
    $id = findClassroomByRoom($pdo, 'нет');
    if ($id !== null) throw new Exception("Для 'нет' должно вернуться null, получено id={$id}");
});

test('findClassroomByRoom: несуществующая', function () {
    $pdo = getDB();
    $id = findClassroomByRoom($pdo, 'Д9999');
    if ($id !== null) throw new Exception("Несуществующая аудитория вернула id={$id}");
});

// ===== 4.1 Парсинг аудиторий =====
echo "\n--- Парсинг аудиторий ---\n";

test('parseClassrooms: ДО', function () {
    $r = parseClassrooms('ДО');
    if (count($r) !== 1 || $r[0]['building'] !== null || $r[0]['room_number'] !== 'ДО') throw new Exception("ДО не распознано: " . json_encode($r));
});
test('parseClassrooms: одна аудитория Д234', function () {
    $r = parseClassrooms('Д234');
    if (count($r) !== 1 || $r[0]['building'] !== 'Д' || $r[0]['room_number'] !== '234') throw new Exception("Д234 неверно: " . json_encode($r));
});
test('parseClassrooms: несколько аудиторий Д234 Д237', function () {
    $r = parseClassrooms('Д234 Д237');
    if (count($r) !== 2 || $r[0]['room_number'] !== '234' || $r[1]['room_number'] !== '237') throw new Exception("Неверно: " . json_encode($r));
});
test('parseClassrooms: через запятую', function () {
    $r = parseClassrooms('Д234, Д237');
    if (count($r) !== 2) throw new Exception("Ожидалось 2, получено " . count($r));
});
test('parseClassrooms: номер без корпуса → корпус Д', function () {
    $r = parseClassrooms('234');
    if (count($r) !== 1 || $r[0]['building'] !== 'Д' || $r[0]['room_number'] !== '234') throw new Exception("Неверно: " . json_encode($r));
});

// ===== 4.2 Сохранение связи schedule_classrooms =====
echo "\n--- Связь schedule → classrooms ---\n";

test('saveScheduleClassrooms: записывает несколько аудиторий', function () {
    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT IGNORE INTO classrooms (room_number, building, room_type) VALUES ('234', 'Д', 'тест'), ('237', 'Д', 'тест')");
        $rows = parseClassrooms('Д234 Д237');
        $pdo->exec("INSERT INTO schedule (dedup_key, discipline) VALUES ('sc-test', 'Тест связи аудиторий')");
        $sid = (int)$pdo->lastInsertId();
        saveScheduleClassrooms($pdo, $sid, $rows);

        $cnt = $pdo->query("SELECT COUNT(*) FROM schedule_classrooms WHERE schedule_id = {$sid}")->fetchColumn();
        if ((int)$cnt !== 2) throw new Exception("Ожидалось 2 связи, получено {$cnt}");
    } finally {
        $pdo->rollBack();
    }
});

// ===== 5. Защита от дублирования данных =====
echo "\n--- Защита от дублирования ---\n";

test('teachers: ON DUPLICATE KEY UPDATE не создает дубль', function () {
    $pdo = getDB();
    // Пытаемся вставить того же преподавателя повторно
    $stmt = $pdo->prepare("INSERT INTO teachers (last_name, first_name, middle_name, department)
        VALUES (:ln, :fn, :mn, :dep)
        ON DUPLICATE KEY UPDATE department=VALUES(department)");
    $stmt->execute(['ln' => 'Воробьев', 'fn' => 'Дмитрий', 'mn' => 'Дмитриевич', 'dep' => 'КиКТ']);
    
    // Считаем количество записей
    $cnt = $pdo->query("SELECT COUNT(*) FROM teachers WHERE last_name='Воробьев' AND first_name='Дмитрий' AND middle_name='Дмитриевич'")->fetchColumn();
    if ((int)$cnt !== 1) throw new Exception("Дубликат создан! Записей: {$cnt}, ожидалась 1");
});

test('schedule: уникальный ключ не дает вставить дубль', function () {
    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        // Создаем тестовые данные
        $pdo->exec("INSERT IGNORE INTO classrooms (room_number, building, room_type) VALUES ('TESTDUP', 'Д', 'тест')");
        $cid = $pdo->query("SELECT id FROM classrooms WHERE room_number='TESTDUP'")->fetchColumn();
        
        // Первая вставка
        $dedup = scheduleDedupKey(['date' => '2026-01-01', 'time_start' => '09:00:00', 'discipline' => 'Тестовая дисциплина', 'group_code' => 'ТСТ-1', 'examiner' => 'Воробьев Д.Д.'], '');
        $stmt = $pdo->prepare("INSERT INTO schedule (dedup_key, classroom_id, date, time_start, discipline, group_code, examiner, is_occupied)
            VALUES (:dk, :cid, '2026-01-01', '09:00:00', 'Тестовая дисциплина', 'ТСТ-1', 'Воробьев Д.Д.', 1)");
        $stmt->execute(['dk' => $dedup, 'cid' => $cid]);
        
        // Вторая вставка тех же данных → должно быть отклонено уникальным ключом
        $caught = false;
        try {
            $stmt->execute(['dk' => $dedup, 'cid' => $cid]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) $caught = true;
        }
        
        if (!$caught) throw new Exception("Дубликат в schedule не был заблокирован уникальным ключом!");
    } finally {
        $pdo->rollBack();
    }
});

test('classrooms: уникальный ключ (room_number, building)', function () {
    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT INTO classrooms (room_number, building, room_type) VALUES ('UNIQTEST', 'Д', 'тест')");
        $caught = false;
        try {
            $pdo->exec("INSERT INTO classrooms (room_number, building, room_type) VALUES ('UNIQTEST', 'Д', 'тест2')");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) $caught = true;
        }
        if (!$caught) throw new Exception("Дубликат classrooms (UNIQTEST, Д) не заблокирован!");
    } finally {
        $pdo->rollBack();
    }
});

test('software: уникальный ключ (room_number, building, name)', function () {
    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT IGNORE INTO classrooms (room_number, building, room_type) VALUES ('SWTEST', 'Д', 'тест')");
        $pdo->exec("INSERT INTO software (room_number, building, name) VALUES ('SWTEST', 'Д', 'Photoshop')");
        $caught = false;
        try {
            $pdo->exec("INSERT INTO software (room_number, building, name) VALUES ('SWTEST', 'Д', 'Photoshop')");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) $caught = true;
        }
        if (!$caught) throw new Exception("Дубликат ПО не заблокирован!");
    } finally {
        $pdo->rollBack();
    }
});

// ===== 6. Целостность связей =====
echo "\n--- Целостность связей ---\n";

test('schedule.teacher_id → teachers.id (SET NULL при удалении)', function () {
    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        // Добавляем временного препода
        $pdo->exec("INSERT INTO teachers (last_name, first_name, middle_name) VALUES ('ТЕСТСВЯЗЬ', 'Тест', 'Тестович')");
        $tid = $pdo->lastInsertId();
        
        $pdo->exec("INSERT IGNORE INTO classrooms (room_number, building, room_type) VALUES ('LINKTST', 'Д', 'тест')");
        $cid = $pdo->query("SELECT id FROM classrooms WHERE room_number='LINKTST'")->fetchColumn();
        
        // Добавляем запись в расписание с teacher_id
        $pdo->prepare("INSERT INTO schedule (dedup_key, classroom_id, teacher_id, date, time_start, discipline, group_code, examiner, is_occupied)
            VALUES (:dk, :cid, :tid, '2026-06-15', '10:00:00', 'Связи', 'ТСТ-99', 'Тест Тестович', 1)")
            ->execute(['dk' => 'link-test', 'cid' => $cid, 'tid' => $tid]);
        
        // Удаляем преподавателя
        $pdo->prepare("DELETE FROM teachers WHERE id = :id")->execute(['id' => $tid]);
        
        // Проверяем, что teacher_id стал NULL (SET NULL)
        $newTid = $pdo->query("SELECT teacher_id FROM schedule WHERE discipline='Связи'")->fetchColumn();
        if ($newTid !== null) throw new Exception("teacher_id не стал NULL после удаления: {$newTid}");
    } finally {
        $pdo->rollBack();
    }
});

// ===== 7. Очистка тестовых данных =====
echo "\n--- Очистка ---\n";

test('Удаление тестового преподавателя', function () {
    $pdo = getDB();
    $pdo->exec("DELETE FROM teachers WHERE last_name='Воробьев' AND first_name='Дмитрий'");
});

test('Удаление тестовых аудиторий', function () {
    $pdo = getDB();
    $pdo->exec("DELETE FROM classrooms WHERE room_number IN ('331', '202', 'TESTDUP', 'UNIQTEST', 'SWTEST', 'LINKTST')");
});

test('Удаление тестового ПО', function () {
    $pdo = getDB();
    $pdo->exec("DELETE FROM software WHERE room_number IN ('SWTEST')");
});

test('Удаление тестового расписания', function () {
    $pdo = getDB();
    $pdo->exec("DELETE FROM schedule WHERE discipline IN ('Тестовая дисциплина', 'Связи')");
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