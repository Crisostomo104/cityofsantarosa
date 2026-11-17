<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  DATABASE CONNECTION — reads credentials from .env
 *  Never store plaintext credentials in version-controlled files.
 * ─────────────────────────────────────────────────────────────
 */

// ── Load .env ──────────────────────────────────────────────────
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            if (!isset($_ENV[$key])) {
                $_ENV[$key]   = $value;
                putenv("$key=$value");
            }
        }
    }
}

// ── DB Credentials (from .env, fallback for local dev) ─────────
$host    = $_ENV['DB_HOST'] ?? 'localhost';
$db      = $_ENV['DB_NAME'] ?? 'e_inquiry_db';
$user    = $_ENV['DB_USER'] ?? 'root';
$pass    = $_ENV['DB_PASS'] ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Log real error server-side only — never expose to browser
    error_log('[E-Inquire DB Error] ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Service temporarily unavailable. Please try again later.',
    ]);
    exit;
}