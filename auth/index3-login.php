<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../data/login_data2.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exit;
}

$csrfToken = csrf_token();
$loginEmailValue = $loginEmail;
$errorMessage = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
$recaptchaSiteKey = htmlspecialchars($_ENV['RECAPTCHA_SITE_KEY'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>Log in | eRequest Mo</title>
    <script>if (window.self !== window.top) document.documentElement.classList.add('auth-embedded');</script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/index.css?v=3">
    <link rel="stylesheet" href="../css/index3.css?v=29">
    <link rel="stylesheet" href="../css/index3-auth-pages.css?v=9">
    <link rel="stylesheet" href="../css/index3-auth-forms.css?v=9">
    <link rel="stylesheet" href="../loads-modal/loads-modal.css?v=3">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="auth-page auth-page--site-shell auth-page--login">
    <?php require __DIR__ . '/../partials/index3-auth-navbar.php'; ?>
    <main class="auth-page-shell">
        <section class="auth-card auth-card--login" aria-labelledby="loginTitle">
            <h1 id="loginTitle">Log in to eRequest Mo</h1>
            <p class="auth-intro">Sign in to submit requests and check their progress.</p>

            <?php if ($errorMessage !== ''): ?>
                <p class="auth-alert" role="alert"><?= $errorMessage ?></p>
            <?php endif; ?>

            <form method="post" action="index3-login" id="index3LoginForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <label class="auth-field">
                    <span>Email address</span>
                    <input type="email" name="email" value="<?= $loginEmailValue ?>" autocomplete="username"
                        placeholder="you@example.com" required>
                </label>

                <div class="auth-field">
                    <label for="index3LoginPassword">Password</label>
                    <div class="auth-password-control">
                        <input id="index3LoginPassword" type="password" name="password" autocomplete="current-password"
                            placeholder="Enter your password" required>
                        <button class="auth-password-toggle" type="button" data-password-toggle
                            aria-controls="index3LoginPassword" aria-label="Show password" aria-pressed="false">
                            <svg data-password-icon="show" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                            </svg>
                            <svg data-password-icon="hide" viewBox="0 0 24 24" aria-hidden="true" focusable="false" hidden>
                                <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a15.5 15.5 0 0 1-3.1 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1 0 1.9-.2 2.8-.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="auth-login-links">
                    <a href="#" id="forgotPasswordBtn">Forgot password?</a>
                </div>

                <div class="auth-captcha">
                    <div class="g-recaptcha" data-sitekey="<?= $recaptchaSiteKey ?>"></div>
                </div>

                <button class="auth-submit" type="submit">Log in</button>
            </form>

            <div class="auth-form-divider"><span>New to eRequest Mo?</span></div>
            <a class="auth-secondary-action" href="index3-registration" data-auth-switch="register">Create a resident account</a>
        </section>
    </main>
    <?php require __DIR__ . '/../partials/index3-auth-footer.php'; ?>

    <div id="forgotPasswordModal" class="auth-modal-backdrop" aria-hidden="true">
        <div class="auth-modal" role="dialog" aria-modal="true" aria-labelledby="forgotPasswordTitle">
            <div class="auth-modal-header">
                <h2 id="forgotPasswordTitle">Reset Password</h2>
                <button type="button" class="auth-modal-close" data-auth-modal-close aria-label="Close reset password dialog">&times;</button>
            </div>
            <p class="auth-modal-body">Enter your email address and we'll send you instructions to reset your password.</p>
            <form id="forgotPasswordForm" class="auth-modal-form" novalidate>
                <input type="email" id="forgotPasswordEmail" name="email" placeholder="you@example.com" autocomplete="username" required>
                <button type="submit">Send Reset Link</button>
            </form>
            <div id="forgotPasswordMessage" class="auth-modal-message" aria-live="polite"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/index3-auth-shell.js?v=1"></script>
    <script src="../js/index3-auth-pages.js?v=8"></script>
</body>
</html>
