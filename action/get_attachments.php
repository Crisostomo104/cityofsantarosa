<?php
/*
 * GET /action/get_attachments.php?request_id=123
 * Returns JSON array of attachments for a request.
 * Used by employee view modal to show uploaded prerequisite files.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/prerequisites.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT session_id FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['session_id'] !== session_id()) {
    session_destroy();
    echo json_encode([]);
    exit;
}

$request_id = intval($_GET['request_id'] ?? 0);

if ($request_id <= 0) {
    echo json_encode([]);
    exit;
}

$attachments = get_request_attachments($pdo, $request_id);

foreach ($attachments as &$attachment) {
    $attachment['duplicate_history'] = [];
    if (!empty($attachment['file_hash'])) {
        $attachment['duplicate_history'] = get_duplicate_attachment_history($pdo, $attachment['file_hash'], $request_id);
    }
}
unset($attachment);

echo json_encode($attachments);
