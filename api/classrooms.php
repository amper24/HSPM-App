<?php
/**
 * API аудиторий (CRUD + поиск)
 * GET    /api/classrooms          — список (с фильтрами)
 * GET    /api/classrooms/{id}     — одна аудитория
 * POST   /api/classrooms          — создать (admin)
 * PUT    /api/classrooms/{id}     — обновить (admin)
 * DELETE /api/classrooms/{id}     — удалить (admin)
 */

function handleClassrooms(string $method, string $action, ?int $id): void {
    if ($action === 'search') {
        if ($method !== 'GET') jsonError('Метод не поддерживается', 405);
        searchClassrooms();
        return;
    }

    if ($action === 'free') {
        if ($method !== 'GET') jsonError('Метод не поддерживается', 405);
        findFreeClassrooms();
        return;
    }

    switch ($method) {
        case 'GET':
            if ($id !== null) {
                getClassroom($id);
            } else {
                listClassrooms();
            }
            break;
        case 'POST':
            requireAdmin();
            createClassroom();
            break;
        case 'PUT':
            requireAdmin();
            if ($id === null) jsonError('ID аудитории обязателен');
            updateClassroom($id);
            break;
        case 'DELETE':
            requireAdmin();
            if ($id === null) jsonError('ID аудитории обязателен');
            deleteClassroom($id);
            break;
        default:
            jsonError('Метод не поддерживается', 405);
    }
}

function listClassrooms(): void {
    $db = getDB();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    foreach (['building', 'room_type', 'room_number', 'has_projector', 'has_speakers'] as $field) {
        if (isset($_GET[$field]) && $_GET[$field] !== '') {
            if (in_array($field, ['has_projector', 'has_speakers'])) {
                $where[] = "c.{$field} = :{$field}";
                $params[$field] = (int)$_GET[$field];
            } elseif ($field === 'room_number') {
                $where[] = "c.room_number LIKE :room_number";
                $params['room_number'] = '%' . $_GET['room_number'] . '%';
            } else {
                $where[] = "c.{$field} = :{$field}";
                $params[$field] = $_GET[$field];
            }
        }
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare("SELECT COUNT(*) FROM classrooms c {$whereSQL}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("SELECT c.* FROM classrooms c {$whereSQL} ORDER BY c.building, c.room_number LIMIT {$perPage} OFFSET {$offset}");
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

function getClassroom(int $id): void {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM classrooms WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $room = $stmt->fetch();
    if (!$room) jsonError('Аудитория не найдена', 404);
    jsonSuccess($room);
}

function createClassroom(): void {
    $data = getJsonInput();
    validateRequired($data, ['room_number', 'building', 'room_type']);

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO classrooms (room_number, building, room_type, software_installed, seats, has_projector, has_speakers, computers_count)
        VALUES (:room_number, :building, :room_type, :software_installed, :seats, :has_projector, :has_speakers, :computers_count)");
    $stmt->execute([
        'room_number'        => $data['room_number'],
        'building'           => $data['building'],
        'room_type'          => $data['room_type'],
        'software_installed' => $data['software_installed'] ?? null,
        'seats'              => $data['seats'] ?? null,
        'has_projector'      => (int)($data['has_projector'] ?? 0),
        'has_speakers'       => (int)($data['has_speakers'] ?? 0),
        'computers_count'    => (int)($data['computers_count'] ?? 0),
    ]);

    $id = $db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM classrooms WHERE id = :id');
    $stmt->execute(['id' => $id]);
    jsonSuccess($stmt->fetch(), 'Аудитория создана');
}

function updateClassroom(int $id): void {
    $data = getJsonInput();
    $db = getDB();
    $check = $db->prepare('SELECT id FROM classrooms WHERE id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) jsonError('Аудитория не найдена', 404);

    $fields = ['room_number', 'building', 'room_type', 'software_installed', 'seats', 'has_projector', 'has_speakers', 'computers_count'];
    $set = [];
    $params = ['id' => $id];

    foreach ($fields as $f) {
        if (array_key_exists($f, $data)) {
            $set[] = "`{$f}` = :{$f}";
            $params[$f] = in_array($f, ['has_projector', 'has_speakers', 'computers_count', 'seats'])
                ? (int)$data[$f]
                : $data[$f];
        }
    }
    if (empty($set)) jsonError('Нет данных для обновления');

    $db->prepare('UPDATE classrooms SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);

    $stmt = $db->prepare('SELECT * FROM classrooms WHERE id = :id');
    $stmt->execute(['id' => $id]);
    jsonSuccess($stmt->fetch(), 'Аудитория обновлена');
}

function deleteClassroom(int $id): void {
    $db = getDB();
    $check = $db->prepare('SELECT id FROM classrooms WHERE id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) jsonError('Аудитория не найдена', 404);
    $db->prepare('DELETE FROM classrooms WHERE id = :id')->execute(['id' => $id]);
    jsonSuccess(null, 'Аудитория удалена');
}

function searchClassrooms(): void {
    $db = getDB();
    $where = [];
    $params = [];

    // Поиск по всем полям таблицы аудитория
    $searchableFields = [
        'room_number' => 'c.room_number LIKE :room_number',
        'building' => 'c.building = :building',
        'room_type' => 'c.room_type = :room_type',
        'has_projector' => 'c.has_projector = :has_projector',
        'has_speakers' => 'c.has_speakers = :has_speakers',
    ];

    foreach ($searchableFields as $key => $condition) {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $where[] = $condition;
            if (in_array($key, ['room_number'])) {
                $params[$key] = '%' . $_GET[$key] . '%';
            } else {
                $params[$key] = $_GET[$key];
            }
        }
    }

    if (isset($_GET['seats_min']) && $_GET['seats_min'] !== '') {
        $where[] = 'c.seats >= :seats_min';
        $params['seats_min'] = (int)$_GET['seats_min'];
    }
    if (isset($_GET['computers_min']) && $_GET['computers_min'] !== '') {
        $where[] = 'c.computers_count >= :computers_min';
        $params['computers_min'] = (int)$_GET['computers_min'];
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $db->prepare("SELECT c.* FROM classrooms c {$whereSQL} ORDER BY c.building, c.room_number");
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}

function findFreeClassrooms(): void {
    $db = getDB();

    if (empty($_GET['date'])) jsonError('Параметр date обязателен');
    $date = $_GET['date'];

    $pairNumber = $_GET['pair_number'] ?? null;

    $where = [
        'NOT EXISTS (SELECT 1 FROM schedule s WHERE s.classroom_id = c.id AND s.date = :date AND s.is_occupied = 1'
    ];
    $params = ['date' => $date];

    if ($pairNumber) {
        $where[0] .= ' AND s.pair_number = :pair_number';
        $params['pair_number'] = (int)$pairNumber;
    }
    $where[0] .= ')';

    // Дополнительные фильтры
    $filters = ['building', 'room_type', 'has_projector', 'has_speakers'];
    foreach ($filters as $f) {
        if (isset($_GET[$f]) && $_GET[$f] !== '') {
            $where[] = "c.{$f} = :{$f}";
            $params[$f] = $_GET[$f];
        }
    }
    if (isset($_GET['seats_min']) && $_GET['seats_min'] !== '') {
        $where[] = 'c.seats >= :seats_min';
        $params['seats_min'] = (int)$_GET['seats_min'];
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare("SELECT c.* FROM classrooms c {$whereSQL} ORDER BY c.building, c.room_number");
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}