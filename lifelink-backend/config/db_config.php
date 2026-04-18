<?php

// ── Load .env ──────────────────────────────────────────────────────────────
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($val));
        $_ENV[trim($key)] = trim($val);
    }
}

$db_host         = getenv('DB_HOST')         ?: '127.0.0.1';
$db_user         = getenv('DB_USER')         ?: 'root';
$db_pass         = getenv('DB_PASS')         ?: '';
$db_name         = getenv('DB_NAME')         ?: 'lifelink_db';
$db_port         = (int)(getenv('DB_PORT')   ?: 3306);
$internal_secret = getenv('INTERNAL_SECRET') ?: '';

// ── CORS: allow only localhost origins ─────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('/^https?:\/\/localhost(:\d+)?$/', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Connect ────────────────────────────────────────────────────────────────
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB Connection Failed"]);
    exit;
}

$conn->set_charset("utf8mb4");
?>