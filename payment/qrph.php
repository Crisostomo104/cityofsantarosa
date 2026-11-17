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
    $_SESSION['flash_error'] = 'Unable to find the payment request.';
    header('Location: ../dashboards/resident');
    exit;
}

$paymentIntentId = $request['payment_reference'];
if (!str_starts_with($paymentIntentId, 'pi_')) {
    $_SESSION['flash_error'] = 'This payment session is not configured for PayMongo QRPh.';
    header('Location: ../dashboards/resident');
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
        'stage' => 'fetch_payment_intent',
    ]) . PHP_EOL, FILE_APPEND | LOCK_EX);

    $_SESSION['flash_error'] = 'Unable to connect to PayMongo to load your QR payment. Please try again.';
    header('Location: ../dashboards/resident');
    exit;
}

$data = json_decode($response, true);
$intent = $data['data'] ?? [];
$qrDetails = extract_paymongo_qr_details($data);
$status = $qrDetails['status'] ?? 'unknown';
$imageUrl = $qrDetails['image_url'] ?? '';
$codeData = $qrDetails['code_data'] ?? '';
$expiresAt = $qrDetails['expires_at'] ?? null;
$errorDetail = '';
if (!empty($data['errors'][0]['detail'])) {
    $errorDetail = $data['errors'][0]['detail'];
} elseif (!empty($data['message'])) {
    $errorDetail = $data['message'];
}

if ($httpCode === 200 && in_array($status, ['paid', 'succeeded', 'processing', 'captured'], true)) {
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
        'stage' => 'payment_intent_fetch_error',
    ]) . PHP_EOL, FILE_APPEND | LOCK_EX);

    $_SESSION['flash_error'] = 'Unable to load PayMongo QRPh details. Please try again later.';
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
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PayMongo QRPh Payment — E-Inquire</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<style>
    body { background: #f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card { max-width:540px; width:100%; border-radius:18px; box-shadow:0 16px 44px rgba(15,23,42,0.08); }
    .qr-placeholder { min-height:260px; display:flex; align-items:center; justify-content:center; background:#ffffff; border:1px dashed #cbd5e1; border-radius:16px; }
    .qr-placeholder img { max-width:100%; height:auto; }
    .qr-code-text { word-break: break-all; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
</style>
<script>
    const REFRESH_SECONDS = 10;
    let countdown = REFRESH_SECONDS;
    function tick() {
        const label = document.getElementById('refresh-countdown');
        if (!label) return;
        if (countdown <= 0) {
            window.location.reload();
            return;
        }
        label.textContent = countdown;
        countdown -= 1;
    }
    window.addEventListener('load', () => {
        setInterval(tick, 1000);
    });
</script>
</head>
<body>
<div class="card p-4 p-md-5">
    <div class="text-center mb-4">
        <h2 class="fw-bold">PayMongo QRPh</h2>
        <p class="text-muted mb-0">Scan this QR code with your banking app or any QRPh-enabled wallet.</p>
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

    <div class="qr-placeholder mb-4">
        <?php if (!empty($codeData)): ?>
            <div id="qr-code" class="w-100 d-flex justify-content-center"></div>
        <?php elseif ($imageUrl): ?>
            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="PayMongo QRPh code">
        <?php else: ?>
            <div class="text-center text-muted px-4">
                <p class="mb-2">QR code image is not available yet.</p>
                <p class="mb-0">Please refresh this page or try again in a few seconds.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($codeData)): ?>
    <div class="mb-4 px-3 py-3 bg-white border rounded-3">
        <div class="text-muted small mb-2">Payment code</div>
        <div class="qr-code-text text-break"><?= htmlspecialchars($codeData) ?></div>
    </div>
    <?php endif; ?>

    <div class="mb-4 text-center text-muted small">
        <?= $expiresAt ? 'Expires on ' . htmlspecialchars(formatExpiry($expiresAt)) . '.' : 'This QR code will remain valid while your payment is pending.' ?>
    </div>

    <div class="mb-3 px-3 py-3 bg-light border rounded-3 small text-muted">
        <div><strong>PayMongo status:</strong> <?= htmlspecialchars($status) ?></div>
        <div><strong>Payment intent:</strong> <?= htmlspecialchars($paymentIntentId) ?></div>
        <?php if (!empty($errorDetail)): ?>
            <div class="mt-2 text-danger"><strong>PayMongo response:</strong> <?= htmlspecialchars($errorDetail) ?></div>
        <?php elseif (empty($codeData) && empty($imageUrl)): ?>
            <div class="mt-2 text-warning">No QR payload was returned by PayMongo yet. This usually means the merchant account or PayMongo configuration is not issuing QRPh payment data for this transaction.</div>
        <?php endif; ?>
    </div>

    <div class="d-grid gap-2">
        <a href="<?= htmlspecialchars('qrph?request_id=' . $request_id) ?>" class="btn btn-outline-primary">Refresh QR Code (<span id="refresh-countdown">10</span>s)</a>
        <a href="../dashboards/resident" class="btn btn-secondary">Back to My Requests</a>
    </div>
</div>
<?php if (!empty($codeData)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('qr-code');
        if (!container || typeof QRCode === 'undefined') {
            return;
        }
        container.innerHTML = '';
        new QRCode(container, {
            text: <?= json_encode($codeData) ?>,
            width: 260,
            height: 260,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    });
</script>
<?php endif; ?>
</body>
</html>
