<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/notifications.php';
require_once __DIR__ . '/../core/payments.php';
require_once __DIR__ . '/../core/request_actions.php';

check_login_redirect(['employee']);

// CSRF verification for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
$reason = trim($_POST['reason'] ?? $_GET['reason'] ?? '');

if ($id > 0) {

    if (empty($reason)) {
        $_SESSION['sms_error'] = "Rejection reason is required.";
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
        exit;
    }

    $result = reject_request_core($pdo, $id, $reason, (int) $_SESSION['user_id'], 'Request Rejected');

    if (!$result['success']) {
        $_SESSION['sms_error'] = $result['error'] ?? 'Request not found.';
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
        exit;
    }

    // Default message
    $_SESSION['sms_success'] = "Request rejected successfully.";
    if (!empty($result['refund_eligible'])) {
        $_SESSION['sms_success'] .= " This request was paid — the resident can now request a refund.";
    }

    if ($result['sms_sent']) {
        $_SESSION['sms_success'] .= " SMS sent.";
    } else {
        $_SESSION['sms_error'] = "Request rejected, but SMS failed: " . htmlspecialchars($result['sms_error'] ?? 'Unknown error');
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>