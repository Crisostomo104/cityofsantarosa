<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// NOTE: matching the existing action/delete_attachment.php convention, this
// endpoint does not currently check a CSRF token since the resident-side
// fetch() calls don't send one. Relies on session-cookie auth + ownership
// check below. See the closing summary for a note on hardening this later.

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid request id']);
    exit;
}

$currentUser = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT request_id, user_id, status, payment_status, payment_amount, refund_status, refund_requested_at
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

// Only the owner of the request can request a refund for it
if ((int) $request['user_id'] !== $currentUser) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

// Must be a rejected, paid request that hasn't already been requested/refunded
if (strtolower($request['status']) !== 'rejected') {
    echo json_encode(['success' => false, 'error' => 'Only rejected requests are eligible for a refund']);
    exit;
}

if ($request['payment_status'] !== 'paid' || (float) $request['payment_amount'] <= 0) {
    echo json_encode(['success' => false, 'error' => 'This request has no paid payment to refund']);
    exit;
}

if (!empty($request['refund_requested_at']) || $request['refund_status'] !== 'none') {
    echo json_encode(['success' => false, 'error' => 'A refund has already been requested or processed for this request']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE requests
    SET refund_requested_at = NOW(), refund_requested_by = ?
    WHERE request_id = ?
");
$stmt->execute([$currentUser, $id]);

log_action(
    $pdo,
    $currentUser,
    'Refund Requested',
    'REQ-00' . $id,
    'Resident requested a refund for their rejected, paid request'
);

echo json_encode(['success' => true]);
exit;