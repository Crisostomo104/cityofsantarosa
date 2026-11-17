<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/sms_helpers.php';
require_once __DIR__ . '/../core/notifications.php';

check_login_redirect(['employee']);

// CSRF verification for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// Accept both GET (legacy) and POST
$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id > 0) {
    // Get request info including user_id and contact number for SMS
    $stmt = $pdo->prepare("
        SELECT r.request_id, r.user_id, r.full_name, r.contact_number, r.document_type,
               r.preferred_payment_method, r.payment_status, r.status
        FROM requests r
        WHERE r.request_id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        $isOnlinePending = strtolower(trim($request['preferred_payment_method'] ?? '')) === 'online'
            && strtolower(trim($request['payment_status'] ?? '')) === 'pending';

        if ($isOnlinePending) {
            $_SESSION['sms_error'] = 'This request is still waiting for online payment confirmation and cannot be approved yet.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
            exit;
        }

        if (strtolower(trim($request['status'] ?? '')) !== 'pending') {
            $_SESSION['sms_error'] = 'Only pending requests can be approved.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
            exit;
        }

        // Approve the request
        $stmt = $pdo->prepare("
            UPDATE requests 
            SET status='Approved', processed_by=? 
            WHERE request_id=?
        ");
        $stmt->execute([$_SESSION['user_id'], $id]);

        // In-app notification for the resident
        create_notification(
            $pdo,
            (int) $request['user_id'],
            '✅ Your ' . $request['document_type'] . ' request has been approved!',
            '/cityofsantarosa/dashboards/resident'
        );

        // SMS notification
        $name  = $request['full_name'];
        $phone = $request['contact_number'] ?? '';

        if (!empty($phone)) {
            $message = "Hello $name, your {$request['document_type']} request has been APPROVED by the barangay. Your requested document can process within 24 hours. Please wait for further processing. \n\nReminder if the requested document is not named to you, please provide at least an ID of the requester. Thank you!";
            $sms_result = sms_send($phone, $message);

            if ($sms_result['success']) {
                $_SESSION['sms_success'] = "Request approved successfully. SMS sent.";
            } else {
                $_SESSION['sms_success'] = "Request approved successfully.";
                $_SESSION['sms_error'] = "SMS failed: " . htmlspecialchars($sms_result['error']);
            }
        } else {
            $_SESSION['sms_success'] = "Request approved successfully.";
        }

        log_action(
            $pdo,
            $_SESSION['user_id'],
            'Request Approved',
            'Request #' . $id,
            'Approved request for ' . $request['full_name']
        );
    } else {
        $_SESSION['sms_error'] = "Request not found.";
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>