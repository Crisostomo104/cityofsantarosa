<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/request_workflow.php';
require_once __DIR__ . '/../core/notifications.php';
require_once __DIR__ . '/../core/sms_helpers.php';

check_login_redirect(['employee']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['sms_error'] = 'Use the request action button to confirm a claim.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

csrf_verify();
ensure_request_workflow_schema($pdo);

$requestId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$requestId || $requestId < 1) {
    $_SESSION['sms_error'] = 'Invalid request ID.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.request_id, r.user_id, r.full_name, r.contact_number, r.document_type, r.status
    FROM requests r
    INNER JOIN users owner ON owner.user_id = r.user_id
    WHERE r.request_id = ? AND owner.barangay = ?
    LIMIT 1
");
$stmt->execute([$requestId, $_SESSION['barangay'] ?? '']);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    $_SESSION['sms_error'] = 'Request not found.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

if (strcasecmp(trim((string) $request['status']), 'To be Claim') !== 0) {
    $_SESSION['sms_error'] = 'Only requests ready to be claimed can be completed.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare("
        UPDATE requests
        SET status = 'Completed', processed_by = ?, completed_at = NOW()
        WHERE request_id = ? AND status = 'To be Claim'
    ");
    $update->execute([(int) $_SESSION['user_id'], $requestId]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Request status changed before the claim could be recorded.');
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Failed to mark request ' . $requestId . ' as claimed: ' . $exception->getMessage());
    $_SESSION['sms_error'] = 'Unable to confirm the document claim.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

try {
    create_notification(
        $pdo,
        (int) $request['user_id'],
        'Your ' . ($request['document_type'] ?? 'document') . ' request has been marked as claimed.',
        '/cityofsantarosa/dashboards/resident'
    );
} catch (Throwable $exception) {
    error_log('Claim notification failed for request ' . $requestId . ': ' . $exception->getMessage());
}

$_SESSION['sms_success'] = 'Request marked as completed after claim.';
$phone = trim((string) ($request['contact_number'] ?? ''));
if ($phone !== '') {
    $smsResult = sms_send($phone, 'Hello ' . $request['full_name'] . ', your document claim has been recorded. Thank you!');
    if (empty($smsResult['success'])) {
        $_SESSION['sms_error'] = 'Claim recorded, but SMS failed: ' . htmlspecialchars((string) ($smsResult['error'] ?? 'Unknown error'));
    }
}

log_action(
    $pdo,
    (int) $_SESSION['user_id'],
    'Request Claimed',
    'Request #' . $requestId,
    'Recorded document claim for ' . $request['full_name']
);

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
exit;
