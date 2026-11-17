<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// No full session (includes mid-login / OTP pending): touch nothing.
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Only the device that owns users.session_id may keep the session alive.
$stmt = $pdo->prepare("SELECT session_id FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$dbSid = $stmt->fetchColumn();

if ($dbSid === false || $dbSid === null || $dbSid === '' || $dbSid === 'DEACTIVATED' || $dbSid !== session_id()) {
    echo json_encode([
        'status' => 'unauthorized',
        'reason' => ($dbSid === 'DEACTIVATED') ? 'deactivated' : 'ended',
    ]);
    exit;
}

$pdo->prepare("UPDATE users SET last_active = NOW() WHERE user_id = ?")->execute([$user_id]);
echo json_encode(['status' => 'alive']);