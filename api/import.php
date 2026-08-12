<?php
/**
 * Импорт данных из Excel (.xlsx, .xls)
 *
 * POST /api/import
 * Параметры: file (multipart), type (teachers|classrooms|schedule|software)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function handleImport(string $method, string $action): void {
    requireAuth();
    requireAdmin();
    header('Content-Type: application/json; charset=utf-8');

    if ($method !== 'POST') jsonError('Метод не поддерживается', 405);

    if (empty($_FILES['file'])) jsonError('Файл не загружен');

    $type = $_POST['type'] ?? 'teachers';
    $createMissing = !empty($_POST['create_missing']);
    $file = $_FILES['file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'xls', 'zip'])) jsonError('Поддерживаются только файлы .xlsx, .xls и .zip');

    try { $spreadsheet = IOFactory::load($file); }
    catch (Exception $e) { jsonError('Ошибка чтения файла: ' . $e->getMessage(), 500); }

    $pdo = getDB();
    $detectedType = detectImportType($spreadsheet);
    $actualType = $detectedType ?: $type;

    if ($detectedType && $detectedType !== $type)
        jsonError("Несовпадение типа файла. Вы выбрали «{$type}», но файл содержит «{$detectedType}».", 400);

    $imported = 0;
    $createdTeachers = 0;
    switch ($actualType) {
        case 'teachers': $imported = importTeachers($spreadsheet, $pdo); break;
        case 'classrooms': $imported = importClassrooms($spreadsheet, $pdo); break;
        case 'schedule': $imported = importSchedule($spreadsheet, $pdo, $createMissing, $createdTeachers); break;
        case 'software': $imported = importSoftware($spreadsheet, $pdo); break;
        default: jsonError('Неизвестный тип импорта: ' . $type);
    }
    jsonSuccess(['imported' => $imported, 'created_teachers' => $createdTeachers], "Импортировано записей: {$imported}" . ($createdTeachers ? ", создано преподавателей: {$createdTeachers}" : ''));
}

// ====================== TEACHERS ======================

function importTeachers($spreadsheet, PDO $pdo): int {
    $sheetNames = $spreadsheet->getSheetNames();
    $sheet1 = $spreadsheet->getSheetByName($sheetNames[0]);
    $mainData = [];

    for ($row = 3; $row <= $sheet1->getHighestRow(); $row++) {
        $fio  = trim((string)($sheet1->getCell('B' . $row)->getValue() ?? ''));
        if ($fio === '' || $fio === 'None') continue;

        $position   = trim((string)($sheet1->getCell('C' . $row)->getValue() ?? ''));
        $degree     = trim((string)($sheet1->getCell('D' . $row)->getValue() ?? ''));
        $title      = trim((string)($sheet1->getCell('E' . $row)->getValue() ?? ''));
        $depFormRaw = trim((string)($sheet1->getCell('F' . $row)->getValue() ?? ''));

        $parts = array_map('trim', explode('/', $depFormRaw));
        $department = $parts[0] ?? '';
        $formPart = '';
        for ($i = 1; $i < count($parts); $i++) { $formPart .= $parts[$i] . ' '; }
        $formPart = trim($formPart);
        $employmentType = normalizeEmploymentType($formPart);
        $department = normalizeDepartment($department);

        if (mb_stripos($fio, 'кафедра') !== false && $position === '') continue;
        if ($position === '' && $depFormRaw === '' && $degree === '' && $title === '') continue;

        $nameParts = parseFIO($fio);
        $mainData[normalizeKey($fio)] = [
            'last_name' => $nameParts[0], 'first_name' => $nameParts[1], 'middle_name' => $nameParts[2],
            'position' => $position,
            'degree' => ($degree !== '' && $degree !== 'None') ? $degree : null,
            'title' => ($title !== '' && $title !== 'None') ? $title : null,
            'department' => ($department !== '' && $department !== 'None') ? $department : null,
            'employment_type' => $employmentType !== '' ? $employmentType : null,
        ];
    }

    if (count($sheetNames) >= 2) {
        $sheet2 = $spreadsheet->getSheetByName($sheetNames[1]);
        for ($row = 4; $row <= $sheet2->getHighestRow(); $row++) {
            $fio   = trim((string)($sheet2->getCell('B' . $row)->getValue() ?? ''));
            $email = trim((string)($sheet2->getCell('C' . $row)->getValue() ?? ''));
            $phone = trim((string)($sheet2->getCell('D' . $row)->getValue() ?? ''));
            if ($fio === '' || $fio === 'None') continue;
            if (isDepartmentHeader($fio)) continue;

            $key = normalizeKey($fio);
            if (isset($mainData[$key])) {
                if ($email !== '' && $email !== 'None') $mainData[$key]['email'] = $email;
                if ($phone !== '' && $phone !== 'None') $mainData[$key]['phone'] = $phone;
            } else {
                $nameParts = parseFIO($fio);
                $mainData[$key] = [
                    'last_name' => $nameParts[0], 'first_name' => $nameParts[1], 'middle_name' => $nameParts[2],
                    'position' => null, 'degree' => null, 'title' => null, 'department' => null, 'employment_type' => null,
                    'email' => ($email !== '' && $email !== 'None') ? $email : null,
                    'phone' => ($phone !== '' && $phone !== 'None') ? $phone : null,
                ];
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO teachers (last_name, first_name, middle_name, position, degree, title, department, employment_type, email, phone)
        VALUES (:last_name, :first_name, :middle_name, :position, :degree, :title, :department, :employment_type, :email, :phone)
        ON DUPLICATE KEY UPDATE position=VALUES(position), degree=VALUES(degree), title=VALUES(title), department=VALUES(department), employment_type=VALUES(employment_type), email=VALUES(email), phone=VALUES(phone)");

    $imported = 0;
    foreach ($mainData as $t) {
        $stmt->execute(['last_name' => $t['last_name'], 'first_name' => $t['first_name'], 'middle_name' => $t['middle_name'] ?? null,
            'position' => $t['position'], 'degree' => $t['degree'], 'title' => $t['title'],
            'department' => $t['department'], 'employment_type' => $t['employment_type'],
            'email' => $t['email'] ?? null, 'phone' => $t['phone'] ?? null]);
        $imported++;
    }
    return $imported;
}

// ====================== CLASSROOMS ======================

function importClassrooms($spreadsheet, PDO $pdo): int {
    // Пытаемся найти лист «Тех хар-ка» (старый спец-формат)
    $sheet1 = $spreadsheet->getSheetByName('Тех хар-ка');
    if ($sheet1) {
        return importClassroomsTech($sheet1, $pdo);
    }
    // Иначе — универсальный формат по заголовкам
    return importClassroomsSimple($spreadsheet->getActiveSheet(), $pdo);
}

/**
 * Старый спец-формат: лист «Тех хар-ка» с фиксированными колонками A-G
 */
function importClassroomsTech($sheet, PDO $pdo): int {
    $stmt = $pdo->prepare("INSERT INTO classrooms (room_number, building, room_type, computers_count, has_projector, has_speakers, seats)
        VALUES (:room_number, :building, :room_type, :computers_count, :has_projector, :has_speakers, :seats)
        ON DUPLICATE KEY UPDATE room_type=VALUES(room_type), computers_count=VALUES(computers_count), has_projector=VALUES(has_projector), has_speakers=VALUES(has_speakers), seats=VALUES(seats)");

    $imported = 0;
    for ($row = 4; $row <= $sheet->getHighestRow(); $row++) {
        $rn = trim((string)($sheet->getCell('A' . $row)->getValue() ?? ''));
        $b  = trim((string)($sheet->getCell('B' . $row)->getValue() ?? ''));
        $rt = trim((string)($sheet->getCell('C' . $row)->getValue() ?? ''));
        $pc = trim((string)($sheet->getCell('D' . $row)->getValue() ?? ''));
        $pr = trim((string)($sheet->getCell('E' . $row)->getValue() ?? ''));
        $sp = trim((string)($sheet->getCell('F' . $row)->getValue() ?? ''));
        $st = trim((string)($sheet->getCell('G' . $row)->getValue() ?? ''));
        if ($rn === '' || $b === '' || $rt === '') continue;
        $stmt->execute(['room_number' => $rn, 'building' => $b, 'room_type' => $rt,
            'computers_count' => is_numeric($pc) ? (int)$pc : 0,
            'has_projector' => $pr === '1' ? 1 : 0, 'has_speakers' => $sp === '1' ? 1 : 0,
            'seats' => is_numeric($st) ? (int)$st : null]);
        $imported++;
    }
    return $imported;
}

/**
 * Универсальный формат: первая строка — заголовки, далее данные.
 * Авто-определение колонок по ключевым словам в заголовках.
 * Поддерживает .xlsx, .xls, .csv (через PhpSpreadsheet).
 *
 * Пример файла (room.csv):
 *   № аудитории;Корпус;Тип;Компьютеров;Проектор;Колонки;Мест
 *   101;Д;лекционная;1;1;0;50
 *   102;В;лаборатория;15;0;1;20
 *
 * Ключевые слова для авто-определения:
 *   № ауд, room, аудит → room_number
 *   корпус, building, здание → building
 *   тип, type → room_type
 *   пк, компьютер, computers → computers_count
 *   проектор, projector → has_projector
 *   колонк, speakers → has_speakers
 *   мест, seats, посад → seats
 */
function importClassroomsSimple($sheet, PDO $pdo): int {
    $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    
    // Читаем заголовки (строка 1)
    $colMap = []; // colIndex => fieldName
    for ($col = 1; $col <= $maxCol; $col++) {
        $h = mb_strtolower(trim((string)($sheet->getCell(colLetter($col) . '1')->getValue() ?? '')), 'UTF-8');
        if ($h === '') continue;
        if (preg_match('/^(№|n).*(ауд|room|аудит)/ui', $h)) $colMap[$col] = 'room_number';
        elseif (mb_strpos($h, 'корпус') !== false || mb_strpos($h, 'building') !== false || mb_strpos($h, 'здание') !== false) $colMap[$col] = 'building';
        elseif (mb_strpos($h, 'тип') !== false || mb_strpos($h, 'type') !== false) $colMap[$col] = 'room_type';
        elseif (mb_strpos($h, 'пк') !== false || mb_strpos($h, 'компьютер') !== false || mb_strpos($h, 'computers') !== false) $colMap[$col] = 'computers_count';
        elseif (mb_strpos($h, 'проектор') !== false || mb_strpos($h, 'projector') !== false) $colMap[$col] = 'has_projector';
        elseif (mb_strpos($h, 'колонк') !== false || mb_strpos($h, 'speakers') !== false) $colMap[$col] = 'has_speakers';
        elseif (mb_strpos($h, 'мест') !== false || mb_strpos($h, 'seats') !== false || mb_strpos($h, 'посад') !== false) $colMap[$col] = 'seats';
    }

    // Если не нашли room_number — пытаемся по первой колонке с цифрами
    if (!in_array('room_number', $colMap)) {
        for ($col = 1; $col <= $maxCol; $col++) {
            $val = trim((string)($sheet->getCell(colLetter($col) . '2')->getValue() ?? ''));
            if (preg_match('/^\d{2,4}$/', $val)) { $colMap[$col] = 'room_number'; break; }
        }
    }
    // Если не нашли building — пробуем по первой букве В/Д
    if (!in_array('building', $colMap)) {
        for ($col = 1; $col <= $maxCol; $col++) {
            $val = trim((string)($sheet->getCell(colLetter($col) . '2')->getValue() ?? ''));
            if (in_array($val, ['В', 'Д', 'БМ'], true)) { $colMap[$col] = 'building'; break; }
        }
    }

    if (!in_array('room_number', $colMap)) return 0; // не смогли определить

    $stmt = $pdo->prepare("INSERT INTO classrooms (room_number, building, room_type, computers_count, has_projector, has_speakers, seats)
        VALUES (:room_number, :building, :room_type, :computers_count, :has_projector, :has_speakers, :seats)
        ON DUPLICATE KEY UPDATE room_type=VALUES(room_type), computers_count=VALUES(computers_count), has_projector=VALUES(has_projector), has_speakers=VALUES(has_speakers), seats=VALUES(seats)");

    $imported = 0;
    for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
        $data = ['room_number' => '', 'building' => 'Д', 'room_type' => '', 'computers_count' => 0, 'has_projector' => 0, 'has_speakers' => 0, 'seats' => null];
        foreach ($colMap as $col => $field) {
            $val = trim((string)($sheet->getCell(colLetter($col) . $row)->getValue() ?? ''));
            if ($field === 'has_projector' || $field === 'has_speakers') {
                $data[$field] = ($val === '1' || $val === 'да' || mb_strtolower($val) === 'есть') ? 1 : 0;
            } elseif ($field === 'computers_count') {
                $data[$field] = is_numeric($val) ? (int)$val : 0;
            } elseif ($field === 'seats') {
                $data[$field] = is_numeric($val) ? (int)$val : null;
            } else {
                $data[$field] = $val;
            }
        }
        if ($data['room_number'] === '') continue;

        $stmt->execute($data);
        $imported++;
    }

    return $imported;
}

// ====================== SCHEDULE ======================

function importSchedule($spreadsheet, PDO $pdo, bool $createMissing = false, &$createdTeachers = null): int {
    $createdTeachers = $createdTeachers ?? 0;
    $sheet = $spreadsheet->getActiveSheet();
    
    // Всегда сначала пробуем авто-определение по заголовкам
    $result = importScheduleByHeaders($sheet, $pdo, $createMissing, $createdTeachers);
    if ($result > 0) return $result;
    
    // Фолбэк на старые фиксированные форматы
    $firstCell = trim((string)($sheet->getCell('A1')->getValue() ?? ''));
    if (mb_stripos($firstCell, 'экзамен') !== false) {
        return importScheduleSession($sheet, $pdo, $createMissing, $createdTeachers);
    }
    return importScheduleRegular($sheet, $pdo, $createMissing, $createdTeachers);
}

function importScheduleByHeaders($sheet, PDO $pdo, bool $createMissing = false, &$createdTeachers = null): int {
    $createdTeachers = $createdTeachers ?? 0;
    $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    $maxCol = min($maxCol, 30);
    
    // Ищем строку заголовков (ключевые слова)
    $headerRow = 0;
    $colMap = []; // colIndex => fieldName
    
    for ($r = 1; $r <= min($sheet->getHighestRow(), 5); $r++) {
        $candidate = [];
        for ($col = 1; $col <= $maxCol; $col++) {
            $h = mb_strtolower(trim((string)($sheet->getCell(colLetter($col) . $r)->getValue() ?? '')), 'UTF-8');
            if ($h === '') continue;
            
            $field = mapScheduleHeader($h);
            if ($field !== null) $candidate[$col] = $field;
        }
        
        // Если нашли минимум 3 ключевых поля — считаем это строкой заголовков
        if (count($candidate) >= 3) {
            $headerRow = $r;
            $colMap = $candidate;
            break;
        }
    }
    
    if ($headerRow === 0) return 0; // не удалось определить заголовки
    
    $stmt = $pdo->prepare("INSERT INTO schedule (dedup_key, classroom_id, classrooms_raw, teacher_id, numerator_denominator, date, pair_number, time_start, time_end, discipline, group_department, group_code, teacher_department, teacher_position, examiner, exam_type, session_start, session_end, lesson_type, is_occupied, transfer_cancel)
        VALUES (:dedup_key, :classroom_id, :classrooms_raw, :teacher_id, :num_denom, :date, 1, :time_start, :time_end, :discipline, :group_department, :group_code, :teacher_department, :teacher_position, :examiner, :exam_type, :session_start, :session_end, :lesson_type, 1, :transfer)
        ON DUPLICATE KEY UPDATE classroom_id=VALUES(classroom_id), classrooms_raw=VALUES(classrooms_raw), teacher_id=VALUES(teacher_id), time_end=VALUES(time_end), group_department=VALUES(group_department), teacher_department=VALUES(teacher_department), teacher_position=VALUES(teacher_position), exam_type=VALUES(exam_type), session_start=VALUES(session_start), session_end=VALUES(session_end), lesson_type=VALUES(lesson_type), transfer_cancel=VALUES(transfer_cancel)");
    
    $imported = 0;
    $skipped = 0;
    for ($row = $headerRow + 1; $row <= $sheet->getHighestRow(); $row++) {
        $hasData = false;
        $rowData = [
            'dedup_key' => null, 'classroom_id' => null, 'classrooms_raw' => null,
            'teacher_id' => null, 'num_denom' => null,
            'date' => null, 'time_start' => null, 'time_end' => null,
            'discipline' => null, 'group_department' => null, 'group_code' => null,
            'teacher_department' => null, 'teacher_position' => null, 'examiner' => null,
            'exam_type' => null, 'session_start' => null, 'session_end' => null,
            'lesson_type' => null, 'transfer' => 'нет',
        ];
        
        $roomRaw = '';
        foreach ($colMap as $col => $field) {
            $val = $sheet->getCell(colLetter($col) . $row)->getValue();
            
            if ($field === 'date') {
                $parsed = parseExcelDate($val);
                if ($parsed) { $rowData['date'] = $parsed; $hasData = true; }
            } elseif ($field === 'time') {
                list($ts, $te) = parseExcelTime($val);
                if ($ts) { $rowData['time_start'] = $ts; $rowData['time_end'] = $te; $hasData = true; }
            } elseif ($field === 'classroom') {
                $roomRaw = trim((string)($val ?? ''));
                if ($roomRaw !== '') $hasData = true;
            } elseif ($field === 'transfer_cancel') {
                $tv = mb_strtolower(trim((string)($val ?? '')), 'UTF-8');
                if ($tv === 'перенос') $rowData['transfer'] = 'перенос';
                elseif ($tv === 'отмена') $rowData['transfer'] = 'отмена';
                elseif ($tv !== '') $hasData = true;
            } elseif ($field === 'session_start' || $field === 'session_end') {
                // Ячейки могут содержать несколько дат (например "22.12.2025 12.01.2026") — берем первую
                $parsed = parseExcelDate($val);
                if ($parsed) { $rowData[$field] = $parsed; $hasData = true; }
            } else {
                $strVal = ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) ? $val->getPlainText() : trim((string)($val ?? ''));
                if ($strVal !== '' && $strVal !== 'None') {
                    $rowData[$field] = $strVal;
                    $hasData = true;
                }
            }
        }
        
        if (!$hasData) continue;
        if (isAdminRow(($rowData['discipline'] ?? '') . ' ' . ($rowData['examiner'] ?? ''))) continue;
        
        // Обработка аудиторий (ДО / одна / несколько)
        $rows = parseClassrooms($roomRaw);
        $rowData['classrooms_raw'] = $roomRaw !== '' ? $roomRaw : null;
        $classroomId = null;
        foreach ($rows as $rc) {
            if ($rc['building'] !== null) {
                ensureClassroom($pdo, $rc['room_number'], $rc['building']);
                $classroomId = findClassroomByRoom($pdo, $rc['building'] . $rc['room_number']);
                break;
            }
        }
        $rowData['classroom_id'] = $classroomId;
        $rowData['dedup_key'] = scheduleDedupKey($rowData, $roomRaw);
        
        // Поиск преподавателя по ФИО экзаменатора (создаем, если нет и разрешено)
        if (!empty($rowData['examiner'])) {
            $rowData['teacher_id'] = findTeacherByShortName($pdo, $rowData['examiner']);
            if (!$rowData['teacher_id'] && $createMissing) {
                $rowData['teacher_id'] = createTeacherFromName($pdo, $rowData['examiner'], $rowData['teacher_department'], $rowData['teacher_position']);
                if ($rowData['teacher_id']) $createdTeachers++;
            }
        }
        
        // Нормализация типа экзамена
        if (!empty($rowData['exam_type'])) {
            $et = mb_strtolower($rowData['exam_type'], 'UTF-8');
            if (mb_strpos($et, 'конс') !== false) $rowData['exam_type'] = 'консультация';
            elseif (mb_strpos($et, 'экз') !== false) $rowData['exam_type'] = 'экзамен';
        }
        
        // INSERT с ON DUPLICATE KEY UPDATE — атомарная защита от дублирования
        $stmt->execute($rowData);
        if ($stmt->rowCount() >= 1) $imported++;
        else $skipped++;

        $sidRes = $pdo->prepare("SELECT id FROM schedule WHERE dedup_key = :dk LIMIT 1");
        $sidRes->execute(['dk' => $rowData['dedup_key']]);
        $scheduleId = (int)$sidRes->fetchColumn();
        saveScheduleClassrooms($pdo, $scheduleId, $rows);
    }
    
    return $imported;
}

function importScheduleSession($sheet, PDO $pdo, bool $createMissing = false, &$createdTeachers = null): int {
    $createdTeachers = $createdTeachers ?? 0;
    // INSERT ... ON DUPLICATE KEY UPDATE — атомарная защита от дублирования
    $stmt = $pdo->prepare("INSERT INTO schedule (dedup_key, classroom_id, teacher_id, numerator_denominator, date, pair_number, time_start, time_end, discipline, group_department, group_code, teacher_department, teacher_position, examiner, exam_type, session_start, session_end, lesson_type, is_occupied, transfer_cancel)
        VALUES (:dedup_key, :classroom_id, :teacher_id, :num_denom, :date, 1, :time_start, :time_end, :discipline, :group_department, :group_code, :teacher_department, :teacher_position, :examiner, :exam_type, :session_start, :session_end, :lesson_type, 1, :transfer)
        ON DUPLICATE KEY UPDATE classroom_id=VALUES(classroom_id), teacher_id=VALUES(teacher_id), time_end=VALUES(time_end), group_department=VALUES(group_department), teacher_department=VALUES(teacher_department), teacher_position=VALUES(teacher_position), exam_type=VALUES(exam_type), session_start=VALUES(session_start), session_end=VALUES(session_end), lesson_type=VALUES(lesson_type), transfer_cancel=VALUES(transfer_cancel)");

    $imported = 0;
    for ($row = 5; $row <= $sheet->getHighestRow(); $row++) {
        // Колонки: A=кафедра группы, B=шифр группы, C=сессия с, D=сессия по, E=дисциплина, F=кафедра преп., G=должность преп., H=экзаменатор, I=экз/конс, J=дата, K=время, L=аудитория
        $groupDep   = trim((string)($sheet->getCell('A' . $row)->getValue() ?? ''));
        $groupCode  = trim((string)($sheet->getCell('B' . $row)->getValue() ?? ''));
        $sessStart  = parseExcelDate($sheet->getCell('C' . $row)->getValue());
        $sessEnd    = parseExcelDate($sheet->getCell('D' . $row)->getValue());
        $discipline = trim((string)($sheet->getCell('E' . $row)->getValue() ?? ''));
        $teacherDep = trim((string)($sheet->getCell('F' . $row)->getValue() ?? ''));
        $teacherPos = trim((string)($sheet->getCell('G' . $row)->getValue() ?? ''));
        $examiner   = trim((string)($sheet->getCell('H' . $row)->getValue() ?? ''));
        $examTypeRaw= trim((string)($sheet->getCell('I' . $row)->getValue() ?? ''));
        $dateRaw    = $sheet->getCell('J' . $row)->getValue();
        $timeRaw    = $sheet->getCell('K' . $row)->getValue();
        $roomRaw    = trim((string)($sheet->getCell('L' . $row)->getValue() ?? ''));

        if ($discipline === '' && $examiner === '' && $groupCode === '') continue;
        if (isAdminRow($discipline . ' ' . $examiner)) continue;

        $date = parseExcelDate($dateRaw);
        if (!$date) continue;
        list($timeStart, $timeEnd) = parseExcelTime($timeRaw);

        $examType = mb_stripos($examTypeRaw, 'конс') !== false ? 'консультация' : (mb_stripos($examTypeRaw, 'экз') !== false ? 'экзамен' : ($examTypeRaw !== '' ? $examTypeRaw : null));
        $classroomId = findClassroomByRoom($pdo, $roomRaw);
        $teacherId = findTeacherByShortName($pdo, $examiner);
        if (!$teacherId && $examiner !== '' && $createMissing) {
            $teacherId = createTeacherFromName($pdo, $examiner, $teacherDep, $teacherPos);
            if ($teacherId) $createdTeachers++;
        }

        $dedupKey = scheduleDedupKey([
            'date' => $date, 'time_start' => $timeStart, 'discipline' => $discipline,
            'group_code' => $groupCode, 'examiner' => $examiner,
        ], $roomRaw);

        $stmt->execute([
            'dedup_key'      => $dedupKey,
            'classroom_id'   => $classroomId,
            'teacher_id'     => $teacherId,
            'num_denom'      => null,
            'date'           => $date,
            'time_start'     => $timeStart, 'time_end' => $timeEnd,
            'discipline'     => $discipline !== '' ? $discipline : null,
            'group_department' => $groupDep !== '' ? $groupDep : null,
            'group_code'     => $groupCode !== '' ? $groupCode : null,
            'teacher_department' => $teacherDep !== '' ? $teacherDep : null,
            'teacher_position' => $teacherPos !== '' ? $teacherPos : null,
            'examiner'       => $examiner !== '' ? $examiner : null,
            'exam_type'      => $examType,
            'session_start'  => $sessStart,
            'session_end'    => $sessEnd,
            'lesson_type'    => null,
            'transfer'       => 'нет',
        ]);
        $imported += ($stmt->rowCount() >= 1) ? 1 : 0;
    }
    return $imported;
}

function importScheduleRegular($sheet, PDO $pdo, bool $createMissing = false, &$createdTeachers = null): int {
    $createdTeachers = $createdTeachers ?? 0;
    // Определяем RichText-формат
    $richTextCount = 0;
    $maxR = min($sheet->getHighestRow(), 10);
    for ($r = 1; $r <= $maxR; $r++) {
        foreach (['A','B','C','D','E'] as $colL) {
            if ($sheet->getCell($colL . $r)->getValue() instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $richTextCount++;
            }
        }
    }
    if ($richTextCount > 3) {
        return importScheduleRichText($sheet, $pdo, $createMissing, $createdTeachers);
    }
    return importScheduleSimple($sheet, $pdo, $createMissing, $createdTeachers);
}

function importScheduleSimple($sheet, PDO $pdo, bool $createMissing = false, &$createdTeachers = null): int {
    $createdTeachers = $createdTeachers ?? 0;
    $stmt = $pdo->prepare("INSERT INTO schedule (dedup_key, classroom_id, teacher_id, date, pair_number, time_start, time_end, lesson_type, is_occupied)
        VALUES (:dedup_key, :classroom_id, :teacher_id, :date, 1, :time_start, :time_end, :lesson_type, 1)
        ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id), lesson_type=VALUES(lesson_type)");

    $imported = 0;
    for ($row = 3; $row <= $sheet->getHighestRow(); $row++) {
        $discRaw = $sheet->getCell('E' . $row)->getValue();
        $discipline = ($discRaw instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) ? $discRaw->getPlainText() : trim((string)($discRaw ?? ''));
        $lessonType = trim((string)($sheet->getCell('G' . $row)->getValue() ?? ''));
        $dateRaw    = $sheet->getCell('J' . $row)->getValue();
        $timeRaw    = $sheet->getCell('K' . $row)->getValue();
        if ($discipline === '') continue;

        $date = parseExcelDate($dateRaw);
        if (!$date) continue;
        list($timeStart, $timeEnd) = parseExcelTime($timeRaw);

        $classroomId = null;
        for ($col = 1; $col <= 12; $col++) {
            $val = trim((string)($sheet->getCell(colLetter($col) . $row)->getValue() ?? ''));
            if ($val === '') continue;
            $cid = findClassroomByRoom($pdo, $val);
            if ($cid) { $classroomId = $cid; break; }
        }

        $teacherId = findTeacherInText($pdo, $discipline);
        $dedupKey = scheduleDedupKey([
            'date' => $date, 'time_start' => $timeStart, 'classroom_id' => $classroomId,
            'discipline' => $discipline,
        ], '');
        $stmt->execute([
            'dedup_key'    => $dedupKey,
            'classroom_id' => $classroomId,
            'teacher_id'   => $teacherId,
            'date'         => $date, 'time_start' => $timeStart, 'time_end' => $timeEnd,
            'lesson_type'  => $lessonType !== '' ? $lessonType : null,
        ]);
        $imported++;
    }
    return $imported;
}

function importScheduleRichText($sheet, PDO $pdo, bool $createMissing = false, &$createdTeachers = null): int {
    $createdTeachers = $createdTeachers ?? 0;
    $stmt = $pdo->prepare("INSERT INTO schedule (dedup_key, classroom_id, teacher_id, date, pair_number, time_start, time_end, lesson_type, is_occupied)
        VALUES (:dedup_key, :classroom_id, :teacher_id, :date, 1, :time_start, :time_end, :lesson_type, 1)
        ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id), lesson_type=VALUES(lesson_type)");

    $imported = 0;
    $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    $maxCol = min($maxCol, 20);

    for ($row = 7; $row <= $sheet->getHighestRow(); $row++) {
        $timeRaw = $sheet->getCell('B' . $row)->getValue();
        if ($timeRaw instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) $timeRaw = $timeRaw->getPlainText();
        list($timeStart, $timeEnd) = parseExcelTime(trim((string)($timeRaw ?? '')));
        if (!$timeStart && !$timeEnd) continue;

        $allText = '';
        $classroomId = null;

        for ($col = 3; $col <= $maxCol; $col++) {
            $val = $sheet->getCell(colLetter($col) . $row)->getValue();
            if ($val === null) continue;
            if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $text = $val->getPlainText();
                $allText .= ' | ' . $text;
                if (!$classroomId) $classroomId = findClassroomInText($pdo, $text);
            } else {
                $text = trim((string)$val);
                if ($text === '') continue;
                $cid = findClassroomByRoom($pdo, $text);
                if ($cid) { $classroomId = $cid; } else { $allText .= ' | ' . $text; }
            }
        }

        if (trim($allText) === '') continue;

        $teacherId = findTeacherInText($pdo, $allText);
        $dedupKey = scheduleDedupKey([
            'date' => null, 'time_start' => $timeStart, 'classroom_id' => $classroomId,
            'discipline' => $allText,
        ], '');
        $stmt->execute([
            'dedup_key'    => $dedupKey,
            'classroom_id' => $classroomId,
            'teacher_id'   => $teacherId,
            'date'         => null, 'time_start' => $timeStart, 'time_end' => $timeEnd,
            'lesson_type'  => extractLessonType($allText),
        ]);
        $imported++;
    }
    return $imported;
}

// ====================== SOFTWARE ======================

function importSoftware($spreadsheet, PDO $pdo): int {
    $sheet = $spreadsheet->getSheetByName('ПО');
    if (!$sheet) return 0;

    $rooms = [];
    $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    for ($col = 1; $col <= $maxCol; $col++) {
        $header = trim((string)($sheet->getCell(colLetter($col) . '3')->getValue() ?? ''));
        if ($header === '') continue;
        $ri = parseRoomHeader($header);
        if ($ri) { $rooms[$col] = $ri; ensureClassroom($pdo, $ri['room_number'], $ri['building']); }
    }

    $stmt = $pdo->prepare("INSERT INTO software (room_number, building, name) VALUES (:room_number, :building, :name) ON DUPLICATE KEY UPDATE name=VALUES(name)");
    $imported = 0;
    for ($row = 4; $row <= $sheet->getHighestRow(); $row++) {
        foreach ($rooms as $col => $ri) {
            $name = trim((string)($sheet->getCell(colLetter($col) . $row)->getValue() ?? ''));
            if ($name === '') continue;
            $stmt->execute(['room_number' => $ri['room_number'], 'building' => $ri['building'], 'name' => $name]);
            $imported++;
        }
    }
    return $imported;
}

// ====================== HELPERS ======================

/**
 * Разбирает строку аудиторий в список записей [['building','room_number'], ...].
 * Поддерживает: «ДО», «Д234», «Д234 Д237», «Д234, Д237», «ДО/Д234».
 * Возвращает массив массивов.
 */
function parseClassrooms(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return [];

    // Разделяем по запятым, слешам, «или», пробелам
    $tokens = preg_split('/[\s,\/;]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $rooms = [];
    foreach ($tokens as $token) {
        $token = trim($token);
        if ($token === '' || mb_strtolower($token, 'UTF-8') === 'или') continue;

        // «ДО» — дистанционное обучение (без аудитории)
        if (mb_strtoupper($token, 'UTF-8') === 'ДО') {
            $rooms[] = ['building' => null, 'room_number' => 'ДО'];
            continue;
        }

        if (preg_match('/^([ВвДд])\s*(\d+)$/u', $token, $m)) {
            $rooms[] = ['building' => mb_strtoupper($m[1], 'UTF-8'), 'room_number' => $m[2]];
        } elseif (preg_match('/^(\d{2,4})$/', $token, $m)) {
            $rooms[] = ['building' => 'Д', 'room_number' => $m[1]];
        }
    }
    return $rooms;
}

/**
 * Сохраняет связи расписания с аудиториями через таблицу schedule_classrooms.
 */
function saveScheduleClassrooms(PDO $pdo, int $scheduleId, array $rows): void {
    if ($scheduleId <= 0) return;
    $insert = $pdo->prepare("INSERT IGNORE INTO schedule_classrooms (schedule_id, classroom_id) VALUES (:sid, :cid)");
    foreach ($rows as $rc) {
        if ($rc['building'] === null) continue; // «ДО» — без аудитории
        $cid = findClassroomByRoom($pdo, $rc['building'] . $rc['room_number']);
        if ($cid) $insert->execute(['sid' => $scheduleId, 'cid' => $cid]);
    }
}

/**
 * Сопоставляет заголовок колонки Excel с полем расписания.
 * Порядок важен: «Экзаменатор» должен распознаваться раньше «экз»/«экзамен».
 */
function mapScheduleHeader(string $header): ?string {
    $h = mb_strtolower(trim($header), 'UTF-8');
    if ($h === '') return null;

    if (preg_match('/^(дат|date)/ui', $h)) return 'date';
    if (preg_match('/^(врем|time)/ui', $h)) return 'time';
    if (preg_match('/^(групп|group|шифр)/ui', $h)) return 'group_code';
    if (preg_match('/^(дисц|discipl)/ui', $h)) return 'discipline';
    // Экзаменатор/преподаватель — раньше exam_type
    if (preg_match('/^(экзаменатор|преподаватель|препод|examiner|фио)/ui', $h)) return 'examiner';
    if (preg_match('/^(тип.*зан|вид.*зан|lesson.*type)/ui', $h)) return 'lesson_type';
    if (preg_match('/^(консульта|зачет|зачёт|экз|экзамен|вид|тип|exam)/ui', $h)) return 'exam_type';
    if (preg_match('/^(ауд|room|classroom|каб)/ui', $h)) return 'classroom';
    if (preg_match('/^(перенос|transfer|отмен)/ui', $h)) return 'transfer_cancel';
    if (preg_match('/^(каф.*груп|group.*dep)/ui', $h)) return 'group_department';
    if (preg_match('/^(каф.*преп|teacher.*dep)/ui', $h)) return 'teacher_department';
    if (preg_match('/^(долж.*преп|teacher.*pos)/ui', $h)) return 'teacher_position';
    if (preg_match('/^(сроки.*начало|сессия.*с|session.*start)/ui', $h)) return 'session_start';
    if (preg_match('/^(сроки.*окончание|сессия.*по|session.*end)/ui', $h)) return 'session_end';
    if (preg_match('/^кафедра/ui', $h)) return 'group_department';
    return null;
}

/**
 * Формирует стабильный уникальный ключ записи расписания.
 * Используется для защиты от дублирования при повторном импорте.
 */
function scheduleDedupKey(array $row, string $roomRaw = ''): string {
    $date = $row['date'] ?? null;
    $time = $row['time_start'] ?? null;
    $discipline = $row['discipline'] ?? null;
    $groupCode = $row['group_code'] ?? null;
    $examiner = $row['examiner'] ?? null;
    $classroomId = $row['classroom_id'] ?? null;
    $lessonType = $row['lesson_type'] ?? null;

    // Для формата по заголовкам/сессионного — по дате+времени+дисциплине+группе+экзаменатору
    $parts = [
        $date ?: '',
        $time ?: '',
        trim((string)$discipline),
        trim((string)$groupCode),
        trim((string)$examiner),
    ];
    // Если ключевых полей мало (RichText/simple), добавляем аудиторию и тип
    if (!trim(implode('', [$discipline, $groupCode, $examiner]))) {
        $parts = [
            $date ?: '',
            $time ?: '',
            trim((string)$lessonType),
            $classroomId ? (string)$classroomId : trim($roomRaw),
            trim((string)$discipline),
        ];
    }
    return substr(md5(implode('|', $parts)), 0, 64);
}

/**
 * Создает преподавателя из краткого ФИО экзаменатора и сведений из расписания.
 * Возвращает id созданного/найденного преподавателя или null.
 */
function createTeacherFromName(PDO $pdo, string $shortName, ?string $department = null, ?string $position = null): ?int {
    $shortName = trim($shortName);
    if ($shortName === '') return null;

    $parts = preg_split('/\s+/', $shortName);
    $lastName = $parts[0] ?? '';
    $firstInitial = isset($parts[1]) ? rtrim($parts[1], '.') : '';
    $middleInitial = isset($parts[2]) ? rtrim($parts[2], '.') : '';

    // Разделяем склеенные инициалы "Д.Д."
    if ($firstInitial !== '' && $middleInitial === '' && preg_match('/^([А-ЯЁ])\.([А-ЯЁ])\.{0,1}$/ui', $firstInitial, $m)) {
        $firstInitial = $m[1];
        $middleInitial = $m[2];
    }

    if ($lastName === '') return null;

    // Имя: расширяем инициал до заглушки-имени, т.к. полного имени в расписании нет
    $first = $firstInitial !== '' ? ($firstInitial . '.') : '';
    $middle = $middleInitial !== '' ? ($middleInitial . '.') : null;
    $department = ($department !== null && $department !== '') ? $department : null;
    $position = ($position !== null && $position !== '') ? $position : null;

    // Ищем по фамилии + инициалам, чтобы не наплодить дублей
    $existing = findTeacherByShortName($pdo, $shortName);
    if ($existing) return $existing;

    // Пытаемся вставить; на случай гонки — ловим дубликат
    try {
        $st = $pdo->prepare("INSERT INTO teachers (last_name, first_name, middle_name, department, position)
            VALUES (:ln, :fn, :mn, :dep, :pos)");
        $st->execute([
            'ln' => $lastName,
            'fn' => $first !== '' ? $first : '—',
            'mn' => $middle,
            'dep' => $department,
            'pos' => $position,
        ]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return findTeacherByShortName($pdo, $shortName);
        }
        throw $e;
    }
}

function isAdminRow(string $text): bool {
    $skip = ['директор', 'начальник', 'ведущий документовед', 'захарова', 'люльева', 'лезунова'];
    $l = mb_strtolower($text, 'UTF-8');
    foreach ($skip as $kw) {
        if (mb_strpos($l, $kw) !== false) return true;
    }
    return false;
}

function colLetter(int $index): string {
    return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
}

function findTeacherByShortName(PDO $pdo, string $sn): ?int {
    if ($sn === '') return null;
    $parts = preg_split('/\s+/', trim($sn));
    $ln = $parts[0] ?? '';
    $i1 = isset($parts[1]) ? rtrim($parts[1], '.') : '';
    $i2 = isset($parts[2]) ? rtrim($parts[2], '.') : '';
    
    // Если инициалы склеены (например "Д.Д."), разбиваем по точке
    if ($i1 !== '' && $i2 === '' && preg_match('/^([А-ЯЁ])\.([А-ЯЁ])\.{0,1}$/ui', $i1, $m)) {
        $i1 = $m[1];
        $i2 = $m[2];
    }
    // Нормализуем: одиночный инициал из 2+ букв без точек — считаем за имя
    if (mb_strlen($i1) > 1 && mb_strlen($i1) <= 3 && mb_strtolower($i1) !== 'гпх') {
        // уже полное/сокращенное имя, оставляем как есть
    }
    
    if ($ln === '') return null;

    // Поиск по фамилии + начальным буквам имени/отчества (инициалы -> полное имя)
    $sql = "SELECT id FROM teachers WHERE last_name = :ln";
    $params = ['ln' => $ln];
    if ($i1 !== '') {
        // Инициал может быть сокращением (Д. -> Дмитрий) или уже полным именем
        $sql .= " AND (first_name LIKE :fn1 OR first_name = :fe1 OR LEFT(first_name, 1) = :fi1)";
        $params['fn1'] = $i1 . '%';
        $params['fe1'] = $i1;
        $params['fi1'] = $i1;
    }
    if ($i2 !== '') {
        $sql .= " AND (middle_name LIKE :mn1 OR middle_name = :me1 OR LEFT(middle_name, 1) = :mi1)";
        $params['mn1'] = $i2 . '%';
        $params['me1'] = $i2;
        $params['mi1'] = $i2;
    }
    $sql .= " LIMIT 1";
    $st = $pdo->prepare($sql); $st->execute($params);
    $r = $st->fetch();
    if ($r) return (int)$r['id'];

    // Фолбэк: ищем только по фамилии (если инициалы не совпали)
    if ($i1 !== '' || $i2 !== '') {
        $st2 = $pdo->prepare("SELECT id FROM teachers WHERE last_name = :ln ORDER BY id LIMIT 1");
        $st2->execute(['ln' => $ln]);
        $r2 = $st2->fetch();
        if ($r2) return (int)$r2['id'];
    }
    return null;
}

function findTeacherInText(PDO $pdo, string $text): ?int {
    if (preg_match('/([А-Я][а-яё]+\s+[А-Я]\.[А-Я]\.)/u', $text, $m)) return findTeacherByShortName($pdo, $m[1]);
    return null;
}

function findClassroomByRoom(PDO $pdo, string $raw): ?int {
    if ($raw === '' || $raw === 'нет' || mb_stripos($raw, 'нет') !== false) return null;
    $building = 'Д'; $number = '';
    if (preg_match('/^([ВвДд])\s*(\d+)/u', $raw, $m)) { $building = mb_strtoupper($m[1]); $number = $m[2]; }
    elseif (preg_match('/(\d{2,4})/', $raw, $m)) { $number = $m[1]; }
    if ($number === '') return null;
    $st = $pdo->prepare("SELECT id FROM classrooms WHERE room_number=:n AND building=:b LIMIT 1");
    $st->execute(['n' => $number, 'b' => $building]);
    $r = $st->fetch(); return $r ? (int)$r['id'] : null;
}

function findClassroomInText(PDO $pdo, string $text): ?int {
    if (preg_match('/[ВвДд]\s*\d{2,4}/u', $text, $m)) return findClassroomByRoom($pdo, $m[0]);
    return null;
}

function parseExcelDate($value): ?string {
    if ($value === null || $value === '') return null;
    if ($value instanceof \DateTime) return $value->format('Y-m-d');
    if (is_numeric($value) && $value > 1) return gmdate('Y-m-d', (int)(($value - 25569) * 86400));
    $str = trim((string)$value);
    if (preg_match('/\d{2}\.\d{2}\.\d{4}/', $str, $m)) { $d = \DateTime::createFromFormat('d.m.Y', $m[0]); return $d ? $d->format('Y-m-d') : null; }
    if (preg_match('/(\d{4}-\d{2}-\d{2})/', $str, $m)) return $m[1];
    return null;
}

function parseExcelTime($value): array {
    if ($value === null || $value === '') return [null, null];
    if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) $value = $value->getPlainText();
    if (is_numeric($value) && $value > 0 && $value < 1) {
        $tm = round($value * 1440);
        return [sprintf('%02d:%02d:00', floor($tm/60), $tm%60), sprintf('%02d:%02d:00', (floor($tm/60)+1)%24, $tm%60)];
    }
    $str = trim((string)$value);
    if (preg_match('/(\d{1,2}:\d{2})\s*[-–—]\s*(\d{1,2}:\d{2})/', $str, $m)) return [$m[1].':00', $m[2].':00'];
    if (preg_match('/(\d{1,2}:\d{2})/', $str, $m)) return [$m[1].':00', null];
    return [null, null];
}

function extractLessonType(string $text): ?string {
    if (mb_strpos($text, 'лекц') !== false) return 'лекция';
    if (mb_strpos($text, 'прак') !== false) return 'практика';
    if (mb_strpos($text, 'лаб') !== false) return 'лабораторная';
    return null;
}

function parseRoomHeader(string $h): ?array {
    if (!preg_match('/^(\d+)/', $h, $m)) return null;
    return ['room_number' => $m[1], 'building' => (mb_stripos($h, 'взн') !== false) ? 'В' : 'Д'];
}

function ensureClassroom(PDO $pdo, string $rn, string $b): void {
    $pdo->prepare("INSERT IGNORE INTO classrooms (room_number, building, room_type) VALUES (:n, :b, '')")->execute(['n' => $rn, 'b' => $b]);
}

function isDepartmentHeader(string $fio): bool {
    $l = mb_strtolower(trim($fio), 'UTF-8');
    foreach (['кафедра', 'физическая культура', 'живопись и рисунок', 'общевузовская'] as $kw) {
        if (mb_strpos($l, $kw) !== false) return true;
    }
    return false;
}

function normalizeKey(string $s): string { return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)), 'UTF-8'); }
function parseFIO(string $fio): array { $p = preg_split('/\s+/', trim($fio)); return [$p[0] ?? '', $p[1] ?? '', isset($p[2]) ? implode(' ', array_slice($p, 2)) : '']; }

function normalizeEmploymentType(string $raw): string {
    $raw = mb_strtolower(trim($raw), 'UTF-8');
    if (strpos($raw, 'внеш') !== false && strpos($raw, 'совм') !== false) return 'внешний совместитель';
    if (strpos($raw, 'внут') !== false && strpos($raw, 'совм') !== false) return 'внутренний совместитель';
    if (strpos($raw, 'гпх') !== false) return 'ГПХ';
    if (strpos($raw, 'почас') !== false) return 'ГПХ';
    if (strpos($raw, 'штат') !== false) return 'штатный';
    return $raw !== '' ? $raw : '';
}

function normalizeDepartment(string $raw): string {
    $map = ['ЖиМ СМИ' => 'ЖиМ СМИ', 'ГиСЭД' => 'ГиСЭД', 'Графика' => 'Графика', 'КиКТ' => 'КиКТ', 'Реклама' => 'Реклама', 'ТПиПК' => 'ТПиПК', 'ТПП' => 'ТПП', 'ИиУС' => 'ИиУС', 'ПОиУ' => 'ПОиУ'];
    foreach ($map as $k => $v) { if (mb_stripos($raw, $k) !== false) return $v; }
    return trim($raw);
}

function detectImportType($spreadsheet): ?string {
    $sheetNames = $spreadsheet->getSheetNames();
    $sheet = $spreadsheet->getActiveSheet();
    $maxRow = min($sheet->getHighestRow(), 15);
    $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

    // Признаки teachers: лист 2 — контакты, лист 1 — ФИО в колонках B,C,D
    if (count($sheetNames) >= 2) {
        $s1 = $spreadsheet->getSheetByName($sheetNames[0]);
        $h1 = mb_strtolower(trim((string)($s1->getCell('B1')->getValue() ?? '')), 'UTF-8');
        if (mb_strpos($h1, 'фио') !== false || mb_strpos($h1, 'фамилия') !== false) return 'teachers';
    }
    // Признаки ПО: лист называется «ПО»
    foreach ($sheetNames as $sn) {
        if (mb_strtolower(trim($sn), 'UTF-8') === 'по') return 'software';
    }
    // Признаки schedule: уникальные заголовки расписания (проверяем раньше classrooms,
    // т.к. в расписании есть колонка «Аудитория», которая ошибочно ловилась как классы)
    for ($r = 1; $r <= 3; $r++) {
        for ($c = 1; $c <= min($maxCol, 30); $c++) {
            $v = mb_strtolower(trim((string)($sheet->getCell(colLetter($c) . $r)->getValue() ?? '')), 'UTF-8');
            if (mb_strpos($v, 'дисциплин') !== false
                || mb_strpos($v, 'экзаменатор') !== false
                || mb_strpos($v, 'вид занятия') !== false
                || mb_strpos($v, 'перенос') !== false
                || mb_strpos($v, 'экзамен/консультация') !== false) {
                return 'schedule';
            }
        }
    }

    // Признаки classrooms: лист «Тех хар-ка» или заголовки «№ аудитории», «корпус»
    $hasTech = false;
    foreach ($sheetNames as $sn) { if (mb_strpos(mb_strtolower(trim($sn), 'UTF-8'), 'тех') !== false) { $hasTech = true; break; } }
    for ($r = 1; $r <= 3; $r++) {
        for ($c = 1; $c <= $maxCol; $c++) {
            $v = mb_strtolower(trim((string)($sheet->getCell(colLetter($c) . $r)->getValue() ?? '')), 'UTF-8');
            if (mb_strpos($v, 'аудитор') !== false || mb_strpos($v, 'корпус') !== false) return 'classrooms';
        }
    }
    if ($hasTech) return 'classrooms';
    // Признаки schedule: есть «экзамен» или «дисциплина» или даты в формате Excel
    $a1 = mb_strtolower(trim((string)($sheet->getCell('A1')->getValue() ?? '')), 'UTF-8');
    if (mb_strpos($a1, 'экзамен') !== false) return 'schedule';
    for ($r = 1; $r <= 5; $r++) {
        for ($c = 1; $c <= min($maxCol, 20); $c++) {
            $v = mb_strtolower(trim((string)($sheet->getCell(colLetter($c) . $r)->getValue() ?? '')), 'UTF-8');
            if (mb_strpos($v, 'расписание') !== false || mb_strpos($v, 'дисциплина') !== false || mb_strpos($v, 'пара') !== false) return 'schedule';
        }
    }
    // Признаки teachers: есть «ФИО», «должность», «степень» в заголовках строк 1-3
    for ($r = 1; $r <= 3; $r++) {
        for ($c = 1; $c <= 10; $c++) {
            $v = mb_strtolower(trim((string)($sheet->getCell(colLetter($c) . $r)->getValue() ?? '')), 'UTF-8');
            if (mb_strpos($v, 'должность') !== false || mb_strpos($v, 'степень') !== false || mb_strpos($v, 'звание') !== false || mb_strpos($v, 'кафедра') !== false) return 'teachers';
        }
    }
    // Признаки ПО: заголовки «название», «аудитория»
    for ($r = 1; $r <= 5; $r++) {
        for ($c = 1; $c <= 10; $c++) {
            $v = mb_strtolower(trim((string)($sheet->getCell(colLetter($c) . $r)->getValue() ?? '')), 'UTF-8');
            if (mb_strpos($v, 'по') !== false || mb_strpos($v, 'программ') !== false || mb_strpos($v, 'software') !== false) return 'software';
        }
    }
    // Если лист один и в первой строке читаются номера аудиторий (3-4 цифры) — classrooms
    $numCount = 0;
    for ($c = 1; $c <= min($maxCol, 10); $c++) {
        $v = trim((string)($sheet->getCell(colLetter($c) . '2')->getValue() ?? ''));
        if (preg_match('/^\d{2,4}$/', $v)) $numCount++;
    }
    if ($numCount >= 2) return 'classrooms';

    return null;
}