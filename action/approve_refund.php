<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/notifications.php';
require_once __DIR__ . '/../core/payments.php';

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
    SELECT request_id, user_id, refund_status, refund_requested_at, payment_amount
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

// This does the actual PayMongo refund call and updates refund_status/payment_status
$refundResult = initiate_refund($pdo, $id, $employeeId);

if (!$refundResult['success']) {
    log_action(
        $pdo,
        $employeeId,
        'Refund Approval Failed',
        'REQ-00' . $id,
        'Attempted to approve refund but it failed: ' . $refundResult['message']
    );
    echo json_encode(['success' => false, 'error' => $refundResult['message']]);
    exit;
}

create_notification(
    $pdo,
    (int) $request['user_id'],
    '💸 Your refund of ' . money_format_php((float) $request['payment_amount']) . ' has been approved and processed.',
    '/cityofsantarosa/dashboards/resident'
);

log_action(
    $pdo,
    $employeeId,
    'Refund Approved',
    'REQ-00' . $id,
    'Approved and processed refund (Ref: ' . $refundResult['refund_id'] . ')'
);

echo json_encode(['success' => true]);
exit;
