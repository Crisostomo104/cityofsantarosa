<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/paymongo_config.php';

check_login_redirect(['resident']);

$request_id = (int)($_GET['request_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM requests WHERE request_id = ? AND user_id = ?");
$stmt->execute([$request_id, $_SESSION['user_id']]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request || empty($request['payment_reference'])) {
    $_SESSION['flash_error'] = 'Unable to find the GCash payment request.';
    header('Location: ../dashboards/resident');
    exit;
}

$paymentIntentId = $request['payment_reference'];
if (!str_starts_with($paymentIntentId, 'pi_') && !is_simulated_payment_reference((string)$paymentIntentId)) {
    $_SESSION['flash_error'] = 'This payment session is not configured for GCash.';
    header('Location: ../dashboards/resident');
    exit;
}

if (isset($_GET['simulate']) && $_GET['simulate'] === 'complete' && is_simulated_payment_reference((string)$paymentIntentId)) {
    $pdo->prepare("UPDATE requests SET payment_status = 'paid' WHERE request_id = ?")->execute([$request_id]);
    header('Location: success?request_id=' . $request_id);
    exit;
}

if ($request['payment_status'] === 'paid') {
    header('Location: success?request_id=' . $request_id);
    exit;
}

$ch = curl_init(PAYMONGO_API_URL . '/payment_intents/' . urlencode($paymentIntentId));
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    @file_put_contents(__DIR__ . '/../logs/paymongo_errors.log', json_encode([
        'time' => date('c'),
        'request_id' => $request_id,
        'payment_reference' => $paymentIntentId,
        'error' => $curlError,
        'stage' => 'fetch_gcash_payment_intent',
    ]) . PHP_EOL, FILE_APPEND | LOCK_EX);

    $_SESSION['flash_error'] = 'Unable to connect to PayMongo to load your GCash payment. Please try again.';
    header('Location: ../dashboards/resident');
    exit;
}

$data = json_decode($response, true);
$intent = $data['data'] ?? [];
$status = $intent['attributes']['status'] ?? 'unknown';
$nextAction = $intent['attributes']['next_action'] ?? [];
$redirectUrl = $nextAction['redirect']['url'] ?? '';
$errorDetail = '';
if (!empty($data['errors'][0]['detail'])) {
    $errorDetail = $data['errors'][0]['detail'];
} elseif (!empty($data['message'])) {
    $errorDetail = $data['message'];
}

if (is_simulated_payment_reference((string)$paymentIntentId)) {
    $status = 'simulated_pending';
    $redirectUrl = '';
    $errorDetail = 'This is a local simulation for GCash checkout.';
} elseif ($httpCode === 200 && in_array($status, ['paid', 'succeeded', 'processing', 'captured'], true)) {
    $pdo->prepare("UPDATE requests SET payment_status = 'paid' WHERE request_id = ?")
        ->execute([$request_id]);
    header('Location: success?request_id=' . $request_id);
    exit;
}

if ($httpCode === 200 && in_array($status, ['canceled', 'failed', 'expired'], true)) {
    $pdo->prepare("UPDATE requests SET payment_status = 'failed' WHERE request_id = ?")
        ->execute([$request_id]);
    header('Location: failed?request_id=' . $request_id);
    exit;
}

if ($httpCode !== 200 || empty($intent)) {
    @file_put_contents(__DIR__ . '/../logs/paymongo_errors.log', json_encode([
        'time' => date('c'),
        'request_id' => $request_id,
        'payment_reference' => $paymentIntentId,
        'http_code' => $httpCode,
        'response' => $response,
        'stage' => 'gcash_payment_intent_fetch_error',
    ]) . PHP_EOL, FILE_APPEND | LOCK_EX);

    $_SESSION['flash_error'] = 'Unable to load the GCash payment details. Please try again later.';
    header('Location: ../dashboards/resident');
    exit;
}

function formatExpiry($timestamp)
{
    if (empty($timestamp)) {
        return 'Unknown';
    }
    return date('F j, Y H:i:s', strtotime($timestamp));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GCash Payment — E-Inquire</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background: #f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card { max-width:560px; width:100%; border-radius:18px; box-shadow:0 16px 44px rgba(15,23,42,0.08); }
</style>
</head>
<body>
<div class="card p-4 p-md-5">
    <div class="text-center mb-4">
        <h2 class="fw-bold">GCash Payment</h2>
        <p class="text-muted mb-0">Complete the payment using your GCash app or the payment link below.</p>
    </div>

    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <div class="text-muted">Amount</div>
                <div class="fs-4 fw-semibold">PHP <?= number_format((float)$request['payment_amount'], 2) ?></div>
            </div>
            <div class="text-end">
                <div class="text-muted">Status</div>
                <div class="badge bg-info text-dark text-uppercase"><?= htmlspecialchars($status) ?></div>
            </div>
        </div>
        <div class="text-muted small">Document: <?= htmlspecialchars($request['document_type']) ?></div>
        <div class="text-muted small">Name: <?= htmlspecialchars($request['full_name']) ?></div>
    </div>

    <div class="mb-4 p-3 border rounded-3 bg-light">
        <div class="fw-semibold mb-2">Payment instructions</div>
        <ul class="mb-0 ps-3">
            <li>Open your GCash app and approve the payment request.</li>
            <li>If a checkout link is provided by PayMongo, use it below.</li>
            <li>Return here after payment is completed.</li>
        </ul>
    </div>

    <?php if (is_simulated_payment_reference((string)$paymentIntentId)): ?>
    <div class="d-grid mb-3">
        <a href="gcash?request_id=<?= $request_id ?>&simulate=complete" class="btn btn-success">Simulate Payment Completion</a>
    </div>
    <?php elseif (!empty($redirectUrl)): ?>
    <div class="d-grid mb-3">
        <a href="<?= htmlspecialchars($redirectUrl) ?>" class="btn btn-success" target="_blank" rel="noopener noreferrer">Open GCash Checkout</a>
    </div>
    <?php endif; ?>

    <div class="mb-3 p-3 bg-white border rounded-3 small text-muted">
        <div><strong>PayMongo status:</strong> <?= htmlspecialchars($status) ?></div>
        <div><strong>Payment intent:</strong> <?= htmlspecialchars($paymentIntentId) ?></div>
        <?php if (!empty($errorDetail)): ?>
            <div class="mt-2 text-danger"><strong>PayMongo response:</strong> <?= htmlspecialchars($errorDetail) ?></div>
        <?php endif; ?>
    </div>

    <div class="d-grid gap-2">
        <a href="gcash?request_id=<?= $request_id ?>" class="btn btn-outline-primary">Refresh Status</a>
        <a href="../dashboards/resident" class="btn btn-secondary">Back to My Requests</a>
    </div>
</div>
</body>
</html>
