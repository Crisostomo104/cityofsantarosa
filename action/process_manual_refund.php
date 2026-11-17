<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../core/payments.php';
require_once __DIR__ . '/../vendor/autoload.php';

check_login_redirect(['employee']);

// CSRF verification for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

header('Content-Type: application/json');

$request_id = (int)($_POST['request_id'] ?? 0);
$password = $_POST['password'] ?? '';

if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
    exit;
}

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password is required for security']);
    exit;
}

// Verify password
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid password']);
    exit;
}

// Fetch request to verify it exists and is paid
$stmt = $pdo->prepare("
    SELECT request_id, payment_status, payment_amount, payment_reference, 
           paymongo_payment_id, refund_status, status
    FROM requests
    WHERE request_id = ?
    LIMIT 1
");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Request not found']);
    exit;
}

// Check if already refunded
if ($request['refund_status'] === 'completed') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Refund already processed for this request']);
    exit;
}

// Check if there's a payment to refund
if ($request['payment_status'] !== 'paid') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Request has no paid payment to refund']);
    exit;
}

// Process refund
try {
    $refundResult = initiate_refund($pdo, $request_id, $_SESSION['user_id']);
    
    if ($refundResult['success']) {
        // Log the manual refund action
        log_action(
            $pdo,
            $_SESSION['user_id'],
            'Manual Refund Processed',
            'REQ-00' . $request_id,
            'Refund ID: ' . $refundResult['refund_id'] . ' | Amount: ' . money_format_php($request['payment_amount'])
        );

        echo json_encode([
            'success' => true,
            'message' => $refundResult['message'],
            'refund_id' => $refundResult['refund_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $refundResult['message']
        ]);
    }
} catch (Exception $e) {
    error_log('[Manual Refund Error] Request ' . $request_id . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing the refund'
    ]);
}
?>
