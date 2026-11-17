<?php
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$logEntry = sprintf(
    "[%s] mark_paid entry: method=%s uri=%s remote=%s agent=%s\n",
    date('c'),
    $_SERVER['REQUEST_METHOD'] ?? 'n/a',
    $_SERVER['REQUEST_URI'] ?? 'n/a',
    $_SERVER['REMOTE_ADDR'] ?? 'n/a',
    $_SERVER['HTTP_USER_AGENT'] ?? 'n/a'
);
@file_put_contents($logDir . '/mark_paid.log', $logEntry, FILE_APPEND | LOCK_EX);

set_exception_handler(function (Throwable $e) use ($logDir) {
    @file_put_contents(
        $logDir . '/mark_paid_error.log',
        sprintf("[%s] exception: %s in %s:%d\n", date('c'), $e->getMessage(), $e->getFile(), $e->getLine()),
        FILE_APPEND | LOCK_EX
    );
});

register_shutdown_function(function () use ($logDir) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR], true)) {
        @file_put_contents(
            $logDir . '/mark_paid_error.log',
            sprintf("[%s] shutdown error: %s in %s:%d\n", date('c'), $error['message'], $error['file'], $error['line']),
            FILE_APPEND | LOCK_EX
        );
    }
});

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../core/payments.php';
require_once __DIR__ . '/../core/payment_receipt.php';
require_once __DIR__ . '/../core/request_workflow.php';
require_once __DIR__ . '/../core/notifications.php';
require_once __DIR__ . '/../core/sms_helpers.php';

check_login_redirect(['employee']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

csrf_verify();
ensure_request_workflow_schema($pdo);

$id = intval($_POST['id'] ?? 0);
$completeClaim = ($_POST['complete_claim'] ?? '') === '1';
if ($id <= 0) {
    $_SESSION['sms_error'] = 'Invalid request ID.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

// NOTE: adjust r.document_type below if your requests table uses a
// different column name for the requested document.
$stmt = $pdo->prepare("
    SELECT r.request_id, r.user_id, r.address, r.payment_status, r.payment_amount,
           r.preferred_payment_method, r.status, r.full_name, r.contact_number,
           r.document_type, r.tracking_code,
           u.email, u.barangay
    FROM requests r
    JOIN users u ON u.user_id = r.user_id
    WHERE r.request_id = ? AND u.barangay = ? LIMIT 1
");
$stmt->execute([$id, $_SESSION['barangay'] ?? '']);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    $_SESSION['sms_error'] = 'Request not found.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

if (strtolower(trim($request['preferred_payment_method'] ?? '')) !== 'walk-in') {
    $_SESSION['sms_error'] = 'Only cash payments can be marked as paid here.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

if (
    !in_array(strtolower(trim($request['status'] ?? '')), ['to be claim', 'completed'], true)
    || ($completeClaim && strtolower(trim($request['status'] ?? '')) !== 'to be claim')
) {
    $_SESSION['sms_error'] = 'Only ready-to-claim or completed cash requests can be marked as paid.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

if (strtolower(trim($request['payment_status'] ?? '')) === 'paid') {
    $_SESSION['sms_success'] = 'This request is already marked as paid.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

$paymentAmount = max(0.00, (float) $request['payment_amount']);
if ($paymentAmount <= 0) {
    $_SESSION['sms_error'] = 'No payment amount is due for this request.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

$paymentReference = 'walk-in';
$barangay = $_SESSION['barangay'] ?? null;
$paymentAddress = trim((string) ($request['address'] ?? ''));

@file_put_contents(
    $logDir . '/mark_paid.log',
    sprintf(
        "[%s] mark_paid start: id=%s, preferred_payment_method=%s, status=%s, payment_status=%s, address=%s\n",
        date('c'),
        $id,
        $request['preferred_payment_method'] ?? 'n/a',
        $request['status'] ?? 'n/a',
        $request['payment_status'] ?? 'n/a',
        $paymentAddress
    ),
    FILE_APPEND | LOCK_EX
);

$receiptTransaction = null;
$claimCompleted = false;
$pdo->beginTransaction();
try {
    $lockedRequest = $pdo->prepare("
        SELECT payment_status, status, preferred_payment_method
        FROM requests
        WHERE request_id = ?
        FOR UPDATE
    ");
    $lockedRequest->execute([$id]);
    $currentRequest = $lockedRequest->fetch(PDO::FETCH_ASSOC);

    if (!$currentRequest) {
        throw new RuntimeException('Request no longer exists.');
    }
    if (strtolower(trim($currentRequest['payment_status'] ?? '')) === 'paid') {
        $pdo->rollBack();
        $_SESSION['sms_success'] = 'This request is already marked as paid.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
        exit;
    }
    if (
        !in_array(strtolower(trim($currentRequest['status'] ?? '')), ['to be claim', 'completed'], true)
        || strtolower(trim($currentRequest['preferred_payment_method'] ?? '')) !== 'walk-in'
    ) {
        throw new RuntimeException('Request is no longer eligible for walk-in payment recording.');
    }

    if ($completeClaim) {
        $update = $pdo->prepare("
            UPDATE requests
            SET payment_status = 'paid', payment_reference = ?,
                status = 'Completed', processed_by = ?, completed_at = NOW()
            WHERE request_id = ? AND status = 'To be Claim'
        ");
        $update->execute([$paymentReference, (int) $_SESSION['user_id'], $id]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Request status changed before payment and claim could be recorded.');
        }
    } else {
        $update = $pdo->prepare("UPDATE requests SET payment_status = 'paid', payment_reference = ? WHERE request_id = ?");
        $update->execute([$paymentReference, $id]);
    }

    if ($paymentAmount > 0) {
        $paymentBarangay = trim((string) ($request['barangay'] ?? ''));
        record_payment_transaction($pdo, $id, $paymentBarangay, $paymentAmount, (int)$_SESSION['user_id'], 'walk-in');
        $transactionQuery = $pdo->prepare("
            SELECT payment_id, recorded_at
            FROM payment_transactions
            WHERE request_id = ?
            LIMIT 1
        ");
        $transactionQuery->execute([$id]);
        $receiptTransaction = $transactionQuery->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$receiptTransaction) {
            throw new RuntimeException('Payment transaction was not available for receipt generation.');
        }
    }

    $pdo->commit();
    $claimCompleted = $completeClaim;
    $_SESSION['sms_success'] = $claimCompleted
        ? 'Payment recorded and request marked as completed.'
        : 'Cash request payment marked as paid.';

    // Notify the resident/user that their cash payment has been recorded.
    // Wrapped separately so a mail failure never rolls back or blocks the payment update.
    if (!empty($request['email'])) {
        $displayRequestId = trim((string) ($request['tracking_code'] ?? '')) ?: ('REQ-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT));
        $documentType = trim((string) ($request['document_type'] ?? '')) ?: 'N/A';
        $requestBarangay = trim((string) ($request['barangay'] ?? '')) ?: 'N/A';
        $paymentMethod = payment_method_label((string) ($request['preferred_payment_method'] ?? 'walk-in'));

        try {
            $receiptId = 'RCPT-' . str_pad((string) $receiptTransaction['payment_id'], 8, '0', STR_PAD_LEFT);
            $recordedAt = $receiptTransaction['recorded_at'];
            $receiptPdf = generate_payment_receipt_pdf([
                'receipt_number' => $receiptId,
                'resident_name' => $request['full_name'] ?? 'Resident',
                'request_number' => $displayRequestId,
                'document_type' => $documentType,
                'barangay' => $requestBarangay,
                'paid_at' => date('F j, Y g:i A', strtotime((string) $recordedAt)),
                'payment_method' => $paymentMethod,
                'amount' => number_format($paymentAmount, 2, '.', ','),
            ]);
            $safeRequestId = preg_replace('/[^A-Za-z0-9_-]/', '-', $displayRequestId);
            $emailSent = send_app_email(
                $request['email'],
                'Payment Received - ' . $displayRequestId,
                "
                    <p>Hi " . htmlspecialchars((string) ($request['full_name'] ?? 'Resident'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ",</p>
                    <p>We've received your " . htmlspecialchars($paymentMethod) . " payment for your request. Your payment acknowledgment is attached as a PDF.</p>
                    <p><strong>Receipt No.:</strong> " . htmlspecialchars($receiptId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                    <p><strong>Request No.:</strong> " . htmlspecialchars($displayRequestId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                    <p><strong>Document Requested:</strong> " . htmlspecialchars($documentType, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                    <p><strong>Barangay:</strong> " . htmlspecialchars($requestBarangay, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                    <p><strong>Amount Paid:</strong> &#8369;" . number_format($paymentAmount, 2) . "</p>
                    <p><strong>Payment Method:</strong> " . htmlspecialchars($paymentMethod, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                    <p>Thank you!</p>
                ",
                $receiptPdf,
                'Payment-Receipt-' . $safeRequestId . '.pdf'
            );
            if (!$emailSent) {
                @file_put_contents(
                    $logDir . '/mark_paid_error.log',
                    sprintf("[%s] receipt email send returned false for request_id=%d\n", date('c'), $id),
                    FILE_APPEND | LOCK_EX
                );
            }
        } catch (Throwable $mailError) {
            $written = file_put_contents(
                $logDir . '/mark_paid_error.log',
                sprintf("[%s] email send failed: %s\n", date('c'), $mailError->getMessage()),
                FILE_APPEND | LOCK_EX
            );
            if ($written === false) {
                error_log('mark_paid: unable to write receipt failure to mark_paid_error.log.');
            }
        }
    } else {
        $written = file_put_contents(
            $logDir . '/mark_paid_error.log',
            sprintf("[%s] receipt email skipped: user has no email address for request_id=%d\n", date('c'), $id),
            FILE_APPEND | LOCK_EX
        );
        if ($written === false) {
            error_log('mark_paid: unable to write missing recipient to mark_paid_error.log.');
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['sms_error'] = 'Unable to mark payment as paid: ' . $e->getMessage();
}

if ($claimCompleted) {
    try {
        create_notification(
            $pdo,
            (int) $request['user_id'],
            'Your ' . ($request['document_type'] ?? 'document') . ' request has been marked as claimed.',
            '/cityofsantarosa/dashboards/resident'
        );
    } catch (Throwable $exception) {
        error_log('Claim notification failed for request ' . $id . ': ' . $exception->getMessage());
    }

    $phone = trim((string) ($request['contact_number'] ?? ''));
    if ($phone !== '') {
        $smsResult = sms_send($phone, 'Hello ' . $request['full_name'] . ', your document claim has been recorded. Thank you!');
        if (empty($smsResult['success'])) {
            $_SESSION['sms_error'] = 'Payment and claim recorded, but SMS failed: ' . htmlspecialchars((string) ($smsResult['error'] ?? 'Unknown error'));
        }
    }

    log_action(
        $pdo,
        (int) $_SESSION['user_id'],
        'Request Claimed and Paid',
        'Request #' . $id,
        'Recorded cash payment and document claim for ' . $request['full_name']
    );
}

// Invalidate affected caches
$payMonth = date('Y-m'); // recorded_at defaults to NOW()
clear_cached_data('employee_payments:' . $barangay . ':' . $payMonth);
clear_cached_data('city_admin_payments_by_barangay:' . $payMonth);

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
exit;