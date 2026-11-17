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

function otp_response(bool $success, string $message, int $status = 200, array $extra = []): never
{
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    otp_response(false, 'Request unavailable.', 405);
}

csrf_verify();

if (!rate_limit('otp_resend', 5, 300)) {
    otp_response(false, 'Please wait before trying again.', 429);
}

$pendingUser = $_SESSION['pending_user'] ?? null;
$phone = $pendingUser['phone'] ?? null;

if (!$pendingUser || !$phone) {
    otp_response(false, 'Request unavailable.', 404);
}

$otpQuery = $pdo->prepare("SELECT locked_until FROM otp_requests WHERE phone = ? LIMIT 1");
$otpQuery->execute([$phone]);
$otpRequest = $otpQuery->fetch(PDO::FETCH_ASSOC);
if (!$otpRequest) {
    otp_response(false, 'Request unavailable.', 404);
}

if (!empty($otpRequest['locked_until']) && (int)$otpRequest['locked_until'] > time()) {
    $retryAfter = (int)$otpRequest['locked_until'] - time();
    otp_response(false, 'Too many incorrect codes. You can request a new code when the 5-minute lock ends.', 429, [
        'retry_after' => $retryAfter,
        'locked_until' => (int)$otpRequest['locked_until'],
    ]);
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$apikey = $_ENV['SEMAPHORE_API_KEY'] ?? '';

$_SESSION['otp_resend_log'] = array_filter(
    $_SESSION['otp_resend_log'] ?? [],
    static fn ($timestamp) => $timestamp > time() - 300
);

if (count($_SESSION['otp_resend_log']) >= 3) {
    otp_response(false, 'Please wait before trying again.', 429);
}

$lastSent = $_SESSION['otp_last_sent'] ?? 0;
if (time() - $lastSent < 30) {
    otp_response(false, 'Please wait before trying again.', 429);
}

$otp = random_int(100000, 999999);
$expiresAt = time() + 120;

$stmt = $pdo->prepare("
    UPDATE otp_requests
    SET otp_code = ?, expires_at = ?, attempts = 0, locked_until = NULL
    WHERE phone = ?
");
$stmt->execute([$otp, $expiresAt, $phone]);

if ($stmt->rowCount() < 1) {
    otp_response(false, 'Request unavailable.', 404);
}

$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expire'] = $expiresAt;
$_SESSION['otp_last_sent'] = time();
$_SESSION['otp_resend_log'][] = time();

$result = send_sms($phone, $otp, $apikey);
if (!$result['success']) {
    error_log('[OTP] Resend failed: ' . ($result['error'] ?? 'unknown error'));
    otp_response(false, 'Unable to send OTP right now.', 502);
}

otp_response(true, 'OTP sent.', 200, ['expires_at' => $expiresAt]);
