<?php
/**
 * ─────────────────────────────────────────────────────────────
 *  PUBLIC QR VERIFICATION PAGE — No login required
 *  Accessed by scanning the QR code a resident downloads after
 *  submitting a request. URL format:
 *    verify.php?code=REQ...&token=...
 *
 *  Purpose: lets ANYONE who scans the QR (barangay staff or the
 *  resident themselves) confirm that a given tracking code +
 *  token pair is real, and see a small set of non-sensitive
 *  details — not a login-gated lookup.
 * ─────────────────────────────────────────────────────────────
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/tracking.php';

$code  = strtoupper(trim($_GET['code'] ?? ''));
$token = trim($_GET['token'] ?? '');

$result = null;
$error  = null;

if ($code === '' || $token === '') {
    $error = 'This link is missing required verification details.';
} else {
    $result = verify_qr_code($pdo, $code, $token);
    if (!$result) {
        $error = 'This QR code could not be verified. It may be invalid or expired.';
    }
}

function status_badge_class(string $status): string {
    $status = strtolower($status);
    if (str_contains($status, 'approve')) {
        return 'badge-approved';
    }
    if (str_contains($status, 'complet') || str_contains($status, 'ready')) {
        return 'badge-success';
    }
    if (str_contains($status, 'reject') || str_contains($status, 'denied') || str_contains($status, 'cancel')) {
        return 'badge-danger';
    }
    return 'badge-pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santa Rosa - Request Verification</title>
    <link rel="icon" href="../images/ibaba2.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://santarosacity.gov.ph/assets/fonts/stylesheet.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/assets/css/bootstrap.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/assets/css/style.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/login.css">
    <style>
        /* Page-specific bits only — everything else (gradient body,
           .index-container card, fonts, colors) comes from login.css */
        .verify-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.8rem;
            color: #fff;
        }
        .verify-icon.ok   { background: linear-gradient(135deg, #2e7d32, #43a047); }
        .verify-icon.fail { background: linear-gradient(135deg, var(--dark-red), var(--red)); }

        .verify-subtitle {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 20px;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.92rem;
        }
        .detail-table tr { border-bottom: 1px solid #f0f0f0; }
        .detail-table tr:last-child { border-bottom: none; }
        .detail-table td { padding: 10px 4px; }
        .detail-table td.label { color: #6b7280; width: 42%; font-weight: 600; }
        .detail-table td.value { color: #111827; font-weight: 500; word-break: break-word; }

        .badge-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .badge-success { background: #e6f4ea; color: #1e7e34; }
        .badge-approved { background: #DBEAFE; color: #1E40AF; }
        .badge-pending { background: #fff4e5; color: #b25f00; }
        .badge-danger  { background: #fdeaea; color: var(--dark-red); }

        .verify-error {
            font-size: 0.95rem;
            line-height: 1.6;
            color: #4b5563;
            margin: 0;
        }
        .verify-footnote {
            margin-top: 20px;
            font-size: 0.75rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>

<!-- Navbar (same as the rest of the system, not clickable) -->
<nav class="navbar bg-theme navbar-light sticky-top shadow p-0">
    <div class="navbar-brand d-flex align-items-center px-4 px-lg-5 mx-auto">
        <img class="h-95" src="../images/logos/erequest-logo.png" alt="City of Santa Rosa">
    </div>
</nav>

<div class="login-page-wrap" style="min-height: 100vh; display: flex; align-items: center;">
<div class="index-container">
    <?php if ($result): ?>
        <div class="verify-icon ok">&#10003;</div>
        <h1 class="mb-1">Verified Request</h1>
        <p class="verify-subtitle">This QR code matches a valid eRequest Mo submission.</p>
        <table class="detail-table">
            <tr>
                <td class="label">Request No.</td>
                <td class="value"><?= htmlspecialchars($result['tracking_code']) ?></td>
            </tr>
            <tr>
                <td class="label">Document Type</td>
                <td class="value"><?= htmlspecialchars($result['document_type'] ?? 'N/A') ?> </td>
            </tr>
            <tr>
                <td class="label">Status</td>
                <td class="value">
                    <span class="badge-pill <?= status_badge_class($result['status']) ?>">
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $result['status']))) ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td class="label">Payment</td>
                <td class="value">
                  <?php
                    $method = $result['preferred_payment_method'] ?? 'N/A';
                    echo htmlspecialchars($method === 'walk-in' ? 'Cash' : $method);
                  ?>
                </td>
                </td>
            </tr>
            <tr>
                <td class="label">Barangay</td>
                <td class="value"><?= htmlspecialchars($result['barangay'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td class="label">Date Submitted</td>
                <td class="value"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($result['created_at']))) ?></td>
            </tr>
        </table>
    <?php else: ?>
        <div class="verify-icon fail">&#10007;</div>
        <h1 class="mb-1">Not Verified</h1>
        <p class="verify-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <div class="verify-footnote">Santa Rosa eRequest &middot; Request Verification</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>