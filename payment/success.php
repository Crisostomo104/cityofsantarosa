<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/paymongo_config.php';

check_login_redirect(['resident']);

$request_id = (int)($_GET['request_id'] ?? 0);

// Load the request with source_id
$stmt = $pdo->prepare("SELECT * FROM requests WHERE request_id = ? AND user_id = ?");
$stmt->execute([$request_id, $_SESSION['user_id']]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request || empty($request['payment_reference'])) {
    $_SESSION['sms_error'] = 'Payment reference not found.';
    header('Location: ../dashboards/resident');
    exit;
}

// ── PayPal Verification ──
$paypalOrderId = $_GET['paypal_order_id'] ?? '';
if (!empty($paypalOrderId) && $request['payment_status'] !== 'paid') {
    // 1. Get Access Token from PayPal
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYPAL_API_URL . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ":" . PAYPAL_CLIENT_SECRET);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    
    $tokenResponse = curl_exec($ch);
    $tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $tokenData = json_decode($tokenResponse, true);
    $accessToken = $tokenData['access_token'] ?? null;
    
    if ($tokenHttpCode !== 200 || !$accessToken) {
        $_SESSION['flash_error'] = 'Unable to verify PayPal transaction (auth failed).';
        header('Location: ../dashboards/resident');
        exit;
    }
    
    // 2. Retrieve Order Details from PayPal
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYPAL_API_URL . '/v2/checkout/orders/' . urlencode($paypalOrderId));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    
    $orderResponse = curl_exec($ch);
    $orderHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $orderData = json_decode($orderResponse, true);
    $paypalStatus = $orderData['status'] ?? '';
    
    // 3. Verify Payment Status and Amount (using converted USD amount)
    $paypalAmount = $orderData['purchase_units'][0]['amount']['value'] ?? 0;
    $usdAmount = round((float)$request['payment_amount'] / PHP_USD_EXCHANGE_RATE, 2);
    $dbAmountUSD = number_format($usdAmount, 2, '.', '');
    
    if ($orderHttpCode === 200 && $paypalStatus === 'COMPLETED' && (float)$paypalAmount === (float)$dbAmountUSD) {
        $pdo->prepare("UPDATE requests SET payment_status = 'paid', payment_reference = ?, paymongo_payment_id = ? WHERE request_id = ?")
            ->execute([$paypalOrderId, $paypalOrderId, $request_id]);
        $request['payment_status'] = 'paid';
        $request['payment_reference'] = $paypalOrderId;
    } else {
        $_SESSION['flash_error'] = 'PayPal payment verification failed. Status: ' . $paypalStatus;
        header('Location: ../dashboards/resident');
        exit;
    }
}

$paymentReference = $request['payment_reference'];

if (is_simulated_payment_reference((string)$paymentReference)) {
    $pdo->prepare("UPDATE requests SET payment_status = 'paid', payment_reference = ?, paymongo_payment_id = ? WHERE request_id = ?")
        ->execute([$paymentReference, $paymentReference, $request_id]);
    $request['payment_status'] = 'paid';
    $request['payment_reference'] = $paymentReference;
    showSuccess($request);
    exit;
}

// If already paid, just show success
if ($request['payment_status'] === 'paid') {
    showSuccess($request);
    exit;
}

if (str_starts_with($paymentReference, 'pi_')) {
    $ch = curl_init(PAYMONGO_API_URL . '/payment_intents/' . urlencode($paymentReference));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    $intent = $data['data'] ?? [];
    $status = $intent['attributes']['status'] ?? 'unknown';

    if (in_array($status, ['canceled', 'failed', 'expired'], true)) {
        $pdo->prepare("UPDATE requests SET payment_status = 'failed' WHERE request_id = ?")
            ->execute([$request_id]);
        header('Location: failed?request_id=' . $request_id);
        exit;
    }

    if (in_array($status, ['paid', 'succeeded', 'processing', 'captured'], true)) {
        // For Payment Intent, extract the actual Payment ID if available
        // (GCash/PayMaya/etc. create a Payment from the Intent after 3rd party processing)
        $actualPaymentId = $intent['attributes']['payments'][0]['id'] ?? null;
        
        // Use actual Payment ID if available, otherwise use the Intent ID
        $paymentIdToStore = $actualPaymentId ?: $paymentReference;
        
        $pdo->prepare("UPDATE requests SET payment_status = 'paid', payment_reference = ?, paymongo_payment_id = ? WHERE request_id = ?")
            ->execute([$paymentIdToStore, $paymentIdToStore, $request_id]);
        $request['payment_status'] = 'paid';
        $request['payment_reference'] = $paymentIdToStore;
    }
} else {
    $sourceId = $paymentReference;

    $ch = curl_init(PAYMONGO_API_URL . '/sources/' . $sourceId);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    $status = $data['data']['attributes']['status'] ?? 'unknown';

    if (in_array($status, ['failed', 'expired'], true)) {
        $pdo->prepare("UPDATE requests SET payment_status = 'failed' WHERE request_id = ?")
            ->execute([$request_id]);
        header('Location: failed?request_id=' . $request_id);
        exit;
    }

    if ($status === 'chargeable') {
        $amount = (int)(($request['payment_amount'] ?? 100) * 100);

        $payload = json_encode([
            'data' => [
                'attributes' => [
                    'amount'      => $amount,
                    'currency'    => 'PHP',
                    'description' => 'E-Inquire: ' . $request['document_type'] . ' - ' . $request['full_name'],
                    'source'      => [
                        'id'   => $sourceId,
                        'type' => 'source',
                    ],
                ],
            ],
        ]);

        $ch2 = curl_init(PAYMONGO_API_URL . '/payments');
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
            ],
        ]);
        $payResponse = curl_exec($ch2);
        curl_close($ch2);
        $payData = json_decode($payResponse, true);

        $paymentId = $payData['data']['id'] ?? null;
        $payStatus = $payData['data']['attributes']['status'] ?? 'unknown';

        if ($paymentId && in_array($payStatus, ['paid', 'pending'], true)) {
            $pdo->prepare("
                UPDATE requests
                SET payment_status = 'paid', payment_reference = ?, paymongo_payment_id = ?
                WHERE request_id = ?
            ")->execute([$paymentId, $paymentId, $request_id]);
            $request['payment_status'] = 'paid';
            $request['payment_reference'] = $paymentId;
        }
    }
}

if (($request['payment_status'] ?? 'pending') === 'paid') {
    showSuccess($request);
    exit;
}

showPending($request);

function showPending($request) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Pending — E-Inquire</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background: #fff8e1; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card { max-width:440px; width:100%; border-radius:18px; box-shadow:0 8px 32px rgba(255,180,0,0.14); }
    .icon { font-size:72px; }
</style>
</head>
<body>
<div class="card p-5 text-center">
    <div class="icon mb-3">⏳</div>
    <h3 class="fw-bold text-warning mb-1">Payment Pending</h3>
    <p class="text-muted mb-3">Your PayMongo QRPh payment is still being processed. Please wait a moment and refresh this page.</p>
    <table class="table table-sm text-start mb-4">
        <tr><td class="text-muted">Document</td><td class="fw-semibold"><?= htmlspecialchars($request['document_type']) ?></td></tr>
        <tr><td class="text-muted">Name</td><td><?= htmlspecialchars($request['full_name']) ?></td></tr>
        <tr><td class="text-muted">Amount Due</td><td class="text-warning fw-bold">PHP <?= number_format($request['payment_amount'], 2) ?></td></tr>
    </table>
    <a href="../dashboards/resident" class="btn btn-outline-warning w-100">Back to My Requests</a>
</div>
</body>
</html>
<?php }

function showSuccess($request) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Successful — E-Inquire</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background: #f0fdf4; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card { max-width:440px; width:100%; border-radius:18px; box-shadow:0 8px 32px rgba(0,128,0,0.12); }
    .icon { font-size:72px; }
</style>
</head>
<body>
<div class="card p-5 text-center">
    <div class="icon mb-3">✅</div>
    <h3 class="fw-bold text-success mb-1">Payment Successful!</h3>
    <p class="text-muted mb-3">Your PayMongo QRPh payment has been confirmed.</p>
    <table class="table table-sm text-start mb-4">
        <tr><td class="text-muted">Document</td><td class="fw-semibold"><?= htmlspecialchars($request['document_type']) ?></td></tr>
        <tr><td class="text-muted">Name</td><td><?= htmlspecialchars($request['full_name']) ?></td></tr>
        <tr><td class="text-muted">Amount Paid</td><td class="text-success fw-bold">PHP <?= number_format($request['payment_amount'], 2) ?></td></tr>
        <tr><td class="text-muted">Reference</td><td><small><?= htmlspecialchars($request['payment_reference']) ?></small></td></tr>
    </table>
    <a href="../dashboards/resident" class="btn btn-success w-100">Back to My Requests</a>
</div>
</body>
</html>
<?php } ?>
