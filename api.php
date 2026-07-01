<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$dataDir = __DIR__ . '/data';
$usersFile = $dataDir . '/users.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}

function json_response(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'Niepoprawny JSON.'], 400);
    }
    return $data;
}

function load_json_file(string $file, array $fallback = []): array {
    if (!file_exists($file)) {
        return $fallback;
    }
    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return $fallback;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

function save_json_file(string $file, array $data): void {
    $tmp = $file . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        json_response(['ok' => false, 'error' => 'Nie można zakodować danych.'], 500);
    }
    $fp = fopen($tmp, 'wb');
    if (!$fp) {
        json_response(['ok' => false, 'error' => 'Brak uprawnień zapisu w katalogu data.'], 500);
    }
    flock($fp, LOCK_EX);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    rename($tmp, $file);
}

function normalize_email(string $email): string {
    return mb_strtolower(trim($email));
}

function valid_email(string $email): bool {
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function progress_file(string $userId): string {
    return __DIR__ . '/data/progress_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $userId) . '.json';
}

function require_user(): array {
    global $usersFile;
    if (empty($_SESSION['user_id'])) {
        json_response(['ok' => false, 'error' => 'Nie jesteś zalogowany.'], 401);
    }
    $users = load_json_file($usersFile, ['users' => []]);
    foreach ($users['users'] as $email => $user) {
        if (($user['id'] ?? '') === $_SESSION['user_id']) {
            return ['email' => $email, 'id' => $user['id']];
        }
    }
    $_SESSION = [];
    session_destroy();
    json_response(['ok' => false, 'error' => 'Sesja wygasła.'], 401);
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'register') {
        $body = read_body();
        $email = normalize_email((string)($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if (!valid_email($email)) {
            json_response(['ok' => false, 'error' => 'Niepoprawny email.'], 400);
        }
        if (strlen($password) < 6) {
            json_response(['ok' => false, 'error' => 'Hasło musi mieć minimum 6 znaków.'], 400);
        }

        $users = load_json_file($usersFile, ['users' => []]);
        if (isset($users['users'][$email])) {
            json_response(['ok' => false, 'error' => 'Takie konto już istnieje.'], 409);
        }

        $id = bin2hex(random_bytes(16));
        $users['users'][$email] = [
            'id' => $id,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('c')
        ];
        save_json_file($usersFile, $users);

        $_SESSION['user_id'] = $id;
        json_response(['ok' => true, 'user' => ['email' => $email, 'id' => $id]]);
    }

    if ($action === 'login') {
        $body = read_body();
        $email = normalize_email((string)($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');

        $users = load_json_file($usersFile, ['users' => []]);
        $user = $users['users'][$email] ?? null;

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            json_response(['ok' => false, 'error' => 'Błędny email albo hasło.'], 401);
        }

        $_SESSION['user_id'] = $user['id'];
        json_response(['ok' => true, 'user' => ['email' => $email, 'id' => $user['id']]]);
    }

    if ($action === 'me') {
        if (empty($_SESSION['user_id'])) {
            json_response(['ok' => true, 'user' => null]);
        }
        $user = require_user();
        json_response(['ok' => true, 'user' => $user]);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        json_response(['ok' => true]);
    }

    if ($action === 'load_progress') {
        $user = require_user();
        $file = progress_file($user['id']);
        $progress = load_json_file($file, [
            'wrong' => new stdClass(),
            'stats' => new stdClass(),
            'saved_at' => null
        ]);
        json_response(['ok' => true, 'progress' => $progress]);
    }

    if ($action === 'save_progress') {
        $user = require_user();
        $body = read_body();

        $progress = [
            'wrong' => is_array($body['wrong'] ?? null) ? $body['wrong'] : [],
            'stats' => is_array($body['stats'] ?? null) ? $body['stats'] : [],
            'saved_at' => date('c')
        ];

        save_json_file(progress_file($user['id']), $progress);
        json_response(['ok' => true, 'saved_at' => $progress['saved_at']]);
    }

    json_response(['ok' => false, 'error' => 'Nieznana akcja API.'], 404);

} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Błąd serwera: ' . $e->getMessage()], 500);
}
