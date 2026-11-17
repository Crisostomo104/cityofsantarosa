<?php
/*
 * GET /action/get_notifications.php
 * Returns JSON: { unread: int, items: [...] }
 * Called by the notification bell via AJAX polling.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../core/notifications.php';

$pdo->exec("SET time_zone = '+00:00'");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread' => 0, 'items' => []]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (($_SESSION['user_type'] ?? '') === 'city_administrator') {
    $accountMessagePrefix = 'New barangay employee account request%';
    ensure_notifications_table($pdo);

    // Seed existing pending accounts once per admin session; new submissions
    // generate durable notifications in save_employee.php.
    if (empty($_SESSION['city_admin_account_notifications_seeded'])) {
        $stmt = $pdo->query("
            SELECT user_id, full_name, barangay
            FROM users
            WHERE user_type = 'employee' AND is_verified = 0
        ");
        $insert = $pdo->prepare("
            INSERT INTO notifications (user_id, message, link)
            SELECT ?, ?, ?
            WHERE NOT EXISTS (
                SELECT 1 FROM notifications
                WHERE user_id = ? AND message = ? AND link = ?
            )
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $account) {
            $barangay = trim((string)($account['barangay'] ?? ''));
            $barangayLabel = $barangay !== '' ? ' from ' . $barangay : '';
            $message = 'New barangay employee account request' . $barangayLabel . ': ' . $account['full_name'];
            $link = '../dashboards/city_administrator?panel=approvals';
            $insert->execute([$user_id, $message, $link, $user_id, $message, $link]);
        }
        $_SESSION['city_admin_account_notifications_seeded'] = true;
    }

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = ? AND is_read = 0 AND message LIKE ? AND link = '../dashboards/city_administrator?panel=approvals'
    ");
    $countStmt->execute([$user_id, $accountMessagePrefix]);
    $unread = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT id, message, link, is_read, created_at
        FROM notifications
        WHERE user_id = ? AND message LIKE ? AND link = '../dashboards/city_administrator?panel=approvals'
        ORDER BY created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$user_id, $accountMessagePrefix]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['created_at'] = (new DateTimeImmutable($item['created_at'], new DateTimeZone('UTC')))
            ->format('Y-m-d\TH:i:s\Z');
    }
    unset($item);

    echo json_encode([
        'unread' => $unread,
        'items' => $items,
    ]);
    exit;
}

$unread  = get_unread_count($pdo, $user_id);
$items   = get_notifications($pdo, $user_id, 15);
foreach ($items as &$item) {
    $item['created_at'] = (new DateTimeImmutable($item['created_at'], new DateTimeZone('UTC')))
        ->format('Y-m-d\TH:i:s\Z');
}
unset($item);

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'employee') {
    $barangay = $_SESSION['barangay'] ?? '';
    
    // Fetch unviewed requests
    $stmt = $pdo->prepare("
        SELECT r.request_id, u.full_name, r.document_type, r.created_at
        FROM requests r
        INNER JOIN users u ON r.user_id = u.user_id
        WHERE u.barangay = ? AND r.status = 'Pending' AND (r.is_viewed = 0 OR r.is_viewed IS NULL)
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$barangay]);
    $unviewed = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($unviewed) > 0) {
        $unread += count($unviewed);
        foreach ($unviewed as $req) {
            $items[] = [
                'id' => 'req_' . $req['request_id'],
                'message' => 'New request from ' . $req['full_name'] . ' for ' . $req['document_type'],
                'link' => '../dashboards/employee?panel=requests',
                'is_read' => 0,
                'created_at' => (new DateTimeImmutable($req['created_at'], new DateTimeZone('Asia/Manila')))
                    ->format('Y-m-d\TH:i:sP')
            ];
        }
        
        // Re-sort combined items by created_at descending
        usort($items, function($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });
    }
}

echo json_encode([
    'unread' => $unread,
    'items'  => $items,
]);
