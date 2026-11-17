<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../data/login_data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santa Rosa - Login</title>
    <link rel="icon" href="../images/ibaba2.png">

    <!-- Nexa Fonts (City of Santa Rosa) -->
    <link href="https://santarosacity.gov.ph/assets/fonts/stylesheet.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://santarosacity.gov.ph/assets/lib/animate/animate.min.css" rel="stylesheet">

    <!-- Customized Bootstrap + Template Stylesheet (same as homepage, for matching header/footer) -->
    <link href="https://santarosacity.gov.ph/assets/css/bootstrap.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/assets/css/style.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/css/custom.css" rel="stylesheet">
    

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../loads-modal/loads-modal.css?v=3">
    <!-- <link rel="stylesheet" href="../css/login-glass.css"> -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body data-blocked="<?= !empty($_GET['blocked']) ? '1' : '0' ?>">

<!-- Navbar Start (same as homepage) -->
<nav class="navbar bg-theme navbar-light sticky-top shadow p-0">
    <a href="../erequest-mo" class="navbar-brand d-flex align-items-center px-4 px-lg-5 mx-auto">
     	<img class="h-95" src="/cityofsantarosa/images/logos/erequest-logo.png" alt="eRequest Mo">
    </a>
</nav>
<!-- Navbar End -->

<!-- LOGIN SPLIT LAYOUT -->
<div class="login-page-wrap">
    <div class="login-split">

        <!-- LEFT: About the System -->
        <div class="login-info-col">
            <h2 class="nexablack">About the System</h2>
            <p class="about-text">
                Have a document request in your barangay? <strong style="color:#8E1B1B;">eRequest Mo</strong> lets you submit it online — no need to visit the office or wait in line. Just send your request and track its progress anytime, from anywhere.
            </p>
            <p class="about-text">
                Log in to check your request status, get notified when it's resolved, and keep a full history of everything you've filed — all in one place.
            </p>
            <ul class="about-features">
                <li>✦ Submit and track requests in real time</li>
                <li>✦ Stay updated with barangay announcements</li>
                <li>✦ Secure personal account for your records</li>
            </ul>
        </div>

        <!-- RIGHT: Login Form -->
        <div class="login-form-col">
            <div class="index-container">
                <h1 class="mb-5">Sign-in</h1>

                <?php if ($error): ?>
                    <div class="error-message"><?= htmlspecialchars($error) ?></div>

                    <?php if (str_contains(strtolower($error), 'too many failed')): ?>
                        <p style="margin-top:8px;">
                            You can <a href="#" id="showForgotModal" data-email="<?= $loginEmail ?>" style="text-decoration: underline;">reset your password</a>.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="example@email.com" value="<?= $loginEmail ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="loginPassword" placeholder="Enter your password" required>
                                <span class="toggle-password" id="togglePassword" title="Show password">
                                    <svg id="eyeShow" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg id="eyeHide" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </span>
                        </div>
                        <a href="#" id="forgotPasswordBtn" class="forgot-password-link">Forgot Password?</a>
                    </div>

                    <!-- BAGO -->
                    <div class="form-group recaptcha-field">
                        <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($_ENV['RECAPTCHA_SITE_KEY'] ?? '') ?>"></div>
                    </div>

                    <button type="submit" class="btn-primary">Login</button>
                </form>

                <p class="bottom-text">Don't have an account? <a href="registration">Register here</a></p>

                <!-- About the System Button (Mobile Only) -->
                <button type="button" class="btn-outline" id="aboutSystemBtn">About the System</button>

                <a href="/cityofsantarosa/erequest-mo" class="btn-outline">Back to Home</a>
            </div>
        </div>

    </div>
</div>
<!-- /LOGIN SPLIT LAYOUT -->

<!-- Footer Start (same as homepage) -->
<footer id="footer" class="footer pt-0 pb-0">
    <div class="container-fluid bg-theme text-light footer pt-2 mt-0">
        <div class="container py-5">
            <div class="row g-5">
                <div class="col-lg-2 col-md-3">
                    <div class="d-lg-block d-none">
                        <img class="img img-fluid w-100" src="/cityofsantarosa/images/logos/erequest-icon.png">
                    </div>
                    <div class="d-lg-none d-md-none d-sm-block">
                        <img class="img img-fluid w-50" src="/cityofsantarosa/images/logos/erequest-icon.png">
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <p class="text-light mb-3 fs-3">Contact Us</p>
                    <p class="mb-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="margin-right:.5rem;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>J. P Rizal, City Government Center, City of Santa Rosa, Laguna, Philippines, 4026</p>
                    <p class="mb-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="margin-right:.5rem;"><path d="M6.62 10.79a15.053 15.053 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.07 21 3 13.93 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.57.16.45.05.96-.25 1.31l-2.2 2.21z"/></svg>Local Number: <a href="tel:+63495300015" class="text-light">(049)530-0015</a></p>
                    <p class="mb-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="margin-right:.5rem;"><path d="M6.62 10.79a15.053 15.053 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.07 21 3 13.93 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.57.16.45.05.96-.25 1.31l-2.2 2.21z"/></svg>Manila Line: <a href="tel:+63285194024" class="text-light">(02) 8519-4024</a></p>
                    <p class="text-light mb-3 mt-4 fs-3">Follow Us</p>
                    <div class="d-flex pt-0">
                        <a class="btn btn-outline-light btn-social p-4" href="https://www.facebook.com/citygovernmentofsantarosa" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a class="btn btn-outline-light btn-social p-4" href="https://twitter.com/santarosacity" target="_blank"><i class="fab fa-twitter"></i></a>
                        <a class="btn btn-outline-light btn-social p-4" href="https://www.youtube.com/user/SantaRosaPhils" target="_blank"><i class="fab fa-youtube"></i></a>
                        <a class="btn btn-outline-light btn-social p-4" href="https://www.instagram.com/cityofsantarosalaguna/" target="_blank"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                	<p class="text-light mb-3 fs-3">Quick Links</p>
					<a class="btn btn-link" href="https://bis.xo.je/cityofsantarosa/">Home</a>
                    <a class="btn btn-link request-footer-brand-link" href="https://bis.xo.je/cityofsantarosa/erequest-mo">eRequest Mo</a>
                    <a class="btn btn-link" href="https://bis.xo.je/cityofsantarosa/auth/registration">Registration</a>
                    <a class="btn btn-link" href="../terms">Terms and Conditions</a>
                    <a class="btn btn-link" href="../cookies">Cookie Policy</a>
                    <a class="btn btn-link" href="https://bis.xo.je/cityofsantarosa/privacy" target="_blank">Privacy Policy</a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <p class="text-light mb-3 fs-3">Government Links</p>
                    <a class="btn btn-link" target="_blank" href="https://op-proper.gov.ph/">Office of the President</a>
                    <a class="btn btn-link" target="_blank" href="https://ovp.gov.ph/">Office of the Vice President</a>
                    <a class="btn btn-link" target="_blank" href="http://legacy.senate.gov.ph/">Senate of the Philippines</a>
                    <a class="btn btn-link" target="_blank" href="https://www.congress.gov.ph/">House of the Representatives</a>
                    <a class="btn btn-link" target="_blank" href="https://sc.judiciary.gov.ph/">Supreme Court</a>
                    <a class="btn btn-link" target="_blank" href="http://ca.judiciary.gov.ph/">Court of Appeals</a>
                    <a class="btn btn-link" target="_blank" href="https://sb.judiciary.gov.ph/">Sandiganbayan</a>
                </div>
            </div>
            <hr>
            <div class="request-footer-bar">
                <small>&copy; <?= date('Y') ?> City Government of Santa Rosa. All Rights Reserved.</small>
                <button type="button" class="request-footer-powered" id="openLoadsssProfile" aria-haspopup="dialog" aria-controls="loadsssProfileDialog">
                    Powered By: <strong>Loadssss</strong>
                </button>
                <div style="display:flex;gap:.5rem;align-items:center;">
                    <a class="request-footer-privacy" href="../terms">Terms</a>
                    <a class="request-footer-privacy" href="../privacy">Privacy Policy</a>
                    <a class="request-footer-privacy" href="../cookies">Cookies</a>
                </div>
            </div>
        </div>
    </div>
</footer>
<!-- Footer End -->
<?php
$loadsModalImage = '../loads-modal/loads.png';
require __DIR__ . '/../loads-modal/loads-modal.php';
unset($loadsModalImage);
?>

<!-- About the System Modal (Mobile Only) -->
<div id="aboutSystemModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" id="closeAboutModal">&times;</span>
        <h2>About the System</h2>
        <div style="text-align: left;">
            <p style="font-size: 1.05rem; line-height: 1.8; margin-bottom: 14px;">
                Have a document request in your barangay? <strong style="color:#8E1B1B;">eRequest Mo</strong> lets you submit it online — no need to visit the office or wait in line. Just send your request and track its progress anytime, from anywhere.
            </p>
            <p style="font-size: 1.05rem; line-height: 1.8; margin-bottom: 14px;">
                Log in to check your request status, get notified when it's resolved, and keep a full history of everything you've filed — all in one place.
            </p>
            <ul style="list-style: none; padding: 0; margin: 18px 0 0; font-size: 0.98rem; line-height: 2;">
                <li>✦ Submit and track requests in real time</li>
                <li>✦ Stay updated with barangay announcements</li>
                <li>✦ Secure personal account for your records</li>
            </ul>
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div id="forgotPasswordModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" id="closeModal">&times;</span>
        <h2>Reset Password</h2>
        <p>Enter your email address below, and we'll send you instructions to reset your password.</p>
        <form id="forgotPasswordForm">
            <input type="email" id="resetEmail" name="resetEmail" placeholder="Your email address" required>
            <button type="submit" class="btn-primary">Send Reset Link</button>
        </form>
        <div id="resetMessage"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/login.js"></script>
<script>
(() => {
    const navbar = document.querySelector('.navbar.sticky-top');
    if (!navbar) return;
    navbar.style.transition = 'top 0.3s ease-in-out';
    let lastScrollTop = window.scrollY;
    window.addEventListener('scroll', () => {
        const scrollTop = window.scrollY;
        navbar.style.top = scrollTop > 100 && scrollTop > lastScrollTop ? '-100px' : '0px';
        lastScrollTop = scrollTop;
    }, { passive: true });
})();
</script>
<script>
(() => {
    const dialog = document.getElementById('loadsssProfileDialog');
    const openButton = document.getElementById('openLoadsssProfile');
    const closeButton = document.getElementById('closeLoadsssProfile');
    if (!dialog || !openButton || !closeButton) return;
    openButton.addEventListener('click', () => dialog.showModal());
    closeButton.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
})();
</script>

</body>
</html>