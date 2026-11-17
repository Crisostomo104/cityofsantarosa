<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/otp_helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

function otp_response(bool $success, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    otp_response(false, 'Request unavailable.', 405);
}

csrf_verify();

if (!rate_limit('otp_send', 5, 300)) {
    otp_response(false, 'Please wait before trying again.', 429);
}

$pendingUser = $_SESSION['pending_user'] ?? null;
$email = strtolower(trim($_POST['email'] ?? ''));
$phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');

if (preg_match('/^09\d{9}$/', $phone)) {
    $phone = '63' . substr($phone, 1);
}

if (
    !$pendingUser ||
    !$email ||
    !$phone ||
    !hash_equals($pendingUser['email'] ?? '', $email) ||
    !hash_equals($pendingUser['phone'] ?? '', $phone)
) {
    otp_response(false, 'Request unavailable.', 404);
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$apikey = $_ENV['SEMAPHORE_API_KEY'] ?? '';

$otp = random_int(100000, 999999);
$expiresAt = time() + 120;

$stmt = $pdo->prepare("
    INSERT INTO otp_requests (phone, otp_code, expires_at, attempts, locked_until)
    VALUES (?, ?, ?, 0, NULL)
    ON DUPLICATE KEY UPDATE
        otp_code = VALUES(otp_code),
        expires_at = VALUES(expires_at),
        attempts = 0,
        locked_until = NULL
");
$stmt->execute([$phone, $otp, $expiresAt]);

$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expire'] = $expiresAt;
$_SESSION['otp_last_sent'] = time();

$result = send_sms($phone, $otp, $apikey);
if (!$result['success']) {
    error_log('[OTP] Send failed: ' . ($result['error'] ?? 'unknown error'));
    otp_response(false, 'Unable to send OTP right now.', 502);
}

otp_response(true, 'OTP sent.');
