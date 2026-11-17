<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function otp_response(bool $success, string $message = '', int $status = 200, array $extra = []): never
{
    http_response_code($status);
    $payload = ['success' => $success];
    if ($message !== '') {
        $payload['message'] = $message;
    }
    echo json_encode(array_merge($payload, $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    otp_response(false, 'Request unavailable.', 405);
}

csrf_verify();

if (!rate_limit('otp_verify', 10, 300)) {
    otp_response(false, 'Please wait before trying again.', 429);
}

$otp = trim($_POST['otp'] ?? '');
$phone = $_SESSION['pending_user']['phone'] ?? null;

if (!$phone || !preg_match('/^\d{6}$/', $otp)) {
    otp_response(false, 'Invalid verification code.', 400);
}

$stmt = $pdo->prepare("
    SELECT *
    FROM otp_requests
    WHERE phone = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$phone]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    otp_response(false, 'Invalid verification code.', 400);
}

if (!empty($row['locked_until']) && (int)$row['locked_until'] > time()) {
    $retryAfter = (int)$row['locked_until'] - time();
    otp_response(false, 'Too many incorrect codes. You can request a new code when the 5-minute lock ends.', 429, [
        'retry_after' => $retryAfter,
        'locked_until' => (int)$row['locked_until'],
    ]);
}

if ((int)$row['expires_at'] < time()) {
    $pdo->prepare("
        UPDATE otp_requests
        SET attempts = 0, locked_until = NULL
        WHERE phone = ?
    ")->execute([$phone]);

    otp_response(false, 'Invalid verification code.', 400);
}

if (!hash_equals((string)$row['otp_code'], $otp)) {
    $newAttempts = (int)$row['attempts'] + 1;
    $lockedUntil = $newAttempts >= 5 ? time() + (5 * 60) : null;

    $pdo->prepare("
        UPDATE otp_requests
        SET attempts = ?, locked_until = ?
        WHERE phone = ?
    ")->execute([$newAttempts, $lockedUntil, $phone]);

    if ($lockedUntil !== null) {
        otp_response(false, 'Too many incorrect codes. You can request a new code in 5 minutes.', 429, [
            'retry_after' => $lockedUntil - time(),
            'locked_until' => $lockedUntil,
        ]);
    }

    otp_response(false, 'Invalid verification code. ' . (5 - $newAttempts) . ' attempt(s) remaining.', 400, [
        'attempts_remaining' => 5 - $newAttempts,
    ]);
}

$u = $_SESSION['pending_user'];

$stmt = $pdo->prepare("
    INSERT INTO users
    (firstname, middlename, lastname, full_name, barangay, email, phone, password_hash, user_type, created_at, is_verified)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'resident', NOW(), 1)
");

$stmt->execute([
    $u['firstname'],
    $u['middlename'],
    $u['lastname'],
    $u['fullname'],
    $u['barangay'],
    $u['email'],
    $u['phone'],
    $u['password']
]);

$userId = $pdo->lastInsertId();

$pdo->prepare("DELETE FROM otp_requests WHERE phone = ?")->execute([$phone]);

log_action(
    $pdo,
    $userId,
    'User Registration',
    'Registration Form',
    'Account created successfully'
);

unset(
    $_SESSION['pending_user'],
    $_SESSION['otp_code'],
    $_SESSION['otp_expire'],
    $_SESSION['otp_last_sent'],
    $_SESSION['otp_resend_log'],
    $_SESSION['otp_locked_until']
);

otp_response(true);
