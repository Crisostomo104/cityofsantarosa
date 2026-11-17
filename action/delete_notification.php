<?php
/*
 * POST /action/delete_notification.php
 * Deletes a single notification for the current user.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../core/notifications.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$notif_raw = $_POST['id'] ?? $_GET['id'] ?? '';

if (str_starts_with($notif_raw, 'req_')) {
    $req_id = (int) substr($notif_raw, 4);
    if ($req_id > 0) {
        $stmt = $pdo->prepare("UPDATE requests SET is_viewed = 1 WHERE request_id = ?");
        $stmt->execute([$req_id]);
    }
} else {
    $notif_id = (int) $notif_raw;
if ($notif_id > 0) {
    delete_notification($pdo, $notif_id, $user_id);
}

echo json_encode(['success' => true]);

}