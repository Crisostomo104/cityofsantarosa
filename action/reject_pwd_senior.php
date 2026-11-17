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

function normalize_phone_reject($phone) {
    $phone = preg_replace('/\D/', '', $phone);

    if (preg_match('/^09\d{9}$/', $phone)) {
        return '63' . substr($phone, 1);
    }

    if (preg_match('/^639\d{9}$/', $phone)) {
        return $phone;
    }

    return false;
}

function send_sms_reject($phone, $message, $apikey) {
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
           r.pwd_senior_requested, r.pwd_senior_verified, r.pwd_senior_rejected, u.barangay
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
    $_SESSION['sms_error'] = 'This discount was already verified and cannot be rejected here.';
    header('Location: ../dashboards/employee?panel=requests');
    exit;
}

if ((int)$req['pwd_senior_rejected'] === 1) {
    $_SESSION['sms_error'] = 'This discount request was already rejected.';
    header('Location: ../dashboards/employee?panel=requests');
    exit;
}

if (strtolower(trim($req['preferred_payment_method'])) !== 'walk-in' || strtolower(trim($req['payment_status'])) === 'paid') {
    $_SESSION['sms_error'] = 'PWD/Senior discount can only be rejected for unpaid walk-in requests. For requests already paid online, use the refund tool instead.';
    header('Location: ../dashboards/employee?panel=requests');
    exit;
}

$pdo->prepare("
    UPDATE requests
    SET pwd_senior_rejected = 1
    WHERE request_id = ?
")->execute([$requestId]);

log_action($pdo, $_SESSION['user_id'], 'PWD/Senior Discount Rejected', 'Request #' . $requestId,
    "Rejected PWD/Senior discount declaration. Full price of PHP " . number_format((float)$req['payment_amount'], 2) . " remains in effect.");

$_SESSION['sms_success'] = 'PWD/Senior discount declaration rejected. Full price remains in effect.';

// ── SMS NOTIFICATION ──
$phone = normalize_phone_reject($req['contact_number'] ?? '');
if ($phone && !empty($apikey)) {
    $message = "Hello {$req['full_name']}, your PWD/Senior Citizen discount request was NOT approved by the barangay. "
        . "Your payment amount remains PHP " . number_format((float)$req['payment_amount'], 2)
        . ". Please contact the barangay office if you have questions. Thank you!";
    $sms_result = send_sms_reject($phone, $message, $apikey);

    if ($sms_result['success']) {
        $_SESSION['sms_success'] .= ' SMS sent.';
    } else {
        $_SESSION['sms_error'] = 'Discount rejected, but SMS failed: ' . htmlspecialchars($sms_result['error']);
    }

    log_action(
        $pdo,
        $_SESSION['user_id'],
        'PWD/Senior Rejection SMS',
        'Request #' . $requestId,
        'SMS to ' . $req['full_name'] . ': ' . ($sms_result['success'] ? 'Sent' : 'Failed - ' . $sms_result['error'])
    );
} else {
    $_SESSION['sms_error'] = 'Discount rejected, but SMS not sent (invalid phone or missing API key).';
}

header('Location: ../dashboards/employee?panel=requests');
exit;