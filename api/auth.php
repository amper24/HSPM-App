<?php
/**
 * API аутентификации
 * POST /api/auth/login  — вход
 * POST /api/auth/logout — выход
 * GET  /api/auth/me     — текущий пользователь
 */

function handleAuth(string $method, string $action, ?int $id): void {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') jsonError('Метод не поддерживается', 405);
            login();
            break;
        case 'logout':
            if ($method !== 'POST') jsonError('Метод не поддерживается', 405);
            logout();
            break;
        case 'me':
            if ($method !== 'GET') jsonError('Метод не поддерживается', 405);
            me();
            break;
        default:
            jsonError('Неизвестный action: ' . $action, 404);
    }
}

function login(): void {
    $data = getJsonInput();
    validateRequired($data, ['username', 'password']);

    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, password, role, full_name FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $data['username']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($data['password'], $user['password'])) {
        jsonError('Неверный логин или пароль', 401);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['full_name'];

    jsonSuccess([
        'id'       => $user['id'],
        'username' => $user['username'],
        'role'     => $user['role'],
        'full_name'=> $user['full_name'],
    ], 'Вход выполнен успешно');
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
    jsonSuccess(null, 'Выход выполнен');
}

function me(): void {
    if (empty($_SESSION['user_id'])) {
        jsonSuccess(null, 'Не авторизован');
    }
    jsonSuccess([
        'id'       => $_SESSION['user_id'],
        'role'     => $_SESSION['user_role'],
        'full_name'=> $_SESSION['user_name'] ?? '',
    ]);
}
