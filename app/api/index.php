<?php
/**
 * Главный API роутер
 * Все запросы проксируются сюда через .htaccess
 */
require_once __DIR__ . '/config.php';

// Разбор URI
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api';

// Убираем query string и базовый путь
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace($basePath, '', $path);
$path = trim($path, '/');
$segments = array_values(array_filter(explode('/', $path)));

$method = $_SERVER['REQUEST_METHOD'];

// Роутинг
$resource = $segments[0] ?? '';
$id = isset($segments[1]) ? (int)$segments[1] : null;
$action = $segments[1] ?? '';

// Маршруты
try {
    switch ($resource) {
        case 'auth':
            require_once __DIR__ . '/auth.php';
            handleAuth($method, $action, $id);
            break;

        case 'teachers':
            require_once __DIR__ . '/teachers.php';
            handleTeachers($method, $action, $id);
            break;

        case 'classrooms':
            require_once __DIR__ . '/classrooms.php';
            handleClassrooms($method, $action, $id);
            break;

        case 'schedule':
            require_once __DIR__ . '/schedule.php';
            handleSchedule($method, $action, $id);
            break;

        case 'software':
            require_once __DIR__ . '/software.php';
            handleSoftware($method, $action, $id);
            break;

        case 'import':
            require_once __DIR__ . '/import.php';
            handleImport($method, $action);
            break;

        case 'export':
            require_once __DIR__ . '/export.php';
            handleExport($method, $action);
            break;

        case 'users':
            require_once __DIR__ . '/users.php';
            handleUsers($method, $action, $id);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Метод API не найден']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка базы данных: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()]);
}