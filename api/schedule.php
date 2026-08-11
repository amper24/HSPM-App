<?php
/**
 * API расписания (CRUD + поиск)
 * GET    /api/schedule          — список (с фильтрами)
 * GET    /api/schedule/{id}     — одна запись
 * POST   /api/schedule          — создать (admin)
 * PUT    /api/schedule/{id}     — обновить (admin)
 * DELETE /api/schedule/{id}     — удалить (admin)
 * POST   /api/schedule/bulk-delete — массовое удаление при обновлении импорта
 */

function handleSchedule(string $method, string $action, ?int $id): void {
    if ($action === 'bulk-delete') {
        if ($method !== 'POST') jsonError('Метод не поддерживается', 405);
        requireAdmin();
        bulkDeleteSchedule();
        return;
    }

    switch ($method) {
        case 'GET':
            if ($id !== null) {
                getScheduleItem($id);
            } else {
                listSchedule();
            }
            break;
        case 'POST':
            requireAdmin();
            createScheduleItem();
            break;
        case 'PUT':
            requireAdmin();
            if ($id === null) jsonError('ID записи обязателен');
            updateScheduleItem($id);
            break;
        case 'DELETE':
            requireAdmin();
            if ($id === null) jsonError('ID записи обязателен');
            deleteScheduleItem($id);
            break;
        default:
            jsonError('Метод не поддерживается', 405);
    }
}

function listSchedule(): void {
    $db = getDB();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    // Фильтры по полям расписания и аудитории
    if (!empty($_GET['date_from'])) {
        $where[] = 's.date >= :date_from';
        $params['date_from'] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $where[] = 's.date <= :date_to';
        $params['date_to'] = $_GET['date_to'];
    }
    if (!empty($_GET['date'])) {
        $where[] = 's.date = :date';
        $params['date'] = $_GET['date'];
    }
    if (!empty($_GET['classroom_id'])) {
        $where[] = 's.classroom_id = :classroom_id';
        $params['classroom_id'] = (int)$_GET['classroom_id'];
    }
    if (!empty($_GET['teacher_id'])) {
        $where[] = 's.teacher_id = :teacher_id';
        $params['teacher_id'] = (int)$_GET['teacher_id'];
    }
    if (!empty($_GET['lesson_type'])) {
        $where[] = 's.lesson_type = :lesson_type';
        $params['lesson_type'] = $_GET['lesson_type'];
    }
    if (!empty($_GET['transfer_cancel'])) {
        $where[] = 's.transfer_cancel = :transfer_cancel';
        $params['transfer_cancel'] = $_GET['transfer_cancel'];
    }
    if (isset($_GET['is_occupied']) && $_GET['is_occupied'] !== '') {
        $where[] = 's.is_occupied = :is_occupied';
        $params['is_occupied'] = (int)$_GET['is_occupied'];
    }
    if (!empty($_GET['building'])) {
        $where[] = 'c.building = :building';
        $params['building'] = $_GET['building'];
    }
    if (!empty($_GET['numerator_denominator'])) {
        $where[] = 's.numerator_denominator = :nd';
        $params['nd'] = $_GET['numerator_denominator'];
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare("
        SELECT COUNT(*) FROM schedule s
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        {$whereSQL}
    ");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT s.*, c.room_number, c.building, c.room_type,
               CONCAT(t.last_name, ' ', t.first_name, ' ', COALESCE(t.middle_name, '')) AS teacher_name
        FROM schedule s
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        {$whereSQL}
        ORDER BY s.date, s.pair_number
        LIMIT {$perPage} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    jsonSuccess([
        'items' => $items,
        'pagination' => [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'pages'    => ceil($total / $perPage),
        ],
    ]);
}

function getScheduleItem(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT s.*, c.room_number, c.building, c.room_type,
               CONCAT(t.last_name, ' ', t.first_name, ' ', COALESCE(t.middle_name, '')) AS teacher_name
        FROM schedule s
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        WHERE s.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch();
    if (!$item) jsonError('Запись расписания не найдена', 404);
    jsonSuccess($item);
}

function createScheduleItem(): void {
    $data = getJsonInput();
    validateRequired($data, ['classroom_id', 'date']);

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO schedule (classroom_id, teacher_id, numerator_denominator, date, day_of_week,
            pair_number, time_start, time_end, is_nonstandard_time, lesson_type, is_occupied, transfer_cancel)
        VALUES (:classroom_id, :teacher_id, :numerator_denominator, :date, :day_of_week,
            :pair_number, :time_start, :time_end, :is_nonstandard_time, :lesson_type, :is_occupied, :transfer_cancel)
    ");
    $stmt->execute([
        'classroom_id'          => $data['classroom_id'],
        'teacher_id'            => $data['teacher_id'] ?? null,
        'numerator_denominator' => $data['numerator_denominator'] ?? null,
        'date'                  => $data['date'],
        'day_of_week'           => $data['day_of_week'] ?? null,
        'pair_number'           => $data['pair_number'] ?? null,
        'time_start'            => $data['time_start'] ?? null,
        'time_end'              => $data['time_end'] ?? null,
        'is_nonstandard_time'   => (int)($data['is_nonstandard_time'] ?? 0),
        'lesson_type'           => $data['lesson_type'] ?? null,
        'is_occupied'           => (int)($data['is_occupied'] ?? 0),
        'transfer_cancel'       => $data['transfer_cancel'] ?? 'нет',
    ]);

    $id = $db->lastInsertId();
    getScheduleItem($id);
}

function updateScheduleItem(int $id): void {
    $data = getJsonInput();
    $db = getDB();
    $check = $db->prepare('SELECT id FROM schedule WHERE id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) jsonError('Запись расписания не найдена', 404);

    $fields = [
        'classroom_id', 'teacher_id', 'numerator_denominator', 'date', 'day_of_week',
        'pair_number', 'time_start', 'time_end', 'is_nonstandard_time', 'lesson_type',
        'is_occupied', 'transfer_cancel'
    ];
    $set = [];
    $params = ['id' => $id];

    foreach ($fields as $f) {
        if (array_key_exists($f, $data)) {
            $set[] = "`{$f}` = :{$f}";
            $params[$f] = in_array($f, ['classroom_id', 'teacher_id', 'pair_number', 'is_nonstandard_time', 'is_occupied'])
                ? (int)$data[$f]
                : $data[$f];
        }
    }
    if (empty($set)) jsonError('Нет данных для обновления');

    $db->prepare('UPDATE schedule SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
    getScheduleItem($id);
}

function deleteScheduleItem(int $id): void {
    $db = getDB();
    $check = $db->prepare('SELECT id FROM schedule WHERE id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) jsonError('Запись расписания не найдена', 404);
    $db->prepare('DELETE FROM schedule WHERE id = :id')->execute(['id' => $id]);
    jsonSuccess(null, 'Запись расписания удалена');
}

function bulkDeleteSchedule(): void {
    $data = getJsonInput();

    if (!empty($data['ids']) && is_array($data['ids'])) {
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($data['ids']), '?'));
        $db->prepare("DELETE FROM schedule WHERE id IN ({$placeholders})")->execute($data['ids']);
    }

    if (!empty($data['date_from']) && !empty($data['date_to'])) {
        $db = getDB();
        $db->prepare('DELETE FROM schedule WHERE date BETWEEN :from AND :to')
           ->execute(['from' => $data['date_from'], 'to' => $data['date_to']]);
    }

    jsonSuccess(null, 'Записи расписания удалены');
}