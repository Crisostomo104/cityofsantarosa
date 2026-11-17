<?php
/*
 * POST /action/delete_all_notifications.php
 * Deletes all notifications for the current user.
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
ensure_notifications_table($pdo);
if (($_SESSION['user_type'] ?? '') === 'city_administrator') {
    $stmt = $pdo->prepare("
        DELETE FROM notifications
        WHERE user_id = ?
          AND message LIKE 'New barangay employee account request%'
          AND link = '../dashboards/city_administrator?panel=approvals'
    ");
    $stmt->execute([$user_id]);
} else {
    delete_all_notifications($pdo, $user_id);
}

echo json_encode(['success' => true]);
