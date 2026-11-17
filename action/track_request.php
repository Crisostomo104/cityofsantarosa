<?php
/**
 * ─────────────────────────────────────────────────────────────
 *  PUBLIC TRACKING API — No login required
 *  POST { "code": "SR-IBAB-20260625-0001" }
 *  Returns JSON with request status details
 * ─────────────────────────────────────────────────────────────
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/tracking.php';
require_once __DIR__ . '/../core/chatbot.php';

header('Content-Type: application/json');

$chatbot_settings = get_chatbot_settings($pdo);
$maintenance_mode = ($chatbot_settings['maintenance_mode'] ?? '0') === '1';
$maintenance_message = $chatbot_settings['maintenance_message'] ?? 'The system is currently under maintenance. Please try again later.';

if ($maintenance_mode) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => $maintenance_message
    ]);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($data['code'] ?? ''));

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a tracking code.']);
    exit;
}

// Validate either the new format (REQ...) or the legacy format (SR-...)
if (!preg_match('/^REQ\d{9,}-\d+$/', $code) && !preg_match('/^SR-[A-Z]{2,4}-\d{8}-\d+$/', $code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid tracking code format. Expected format: REQ(Date)(UserID)-(RequestID) (e.g. REQ202606281-012)']);
    exit;
}

$request = lookup_tracking_code($pdo, $code);

if (!$request) {
    echo json_encode(['success' => false, 'message' => 'No request found for this tracking code.']);
    exit;
}

// Return limited public info (don't expose personal data)
echo json_encode([
    'success' => true,
    'data'    => [
        'tracking_code'  => $request['tracking_code'],
        'document_type'  => $request['document_type'] ?? 'N/A',
        'status'         => $request['status'],
        'barangay'       => $request['barangay'] ?? '',
        'submitted_at'   => date('M d, Y h:i A', strtotime($request['created_at'])),
        'payment_status' => $request['payment_status'] ?? 'unpaid',
    ]
]);
