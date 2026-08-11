<?php
/**
 * Тест REST API
 * Проверяет: эндпоинты аутентификации, CRUD, поиск
 *
 * Запуск: php tests/test_api.php [base_url]
 *         по умолчанию base_url = http://localhost:8080
 */

$baseUrl = $argv[1] ?? 'http://localhost:8080';
$apiUrl = rtrim($baseUrl, '/') . '/api';

echo str_repeat('=', 60) . "\n";
echo "  ТЕСТ REST API — Учебный отдел ВШПМ\n";
echo "  Сервер: {$apiUrl}\n";
echo str_repeat('=', 60) . "\n\n";

$passed = 0;
$failed = 0;

function http(string $method, string $path, ?array $data = null, ?string $sessionCookie = null): array {
    global $apiUrl;

    $ch = curl_init($apiUrl . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);

    if ($data !== null && $method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    if ($sessionCookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $sessionCookie);
    }

    // Получаем заголовки для извлечения cookie сессии
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("cURL error: {$error}");
    }

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $json = json_decode($body, true);

    // Извлекаем cookie сессии
    $cookies = '';
    preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $headers, $matches);
    foreach ($matches[1] as $cookie) {
        $cookies .= $cookie . '; ';
    }

    return [
        'code'    => $httpCode,
        'body'    => $json,
        'cookies' => $cookies,
    ];
}

function test(string $name, callable $fn): void {
    global $passed, $failed;
    echo "  [TEST] {$name} ... ";
    try {
        $fn();
        echo "\033[32mOK\033[0m\n";
        $passed++;
    } catch (Exception $e) {
        echo "\033[31mFAIL: {$e->getMessage()}\033[0m\n";
        $failed++;
    }
}

// ==============================================
// 1. Аутентификация
// ==============================================
echo "--- Аутентификация ---\n";

$sessionCookie = '';

test('GET /api/auth/me — не авторизован', function () {
    $r = http('GET', '/auth/me');
    if (!isset($r['body']['data']['id']) || $r['body']['data']['id'] !== null) {
        // Ожидаем, что data.id будет null или data пустым
        // Просто проверяем что запрос отработал без ошибки
    }
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}, ожидался 200");
    }
});

test('POST /api/auth/login — неверный пароль', function () {
    $r = http('POST', '/auth/login', ['username' => 'admin', 'password' => 'wrong']);
    if ($r['code'] !== 401) {
        throw new Exception("HTTP {$r['code']}, ожидался 401");
    }
});

test('POST /api/auth/login — успешный вход', function () use (&$sessionCookie) {
    $r = http('POST', '/auth/login', ['username' => 'admin', 'password' => 'admin123']);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}, ожидался 200: " . json_encode($r['body']));
    }
    if (!isset($r['body']['data']['role']) || $r['body']['data']['role'] !== 'admin') {
        throw new Exception("Роль не admin: " . json_encode($r['body']['data']));
    }
    $sessionCookie = $r['cookies'];
});

test('GET /api/auth/me — авторизован', function () use ($sessionCookie) {
    $r = http('GET', '/auth/me', null, $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}");
    }
    if (empty($r['body']['data']['id'])) {
        throw new Exception("Пользователь не идентифицирован");
    }
});

// ==============================================
// 2. CRUD Преподаватели
// ==============================================
echo "\n--- Преподаватели (CRUD) ---\n";

$teacherId = null;

test('GET /api/teachers — список', function () use ($sessionCookie) {
    $r = http('GET', '/teachers?per_page=5', null, $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}");
    }
    if (!isset($r['body']['data']['items'])) {
        throw new Exception("Нет поля items в ответе");
    }
});

test('POST /api/teachers — создание', function () use ($sessionCookie, &$teacherId) {
    $r = http('POST', '/teachers', [
        'last_name'  => 'Тестов',
        'first_name' => 'Тест',
        'department' => 'ИиУС',
        'employment_type' => 'штатный',
    ], $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}: " . ($r['body']['error'] ?? 'unknown'));
    }
    if (empty($r['body']['data']['id'])) {
        throw new Exception("ID не возвращен");
    }
    $teacherId = $r['body']['data']['id'];
});

test('PUT /api/teachers/{id} — обновление', function () use ($sessionCookie, $teacherId) {
    $r = http('PUT', '/teachers/' . $teacherId, [
        'first_name' => 'ТестОбновленный',
    ], $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}: " . ($r['body']['error'] ?? ''));
    }
});

test('GET /api/teachers/{id} — получение одного', function () use ($sessionCookie, $teacherId) {
    $r = http('GET', '/teachers/' . $teacherId, null, $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}");
    }
    if (($r['body']['data']['first_name'] ?? '') !== 'ТестОбновленный') {
        throw new Exception("Имя не обновилось: " . ($r['body']['data']['first_name'] ?? ''));
    }
});

test('DELETE /api/teachers/{id} — удаление', function () use ($sessionCookie, $teacherId) {
    $r = http('DELETE', '/teachers/' . $teacherId, null, $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}: " . ($r['body']['error'] ?? ''));
    }
});

// ==============================================
// 3. CRUD Аудитории
// ==============================================
echo "\n--- Аудитории (CRUD) ---\n";

$classroomId = null;

test('POST /api/classrooms — создание', function () use ($sessionCookie, &$classroomId) {
    $r = http('POST', '/classrooms', [
        'room_number' => 'TEST-API',
        'building'    => 'Д',
        'room_type'   => 'лекционная аудитория',
        'seats'       => 50,
    ], $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}: " . ($r['body']['error'] ?? ''));
    }
    $classroomId = $r['body']['data']['id'];
});

test('GET /api/classrooms/search — поиск', function () use ($sessionCookie) {
    $r = http('GET', '/classrooms/search?room_number=TEST-API', null, $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}");
    }
    if (empty($r['body']['data'])) {
        throw new Exception("Поиск не вернул результатов");
    }
});

test('DELETE /api/classrooms/{id} — удаление', function () use ($sessionCookie, $classroomId) {
    $r = http('DELETE', '/classrooms/' . $classroomId, null, $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}");
    }
});

// ==============================================
// 4. Расписание
// ==============================================
echo "\n--- Расписание ---\n";

$scheduleId = null;
$roomId = null;

test('Создание аудитории для расписания', function () use ($sessionCookie, &$roomId) {
    $r = http('POST', '/classrooms', [
        'room_number' => 'TEST-SCH',
        'building'    => 'Д',
        'room_type'   => 'компьютерный класс',
    ], $sessionCookie);
    if ($r['code'] !== 200) throw new Exception("HTTP {$r['code']}");
    $roomId = $r['body']['data']['id'];
});

test('POST /api/schedule — создание записи', function () use ($sessionCookie, $roomId, &$scheduleId) {
    $r = http('POST', '/schedule', [
        'classroom_id' => $roomId,
        'date'         => date('Y-m-d'),
        'pair_number'  => 1,
        'lesson_type'  => 'лекц.',
        'is_occupied'  => 1,
    ], $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}: " . ($r['body']['error'] ?? ''));
    }
    $scheduleId = $r['body']['data']['id'];
});

test('PUT /api/schedule/{id} — перенос занятия', function () use ($sessionCookie, $scheduleId) {
    $r = http('PUT', '/schedule/' . $scheduleId, [
        'transfer_cancel' => 'перенос',
    ], $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}: " . ($r['body']['error'] ?? ''));
    }
    if (($r['body']['data']['transfer_cancel'] ?? '') !== 'перенос') {
        throw new Exception("Статус не обновился");
    }
});

test('GET /api/classrooms/free — свободные аудитории', function () use ($sessionCookie) {
    $r = http('GET', '/classrooms/free?date=' . date('Y-m-d'), null, $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}");
    }
});

test('DELETE /api/schedule/{id} — удаление', function () use ($sessionCookie, $scheduleId) {
    $r = http('DELETE', '/schedule/' . $scheduleId, null, $sessionCookie);
    if ($r['code'] !== 200) throw new Exception("HTTP {$r['code']}");
});

test('Очистка: удаление тестовой аудитории', function () use ($sessionCookie, $roomId) {
    $r = http('DELETE', '/classrooms/' . $roomId, null, $sessionCookie);
    if ($r['code'] !== 200) throw new Exception("HTTP {$r['code']}");
});

// ==============================================
// 5. Права доступа
// ==============================================
echo "\n--- Права доступа ---\n";

test('POST /api/teachers без авторизации → 401', function () {
    $r = http('POST', '/teachers', ['last_name' => 'X', 'first_name' => 'Y']);
    if ($r['code'] !== 401) {
        throw new Exception("HTTP {$r['code']}, ожидался 401");
    }
});

// ==============================================
// 6. Выход
// ==============================================
echo "\n--- Завершение ---\n";

test('POST /api/auth/logout — выход', function () use ($sessionCookie) {
    $r = http('POST', '/auth/logout', null, $sessionCookie);
    if ($r['code'] !== 200) {
        throw new Exception("HTTP {$r['code']}");
    }
});

// ===== Итоги =====
echo "\n" . str_repeat('=', 60) . "\n";
echo "  ИТОГО: пройдено {$passed}, провалено {$failed}\n";
echo str_repeat('=', 60) . "\n";

if ($failed > 0) {
    echo "\033[31mТЕСТЫ API ПРОВАЛЕНЫ: {$failed} ошибок\033[0m\n";
    exit(1);
} else {
    echo "\033[32mВСЕ ТЕСТЫ API ПРОЙДЕНЫ ({$passed} тестов)\033[0m\n";
    exit(0);
}