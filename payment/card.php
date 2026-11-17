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

if (!$request) {
    $_SESSION['flash_error'] = 'Unable to find the card payment request.';
    header('Location: ../dashboards/resident');
    exit;
}

if ($request['payment_status'] === 'paid') {
    header('Location: success?request_id=' . $request_id);
    exit;
}

$paymentIntentId = $request['payment_reference'];
if (!str_starts_with($paymentIntentId, 'pi_')) {
    $_SESSION['flash_error'] = 'This payment session is not configured for card payments. Please initiate the payment again.';
    header('Location: ../dashboards/resident');
    exit;
}

$clientKey = $_SESSION['card_client_key_' . $request_id] ?? '';

$formError = '';
$formSuccess = false;

// Handle POST: process card details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $expMonth   = (int)($_POST['exp_month'] ?? 0);
    $expYear    = (int)($_POST['exp_year'] ?? 0);
    $cvc        = trim($_POST['cvc'] ?? '');
    $cardName   = trim($_POST['card_name'] ?? '');

    // Basic validation
    if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
        $formError = 'Please enter a valid card number.';
    } elseif ($expMonth < 1 || $expMonth > 12) {
        $formError = 'Please enter a valid expiry month (01-12).';
    } elseif ($expYear < (int)date('Y')) {
        $formError = 'Your card has expired.';
    } elseif (strlen($cvc) < 3 || strlen($cvc) > 4) {
        $formError = 'Please enter a valid CVC (3 or 4 digits).';
    }

    if (empty($formError)) {
        // Step 1: Create a PayMongo Payment Method with card details
        $pmPayload = json_encode([
            'data' => [
                'attributes' => [
                    'type' => 'card',
                    'details' => [
                        'card_number' => $cardNumber,
                        'exp_month'   => $expMonth,
                        'exp_year'    => $expYear,
                        'cvc'         => $cvc,
                    ],
                    'billing' => [
                        'name'  => $cardName ?: ($request['full_name'] ?? 'Cardholder'),
                        'email' => $request['email'] ?? 'noreply@einquire.gov.ph',
                        'phone' => $request['contact_number'] ?? '',
                    ],
                ],
            ],
        ]);

        $ch = curl_init(PAYMONGO_API_URL . '/payment_methods');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $pmPayload,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
            ],
        ]);

        $pmResponse = curl_exec($ch);
        $pmHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $pmCurlError = curl_error($ch);
        curl_close($ch);

        if ($pmCurlError) {
            $formError = 'Connection error: ' . $pmCurlError;
        } else {
            $pmData = json_decode($pmResponse, true);
            $paymentMethodId = $pmData['data']['id'] ?? null;

            if ($pmHttpCode !== 200 || !$paymentMethodId) {
                $formError = $pmData['errors'][0]['detail']
                    ?? $pmData['message']
                    ?? 'Failed to process your card details. Please try again.';
            }
        }
    }

    // Step 2: Attach the Payment Method to the Payment Intent
    if (empty($formError) && !empty($paymentMethodId)) {
        $baseUrl = get_site_base_url();
        $attachPayload = json_encode([
            'data' => [
                'attributes' => [
                    'payment_method' => $paymentMethodId,
                    'client_key' => $clientKey,
                    'return_url' => $baseUrl . '/payment/success?request_id=' . $request_id,
                ],
            ],
        ]);

        $ch = curl_init(PAYMONGO_API_URL . '/payment_intents/' . $paymentIntentId . '/attach');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $attachPayload,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
            ],
        ]);

        $attachResponse = curl_exec($ch);
        $attachHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $attachCurlError = curl_error($ch);
        curl_close($ch);

        if ($attachCurlError) {
            $formError = 'Connection error during payment: ' . $attachCurlError;
        } else {
            $attachData = json_decode($attachResponse, true);
            $attachStatus = $attachData['data']['attributes']['status'] ?? 'unknown';
            $nextAction = $attachData['data']['attributes']['next_action'] ?? [];

            if ($attachStatus === 'succeeded' || $attachStatus === 'paid') {
                // Payment completed directly (no 3DS required)
                $actualPaymentId = $attachData['data']['attributes']['payments'][0]['id'] ?? $paymentIntentId;
                
                $pdo->prepare("UPDATE requests SET payment_status = 'paid', payment_reference = ?, paymongo_payment_id = ? WHERE request_id = ?")
                    ->execute([$actualPaymentId, $actualPaymentId, $request_id]);
                    
                unset($_SESSION['card_client_key_' . $request_id]);
                header('Location: success?request_id=' . $request_id);
                exit;
            } elseif ($attachStatus === 'awaiting_next_action' && !empty($nextAction['redirect']['url'])) {
                // 3D Secure authentication required — redirect user to the bank's 3DS page
                unset($_SESSION['card_client_key_' . $request_id]);
                header('Location: ' . $nextAction['redirect']['url']);
                exit;
            } elseif (in_array($attachStatus, ['failed', 'expired', 'canceled'], true)) {
                $formError = 'Your card payment was declined. Please try a different card.';
                $pdo->prepare("UPDATE requests SET payment_status = 'failed' WHERE request_id = ?")
                    ->execute([$request_id]);
            } else {
                $formError = $attachData['errors'][0]['detail']
                    ?? 'An unexpected error occurred while processing your payment.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Card Payment — E-Inquire</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    * { font-family: 'Inter', sans-serif; }
    body { background: #f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .checkout-card { max-width:520px; width:100%; border-radius:18px; box-shadow:0 16px 44px rgba(15,23,42,0.08); border:none; }
    .checkout-card .card-body { padding: 2.5rem; }
    .checkout-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
    .amount-display { font-size: 1.75rem; font-weight: 700; color: #1e293b; }
    .status-badge { font-size: 0.7rem; padding: 4px 10px; border-radius: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-pending { background: #fef3c7; color: #92400e; }

    /* Card form styling */
    .card-form .form-label { font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 4px; }
    .card-form .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.65rem 0.9rem;
        font-size: 0.95rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .card-form .form-control:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }
    .card-number-input { font-family: 'Courier New', Courier, monospace; letter-spacing: 2px; font-size: 1.05rem !important; }

    .btn-pay {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-size: 1rem;
        transition: transform 0.15s, box-shadow 0.15s;
        width: 100%;
    }
    .btn-pay:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(79,70,229,0.35); color:#fff; }
    .btn-pay:active { transform: translateY(0); }
    .btn-pay:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

    .btn-cancel {
        background: transparent;
        border: 2px solid #e2e8f0;
        color: #64748b;
        font-weight: 500;
        padding: 0.6rem 1.5rem;
        border-radius: 12px;
        font-size: 0.9rem;
        width: 100%;
        transition: background 0.15s;
    }
    .btn-cancel:hover { background: #f1f5f9; color: #334155; }

    .secure-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.78rem;
        color: #16a34a;
        font-weight: 500;
    }

    .test-cards {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 10px;
        padding: 0.8rem 1rem;
        font-size: 0.78rem;
        color: #166534;
    }
    .test-cards strong { color: #15803d; }
</style>
</head>
<body>
<div class="card checkout-card">
    <div class="card-body">
        <!-- Header -->
        <div class="text-center mb-4">
            <div class="checkout-icon">💳</div>
            <h2 class="fw-bold mb-1">Card Payment</h2>
            <p class="text-muted mb-0">Enter your credit or debit card details below.</p>
        </div>

        <!-- Amount & Details -->
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <div class="text-muted small">Amount</div>
                <div class="amount-display">PHP <?= number_format((float)($request['payment_amount'] ?? 0), 2) ?></div>
            </div>
            <div class="text-end">
                <div class="text-muted small">Status</div>
                <span class="status-badge status-pending">PENDING</span>
            </div>
        </div>
        <div class="mb-4">
            <span class="text-muted small">Document:</span> <strong><?= htmlspecialchars($request['document_type'] ?? 'N/A') ?></strong><br>
            <span class="text-muted small">Name:</span> <?= htmlspecialchars($request['full_name'] ?? 'N/A') ?>
        </div>

        <?php if ($formError): ?>
            <div class="alert alert-danger py-2 px-3" style="border-radius:10px; font-size:0.88rem;">
                ⚠️ <?= htmlspecialchars($formError) ?>
            </div>
        <?php endif; ?>

        <!-- Card Form -->
        <form method="POST" action="" class="card-form" id="cardForm">
            <div class="mb-3">
                <label for="card_name" class="form-label">Cardholder Name</label>
                <input type="text" class="form-control" id="card_name" name="card_name"
                       placeholder="Juan Dela Cruz"
                       value="<?= htmlspecialchars($_POST['card_name'] ?? $request['full_name'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="card_number" class="form-label">Card Number</label>
                <input type="text" class="form-control card-number-input" id="card_number" name="card_number"
                       placeholder="4343 4343 4343 4345"
                       maxlength="23" autocomplete="cc-number" inputmode="numeric" required>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-4">
                    <label for="exp_month" class="form-label">Month</label>
                    <select class="form-control" id="exp_month" name="exp_month" required>
                        <option value="">MM</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= (($_POST['exp_month'] ?? '') == $m) ? 'selected' : '' ?>>
                                <?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-4">
                    <label for="exp_year" class="form-label">Year</label>
                    <select class="form-control" id="exp_year" name="exp_year" required>
                        <option value="">YYYY</option>
                        <?php for ($y = (int)date('Y'); $y <= (int)date('Y') + 15; $y++): ?>
                            <option value="<?= $y ?>" <?= (($_POST['exp_year'] ?? '') == $y) ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-4">
                    <label for="cvc" class="form-label">CVC</label>
                    <input type="text" class="form-control text-center" id="cvc" name="cvc"
                           placeholder="123" maxlength="4" inputmode="numeric"
                           autocomplete="cc-csc" required>
                </div>
            </div>

            <div class="d-grid gap-2 mb-3">
                <button type="submit" class="btn btn-pay" id="payBtn">
                    🔒 Pay PHP <?= number_format((float)($request['payment_amount'] ?? 0), 2) ?>
                </button>
            </div>

            <div class="text-center mb-3">
                <span class="secure-badge">
                    🛡️ Secured by PayMongo — Your card data is encrypted
                </span>
            </div>

            <!-- Test cards info -->
            <div class="test-cards mb-3">
                <strong>Test Cards:</strong><br>
                Visa: <code>4343 4343 4343 4345</code> &nbsp;|&nbsp; CVC: <code>123</code><br>
                Mastercard: <code>5555 4444 3333 1111</code> &nbsp;|&nbsp; CVC: <code>123</code><br>
                Use any future expiry date (e.g. 12/2029).
            </div>
        </form>

        <div class="d-grid">
            <a href="../dashboards/resident" class="btn btn-cancel">Cancel and Go Back</a>
        </div>
    </div>
</div>

<script>
// Format card number with spaces every 4 digits
const cardInput = document.getElementById('card_number');
cardInput.addEventListener('input', function(e) {
    let value = this.value.replace(/\D/g, '');
    let formatted = value.replace(/(.{4})/g, '$1 ').trim();
    this.value = formatted;
});

// Only allow digits in CVC
document.getElementById('cvc').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
});

// Disable button on submit to prevent double-click
document.getElementById('cardForm').addEventListener('submit', function() {
    const btn = document.getElementById('payBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Processing...';
});
</script>
</body>
</html>
