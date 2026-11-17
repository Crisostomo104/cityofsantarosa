<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/notifications.php';

check_login_redirect(['employee']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

csrf_verify();

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid request id']);
    exit;
}

$employeeId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT request_id, user_id, refund_status, refund_requested_at
    FROM requests
    WHERE request_id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo json_encode(['success' => false, 'error' => 'Request not found']);
    exit;
}

if (empty($request['refund_requested_at']) || $request['refund_status'] !== 'none') {
    echo json_encode(['success' => false, 'error' => 'This request has no pending refund request']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE requests
    SET refund_status = 'failed', refund_processed_at = NOW(), refund_processed_by = ?
    WHERE request_id = ?
");
$stmt->execute([$employeeId, $id]);

create_notification(
    $pdo,
    (int) $request['user_id'],
    '⚠️ Your refund request was reviewed and was not approved. Please visit the barangay office for assistance.',
    '/cityofsantarosa/dashboards/resident'
);

log_action(
    $pdo,
    $employeeId,
    'Refund Denied',
    'REQ-00' . $id,
    'Denied resident refund request'
);

echo json_encode(['success' => true]);
exit;
