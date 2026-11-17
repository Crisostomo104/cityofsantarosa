<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../data/verify_otp_data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santa Rosa - Verify Code</title>
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
</head>
<body>

<!-- Navbar Start (same as homepage, not clickable) -->
<nav class="navbar bg-theme navbar-light sticky-top shadow p-0">
    <div class="navbar-brand d-flex align-items-center px-4 px-lg-5 mx-auto">
        <img class="h-95" src="/cityofsantarosa/images/logos/erequest-logo.png" alt="City of Santa Rosa">
    </div>
</nav>
<!-- Navbar End -->

<div class="login-page-wrap" style="min-height: 100vh; display: flex; align-items: center;">
<div class="index-container">
    <h1 class="mb-3">Verify Your Login</h1>
    <p class="mb-4" style="font-size:14px;">
        We sent a 6-digit code to <strong><?= htmlspecialchars($pending_email) ?></strong>. Enter it below to continue.
    </p>

    <?php if ($otp_error): ?>
        <div class="error-message"><?= htmlspecialchars($otp_error) ?></div>
    <?php endif; ?>

    <?php if ($otp_notice): ?>
        <div style="background:#e6f4ea; color:#1e7e34; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-size:14px;">
            <?= htmlspecialchars($otp_notice) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="otpForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>One-Time Code</label>
            <input
                type="text"
                name="otp"
                id="otpInput"
                inputmode="numeric"
                pattern="\d{6}"
                maxlength="6"
                placeholder="000000"
                required
                autofocus
                style="letter-spacing:8px; text-align:center; font-size:1.4rem;"
            >
        </div>
        <button type="submit" class="btn-primary">Verify</button>
    </form>

    <form method="POST" id="resendForm" style="margin-top:12px;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="resend" value="1">
        <button type="submit" class="btn-outline" id="resendBtn">Resend Code</button>
    </form>

    <a href="verify-otp?cancel=1" class="bottom-text" style="display:block; margin-top:16px;">Cancel and go back to login</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Digits-only input
    document.getElementById('otpInput').addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
    });

    // Client-side resend cooldown UX (server still enforces the real limit via rate_limit())
    const resendBtn = document.getElementById('resendBtn');
    const resendForm = document.getElementById('resendForm');
    let cooldown = 30;
    let cooldownInterval = null;

    function startCooldown() {
        resendBtn.disabled = true;
        cooldown = 30;
        cooldownInterval = setInterval(() => {
            resendBtn.textContent = `Resend Code (${cooldown}s)`;
            cooldown--;
            if (cooldown < 0) {
                clearInterval(cooldownInterval);
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend Code';
            }
        }, 1000);
    }

    startCooldown();
</script>
    
<?php if (!empty($needsTakeover)): ?>
<form id="takeoverForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="takeover" id="takeoverField" value="1">
</form>
<script>
Swal.fire({
    icon: 'warning',
    title: 'Account already in use',
    html: '<p>This account is currently logged in on <strong>another device or browser</strong>.</p>' +
          '<p style="color:#888;font-size:13px;margin-top:8px;">Do you want to log out the other device and continue here?</p>',
    showCancelButton: true,
    confirmButtonText: 'Log out other device & continue',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#dc3545',
    allowOutsideClick: false,
    allowEscapeKey: false
}).then(r => {
    document.getElementById('takeoverField').name = r.isConfirmed ? 'takeover' : 'cancel_takeover';
    document.getElementById('takeoverForm').submit();
});
</script>
<?php endif; ?>
    
</div>
</div>
</body>
</html>