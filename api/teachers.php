<?php
/**
 * API преподавателей (CRUD + поиск)
 * GET    /api/teachers          — список (с фильтрами)
 * GET    /api/teachers/{id}     — один преподаватель
 * POST   /api/teachers          — создать (admin)
 * PUT    /api/teachers/{id}     — обновить (admin)
 * DELETE /api/teachers/{id}     — удалить (admin)
 * GET    /api/teachers/search   — поиск по ФИО, кафедре, форме занятости
 */

function handleTeachers(string $method, string $action, ?int $id): void {
    if ($action === 'search') {
        if ($method !== 'GET') jsonError('Метод не поддерживается', 405);
        searchTeachers();
        return;
    }

    if ($action === 'truncate') {
        if ($method !== 'POST') jsonError('Метод не поддерживается', 405);
        requireAdmin();
        truncateTeachers();
        return;
    }

    if ($action === 'departments') {
        if ($method !== 'GET') jsonError('Метод не поддерживается', 405);
        listDepartments();
        return;
    }

    if ($action === 'degrees') {
        if ($method !== 'GET') jsonError('Метод не поддерживается', 405);
        listDegrees();
        return;
    }

    if ($action === 'titles') {
        if ($method !== 'GET') jsonError('Метод не поддерживается', 405);
        listTitles();
        return;
    }

    if ($action === 'employment-types') {
        if ($method !== 'GET') jsonError('Метод не поддерживается', 405);
        listEmploymentTypes();
        return;
    }

    switch ($method) {
        case 'GET':
            if ($id !== null) {
                getTeacher($id);
            } else {
                listTeachers();
            }
            break;
        case 'POST':
            requireAdmin();
            createTeacher();
            break;
        case 'PUT':
            requireAdmin();
            if ($id === null) jsonError('ID преподавателя обязателен');
            updateTeacher($id);
            break;
        case 'DELETE':
            requireAdmin();
            if ($id === null) jsonError('ID преподавателя обязателен');
            deleteTeacher($id);
            break;
        default:
            jsonError('Метод не поддерживается', 405);
    }
}

function listTeachers(): void {
    $db = getDB();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    if (!empty($_GET['department'])) {
        $where[] = 't.department = :department';
        $params['department'] = $_GET['department'];
    }
    if (!empty($_GET['employment_type'])) {
        $where[] = 't.employment_type = :employment_type';
        $params['employment_type'] = $_GET['employment_type'];
    }
    if (!empty($_GET['degree'])) {
        $where[] = 't.degree = :degree';
        $params['degree'] = $_GET['degree'];
    }
    if (!empty($_GET['title'])) {
        $where[] = 't.title = :title';
        $params['title'] = $_GET['title'];
    }
    if (!empty($_GET['transfer_cancel'])) {
        $where[] = 'EXISTS (SELECT 1 FROM schedule s WHERE s.teacher_id = t.id AND s.transfer_cancel = :transfer_cancel)';
        $params['transfer_cancel'] = $_GET['transfer_cancel'];
    }
    if (!empty($_GET['search'])) {
        $where[] = "(t.last_name LIKE :search OR t.first_name LIKE :search2 OR t.middle_name LIKE :search3)";
        $params['search'] = "%{$_GET['search']}%";
        $params['search2'] = "%{$_GET['search']}%";
        $params['search3'] = "%{$_GET['search']}%";
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Счетчик общего количества
    $countStmt = $db->prepare("SELECT COUNT(*) FROM teachers t {$whereSQL}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("SELECT t.* FROM teachers t {$whereSQL} ORDER BY t.last_name, t.first_name LIMIT {$perPage} OFFSET {$offset}");
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

function getTeacher(int $id): void {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM teachers WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        jsonError('Преподаватель не найден', 404);
    }
    jsonSuccess($teacher);
}

function createTeacher(): void {
    $data = getJsonInput();
    validateRequired($data, ['last_name', 'first_name']);

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO teachers (last_name, first_name, middle_name, position, degree, title, department, employment_type, email, phone, notes)
        VALUES (:last_name, :first_name, :middle_name, :position, :degree, :title, :department, :employment_type, :email, :phone, :notes)");
    $stmt->execute([
        'last_name'       => $data['last_name'],
        'first_name'      => $data['first_name'],
        'middle_name'     => $data['middle_name'] ?? null,
        'position'        => $data['position'] ?? null,
        'degree'          => $data['degree'] ?? null,
        'title'           => $data['title'] ?? null,
        'department'      => $data['department'] ?? null,
        'employment_type' => $data['employment_type'] ?? null,
        'email'           => $data['email'] ?? null,
        'phone'           => $data['phone'] ?? null,
        'notes'           => $data['notes'] ?? null,
    ]);

    $id = $db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM teachers WHERE id = :id');
    $stmt->execute(['id' => $id]);
    jsonSuccess($stmt->fetch(), 'Преподаватель создан');
}

function updateTeacher(int $id): void {
    $data = getJsonInput();

    $db = getDB();
    $check = $db->prepare('SELECT id FROM teachers WHERE id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) {
        jsonError('Преподаватель не найден', 404);
    }

    $fields = ['last_name', 'first_name', 'middle_name', 'position', 'degree', 'title', 'department', 'employment_type', 'email', 'phone', 'notes'];
    $setClauses = [];
    $params = ['id' => $id];

    foreach ($fields as $field) {
        if (array_key_exists($field, $data)) {
            $setClauses[] = "`{$field}` = :{$field}";
            $params[$field] = $data[$field];
        }
    }

    if (empty($setClauses)) {
        jsonError('Нет данных для обновления');
    }

    $sql = 'UPDATE teachers SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
    $db->prepare($sql)->execute($params);

    $stmt = $db->prepare('SELECT * FROM teachers WHERE id = :id');
    $stmt->execute(['id' => $id]);
    jsonSuccess($stmt->fetch(), 'Преподаватель обновлен');
}

function deleteTeacher(int $id): void {
    $db = getDB();
    $check = $db->prepare('SELECT id FROM teachers WHERE id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) {
        jsonError('Преподаватель не найден', 404);
    }

    $db->prepare('DELETE FROM teachers WHERE id = :id')->execute(['id' => $id]);
    jsonSuccess(null, 'Преподаватель удален');
}

function truncateTeachers(): void {
    $db = getDB();
    $db->exec('DELETE FROM teachers');
    jsonSuccess(null, 'Таблица преподавателей очищена');
}

function listDepartments(): void {
    $db = getDB();
    $stmt = $db->query("SELECT DISTINCT department FROM teachers WHERE department IS NOT NULL AND department != '' ORDER BY department");
    jsonSuccess($stmt->fetchAll(PDO::FETCH_COLUMN));
}

function listDegrees(): void {
    $db = getDB();
    $stmt = $db->query("SELECT DISTINCT degree FROM teachers WHERE degree IS NOT NULL AND degree != '' ORDER BY degree");
    jsonSuccess($stmt->fetchAll(PDO::FETCH_COLUMN));
}

function listTitles(): void {
    $db = getDB();
    $stmt = $db->query("SELECT DISTINCT title FROM teachers WHERE title IS NOT NULL AND title != '' ORDER BY title");
    jsonSuccess($stmt->fetchAll(PDO::FETCH_COLUMN));
}

function listEmploymentTypes(): void {
    $db = getDB();
    $stmt = $db->query("SELECT DISTINCT employment_type FROM teachers WHERE employment_type IS NOT NULL AND employment_type != '' ORDER BY employment_type");
    jsonSuccess($stmt->fetchAll(PDO::FETCH_COLUMN));
}

function searchTeachers(): void {
    $db = getDB();
    $where = [];
    $params = [];

    // Поиск по ФИО
    if (!empty($_GET['fio'])) {
        $where[] = "CONCAT(t.last_name, ' ', t.first_name, ' ', COALESCE(t.middle_name, '')) LIKE :fio";
        $params['fio'] = '%' . $_GET['fio'] . '%';
    }
    // По кафедре
    if (!empty($_GET['department'])) {
        $where[] = 't.department = :department';
        $params['department'] = $_GET['department'];
    }
    // По форме занятости
    if (!empty($_GET['employment_type'])) {
        $where[] = 't.employment_type = :employment_type';
        $params['employment_type'] = $_GET['employment_type'];
    }
    // По отмене/переносу — через JOIN с расписанием
    if (!empty($_GET['transfer_cancel'])) {
        $where[] = 's.transfer_cancel = :transfer_cancel';
        $params['transfer_cancel'] = $_GET['transfer_cancel'];
        $joinSchedule = true;
    }

    $joinSQL = !empty($joinSchedule) ? 'LEFT JOIN schedule s ON t.id = s.teacher_id' : '';
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT DISTINCT t.* FROM teachers t {$joinSQL} {$whereSQL} ORDER BY t.last_name, t.first_name";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}