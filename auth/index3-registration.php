<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/barangays.php';
require_once __DIR__ . '/registration_php.php';

$csrfToken = csrf_token();
$errorMessage = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
$recaptchaSiteKey = htmlspecialchars($_ENV['RECAPTCHA_SITE_KEY'] ?? '', ENT_QUOTES, 'UTF-8');
$registrationOtpReady = !empty($_SESSION['pending_user'])
    && in_array($success, ['Sending OTP...', 'OTP already sent.', 'OTP sent to your phone.'], true);
$idVerificationSuccess = !empty($_SESSION['registration_id_verified_flash']);
unset($_SESSION['registration_id_verified_flash']);
$otpExpiresAt = (int)($_SESSION['otp_expire'] ?? 0);
$otpLockedUntil = (int)($_SESSION['otp_locked_until'] ?? 0);
$otpResendAfter = (int)($_SESSION['otp_last_sent'] ?? 0) + 30;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>Create account | eRequest Mo</title>
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
<body class="auth-page auth-page--site-shell auth-page--registration">
    <?php require __DIR__ . '/../partials/index3-auth-navbar.php'; ?>
    <main class="auth-page-shell auth-page-shell--registration">
        <section class="auth-card auth-card--registration" aria-labelledby="registerTitle">
            <h1 id="registerTitle">Create your account</h1>
            <p class="auth-intro">Set up your account to request barangay documents online.</p>
            <p class="auth-refresh-reminder">Please avoid refreshing this page while filling out the form. Your entered details and selected ID image may be cleared.</p>

            <ol class="auth-registration-steps" aria-label="Registration steps">
                <li class="is-current"><span>1</span><small>Details</small></li>
                <li><span>2</span><small>ID check</small></li>
                <li><span>3</span><small>Mobile code</small></li>
            </ol>

            <?php if ($errorMessage !== ''): ?>
                <p class="auth-alert auth-form-message" role="alert"><?= $errorMessage ?></p>
            <?php elseif ($success !== '' && !$registrationOtpReady): ?>
                <p class="auth-success auth-form-message" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form method="post" action="index3-registration" enctype="multipart/form-data" id="index3RegistrationForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <h2 class="auth-form-section-title"><span>01</span> Personal information</h2>
                <div class="auth-form-grid">
                    <label class="auth-field">
                        <span>First name *</span>
                        <input type="text" name="firstname" value="<?= old('firstname') ?>" autocomplete="given-name"
                            pattern="[A-Za-zÑñ\s\-\.]+" required>
                    </label>
                    <label class="auth-field">
                        <span>Middle name / initial</span>
                        <input type="text" name="middlename" value="<?= old('middlename') ?>" autocomplete="additional-name"
                            pattern="[A-Za-zÑñ\s\-\.]*">
                    </label>
                    <label class="auth-field">
                        <span>Last name *</span>
                        <input type="text" name="lastname" value="<?= old('lastname') ?>" autocomplete="family-name"
                            pattern="[A-Za-zÑñ\s\-\.]+" required>
                    </label>
                    <label class="auth-field">
                        <span>Suffix</span>
                        <select name="suffix">
                            <option value="">None</option>
                            <?php foreach (['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'] as $suffix): ?>
                                <option value="<?= htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= old('suffix') === $suffix ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="auth-field">
                        <span>Email address *</span>
                        <input type="email" name="email" value="<?= old('email') ?>" autocomplete="email"
                            placeholder="you@example.com" required>
                    </label>
                    <label class="auth-field">
                        <span>Mobile number *</span>
                        <input type="tel" name="phone" value="<?= old('phone') ?>" autocomplete="tel"
                            inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" required>
                    </label>
                    <label class="auth-field">
                        <span>Barangay *</span>
                        <select name="barangay" required><?= barangay_options(old('barangay')) ?></select>
                    </label>
                    <div class="auth-field">
                        <label for="index3RegistrationPassword">Password *</label>
                        <div class="auth-password-control">
                            <input id="index3RegistrationPassword" type="password" name="password" autocomplete="new-password" minlength="8"
                                aria-describedby="passwordHelp" required>
                            <button class="auth-password-toggle" type="button" data-password-toggle
                                aria-controls="index3RegistrationPassword" aria-label="Show password" aria-pressed="false">
                                <svg data-password-icon="show" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                                <svg data-password-icon="hide" viewBox="0 0 24 24" aria-hidden="true" focusable="false" hidden>
                                    <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a15.5 15.5 0 0 1-3.1 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1 0 1.9-.2 2.8-.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>
                        <small id="passwordHelp">At least 8 characters, with uppercase, lowercase, a number, and a symbol.</small>
                    </div>
                    <div class="auth-field">
                        <label for="index3ConfirmPassword">Confirm password *</label>
                        <div class="auth-password-control">
                            <input id="index3ConfirmPassword" type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                            <button class="auth-password-toggle" type="button" data-password-toggle
                                aria-controls="index3ConfirmPassword" aria-label="Show password" aria-pressed="false">
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
                </div>

                <div class="auth-upload-panel">
                    <h2 class="auth-form-section-title"><span>02</span> Verify your identity</h2>
                    <label class="auth-field auth-field--upload">
                        <span>Government ID image *</span>
                        <input type="file" name="id_image" id="registrationIdImage" accept="image/jpeg,image/png" required>
                        <small>JPG or PNG, up to 5 MB. Upload a clear ID showing your name, birth date, and Santa Rosa address.</small>
                        <div class="auth-id-preview" id="registrationIdPreview" hidden>
                            <img id="registrationIdThumbnail" alt="Preview of the selected government ID">
                            <button type="button" class="auth-id-preview-button" id="viewRegistrationId">View ID</button>
                        </div>
                    </label>
                </div>

                <label class="auth-checkbox">
                    <input type="checkbox" name="terms" value="1" required>
                    <span>I agree to the <a href="../terms" target="_blank" rel="noopener noreferrer">Terms and Conditions</a>
                        and <a href="../privacy" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.</span>
                </label>

                <div class="auth-captcha">
                    <div class="g-recaptcha" data-sitekey="<?= $recaptchaSiteKey ?>"></div>
                </div>

                <button class="auth-submit" type="submit">Create account</button>
            </form>

            <div class="auth-form-divider"><span>Already registered?</span></div>
            <a class="auth-secondary-action" href="index3-login" data-auth-switch="login">Log in to your account</a>
            <p class="auth-security-note">Your details are used to verify and manage your service requests.</p>
        </section>
    </main>
    <?php require __DIR__ . '/../partials/index3-auth-footer.php'; ?>

    <dialog class="auth-success-dialog" id="idVerificationSuccessDialog" aria-labelledby="idVerificationSuccessTitle"
        <?= $idVerificationSuccess ? 'data-open="true"' : '' ?>>
        <div class="auth-success-dialog-icon" aria-hidden="true">✓</div>
        <h2 id="idVerificationSuccessTitle">ID check successful</h2>
        <p>Your name, date of birth, and Santa Rosa details matched the information you entered.</p>
        <button class="auth-submit" type="button" data-id-verification-continue>Continue to mobile verification</button>
    </dialog>

    <dialog class="auth-id-preview-dialog" id="registrationIdPreviewDialog" aria-labelledby="registrationIdPreviewTitle">
        <form method="dialog" class="auth-otp-close-row">
            <button class="auth-dialog-close" aria-label="Close ID preview">×</button>
        </form>
        <h2 id="registrationIdPreviewTitle">Government ID preview</h2>
        <img id="registrationIdLargePreview" alt="Full preview of the selected government ID">
    </dialog>

    <dialog class="auth-otp-dialog" id="registrationOtpDialog" aria-labelledby="registrationOtpTitle"
        data-expires-at="<?= $otpExpiresAt ?>" data-resend-after="<?= $otpResendAfter ?>"
        data-locked-until="<?= $otpLockedUntil ?>" <?= $registrationOtpReady ? 'data-open="true"' : '' ?>>
        <form method="dialog" class="auth-otp-close-row">
            <button class="auth-dialog-close" aria-label="Close verification">×</button>
        </form>
        <h2 id="registrationOtpTitle">Verify your mobile number</h2>
        <p>Enter the 6-digit code sent to your phone to finish creating your account.</p>
        <label class="auth-field">
            <span>Verification code</span>
            <input id="registrationOtpInput" type="text" inputmode="numeric" autocomplete="one-time-code"
                pattern="[0-9]{6}" maxlength="6" placeholder="000000" required>
        </label>
        <p class="auth-otp-countdown" id="registrationOtpCountdown" role="status" aria-live="polite"></p>
        <p id="registrationOtpStatus" class="auth-otp-status" role="status" aria-live="polite"></p>
        <div class="auth-otp-actions">
            <button class="auth-submit" id="registrationOtpVerify" type="button">Verify code</button>
            <button class="auth-otp-resend" id="registrationOtpResend" type="button">Resend code</button>
        </div>
    </dialog>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/index3-auth-shell.js?v=1"></script>
    <script src="../js/index3-auth-pages.js?v=8"></script>
    <script src="../js/index3-registration.js?v=2"></script>
</body>
</html>
