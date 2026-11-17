<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  REQUEST ACTION HELPERS
 *  Shared logic for rejecting a request — used by:
 *    - reject_request.php   (employee manually rejects with a reason)
 *    - delete_attachment.php (auto-reject when a prerequisite
 *      document attachment is removed, by either the resident or
 *      an employee)
 *
 *  Usage: require_once __DIR__ . '/../core/request_actions.php';
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/sms_helpers.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/payments.php';

/**
 * Reject a request: updates status, creates an in-app notification,
 * attempts an SMS, and logs the action. Does NOT initiate a refund —
 * if the request was paid, it's left refund-eligible (refund_status
 * stays 'none') for the resident to request via action/request_refund.php.
 *
 * @param PDO    $pdo
 * @param int    $requestId
 * @param string $reason      Rejection reason shown to the resident.
 * @param int    $actorUserId The user_id performing the rejection (recorded as processed_by).
 * @param string $logLabel    Label used in the audit log.
 * @return array ['success' => bool, 'error' => string|null, 'refund_eligible' => bool, 'sms_sent' => bool, 'sms_error' => string|null]
 */
function reject_request_core(PDO $pdo, int $requestId, string $reason, int $actorUserId, string $logLabel = 'Request Rejected'): array
{
    $reason = trim($reason);
    if ($reason === '') {
        return ['success' => false, 'error' => 'Rejection reason is required.'];
    }

    $stmt = $pdo->prepare("
        SELECT r.request_id, r.user_id, r.status, r.payment_status, r.payment_amount, r.payment_reference,
               r.paymongo_payment_id, u.full_name, r.contact_number
        FROM requests r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.request_id = ?
        LIMIT 1
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        return ['success' => false, 'error' => 'Request not found.'];
    }

    // Keep the same status casing ('rejected') the original reject_request.php
    // used, so nothing elsewhere that depends on it breaks.
    ensure_payment_schema($pdo);

    $logsDir = __DIR__ . '/../logs';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0755, true);
    }

    $stmt = $pdo->prepare("
        UPDATE requests
        SET status='rejected', rejection_reason=?, processed_by=?
        WHERE request_id=?
    ");
    $stmt->execute([$reason, $actorUserId, $requestId]);

    // NOTE: refunds are no longer initiated automatically here. If a paid
    // request gets rejected, refund_status stays 'none' (the default) —
    // the resident sees a "Request Refund" option, and an employee must
    // approve it before initiate_refund() is actually called. See
    // action/request_refund.php and action/approve_refund.php.
    $refundEligible = ($request['payment_status'] === 'paid' && (float)($request['payment_amount'] ?? 0) > 0);

    // In-app notification
    $notificationText = '❌ Your request has been rejected. Reason: ' . mb_substr($reason, 0, 100);
    if ($refundEligible) {
        $notificationText .= ' Since you already paid for this request, you may request a refund from your Request Details.';
    }
    create_notification($pdo, (int) $request['user_id'], $notificationText, '/cityofsantarosa/dashboards/resident');

    // SMS (best-effort, non-fatal)
    $smsSent = false;
    $smsError = null;
    $name = $request['full_name'];
    $phone = $request['contact_number'] ?? '';

    if (!empty($phone)) {
        $smsMessage = "Hello $name, your request has been REJECTED by the barangay. Reason: $reason";
        if ($refundEligible) {
            $smsMessage .= " Since you already paid, you may request a refund in the app.";
        }
        $sms_result = sms_send($phone, $smsMessage);
        $smsSent = (bool)($sms_result['success'] ?? false);
        $smsError = $sms_result['error'] ?? null;
    }

    // Audit log
    $logMessage = 'Rejected request for ' . $name . ' | Reason: ' . $reason;
    if ($refundEligible) {
        $logMessage .= ' | Refund: ELIGIBLE (awaiting resident request)';
    }
    $logMessage .= ' | SMS: ' . ($smsSent ? 'Sent' : ('Not sent' . ($smsError ? " ($smsError)" : '')));

    log_action($pdo, $actorUserId, $logLabel, 'REQ-00' . $requestId, $logMessage);

    return [
        'success' => true,
        'error' => null,
        'refund_eligible' => $refundEligible,
        'sms_sent' => $smsSent,
        'sms_error' => $smsError,
    ];
}