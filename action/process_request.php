<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../config/functions.php';
require_once __DIR__ . '/../core/request_workflow.php';
require_once __DIR__ . '/../core/sms_helpers.php';
require_once __DIR__ . '/../core/notifications.php';

check_login_redirect(['employee']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['sms_error'] = 'Use the request action button to mark a request ready.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

csrf_verify();
ensure_request_workflow_schema($pdo);

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['sms_error'] = "Request not found.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.request_id, r.user_id, r.contact_number, r.full_name, r.document_type, r.status
    FROM requests r
    INNER JOIN users owner ON owner.user_id = r.user_id
    WHERE r.request_id = ? AND owner.barangay = ?
    LIMIT 1
");
$stmt->execute([$id, $_SESSION['barangay'] ?? '']);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    log_action($pdo, $_SESSION['user_id'], 'Request Completion Failed', 'REQ-00' . $id, 'Request not found');
    $_SESSION['sms_error'] = "Request not found.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

if ($request['status'] !== 'Approved') {
    log_action($pdo, $_SESSION['user_id'], 'Request Processing Failed', 'REQ-00' . $id, 'Request not approved for pickup preparation');
    $_SESSION['sms_error'] = 'Only approved requests can be marked ready for pickup.';
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

$name = $request['full_name'];
$phone = $request['contact_number'] ?? '';

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        UPDATE requests
        SET status='To be Claim', processed_by=?, completed_at=NULL
        WHERE request_id=? AND status='Approved'
    ");
    $stmt->execute([$_SESSION['user_id'], $id]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Request status changed before it could be marked ready.');
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['sms_error'] = 'Unable to mark request ready for pickup: ' . $e->getMessage();
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

if (!empty($request['user_id'])) {
    try {
        create_notification(
            $pdo,
            (int) $request['user_id'],
            'Your ' . ($request['document_type'] ?? 'document') . ' is ready to be claimed. Please note that pickup may be delayed during calamities or suspended office hours.',
            '/cityofsantarosa/dashboards/resident'
        );
    } catch (Throwable $e) {
        error_log('Ready-for-claim notification failed for request ' . $id . ': ' . $e->getMessage());
    }
}

$_SESSION['sms_success'] = 'Request marked ready for pickup.';

if ($phone !== '') {
    $message = "Hello $name, your request is ready to be CLAIMED at the barangay office.This is certify that the requested document is ready for pickup.You may now pick them up today from 08:00 AM - 05:00 PM. Please bring the required documents.\n\nPlease note that pickup may be delayed during calamities or suspended office hours. \n\nReminder if the requested document is not named to you, please provide at least an ID of the requester. \n\nThank you!";
    $sms_result = sms_send($phone, $message);

    if ($sms_result['success']) {
        $_SESSION['sms_success'] .= " SMS sent.";
    } else {
        $_SESSION['sms_error'] = 'Request is ready for pickup, but SMS failed: ' . htmlspecialchars($sms_result['error']);
    }

    log_action(
        $pdo,
        $_SESSION['user_id'],
        'Request Ready for Claim',
        'Request #' . $request['request_id'],
        'Marked request ready for claim for ' . $name .
        ' | SMS: ' . ($sms_result['success'] ? 'Sent' : 'Failed - ' . $sms_result['error'])
    );
} else {
    $_SESSION['sms_error'] = 'Request is ready for pickup, but SMS was not sent (invalid phone).';

    log_action(
        $pdo,
        $_SESSION['user_id'],
        'Request Ready for Claim (No SMS)',
        'Request #' . $request['request_id'],
        'Marked request ready for claim for ' . $name . ' but SMS was not sent'
    );
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
exit;
