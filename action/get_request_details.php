<?php
// API endpoint: Fetch request details as JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Load config in correct order
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/prerequisites.php';
require_once __DIR__ . '/../core/request_workflow.php';

function send_json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    if (ob_get_level() > 0) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    send_json_response(['error' => 'Unauthorized'], 401);
}

$stmt = $pdo->prepare("SELECT session_id, user_type, barangay FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['session_id'] !== session_id()) {
    session_destroy();
    send_json_response(['error' => 'Unauthorized'], 401);
}

// Keep session data in sync if needed
$_SESSION['user_type'] = $user['user_type'];
$_SESSION['barangay']   = $user['barangay'];

ensure_prerequisite_tables($pdo);
ensure_request_workflow_schema($pdo);

$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
if (!$request_id) {
    send_json_response(['error' => 'Invalid request ID'], 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT r.*,
               owner.full_name AS account_name,
               emp.user_id     AS processed_by_id,
               emp.full_name   AS processed_by_name,
               CASE
                   WHEN r.preferred_payment_method = 'walk-in' AND r.payment_status IN ('paid', 'refunded') THEN (
                       SELECT CONCAT('RCPT-', LPAD(pt.payment_id, 8, '0'))
                       FROM payment_transactions pt
                       WHERE pt.request_id = r.request_id
                       LIMIT 1
                   )
                   ELSE NULL
               END AS receipt_number,
               (SELECT MAX(validity_months) FROM document_prerequisites WHERE document_type = r.document_type) AS validity_months
        FROM requests r
        LEFT JOIN users owner ON r.user_id = owner.user_id
        LEFT JOIN users emp   ON r.processed_by = emp.user_id
        WHERE r.request_id = ?
        LIMIT 1
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        send_json_response(['error' => 'Request not found'], 404);
    }

    $documentNameParts = json_decode((string) ($request['document_name_parts'] ?? ''), true);
    $request['document_name_parts'] = is_array($documentNameParts) ? $documentNameParts : null;

    // Compute "Valid Until" — completed date + validity months, kung meron
    $validityMonths = (int) ($request['validity_months'] ?? 0);
    $request['valid_until'] = null;
    if ($validityMonths > 0 && !empty($request['completed_at'])) {
        try {
            $validUntil = new DateTime($request['completed_at']);
            $validUntil->modify("+{$validityMonths} months");
            $request['valid_until'] = $validUntil->format('Y-m-d');
        } catch (Exception $e) {
            $request['valid_until'] = null;
        }
    }

    send_json_response($request, 200);
} catch (Throwable $e) {
    error_log('get_request_details error: ' . $e->getMessage());
    send_json_response(['error' => 'Unable to load request details'], 500);
}