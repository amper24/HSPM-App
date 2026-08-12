<?php
/**
 * API экспорта данных в Excel.
 * Форматы экспорта соответствуют форматам импорта (round-trip):
 * teachers, classrooms, schedule, software можно заново импортировать через api/import.php.
 *
 * GET /api/export/teachers   — экспорт преподавателей (2 листа, как в исходной форме)
 * GET /api/export/classrooms — экспорт аудиторий (заголовки в строке 1)
 * GET /api/export/schedule   — экспорт расписания (заголовки, распознаваемые mapScheduleHeader)
 * GET /api/export/software   — экспорт ПО (лист «ПО», транспонированный формат)
 * GET /api/export/report     — сводный отчёт (не для импорта)
 */

function handleExport(string $method, string $action): void {
    requireAuth();

    if ($method !== 'GET') {
        jsonError('Метод не поддерживается', 405);
    }

    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        jsonError('Библиотека PhpSpreadsheet не установлена. composer require phpoffice/phpspreadsheet');
    }
    require_once $autoloadPath;

    switch ($action) {
        case 'teachers':   exportTeachers(); break;
        case 'classrooms': exportClassrooms(); break;
        case 'schedule':   exportSchedule(); break;
        case 'software':   exportSoftware(); break;
        case 'report':     exportReport(); break;
        default:           jsonError('Тип экспорта не поддерживается: ' . $action, 404);
    }
}

function exportColLetter(int $index): string {
    return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
}

function exportSend(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $filename): void {
    if (!is_dir(EXPORT_DIR)) {
        mkdir(EXPORT_DIR, 0755, true);
    }
    $filePath = EXPORT_DIR . $filename;
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($filePath);
    jsonSuccess(['file' => $filename, 'url' => '/exports/' . $filename], 'Экспорт выполнен');
}

function exportStyleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, int $maxCol): void {
    if ($maxCol < 1) return;
    $sheet->getStyle('A' . $row . ':' . exportColLetter($maxCol) . $row)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9E1F2'],
        ],
    ]);
}

// ====================== TEACHERS ======================
// Формат импорта (importTeachers):
//   Лист 1: B=ФИО, C=Должность, D=Степень, E=Звание, F=Кафедра/Форма занятости — данные со строки 3.
//   Лист 2: B=ФИО, C=Email, D=Телефон — данные со строки 4.
function exportTeachers(): void {
    $db = getDB();
    $teachers = $db->query('SELECT * FROM teachers ORDER BY last_name, first_name')->fetchAll();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Преподаватели');

    $sheet->setCellValue('B1', 'ФИО');
    $sheet->setCellValue('C1', 'Должность');
    $sheet->setCellValue('D1', 'Степень');
    $sheet->setCellValue('E1', 'Звание');
    $sheet->setCellValue('F1', 'Кафедра/Форма занятости');
    exportStyleHeader($sheet, 1, 6);

    $row = 3;
    foreach ($teachers as $t) {
        $fio = trim(($t['last_name'] ?? '') . ' ' . ($t['first_name'] ?? '') . ' ' . ($t['middle_name'] ?? ''));
        $depForm = ($t['department'] ?? '') . '/' . ($t['employment_type'] ?? '');
        $sheet->setCellValue('B' . $row, $fio);
        $sheet->setCellValue('C' . $row, $t['position'] ?? '');
        $sheet->setCellValue('D' . $row, $t['degree'] ?? '');
        $sheet->setCellValue('E' . $row, $t['title'] ?? '');
        $sheet->setCellValue('F' . $row, $depForm);
        $row++;
    }

    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Контакты');
    $sheet2->setCellValue('B1', 'ФИО');
    $sheet2->setCellValue('C1', 'Email');
    $sheet2->setCellValue('D1', 'Телефон');

    $r = 4;
    foreach ($teachers as $t) {
        $fio = trim(($t['last_name'] ?? '') . ' ' . ($t['first_name'] ?? '') . ' ' . ($t['middle_name'] ?? ''));
        $sheet2->setCellValue('B' . $r, $fio);
        $sheet2->setCellValue('C' . $r, $t['email'] ?? '');
        $sheet2->setCellValue('D' . $r, $t['phone'] ?? '');
        $r++;
    }

    $spreadsheet->setActiveSheetIndex(0);
    exportSend($spreadsheet, 'teachers_' . date('Y-m-d_His') . '.xlsx');
}

// ====================== CLASSROOMS ======================
// Формат импорта (importClassroomsSimple): заголовки в строке 1, данные со строки 2.
function exportClassrooms(): void {
    $db = getDB();
    $rooms = $db->query('SELECT * FROM classrooms ORDER BY building, room_number')->fetchAll();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Аудитории');

    $headers = ['№ аудитории', 'Корпус', 'Тип', 'Компьютеров', 'Проектор', 'Колонки', 'Мест'];
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }
    exportStyleHeader($sheet, 1, count($headers));

    $row = 2;
    foreach ($rooms as $r) {
        $sheet->setCellValueByColumnAndRow(1, $row, $r['room_number']);
        $sheet->setCellValueByColumnAndRow(2, $row, $r['building']);
        $sheet->setCellValueByColumnAndRow(3, $row, $r['room_type']);
        $sheet->setCellValueByColumnAndRow(4, $row, (int)$r['computers_count']);
        $sheet->setCellValueByColumnAndRow(5, $row, (int)$r['has_projector']);
        $sheet->setCellValueByColumnAndRow(6, $row, (int)$r['has_speakers']);
        $sheet->setCellValueByColumnAndRow(7, $row, $r['seats'] !== null && $r['seats'] !== '' ? (int)$r['seats'] : '');
        $row++;
    }

    exportSend($spreadsheet, 'classrooms_' . date('Y-m-d_His') . '.xlsx');
}

// ====================== SCHEDULE ======================
// Формат импорта (importScheduleByHeaders): заголовки в первых 5 строках,
// распознаны по mapScheduleHeader, данные со строки заголовков + 1.
function exportSchedule(): void {
    $db = getDB();
    $items = $db->query("
        SELECT s.*, c.room_number, c.building,
               CONCAT(t.last_name, ' ', t.first_name, ' ', COALESCE(t.middle_name, '')) AS teacher_name,
               (SELECT GROUP_CONCAT(CONCAT(c2.building, c2.room_number) ORDER BY c2.room_number SEPARATOR ', ')
                FROM schedule_classrooms sc
                JOIN classrooms c2 ON sc.classroom_id = c2.id
                WHERE sc.schedule_id = s.id) AS classrooms
        FROM schedule s
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        ORDER BY s.date, s.pair_number
    ")->fetchAll();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Расписание');

    $headers = [
        'Дата', 'Время', 'Группа', 'Дисциплина', 'Экзаменатор', 'Вид занятия',
        'Экзамен/консультация', 'Аудитория', 'Перенос/отмена',
        'Кафедра группы', 'Кафедра преподавателя', 'Должность преподавателя',
        'Сессия с', 'Сессия по',
    ];
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }
    exportStyleHeader($sheet, 1, count($headers));

    $row = 2;
    foreach ($items as $it) {
        $time = '';
        if ($it['time_start']) {
            $time = substr($it['time_start'], 0, 5);
            if ($it['time_end']) {
                $time .= '-' . substr($it['time_end'], 0, 5);
            }
        }
        $aud = $it['classrooms'] ?: $it['classrooms_raw'] ?: ($it['room_number'] ?: '');

        $sheet->setCellValueByColumnAndRow(1,  $row, $it['date']);
        $sheet->setCellValueByColumnAndRow(2,  $row, $time);
        $sheet->setCellValueByColumnAndRow(3,  $row, $it['group_code'] ?? '');
        $sheet->setCellValueByColumnAndRow(4,  $row, $it['discipline'] ?? '');
        $sheet->setCellValueByColumnAndRow(5,  $row, $it['examiner'] ?? '');
        $sheet->setCellValueByColumnAndRow(6,  $row, $it['lesson_type'] ?? '');
        $sheet->setCellValueByColumnAndRow(7,  $row, $it['exam_type'] ?? '');
        $sheet->setCellValueByColumnAndRow(8,  $row, $aud);
        $sheet->setCellValueByColumnAndRow(9,  $row, $it['transfer_cancel'] ?? 'нет');
        $sheet->setCellValueByColumnAndRow(10, $row, $it['group_department'] ?? '');
        $sheet->setCellValueByColumnAndRow(11, $row, $it['teacher_department'] ?? '');
        $sheet->setCellValueByColumnAndRow(12, $row, $it['teacher_position'] ?? '');
        $sheet->setCellValueByColumnAndRow(13, $row, $it['session_start'] ?? '');
        $sheet->setCellValueByColumnAndRow(14, $row, $it['session_end'] ?? '');
        $row++;
    }

    exportSend($spreadsheet, 'schedule_' . date('Y-m-d_His') . '.xlsx');
}

// ====================== SOFTWARE ======================
// Формат импорта (importSoftware): лист «ПО», заголовки аудиторий в строке 3,
// названия ПО в строках 4+ (транспонированный вид).
function exportSoftware(): void {
    $db = getDB();
    $items = $db->query("SELECT room_number, building, name FROM software WHERE room_number IS NOT NULL AND room_number != '' ORDER BY building, room_number, name")->fetchAll();

    // Группируем ПО по аудиториям, сохраняя порядок появления
    $order = [];
    $rooms = [];
    foreach ($items as $it) {
        $building = $it['building'] ?: 'Д';
        $key = $building . '|' . $it['room_number'];
        if (!isset($rooms[$key])) {
            $order[] = $key;
            $rooms[$key] = ['room_number' => $it['room_number'], 'building' => $building, 'software' => []];
        }
        if (!in_array($it['name'], $rooms[$key]['software'], true)) {
            $rooms[$key]['software'][] = $it['name'];
        }
    }

    $maxRows = 0;
    foreach ($rooms as $r) {
        if (count($r['software']) > $maxRows) $maxRows = count($r['software']);
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ПО');

    $col = 1;
    foreach ($order as $key) {
        $r = $rooms[$key];
        $header = $r['room_number'];
        if ($r['building'] === 'В') {
            $header .= ' взн';
        }
        $sheet->setCellValueByColumnAndRow($col, 3, $header);
        $col++;
    }

    for ($i = 0; $i < $maxRows; $i++) {
        $col = 1;
        foreach ($order as $key) {
            $list = $rooms[$key]['software'];
            $sheet->setCellValueByColumnAndRow($col, 4 + $i, $list[$i] ?? '');
            $col++;
        }
    }

    for ($ci = 1; $ci < $col; $ci++) {
        $sheet->getColumnDimension(exportColLetter($ci))->setAutoSize(true);
    }

    exportSend($spreadsheet, 'software_' . date('Y-m-d_His') . '.xlsx');
}

// ====================== REPORT (не для импорта) ======================
function exportReport(): void {
    $db = getDB();
    $report = $db->query("
        SELECT c.room_number, c.building, c.room_type, c.seats, c.has_projector, c.has_speakers, c.computers_count,
               COUNT(s.id) AS total_lessons,
               SUM(CASE WHEN s.is_occupied = 1 THEN 1 ELSE 0 END) AS occupied_count,
               SUM(CASE WHEN s.transfer_cancel = 'перенос' THEN 1 ELSE 0 END) AS transfers,
               SUM(CASE WHEN s.transfer_cancel = 'отмена' THEN 1 ELSE 0 END) AS cancellations
        FROM classrooms c
        LEFT JOIN schedule s ON c.id = s.classroom_id
        GROUP BY c.id
        ORDER BY c.building, c.room_number
    ")->fetchAll();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Отчет по аудиториям');

    $headers = ['Аудитория', 'Корпус', 'Тип', 'Мест', 'Проектор', 'Колонки', 'ПК',
                'Всего занятий', 'Проведено', 'Переносов', 'Отмен'];
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }
    exportStyleHeader($sheet, 1, count($headers));

    $row = 2;
    foreach ($report as $r) {
        $sheet->setCellValueByColumnAndRow(1,  $row, $r['room_number']);
        $sheet->setCellValueByColumnAndRow(2,  $row, $r['building']);
        $sheet->setCellValueByColumnAndRow(3,  $row, $r['room_type']);
        $sheet->setCellValueByColumnAndRow(4,  $row, $r['seats']);
        $sheet->setCellValueByColumnAndRow(5,  $row, $r['has_projector'] ? '+' : '-');
        $sheet->setCellValueByColumnAndRow(6,  $row, $r['has_speakers'] ? '+' : '-');
        $sheet->setCellValueByColumnAndRow(7,  $row, $r['computers_count']);
        $sheet->setCellValueByColumnAndRow(8,  $row, $r['total_lessons']);
        $sheet->setCellValueByColumnAndRow(9,  $row, $r['occupied_count']);
        $sheet->setCellValueByColumnAndRow(10, $row, $r['transfers']);
        $sheet->setCellValueByColumnAndRow(11, $row, $r['cancellations']);
        $row++;
    }

    exportSend($spreadsheet, 'report_' . date('Y-m-d_His') . '.xlsx');
}