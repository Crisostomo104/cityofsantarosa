<?php
/*
 * POST /action/mark_notifications_read.php
 * Marks all notifications as read for the current user.
 * Called when user clicks the notification bell.
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

// Mark one notification or all for the current user.
$notif_raw = (string)($_POST['id'] ?? $_GET['id'] ?? '');

if (preg_match('/^req_(\d+)$/', $notif_raw, $matches)
    && ($_SESSION['user_type'] ?? '') === 'employee') {
    $stmt = $pdo->prepare("
        UPDATE requests r
        INNER JOIN users u ON r.user_id = u.user_id
        SET r.is_viewed = 1
        WHERE r.request_id = ? AND u.barangay = ? AND r.status = 'Pending'
    ");
    $stmt->execute([(int)$matches[1], $_SESSION['barangay'] ?? '']);
} elseif (ctype_digit($notif_raw) && (int)$notif_raw > 0) {
    mark_notification_read($pdo, (int)$notif_raw, $user_id);
} elseif ($notif_raw === '') {
    if (($_SESSION['user_type'] ?? '') === 'city_administrator') {
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ? AND is_read = 0
              AND message LIKE 'New barangay employee account request%'
              AND link = '../dashboards/city_administrator?panel=approvals'
        ");
        $stmt->execute([$user_id]);
    } else {
        mark_all_notifications_read($pdo, $user_id);
    }

    if (($_SESSION['user_type'] ?? '') === 'employee') {
        $stmt = $pdo->prepare("
            UPDATE requests r
            INNER JOIN users u ON r.user_id = u.user_id
            SET r.is_viewed = 1
            WHERE u.barangay = ? AND r.status = 'Pending' AND (r.is_viewed = 0 OR r.is_viewed IS NULL)
        ");
        $stmt->execute([$_SESSION['barangay'] ?? '']);
    }
}

echo json_encode(['success' => true]);
