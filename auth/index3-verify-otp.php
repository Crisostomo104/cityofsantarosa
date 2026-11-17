<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../data/verify_otp_data2.php';

$csrfToken = csrf_token();
$otpError = htmlspecialchars($otp_error, ENT_QUOTES, 'UTF-8');
$otpNotice = htmlspecialchars($otp_notice, ENT_QUOTES, 'UTF-8');
$pendingEmailSafe = htmlspecialchars($pending_email, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>Verify login | eRequest Mo</title>
    <script>if (window.self !== window.top) document.documentElement.classList.add('auth-embedded');</script>
    <link rel="stylesheet" href="../css/index3-auth-pages.css?v=9">
    <link rel="stylesheet" href="../css/index3-verify-otp.css?v=5">
    <link rel="stylesheet" href="../css/auth-page-navbar.css?v=5">
</head>
<body class="auth-page auth-page--verify">
    <nav class="navbar bg-theme navbar-light sticky-top shadow p-0 auth-page-navbar auth-page-navbar--desktop-only auth-page-navbar--glass" aria-label="Login verification">
        <div class="navbar-brand d-flex align-items-center auth-page-navbar-brand">
            <img src="../images/logos/erequest-logo.png" alt="eRequest Mo">
        </div>
        <span class="auth-page-navbar-title">Verify Login</span>
    </nav>
    <main class="auth-page-shell auth-page-shell--verify">
        <section class="auth-card auth-card--verify" aria-labelledby="loginOtpTitle">
            <h1 id="loginOtpTitle">Verify it’s you</h1>
            <p class="auth-intro">We sent a one-time code to your email. Enter it below to securely finish signing in.</p>

            <div class="verify-otp-recipient">
                <span class="verify-otp-recipient-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/>
                        <path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span><small>Code sent to</small><strong><?= $pendingEmailSafe ?></strong></span>
            </div>

            <?php if ($otpError !== ''): ?>
                <p class="auth-alert verify-otp-alert" role="alert"><?= $otpError ?></p>
            <?php elseif ($otpNotice !== ''): ?>
                <p class="auth-success verify-otp-alert" role="status"><?= $otpNotice ?></p>
            <?php endif; ?>

            <?php if (empty($needsTakeover)): ?>
                <form method="post" action="index3-verify-otp" class="verify-otp-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="auth_modal" value="">
                    <label class="auth-field">
                        <span>6-digit verification code</span>
                        <input class="verify-otp-code" type="text" name="otp" inputmode="numeric"
                            autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6"
                            aria-label="6-digit numeric verification code"
                            placeholder="••••••" aria-describedby="verifyOtpHelp" required autofocus>
                        <small id="verifyOtpHelp">Check your inbox and enter the code exactly as received.</small>
                    </label>
                    <button class="auth-submit verify-otp-submit" type="submit">
                        Verify and continue
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </form>

                <form method="post" action="index3-verify-otp" class="auth-otp-resend-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="auth_modal" value="">
                    <button class="auth-otp-resend verify-otp-resend" type="submit" name="resend" value="1"
                        data-otp-resend data-otp-expires-at="<?= (int)$pendingOtpExpiresAt ?>">
                        Didn’t get a code? <strong data-otp-resend-label>Send a new code</strong>
                    </button>
                </form>
                <a class="auth-back-link verify-otp-back" href="index3-verify-otp?cancel=1">Cancel and return to login</a>
            <?php else: ?>
                <div class="auth-alert verify-takeover-alert" role="alert">
                    <strong>This account is active on another device.</strong>
                    <span>To continue here, you’ll need to end that other session.</span>
                </div>
                <form method="post" action="index3-verify-otp" class="auth-takeover-actions">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="auth_modal" value="">
                    <button class="auth-submit verify-otp-submit" type="submit" name="takeover" value="1">Log out other device and continue</button>
                    <button class="auth-otp-resend verify-otp-cancel" type="submit" name="cancel_takeover" value="1">Keep other session</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
    <script src="../js/index3-verify-otp.js?v=3"></script>
</body>
</html>
