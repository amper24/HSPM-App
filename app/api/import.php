<?php
/**
 * API импорта данных из Excel
 * POST /api/import/teachers   — импорт преподавателей
 * POST /api/import/classrooms — импорт аудиторий
 * POST /api/import/schedule   — импорт расписания
 * POST /api/import/software   — импорт ПО
 *
 * Требуется библиотека PhpSpreadsheet (phpoffice/phpspreadsheet)
 * Установка: composer require phpoffice/phpspreadsheet
 */

function handleImport(string $method, string $action): void {
    requireAdmin();

    if ($method !== 'POST') {
        jsonError('Метод не поддерживается', 405);
    }

    switch ($action) {
        case 'teachers':
            importTeachers();
            break;
        case 'classrooms':
            importClassrooms();
            break;
        case 'schedule':
            importSchedule();
            break;
        case 'software':
            importSoftware();
            break;
        default:
            jsonError('Тип импорта не поддерживается: ' . $action, 404);
    }
}

/**
 * Загрузка файла из запроса
 */
function getUploadedFile(): string {
    if (empty($_FILES['file'])) {
        jsonError('Файл не загружен');
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonError('Ошибка загрузки файла: ' . $file['error']);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls', 'xlsx', 'csv'])) {
        jsonError('Поддерживаются только файлы Excel (.xls, .xlsx) и CSV');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $tmpPath = UPLOAD_DIR . uniqid('import_') . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
        jsonError('Не удалось сохранить загруженный файл');
    }

    return $tmpPath;
}

/**
 * Чтение Excel/CSV файла и возврат массива строк
 */
function readSpreadsheet(string $filePath): array {
    // Проверяем наличие PhpSpreadsheet
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        jsonError('Библиотека PhpSpreadsheet не установлена. Выполните: composer require phpoffice/phpspreadsheet');
    }

    require_once $autoloadPath;

    try {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            $reader->setDelimiter(';');
            $reader->setInputEncoding('CP1251');
        } else {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
        }

        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        return $worksheet->toArray(null, true, true, true);
    } catch (Exception $e) {
        jsonError('Ошибка чтения файла: ' . $e->getMessage());
    }
}

function importTeachers(): void {
    $filePath = getUploadedFile();
    $rows = readSpreadsheet($filePath);
    unlink($filePath);

    $db = getDB();
    $imported = 0;
    $skipped = 0;
    $errors = [];

    // Пропускаем заголовок (первая строка), если определена
    $startRow = 1;
    $firstRow = $rows[1] ?? [];
    $firstCell = is_array($firstRow) ? trim((string)reset($firstRow)) : '';
    if (mb_stripos($firstCell, 'фамилия') !== false || mb_stripos($firstCell, 'ФИО') !== false) {
        $startRow = 2;
    }

    for ($i = $startRow; $i <= count($rows); $i++) {
        $row = array_values($rows[$i]);
        // Убираем пустые значения в конце, если строка короче
        $lastName = trim((string)($row[0] ?? ''));
        $firstName = trim((string)($row[1] ?? ''));
        $middleName = trim((string)($row[2] ?? ''));

        if (empty($lastName) && empty($firstName)) {
            continue; // Пропускаем пустые строки
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO teachers (last_name, first_name, middle_name, position, degree, title, department, employment_type, email, phone, notes)
                VALUES (:ln, :fn, :mn, :pos, :deg, :ttl, :dep, :emp, :eml, :phn, :nts)
            ");
            $stmt->execute([
                'ln'  => $lastName,
                'fn'  => $firstName,
                'mn'  => $middleName ?: null,
                'pos' => !empty($row[3]) ? trim((string)$row[3]) : null,
                'deg' => !empty($row[4]) ? trim((string)$row[4]) : null,
                'ttl' => !empty($row[5]) ? trim((string)$row[5]) : null,
                'dep' => !empty($row[6]) ? trim((string)$row[6]) : null,
                'emp' => !empty($row[7]) ? trim((string)$row[7]) : null,
                'eml' => !empty($row[8]) ? trim((string)$row[8]) : null,
                'phn' => !empty($row[9]) ? trim((string)$row[9]) : null,
                'nts' => !empty($row[10]) ? trim((string)$row[10]) : null,
            ]);
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "Строка {$i}: {$e->getMessage()}";
            $skipped++;
        }
    }

    jsonSuccess(compact('imported', 'skipped', 'errors'), "Импортировано преподавателей: {$imported}");
}

function importClassrooms(): void {
    $filePath = getUploadedFile();
    $rows = readSpreadsheet($filePath);
    unlink($filePath);

    $db = getDB();
    $imported = 0;
    $updated = 0;
    $errors = [];

    $startRow = 1;
    $firstRow = $rows[1] ?? [];
    $firstCell = is_array($firstRow) ? trim((string)reset($firstRow)) : '';
    if (mb_stripos($firstCell, 'номер') !== false || mb_stripos($firstCell, 'ауд') !== false) {
        $startRow = 2;
    }

    for ($i = $startRow; $i <= count($rows); $i++) {
        $row = array_values($rows[$i]);
        $roomNumber = trim((string)($row[0] ?? ''));
        if (empty($roomNumber)) continue;

        try {
            $building = trim((string)($row[1] ?? 'Д'));
            // Нормализация корпуса
            if (mb_stripos($building, 'Вознесенский') !== false || mb_stripos($building, 'В') !== false) {
                $building = 'В';
            } else {
                $building = 'Д';
            }

            // Проверка на существование
            $check = $db->prepare('SELECT id FROM classrooms WHERE room_number = :rn AND building = :b');
            $check->execute(['rn' => $roomNumber, 'b' => $building]);

            $data = [
                'rn'  => $roomNumber,
                'b'   => $building,
                'rt'  => !empty($row[2]) ? trim((string)$row[2]) : 'лекционная аудитория',
                'sw'  => !empty($row[3]) ? trim((string)$row[3]) : null,
                'st'  => !empty($row[4]) ? (int)$row[4] : null,
                'pr'  => !empty($row[5]) ? (preg_match('/да|есть|1/i', (string)$row[5]) ? 1 : 0) : 0,
                'sp'  => !empty($row[6]) ? (preg_match('/да|есть|1/i', (string)$row[6]) ? 1 : 0) : 0,
                'pc'  => !empty($row[7]) ? (int)$row[7] : 0,
            ];

            if ($existing = $check->fetch()) {
                // Обновление
                $stmt = $db->prepare("UPDATE classrooms SET room_type=:rt, software_installed=:sw, seats=:st,
                    has_projector=:pr, has_speakers=:sp, computers_count=:pc WHERE id=:id");
                $stmt->execute(array_merge($data, ['id' => $existing['id']]));
                $updated++;
            } else {
                $stmt = $db->prepare("INSERT INTO classrooms (room_number, building, room_type, software_installed, seats, has_projector, has_speakers, computers_count)
                    VALUES (:rn, :b, :rt, :sw, :st, :pr, :sp, :pc)");
                $stmt->execute($data);
                $imported++;
            }
        } catch (Exception $e) {
            $errors[] = "Строка {$i}: {$e->getMessage()}";
        }
    }

    jsonSuccess(compact('imported', 'updated', 'errors'), "Импорт аудиторий: создано {$imported}, обновлено {$updated}");
}

function importSchedule(): void {
    $filePath = getUploadedFile();
    $rows = readSpreadsheet($filePath);
    unlink($filePath);

    $replaceExisting = ($_POST['replace'] ?? 'false') === 'true';

    $db = getDB();
    $imported = 0;
    $errors = [];

    $startRow = 1;
    $firstRow = $rows[1] ?? [];
    $firstCell = is_array($firstRow) ? trim((string)reset($firstRow)) : '';
    if (mb_stripos($firstCell, 'ауд') !== false || mb_stripos($firstCell, 'дата') !== false || mb_stripos($firstCell, 'день') !== false) {
        $startRow = 2;
    }

    try {
        $db->beginTransaction();

        if ($replaceExisting && count($rows) > 1) {
            // Находим диапазон дат
            $dates = [];
            for ($i = $startRow; $i <= min($startRow + 5, count($rows)); $i++) {
                $row = array_values($rows[$i]);
                if (!empty($row[1]) && preg_match('/\d{2}\.\d{2}\.\d{4}/', (string)$row[1])) {
                    $dates[] = date('Y-m-d', strtotime(str_replace('.', '-', $row[1])));
                }
            }
            if ($dates) {
                sort($dates);
                $db->prepare('DELETE FROM schedule WHERE date BETWEEN :from AND :to')
                   ->execute(['from' => $dates[0], 'to' => end($dates)]);
            }
        }

        $classroomCache = [];
        $teacherCache = [];

        for ($i = $startRow; $i <= count($rows); $i++) {
            $row = array_values($rows[$i]);
            $roomNumber = trim((string)($row[0] ?? ''));
            $dateStr = trim((string)($row[1] ?? ''));

            if (empty($roomNumber) || empty($dateStr)) continue;

            // Получаем classroom_id (кэшируем)
            $cacheKey = $roomNumber;
            if (!isset($classroomCache[$cacheKey])) {
                $stmt = $db->prepare('SELECT id FROM classrooms WHERE room_number = :rn LIMIT 1');
                $stmt->execute(['rn' => $roomNumber]);
                $classroomCache[$cacheKey] = $stmt->fetchColumn() ?: null;
            }
            $classroomId = $classroomCache[$cacheKey];

            if (!$classroomId) {
                $errors[] = "Строка {$i}: Аудитория '{$roomNumber}' не найдена в БД";
                continue;
            }

            // Парсинг даты
            $date = date('Y-m-d', strtotime(preg_replace('/(\d{2})\.(\d{2})\.(\d{4})/', '$3-$2-$1', $dateStr)));

            // День недели
            $dayOfWeek = !empty($row[2]) ? trim((string)$row[2]) : null;

            // Пара и время
            $pairNumber = !empty($row[3]) ? (int)$row[3] : null;
            $timeStart = !empty($row[4]) ? trim((string)$row[4]) : null;
            $timeEnd = !empty($row[5]) ? trim((string)$row[5]) : null;

            // Вид занятия
            $lessonType = !empty($row[6]) ? trim((string)$row[6]) : null;

            // Преподаватель
            $teacherName = !empty($row[7]) ? trim((string)$row[7]) : null;
            $teacherId = null;

            if ($teacherName && !isset($teacherCache[$teacherName])) {
                $parts = array_map('trim', explode(' ', $teacherName, 3));
                $stmt = $db->prepare("SELECT id FROM teachers WHERE last_name LIKE :ln AND first_name LIKE :fn LIMIT 1");
                $stmt->execute([
                    'ln' => $parts[0] ?? '',
                    'fn' => $parts[1] ?? '',
                ]);
                $teacherCache[$teacherName] = $stmt->fetchColumn() ?: null;
            }
            $teacherId = $teacherCache[$teacherName] ?? null;

            // Числитель/знаменатель
            $nd = !empty($row[8]) ? trim((string)$row[8]) : null;

            $stmt = $db->prepare("INSERT INTO schedule (classroom_id, teacher_id, numerator_denominator, date, day_of_week,
                pair_number, time_start, time_end, lesson_type, is_occupied, transfer_cancel)
                VALUES (:cid, :tid, :nd, :d, :dow, :pn, :ts, :te, :lt, 1, 'нет')");
            $stmt->execute([
                'cid' => $classroomId,
                'tid' => $teacherId,
                'nd'  => $nd,
                'd'   => $date,
                'dow' => $dayOfWeek,
                'pn'  => $pairNumber,
                'ts'  => $timeStart,
                'te'  => $timeEnd,
                'lt'  => $lessonType,
            ]);
            $imported++;
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('Ошибка импорта расписания: ' . $e->getMessage());
    }

    jsonSuccess(compact('imported', 'errors'), "Импортировано записей расписания: {$imported}");
}

function importSoftware(): void {
    $filePath = getUploadedFile();
    $rows = readSpreadsheet($filePath);
    unlink($filePath);

    $db = getDB();
    $imported = 0;
    $errors = [];

    $startRow = 1;
    $firstRow = $rows[1] ?? [];
    $firstCell = is_array($firstRow) ? trim((string)reset($firstRow)) : '';
    if (mb_stripos($firstCell, 'ауд') !== false || mb_stripos($firstCell, 'ПО') !== false) {
        $startRow = 2;
    }

    for ($i = $startRow; $i <= count($rows); $i++) {
        $row = array_values($rows[$i]);
        $roomNumber = trim((string)($row[0] ?? ''));
        if (empty($roomNumber)) continue;

        try {
            $stmt = $db->prepare('INSERT INTO software (room_number, building, name, notes) VALUES (:rn, :b, :n, :nt)');
            $stmt->execute([
                'rn' => $roomNumber,
                'b'  => !empty($row[1]) ? trim((string)$row[1]) : null,
                'n'  => !empty($row[2]) ? trim((string)$row[2]) : 'Не указано',
                'nt' => !empty($row[3]) ? trim((string)$row[3]) : null,
            ]);
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Строка {$i}: {$e->getMessage()}";
        }
    }

    jsonSuccess(compact('imported', 'errors'), "Импортировано записей ПО: {$imported}");
}