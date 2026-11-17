<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth_guard.php';

check_login_redirect(['resident']);

$request_id = (int)($_GET['request_id'] ?? 0);

// Mark payment as failed (unless already paid)
$stmt = $pdo->prepare("SELECT * FROM requests WHERE request_id = ? AND user_id = ?");
$stmt->execute([$request_id, $_SESSION['user_id']]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if ($request && $request['payment_status'] !== 'paid') {
    $pdo->prepare("UPDATE requests SET payment_status = 'failed' WHERE request_id = ?")
        ->execute([$request_id]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Failed — E-Inquire</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background: #fff5f5; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card { max-width:440px; width:100%; border-radius:18px; box-shadow:0 8px 32px rgba(200,0,0,0.1); }
    .icon { font-size:72px; }
</style>
</head>
<body>
<div class="card p-5 text-center">
    <div class="icon mb-3">❌</div>
    <h3 class="fw-bold text-danger mb-1">Payment Failed</h3>
    <p class="text-muted mb-4">Your PayMongo QRPh payment was not completed. Your request has been saved — you can try again.</p>
    <?php if ($request_id > 0): ?>
    <div class="d-grid gap-2 mb-2">
        <a href="../action/initiate_payment?request_id=<?= $request_id ?>&provider=qrph" class="btn btn-success">
            💳 Try PayMongo QRPh Again
        </a>
    </div>
    <?php endif; ?>
    <a href="../dashboards/resident" class="btn btn-outline-secondary w-100">Back to My Requests</a>
</div>
</body>
</html>
