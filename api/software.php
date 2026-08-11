<?php
/**
 * API программного обеспечения (CRUD)
 * GET    /api/software          — список (с фильтрами)
 * GET    /api/software/{id}     — одна запись
 * POST   /api/software          — создать (admin)
 * PUT    /api/software/{id}     — обновить (admin)
 * DELETE /api/software/{id}     — удалить (admin)
 */

function handleSoftware(string $method, string $action, ?int $id): void {
    if ($action === 'truncate') {
        if ($method !== 'POST') jsonError('Метод не поддерживается', 405);
        requireAdmin();
        truncateSoftware();
        return;
    }

    if ($action === 'buildings') {
        if ($method !== 'GET') jsonError('Метод не поддерживается', 405);
        listSoftwareBuildings();
        return;
    }

    switch ($method) {
        case 'GET':
            if ($id !== null) {
                getSoftware($id);
            } else {
                listSoftware();
            }
            break;
        case 'POST':
            requireAdmin();
            createSoftware();
            break;
        case 'PUT':
            requireAdmin();
            if ($id === null) jsonError('ID записи обязателен');
            updateSoftware($id);
            break;
        case 'DELETE':
            requireAdmin();
            if ($id === null) jsonError('ID записи обязателен');
            deleteSoftware($id);
            break;
        default:
            jsonError('Метод не поддерживается', 405);
    }
}

function truncateSoftware(): void {
    $db = getDB();
    $db->exec('DELETE FROM software');
    jsonSuccess(null, 'Таблица ПО очищена');
}

function listSoftwareBuildings(): void {
    $db = getDB();
    $stmt = $db->query("SELECT DISTINCT building FROM software WHERE building IS NOT NULL AND building != '' ORDER BY building");
    jsonSuccess($stmt->fetchAll(PDO::FETCH_COLUMN));
}

function listSoftware(): void {
    $db = getDB();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    if (!empty($_GET['building'])) {
        $where[] = 's.building = :building';
        $params['building'] = $_GET['building'];
    }
    if (!empty($_GET['room_number'])) {
        $where[] = 's.room_number LIKE :room_number';
        $params['room_number'] = '%' . $_GET['room_number'] . '%';
    }
    if (!empty($_GET['name'])) {
        $where[] = 's.name LIKE :name';
        $params['name'] = '%' . $_GET['name'] . '%';
    }
    if (!empty($_GET['classroom_id'])) {
        $where[] = 's.classroom_id = :classroom_id';
        $params['classroom_id'] = (int)$_GET['classroom_id'];
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare("SELECT COUNT(*) FROM software s {$whereSQL}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("SELECT s.* FROM software s {$whereSQL} ORDER BY s.building, s.room_number LIMIT {$perPage} OFFSET {$offset}");
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

function getSoftware(int $id): void {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM software WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch();
    if (!$item) jsonError('Запись ПО не найдена', 404);
    jsonSuccess($item);
}

function createSoftware(): void {
    $data = getJsonInput();
    validateRequired($data, ['name']);

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO software (classroom_id, room_number, building, name, notes)
        VALUES (:classroom_id, :room_number, :building, :name, :notes)");
    $stmt->execute([
        'classroom_id' => $data['classroom_id'] ?? null,
        'room_number'  => $data['room_number'] ?? null,
        'building'     => $data['building'] ?? null,
        'name'         => $data['name'],
        'notes'        => $data['notes'] ?? null,
    ]);

    $id = $db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM software WHERE id = :id');
    $stmt->execute(['id' => $id]);
    jsonSuccess($stmt->fetch(), 'Запись ПО создана');
}

function updateSoftware(int $id): void {
    $data = getJsonInput();
    $db = getDB();
    $check = $db->prepare('SELECT id FROM software WHERE id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) jsonError('Запись ПО не найдена', 404);

    $fields = ['classroom_id', 'room_number', 'building', 'name', 'notes'];
    $set = [];
    $params = ['id' => $id];

    foreach ($fields as $f) {
        if (array_key_exists($f, $data)) {
            $set[] = "`{$f}` = :{$f}";
            $params[$f] = ($f === 'classroom_id') ? (int)$data[$f] : $data[$f];
        }
    }
    if (empty($set)) jsonError('Нет данных для обновления');

    $db->prepare('UPDATE software SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);

    $stmt = $db->prepare('SELECT * FROM software WHERE id = :id');
    $stmt->execute(['id' => $id]);
    jsonSuccess($stmt->fetch(), 'Запись ПО обновлена');
}

function deleteSoftware(int $id): void {
    $db = getDB();
    $check = $db->prepare('SELECT id FROM software WHERE id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) jsonError('Запись ПО не найдена', 404);
    $db->prepare('DELETE FROM software WHERE id = :id')->execute(['id' => $id]);
    jsonSuccess(null, 'Запись ПО удалена');
}