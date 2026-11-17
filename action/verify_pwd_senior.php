<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../config/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

check_login_redirect(['employee']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboards/employee');
    exit;
}

csrf_verify();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$apikey = $_ENV['SEMAPHORE_API_KEY'] ?? '';

function normalize_phone($phone) {
    $phone = preg_replace('/\D/', '', $phone);

    if (preg_match('/^09\d{9}$/', $phone)) {
        return '63' . substr($phone, 1);
    }

    if (preg_match('/^639\d{9}$/', $phone)) {
        return $phone;
    }

    return false;
}

function send_sms($phone, $message, $apikey) {
    $data = [
        'apikey' => $apikey,
        'number' => $phone,
        'message' => $message,
        'sendername' => 'BrgyIbaba'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.semaphore.co/api/v4/messages",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => $error];
    }

    curl_close($ch);

    if (is_dir(__DIR__ . '/../logs')) {
        file_put_contents(__DIR__ . '/../logs/sms_debug.log', $response . PHP_EOL, FILE_APPEND);
    }

    $decoded = json_decode($response, true);
    $status = $decoded[0]['status'] ?? '';

    if (in_array($status, ['Queued', 'Pending'], true)) {
        return ['success' => true];
    }

    return [
        'success' => false,
        'error' => $status ?: 'Unknown error'
    ];
}

$requestId = (int)($_POST['request_id'] ?? 0);
$barangay  = $_SESSION['barangay'] ?? '';

if ($requestId <= 0) {
    $_SESSION['sms_error'] = 'Invalid request.';
    header('Location: ../dashboards/employee?panel=requests');
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.request_id, r.full_name, r.contact_number, r.payment_amount,
           r.preferred_payment_method, r.payment_status,
           r.pwd_senior_requested, r.pwd_senior_verified, u.barangay
    FROM requests r
    INNER JOIN users u ON r.user_id = u.user_id
    WHERE r.request_id = ?
");
$stmt->execute([$requestId]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req || $req['barangay'] !== $barangay) {
    $_SESSION['sms_error'] = 'Request not found for your barangay.';
    header('Location: ../dashboards/employee?panel=requests');
    exit;
}

if ((int)$req['pwd_senior_requested'] !== 1) {
    $_SESSION['sms_error'] = 'This request did not declare a PWD/Senior discount.';
    header('Location: ../dashboards/employee?panel=requests');
    exit;
}

if ((int)$req['pwd_senior_verified'] === 1) {
    $_SESSION['sms_error'] = 'Discount already verified for this request.';
    header('Location: ../dashboards/employee?panel=requests');
    exit;
}

if (strtolower(trim($req['preferred_payment_method'])) !== 'walk-in' || strtolower(trim($req['payment_status'])) === 'paid') {
    $_SESSION['sms_error'] = 'PWD/Senior discount can currently only be verified for unpaid walk-in requests. For requests already paid online, use the refund tool instead.';
    header('Location: ../dashboards/employee?panel=requests');
    exit;
}

$originalAmount   = (float) $req['payment_amount'];
$discountedAmount = round($originalAmount * 0.8, 2);

$pdo->prepare("
    UPDATE requests
    SET pwd_senior_verified = 1, pwd_senior_original_amount = ?, payment_amount = ?
    WHERE request_id = ?
")->execute([$originalAmount, $discountedAmount, $requestId]);

log_action($pdo, $_SESSION['user_id'], 'PWD/Senior Discount Verified', 'Request #' . $requestId,
    "Verified PWD/Senior discount: PHP " . number_format($originalAmount, 2) . " → PHP " . number_format($discountedAmount, 2));

$_SESSION['sms_success'] = 'PWD/Senior discount verified. New amount: PHP ' . number_format($discountedAmount, 2) . '.';

// ── SMS NOTIFICATION ──
$phone = normalize_phone($req['contact_number'] ?? '');
if ($phone && !empty($apikey)) {
    $message = "Hello {$req['full_name']}, your PWD/Senior Citizen discount has been VERIFIED by the barangay. "
        . "Your updated payment amount is now PHP " . number_format($discountedAmount, 2)
        . " (from PHP " . number_format($originalAmount, 2) . "). Thank you!";
    $sms_result = send_sms($phone, $message, $apikey);

    if ($sms_result['success']) {
        $_SESSION['sms_success'] .= ' SMS sent.';
    } else {
        $_SESSION['sms_error'] = 'Discount verified, but SMS failed: ' . htmlspecialchars($sms_result['error']);
    }

    log_action(
        $pdo,
        $_SESSION['user_id'],
        'PWD/Senior Verification SMS',
        'Request #' . $requestId,
        'SMS to ' . $req['full_name'] . ': ' . ($sms_result['success'] ? 'Sent' : 'Failed - ' . $sms_result['error'])
    );
} else {
    $_SESSION['sms_error'] = 'Discount verified, but SMS not sent (invalid phone or missing API key).';
}

header('Location: ../dashboards/employee?panel=requests');
exit;