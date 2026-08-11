<?php
/**
 * API экспорта данных в Excel
 * GET /api/export/teachers   — экспорт преподавателей
 * GET /api/export/classrooms — экспорт аудиторий
 * GET /api/export/schedule   — экспорт расписания
 * GET /api/export/software   — экспорт ПО
 * GET /api/export/report     — отчет по аудиториям
 */

function handleExport(string $method, string $action): void {
    requireAuth();

    if ($method !== 'GET') {
        jsonError('Метод не поддерживается', 405);
    }

    // Проверяем PhpSpreadsheet
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        jsonError('Библиотека PhpSpreadsheet не установлена. composer require phpoffice/phpspreadsheet');
    }
    require_once $autoloadPath;

    switch ($action) {
        case 'teachers':
            exportTeachers();
            break;
        case 'classrooms':
            exportClassrooms();
            break;
        case 'schedule':
            exportSchedule();
            break;
        case 'software':
            exportSoftware();
            break;
        case 'report':
            exportReport();
            break;
        default:
            jsonError('Тип экспорта не поддерживается: ' . $action, 404);
    }
}

function createSpreadsheet(string $title, array $headers, array $data): \PhpOffice\PhpSpreadsheet\Spreadsheet {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(mb_substr($title, 0, 31));

    // Заголовки
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }

    // Данные
    $rowNum = 2;
    foreach ($data as $row) {
        $col = 1;
        foreach ($row as $value) {
            $sheet->setCellValueByColumnAndRow($col, $rowNum, $value);
            $col++;
        }
        $rowNum++;
    }

    // Автоширина
    foreach (range('A', $sheet->getHighestColumn()) as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // Стиль заголовков
    $styleArray = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9E1F2'],
        ],
    ];
    $headerRange = 'A1:' . $sheet->getHighestColumn() . '1';
    $sheet->getStyle($headerRange)->applyFromArray($styleArray);

    return $spreadsheet;
}

function sendExcelFile(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $filename): void {
    if (!is_dir(EXPORT_DIR)) {
        mkdir(EXPORT_DIR, 0755, true);
    }

    $filePath = EXPORT_DIR . $filename;
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($filePath);

    jsonSuccess(['file' => $filename, 'url' => '/exports/' . $filename], 'Экспорт выполнен');
}

function exportTeachers(): void {
    $db = getDB();

    $where = [];
    $params = [];
    if (!empty($_GET['department'])) {
        $where[] = 'department = :dep';
        $params['dep'] = $_GET['department'];
    }
    if (!empty($_GET['employment_type'])) {
        $where[] = 'employment_type = :emp';
        $params['emp'] = $_GET['employment_type'];
    }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("SELECT * FROM teachers {$whereSQL} ORDER BY last_name, first_name");
    $stmt->execute($params);
    $teachers = $stmt->fetchAll();

    $headers = ['ID', 'Фамилия', 'Имя', 'Отчество', 'Должность', 'Степень', 'Звание', 'Кафедра', 'Форма занятости', 'Email', 'Телефон', 'Особые отметки'];
    $data = [];
    foreach ($teachers as $t) {
        $data[] = [
            $t['id'], $t['last_name'], $t['first_name'], $t['middle_name'],
            $t['position'], $t['degree'], $t['title'], $t['department'],
            $t['employment_type'], $t['email'], $t['phone'], $t['notes'],
        ];
    }

    $spreadsheet = createSpreadsheet('Преподаватели', $headers, $data);
    sendExcelFile($spreadsheet, 'teachers_' . date('Y-m-d_His') . '.xlsx');
}

function exportClassrooms(): void {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM classrooms ORDER BY building, room_number');
    $rooms = $stmt->fetchAll();

    $headers = ['ID', 'Номер ауд.', 'Корпус', 'Тип помещения', 'Установленное ПО', 'Посадочных мест', 'Проектор', 'Колонки', 'Компьютеров'];
    $data = [];
    foreach ($rooms as $r) {
        $data[] = [
            $r['id'], $r['room_number'], $r['building'] === 'Д' ? 'Джамбула' : 'Вознесенский',
            $r['room_type'], $r['software_installed'], $r['seats'],
            $r['has_projector'] ? 'Да' : 'Нет', $r['has_speakers'] ? 'Да' : 'Нет',
            $r['computers_count'],
        ];
    }

    $spreadsheet = createSpreadsheet('Аудитории', $headers, $data);
    sendExcelFile($spreadsheet, 'classrooms_' . date('Y-m-d_His') . '.xlsx');
}

function exportSchedule(): void {
    $db = getDB();
    $where = [];
    $params = [];

    if (!empty($_GET['date_from'])) {
        $where[] = 's.date >= :df';
        $params['df'] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $where[] = 's.date <= :dt';
        $params['dt'] = $_GET['date_to'];
    }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("
        SELECT s.*, c.room_number, c.building,
               CONCAT(t.last_name, ' ', t.first_name, ' ', COALESCE(t.middle_name, '')) AS teacher_name
        FROM schedule s
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        {$whereSQL}
        ORDER BY s.date, s.pair_number
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $headers = ['ID', 'Аудитория', 'Корпус', 'Преподаватель', 'Числ/Знам', 'Дата', 'День недели',
                'Пара', 'Время начала', 'Время окончания', 'Нестандартное время',
                'Вид занятия', 'Перенос/отмена'];

    $data = [];
    foreach ($items as $item) {
        $data[] = [
            $item['id'], $item['room_number'], $item['building'], $item['teacher_name'],
            $item['numerator_denominator'], $item['date'], $item['day_of_week'],
            $item['pair_number'], $item['time_start'], $item['time_end'],
            $item['is_nonstandard_time'] ? 'Да' : 'Нет', $item['lesson_type'],
            $item['transfer_cancel'],
        ];
    }

    $spreadsheet = createSpreadsheet('Расписание', $headers, $data);
    sendExcelFile($spreadsheet, 'schedule_' . date('Y-m-d_His') . '.xlsx');
}

function exportSoftware(): void {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM software ORDER BY building, room_number');
    $items = $stmt->fetchAll();

    $headers = ['ID', 'Номер ауд.', 'Корпус', 'ПО', 'Особые отметки'];
    $data = [];
    foreach ($items as $item) {
        $data[] = [
            $item['id'], $item['room_number'], $item['building'],
            $item['name'], $item['notes'],
        ];
    }

    $spreadsheet = createSpreadsheet('Программное обеспечение', $headers, $data);
    sendExcelFile($spreadsheet, 'software_' . date('Y-m-d_His') . '.xlsx');
}

function exportReport(): void {
    $db = getDB();

    // Сводный отчет: аудитории, их загруженность, ПО
    $stmt = $db->query("
        SELECT c.room_number, c.building, c.room_type, c.seats, c.has_projector, c.has_speakers, c.computers_count,
               COUNT(s.id) AS total_lessons,
               SUM(CASE WHEN s.is_occupied = 1 THEN 1 ELSE 0 END) AS occupied_count,
               SUM(CASE WHEN s.transfer_cancel = 'перенос' THEN 1 ELSE 0 END) AS transfers,
               SUM(CASE WHEN s.transfer_cancel = 'отмена' THEN 1 ELSE 0 END) AS cancellations
        FROM classrooms c
        LEFT JOIN schedule s ON c.id = s.classroom_id
        GROUP BY c.id
        ORDER BY c.building, c.room_number
    ");
    $report = $stmt->fetchAll();

    $headers = ['Аудитория', 'Корпус', 'Тип', 'Мест', 'Проектор', 'Колонки', 'ПК',
                'Всего занятий', 'Проведено', 'Переносов', 'Отмен'];

    $data = [];
    foreach ($report as $r) {
        $data[] = [
            $r['room_number'], $r['building'], $r['room_type'], $r['seats'],
            $r['has_projector'] ? '+' : '-', $r['has_speakers'] ? '+' : '-', $r['computers_count'],
            $r['total_lessons'], $r['occupied_count'], $r['transfers'], $r['cancellations'],
        ];
    }

    $spreadsheet = createSpreadsheet('Отчет по аудиториям', $headers, $data);
    sendExcelFile($spreadsheet, 'report_' . date('Y-m-d_His') . '.xlsx');
}