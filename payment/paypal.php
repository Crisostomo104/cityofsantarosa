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
    $_SESSION['flash_error'] = 'Unable to find the PayPal payment request.';
    header('Location: ../dashboards/resident');
    exit;
}

$paymentReference = $request['payment_reference'];
if (!str_starts_with($paymentReference, 'paypal_pending_') && !str_starts_with($paymentReference, 'paypal_order_')) {
    $_SESSION['flash_error'] = 'This payment session is not configured for PayPal.';
    header('Location: ../dashboards/resident');
    exit;
}

if ($request['payment_status'] === 'paid') {
    header('Location: success?request_id=' . $request_id);
    exit;
}

// Convert PHP to USD for PayPal SDK compatibility
$usdAmount = round((float)$request['payment_amount'] / PHP_USD_EXCHANGE_RATE, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PayPal Payment — E-Inquire</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background: #f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card { max-width:560px; width:100%; border-radius:18px; box-shadow:0 16px 44px rgba(15,23,42,0.08); }
    .paypal-brand { color: #003087; font-weight: 700; }
</style>
</head>
<body>
<div class="card p-4 p-md-5">
    <div class="text-center mb-4">
        <!-- PayPal-themed styling SVG -->
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#003087" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.08 6.01c-.13-.39-.44-.71-.83-.84l-11-3.66c-.66-.22-1.37.13-1.59.79l-4.14 12.42c-.22.66.13 1.37.79 1.59l11 3.66c.13.04.26.06.39.06c.53 0 1.02-.33 1.2-.84l4.14-12.42c.22-.66-.13-1.37-.79-1.59l.83.83z"/><path d="M12 2v20"/></svg>
        <h2 class="mt-2 paypal-brand">PayPal Checkout</h2>
        <p class="text-muted mb-0">Complete the payment using your PayPal account.</p>
    </div>

    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <div class="text-muted small">Amount (PHP)</div>
                <div class="fs-4 fw-semibold text-primary">PHP <?= number_format((float)$request['payment_amount'], 2) ?></div>
                <div class="text-muted small">Converted amount: <strong>USD <?= number_format($usdAmount, 2) ?></strong></div>
            </div>
            <div class="text-end">
                <div class="text-muted small">Status</div>
                <div class="badge bg-warning text-dark text-uppercase"><?= htmlspecialchars($request['payment_status']) ?></div>
            </div>
        </div>
        <div class="text-muted small mt-3">Document: <strong><?= htmlspecialchars($request['document_type']) ?></strong></div>
        <div class="text-muted small">Name: <?= htmlspecialchars($request['full_name']) ?></div>
    </div>

    <div class="mb-4 p-3 border rounded-3 bg-light">
        <div class="fw-semibold mb-1 small text-muted">Payment instructions:</div>
        <ul class="mb-0 ps-3 small text-muted">
            <li>Click the gold PayPal button below to launch the secure checkout window.</li>
            <li>Log in to your PayPal account to complete the payment (charged as <strong>$<?= number_format($usdAmount, 2) ?> USD</strong>).</li>
            <li>Do not close or reload this window until redirecting completes.</li>
        </ul>
    </div>

    <!-- PayPal Button Container -->
    <div id="paypal-button-container" class="mb-4"></div>

    <div class="mb-4 p-3 bg-white border rounded-3 small text-muted">
        <div><strong>Payment Type:</strong> PayPal API (USD Converted)</div>
        <div><strong>Reference:</strong> <?= htmlspecialchars($paymentReference) ?></div>
    </div>

    <div class="d-grid">
        <a href="../dashboards/resident" class="btn btn-outline-secondary">Cancel and Go Back</a>
    </div>
</div>

<!-- Load PayPal JavaScript SDK with USD currency -->
<script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode(PAYPAL_CLIENT_ID) ?>&currency=USD"></script>
<script>
    paypal.Buttons({
        // Set up the transaction details in USD
        createOrder: function(data, actions) {
            return actions.order.create({
                purchase_units: [{
                    amount: {
                        currency_code: 'USD',
                        value: '<?= number_format($usdAmount, 2, '.', '') ?>'
                    },
                    description: 'E-Inquire Document Fee: <?= htmlspecialchars(addslashes($request['document_type'])) ?>'
                }]
            });
        },
        // Capture the funds when the user authorizes the payment
        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                // Redirect the user to success.php with order details for server-side verification
                window.location.replace("success?request_id=<?= $request_id ?>&paypal_order_id=" + encodeURIComponent(details.id));
            });
        },
        // Handle checkout errors
        onError: function(err) {
            console.error('PayPal Checkout Error:', err);
            alert('An error occurred during your PayPal transaction. Please try again or use another payment method.');
        }
    }).render('#paypal-button-container');
</script>
</body>
</html>
