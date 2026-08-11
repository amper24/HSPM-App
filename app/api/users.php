<?php
/**
 * API управления пользователями (только admin)
 * GET    /api/users          — список
 * POST   /api/users          — создать
 * PUT    /api/users/{id}     — обновить
 * DELETE /api/users/{id}     — удалить
 */

function handleUsers(string $method, string $action, ?int $id): void {
    requireAdmin();

    switch ($method) {
        case 'GET':
            listUsers();
            break;
        case 'POST':
            createUser();
            break;
        case 'PUT':
            if ($id === null) jsonError('ID пользователя обязателен');
            updateUser($id);
            break;
        case 'DELETE':
            if ($id === null) jsonError('ID пользователя обязателен');
            deleteUser($id);
            break;
        default:
            jsonError('Метод не поддерживается', 405);
    }
}

function listUsers(): void {
    $db = getDB();
    $stmt = $db->query('SELECT id, username, role, full_name, created_at, updated_at FROM users ORDER BY id');
    jsonSuccess($stmt->fetchAll());
}

function createUser(): void {
    $data = getJsonInput();
    validateRequired($data, ['username', 'password', 'role']);

    if (!in_array($data['role'], ['admin', 'user'])) {
        jsonError('Роль должна быть admin или user');
    }

    $db = getDB();
    // Проверка уникальности
    $check = $db->prepare('SELECT id FROM users WHERE username = :u');
    $check->execute(['u' => $data['username']]);
    if ($check->fetch()) {
        jsonError('Пользователь с таким логином уже существует');
    }

    $hash = password_hash($data['password'], PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (username, password, role, full_name) VALUES (:u, :p, :r, :fn)');
    $stmt->execute([
        'u'  => $data['username'],
        'p'  => $hash,
        'r'  => $data['role'],
        'fn' => $data['full_name'] ?? null,
    ]);

    $id = $db->lastInsertId();
    $stmt = $db->prepare('SELECT id, username, role, full_name, created_at FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    jsonSuccess($stmt->fetch(), 'Пользователь создан');
}

function updateUser(int $id): void {
    $data = getJsonInput();
    $db = getDB();

    $check = $db->prepare('SELECT id FROM users WHERE id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) jsonError('Пользователь не найден', 404);

    $set = [];
    $params = ['id' => $id];

    if (isset($data['username'])) {
        $set[] = 'username = :u';
        $params['u'] = $data['username'];
    }
    if (isset($data['password']) && !empty($data['password'])) {
        $set[] = 'password = :p';
        $params['p'] = password_hash($data['password'], PASSWORD_BCRYPT);
    }
    if (isset($data['role'])) {
        if (!in_array($data['role'], ['admin', 'user'])) jsonError('Роль должна быть admin или user');
        $set[] = 'role = :r';
        $params['r'] = $data['role'];
    }
    if (isset($data['full_name'])) {
        $set[] = 'full_name = :fn';
        $params['fn'] = $data['full_name'];
    }

    if (empty($set)) jsonError('Нет данных для обновления');

    $db->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);

    $stmt = $db->prepare('SELECT id, username, role, full_name, created_at, updated_at FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    jsonSuccess($stmt->fetch(), 'Пользователь обновлен');
}

function deleteUser(int $id): void {
    // Нельзя удалить самого себя
    if ($id === (int)$_SESSION['user_id']) {
        jsonError('Нельзя удалить самого себя');
    }

    $db = getDB();
    $check = $db->prepare('SELECT id FROM users WHERE id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) jsonError('Пользователь не найден', 404);

    $db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
    jsonSuccess(null, 'Пользователь удален');
}