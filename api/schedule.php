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

require_once __DIR__ . '/import.php';

function handleSchedule(string $method, string $action, ?int $id): void {
    if ($action === 'bulk-delete') {
        if ($method !== 'POST') jsonError('Метод не поддерживается', 405);
        requireAdmin();
        bulkDeleteSchedule();
        return;
    }

    if ($action === 'truncate') {
        if ($method !== 'POST') jsonError('Метод не поддерживается', 405);
        requireAdmin();
        truncateSchedule();
        return;
    }

    if ($action === 'groups') {
        if ($method !== 'GET') jsonError('Метод не поддерживается', 405);
        listGroups();
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
    if (!empty($_GET['group_code'])) {
        $where[] = 's.group_code = :group_code';
        $params['group_code'] = $_GET['group_code'];
    }
    if (!empty($_GET['discipline'])) {
        $where[] = 's.discipline LIKE :discipline';
        $params['discipline'] = '%' . $_GET['discipline'] . '%';
    }
    if (!empty($_GET['search'])) {
        $where[] = "(s.discipline LIKE :search OR t.last_name LIKE :search2 OR s.group_code LIKE :search3 OR s.examiner LIKE :search4)";
        $params['search'] = "%{$_GET['search']}%";
        $params['search2'] = "%{$_GET['search']}%";
        $params['search3'] = "%{$_GET['search']}%";
        $params['search4'] = "%{$_GET['search']}%";
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare("
        SELECT COUNT(*) FROM schedule s
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        {$whereSQL}
    ");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT s.*, c.room_number, c.building,
               CONCAT(t.last_name, ' ', t.first_name, ' ', COALESCE(t.middle_name, '')) AS teacher_name,
               (SELECT GROUP_CONCAT(CONCAT(c2.building, c2.room_number) ORDER BY c2.room_number SEPARATOR ', ')
                FROM schedule_classrooms sc
                JOIN classrooms c2 ON sc.classroom_id = c2.id
                WHERE sc.schedule_id = s.id) AS classrooms
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
        SELECT s.*, c.room_number, c.building,
               CONCAT(t.last_name, ' ', t.first_name, ' ', COALESCE(t.middle_name, '')) AS teacher_name,
               (SELECT GROUP_CONCAT(CONCAT(c2.building, c2.room_number) ORDER BY c2.room_number SEPARATOR ', ')
                FROM schedule_classrooms sc
                JOIN classrooms c2 ON sc.classroom_id = c2.id
                WHERE sc.schedule_id = s.id) AS classrooms
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
    validateRequired($data, ['date']);
    $dedupKey = scheduleDedupKeyFromData($data);

    // Аудитории: строка из формы (например «Д234, Д237» или «ДО»)
    $classroomsRaw = isset($data['classrooms']) ? trim((string)$data['classrooms']) : '';

    // classroom_id определяем из строки аудиторий, если не задан явно
    $classroomId = isset($data['classroom_id']) && $data['classroom_id'] !== '' && $data['classroom_id'] !== null
        ? (int)$data['classroom_id']
        : null;
    if (!$classroomId && $classroomsRaw !== '') {
        foreach (parseClassrooms($classroomsRaw) as $rc) {
            if ($rc['building'] !== null) {
                ensureClassroom($pdo = getDB(), $rc['room_number'], $rc['building']);
                $classroomId = findClassroomByRoom($pdo, $rc['building'] . $rc['room_number']);
                break;
            }
        }
    }

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO schedule (dedup_key, classroom_id, classrooms_raw, teacher_id, numerator_denominator, date, day_of_week,
            discipline, group_department, group_code, teacher_department, teacher_position,
            examiner, exam_type, session_start, session_end,
            pair_number, time_start, time_end, is_nonstandard_time, lesson_type, is_occupied, transfer_cancel)
        VALUES (:dedup_key, :classroom_id, :classrooms_raw, :teacher_id, :numerator_denominator, :date, :day_of_week,
            :discipline, :group_department, :group_code, :teacher_department, :teacher_position,
            :examiner, :exam_type, :session_start, :session_end,
            :pair_number, :time_start, :time_end, :is_nonstandard_time, :lesson_type, :is_occupied, :transfer_cancel)
    ");
    $stmt->execute([
        'dedup_key'             => $dedupKey,
        'classroom_id'          => $classroomId,
        'classrooms_raw'        => $classroomsRaw !== '' ? $classroomsRaw : null,
        'teacher_id'            => $data['teacher_id'] ?? null,
        'numerator_denominator' => $data['numerator_denominator'] ?? null,
        'date'                  => $data['date'],
        'day_of_week'           => $data['day_of_week'] ?? null,
        'discipline'            => $data['discipline'] ?? null,
        'group_department'      => $data['group_department'] ?? null,
        'group_code'            => $data['group_code'] ?? null,
        'teacher_department'    => $data['teacher_department'] ?? null,
        'teacher_position'      => $data['teacher_position'] ?? null,
        'examiner'              => $data['examiner'] ?? null,
        'exam_type'             => $data['exam_type'] ?? null,
        'session_start'         => $data['session_start'] ?? null,
        'session_end'           => $data['session_end'] ?? null,
        'pair_number'           => $data['pair_number'] ?? null,
        'time_start'            => $data['time_start'] ?? null,
        'time_end'              => $data['time_end'] ?? null,
        'is_nonstandard_time'   => (int)($data['is_nonstandard_time'] ?? 0),
        'lesson_type'           => $data['lesson_type'] ?? null,
        'is_occupied'           => (int)($data['is_occupied'] ?? 0),
        'transfer_cancel'       => $data['transfer_cancel'] ?? 'нет',
    ]);

    $id = $db->lastInsertId();
    syncScheduleClassrooms($db, $id, $classroomsRaw);
    getScheduleItem($id);
}

function updateScheduleItem(int $id): void {
    $data = getJsonInput();
    $db = getDB();
    $check = $db->prepare('SELECT id FROM schedule WHERE id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) jsonError('Запись расписания не найдена', 404);

    $fields = [
        'classroom_id', 'classrooms_raw', 'teacher_id', 'numerator_denominator', 'date', 'day_of_week',
        'discipline', 'group_department', 'group_code', 'teacher_department', 'teacher_position',
        'examiner', 'exam_type', 'session_start', 'session_end',
        'pair_number', 'time_start', 'time_end', 'is_nonstandard_time', 'lesson_type',
        'is_occupied', 'transfer_cancel'
    ];
    $intFields = ['classroom_id', 'teacher_id', 'pair_number', 'is_nonstandard_time', 'is_occupied'];
    $set = [];
    $params = ['id' => $id];

    foreach ($fields as $f) {
        if (array_key_exists($f, $data)) {
            $set[] = "`{$f}` = :{$f}";
            $params[$f] = in_array($f, $intFields) ? (int)$data[$f] : $data[$f];
        }
    }
    if (empty($set)) jsonError('Нет данных для обновления');

    $db->prepare('UPDATE schedule SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
    if (array_key_exists('classrooms', $data) || array_key_exists('classrooms_raw', $data)) {
        $raw = isset($data['classrooms']) ? trim((string)$data['classrooms']) : (isset($data['classrooms_raw']) ? (string)$data['classrooms_raw'] : '');
        syncScheduleClassrooms($db, $id, $raw);
    }
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

function truncateSchedule(): void {
    $db = getDB();
    $db->exec('DELETE FROM schedule');
    jsonSuccess(null, 'Расписание очищено');
}

/**
 * Пересоздаёт связи schedule_classrooms для записи по строке аудиторий.
 */
function syncScheduleClassrooms(PDO $db, int $scheduleId, string $raw): void {
    if ($scheduleId <= 0) return;
    $db->prepare('DELETE FROM schedule_classrooms WHERE schedule_id = :sid')->execute(['sid' => $scheduleId]);
    $rows = parseClassrooms($raw);
    $insert = $db->prepare("INSERT IGNORE INTO schedule_classrooms (schedule_id, classroom_id) VALUES (:sid, :cid)");
    foreach ($rows as $rc) {
        if ($rc['building'] === null) continue; // «ДО»
        ensureClassroom($db, $rc['room_number'], $rc['building']);
        $cid = findClassroomByRoom($db, $rc['building'] . $rc['room_number']);
        if ($cid) $insert->execute(['sid' => $scheduleId, 'cid' => $cid]);
    }
}

function scheduleDedupKeyFromData(array $data): string {
    $date = $data['date'] ?? null;
    $time = $data['time_start'] ?? null;
    $discipline = $data['discipline'] ?? null;
    $groupCode = $data['group_code'] ?? null;
    $examiner = $data['examiner'] ?? null;
    $classroomId = $data['classroom_id'] ?? null;

    $parts = [
        $date ?: '',
        $time ?: '',
        trim((string)$discipline),
        trim((string)$groupCode),
        trim((string)$examiner),
    ];
    // фолбэк по аудитории для записей без дисциплины/группы/экзаменатора
    if (!trim(implode('', [$discipline, $groupCode, $examiner]))) {
        $parts = [
            $date ?: '',
            $time ?: '',
            $classroomId ? (string)$classroomId : '',
        ];
    }
    return substr(hash('md5', implode('|', $parts)), 0, 64);
}

function listGroups(): void {
    $db = getDB();
    $stmt = $db->query("SELECT DISTINCT group_code FROM schedule WHERE group_code IS NOT NULL AND group_code != '' ORDER BY group_code");
    $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($codes)) {
        $stmt = $db->query("SELECT DISTINCT numerator_denominator FROM schedule WHERE numerator_denominator IS NOT NULL AND numerator_denominator != '' ORDER BY numerator_denominator");
        $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    jsonSuccess($codes);
}