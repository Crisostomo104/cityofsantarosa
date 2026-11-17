<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/barangays.php';
require_once __DIR__ . "/registration_php.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santa Rosa - Register</title>
    <meta name="csrf-token" content="<?= csrf_token() ?>">

    <!-- ICON -->
    <link rel="icon" href="../images/ibaba2.png">

    <!-- BOOTSTRAP BASE CSS (loaded first so the theme's overrides below can win) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Nexa Fonts + template CSS (for matching header/footer) -->
    <link href="https://santarosacity.gov.ph/assets/fonts/stylesheet.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://santarosacity.gov.ph/assets/lib/animate/animate.min.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/assets/css/bootstrap.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/assets/css/style.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/css/custom.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <!-- CUSTOM CSS -->
    <link rel="stylesheet" href="../css/registration.css">
    <link rel="stylesheet" href="../loads-modal/loads-modal.css?v=3">

    <!-- reCAPTCHA v2 -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>

<!-- Navbar Start (same as homepage/login) -->
<nav class="navbar bg-theme navbar-light sticky-top shadow p-0">
    <a href="../erequest-mo" class="navbar-brand d-flex align-items-center px-4 px-lg-5 mx-auto">
        <img class="h-95" src="/cityofsantarosa/images/logos/erequest-logo.png" alt="eRequest Mo">
    </a>
</nav>
<!-- Navbar End -->


<!-- START OF REGISTRATION FORM -->
    <div class="desktop-shell">
        <div class="my-container">
            <h1>Register</h1>

            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <p style="font-size:0.85rem; color:#666; margin-bottom:15px; text-align:left;">
                    <span style="color:#C62828; font-weight:700;">*</span> Required field
                </p>

                <div class="reg-two-col">
                    <!-- LEFT COLUMN: Personal Info -->
                    <div class="reg-col-fields">
                        <div class="desktop-grid three-column">
                            <div class="form-group">
                                <label>First Name <span style="color:#C62828;">*</span></label>
                                <input type="text" name="firstname" placeholder="JUAN" value="<?= old('firstname') ?>"
                                       pattern="[A-Za-zÑñ\s\-\.]+" title="Letters only" required>
                            </div>

                            <div class="form-group">
                                <label>Middle Name / Initial</label>
                                <input type="text" name="middlename" placeholder="Optional"
                                       pattern="[A-Za-zÑñ\s\-\.]*" title="Letters only">
                            </div>

                            <div class="form-group">
                                <label>Last Name <span style="color:#C62828;">*</span></label>
                                <input type="text" name="lastname" placeholder="DELA CRUZ" value="<?= old('lastname') ?>"
                                       pattern="[A-Za-zÑñ\s\-\.]+" title="Letters only" required>
                            </div>
                        </div>

                        <div class="desktop-grid three-column">
                            <div class="form-group">
                                <label>Suffix <small class="text-muted">(optional)</small></label>
                                <select name="suffix" class="form-control">
                                    <option value="">None</option>
                                    <option value="Jr." <?= old('suffix') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                                    <option value="Sr." <?= old('suffix') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                                    <option value="II" <?= old('suffix') === 'II' ? 'selected' : '' ?>>II</option>
                                    <option value="III" <?= old('suffix') === 'III' ? 'selected' : '' ?>>III</option>
                                    <option value="IV" <?= old('suffix') === 'IV' ? 'selected' : '' ?>>IV</option>
                                    <option value="V" <?= old('suffix') === 'V' ? 'selected' : '' ?>>V</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Email <span style="color:#C62828;">*</span></label>
                                <input type="email" name="email" placeholder="example@email.com" value="<?= old('email') ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Phone Number <span style="color:#C62828;">*</span></label>
                                <input type="tel" name="phone" placeholder="09XXXXXXXXX" value="<?= old('phone') ?>"
                                       inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" title="Format: 09XXXXXXXXX" required>
                            </div>
                        </div>

                        <div class="desktop-grid three-column">
                            <div class="form-group">
                                <label>Barangay <span style="color:#C62828;">*</span></label>
                                <select name="barangay" class="form-control" required>
                                    <?= barangay_options(old('barangay')) ?>
                                </select>
                            </div>


                            <div class="form-group password-field-group">
                                <label>Password <span style="color:#C62828;">*</span> <span id="showPasswordBtn" class="show-password-toggle">Show Both</span></label>
                                <input type="password" name="password" id="regPassword" placeholder="Create a password" required>
                                <div id="passwordStrength" style="margin-top:6px;font-size:12px;">
                                    <div style="display:flex;gap:4px;margin-bottom:4px;">
                                        <div id="str1" style="height:4px;flex:1;background:#e0e0e0;border-radius:2px;transition:background .3s;"></div>
                                        <div id="str2" style="height:4px;flex:1;background:#e0e0e0;border-radius:2px;transition:background .3s;"></div>
                                        <div id="str3" style="height:4px;flex:1;background:#e0e0e0;border-radius:2px;transition:background .3s;"></div>
                                        <div id="str4" style="height:4px;flex:1;background:#e0e0e0;border-radius:2px;transition:background .3s;"></div>
                                    </div>
                                    <span id="strLabel" style="color:#999;">Min 8 chars, 1 upper, 1 lower, 1 number</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Confirm Password <span style="color:#C62828;">*</span></label>
                                <input type="password" name="confirm_password" placeholder="Re-enter your password" required>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Government ID -->
                    <div class="reg-col-id">
                        <div class="form-group">
                            <label>Government ID Image <span style="color:#C62828;">*</span></label>
                            <div class="id-upload-buttons" style="display:flex; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
                                <button type="button" id="idCameraBtn" class="btn btn-outline-danger btn-sm" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; text-align:center;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                        <circle cx="12" cy="13" r="4"/>
                                    </svg>
                                    Take Photo
                                </button>
                                <button type="button" id="idGalleryBtn" class="btn btn-outline-secondary btn-sm" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; text-align:center;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <path d="M21 15l-5-5L5 21"/>
                                    </svg>
                                    Choose from Gallery
                                </button>
                            </div>
                            <input type="file" name="id_image" id="idImageInput" accept="image/*" required class="visually-hidden">
                            <small class="text-muted">Use your camera or choose from Photos/Videos. Upload a clear scan or photo of your ID showing your name and Santa Rosa City.</small>
                        </div>

                        <div class="form-group upload-preview-box" id="idPreviewBox" role="button" tabindex="0" aria-label="Open uploaded image preview">
                            <div class="preview-placeholder">Select an image to preview your upload here.</div>
                        </div>
                        <div class="preview-hint">Click the preview to enlarge the uploaded image.</div>
                    </div>

                    <!-- Placed via CSS grid-area: after the fields column on desktop,
                         after the ID-upload column on mobile (see .reg-two-col) -->
                    <div class="desktop-row terms-login-row">
                        <div class="form-group terms-field">
                            <label class="terms-label">
                                <input type="checkbox" name="terms" id="termsCheckbox" required>
                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a><span style="color:#C62828;">*</span>
                            </label>
                        </div>

                        <div class="form-group recaptcha-field">
                            <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($_ENV['RECAPTCHA_SITE_KEY'] ?? '') ?>"></div>
                        </div>
                    </div>

                    <div class="desktop-row button-row">
                        <button type="submit">Register</button>
                        <p class="bottom-text">Already have an account? <a href="login">Login here</a></p>
                        <a href="/cityofsantarosa/erequest-mo" class="btn-outline">Back to Home</a>
                    </div>
                </div>

            </form>
        </div>


    </div>
    
    <div id="scanLoadingOverlay" class="scan-loading-overlay" aria-hidden="true">
      <div class="scan-loading-card">
        <div class="scan-loader" aria-hidden="true">
          <div></div><div></div><div></div><div></div>
        </div>
        <div class="scan-loading-text">
          Scanning your ID and verifying the details. Please wait…
        </div>
      </div>
    </div>

    <div id="matchSuccessOverlay" class="match-success-overlay" aria-hidden="true">
      <div class="match-success-card">
        <div class="match-icon" aria-hidden="true">✓</div>
        <div class="match-success-text">
          ID detected successfully. Details match.
        </div>
      </div>
    </div>
<!-- END OF REGISTRATION FORM -->

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
                        <a class="btn btn-link" href="https://bis.xo.je/cityofsantarosa/auth/login">Login</a>
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

    <!-- IMAGE PREVIEW MODAL -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0 shadow-none">
          <div class="modal-body p-0">
            <img id="imagePreviewModalImg" src="" alt="Uploaded ID preview">
          </div>
        </div>
      </div>
    </div>

    <!-- OTP MODAL -->
    <div class="modal fade" id="otpModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">OTP Verification</h5>
            
         </div>

        <div class="modal-body">
            <input
                type="text"
                id="otpInput"
                class="form-control"
                placeholder="Enter 6-digit OTP"
                maxlength="6"
                inputmode="numeric"
                pattern="\d{6}"
                oninput="this.value = this.value.replace(/\D/g, '')"
            >
        </div>

        <div class="text-center mt-2">
            <small id="otpTimer" class="text-muted fw-bold">
                Resend available in: <span id="countdown">02:00</span>
            </small>
        </div>

        <div class="modal-footer justify-content-center">
            <button class="btn btn-primary" onclick="verifyOTP()">Verify</button>

            <button id="resendBtn"
                class="btn btn-outline-danger"
                disabled
                onclick="resendOTP()">
                Resend OTP
            </button>

        </div>

        </div>
      </div>
    </div>

    <!-- TERMS AND AGREEMENT -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content border-0 shadow">

                <div class="modal-header bg-danger text-white py-3">
                    <h5 class="modal-title fw-bold text-white" id="termsModalLabel">
                        <i class="fa fa-file-contract me-2"></i>Terms and Conditions & Service Agreement
                    </h5>
                </div>

                <div class="modal-body p-4" style="font-size: 0.92rem; line-height: 1.6; color: #333; text-align: justify;">
                    <div class="alert alert-light border border-danger-subtle mb-3 p-3 rounded" style="background-color: #fff8f8;">
                        <span class="badge bg-danger me-2">Official Portal Notice</span>
                        <strong>eRequest Portal — City Government of Santa Rosa</strong>
                        <p class="small text-muted mb-0 mt-1">Please read these terms carefully before registering. By proceeding, you acknowledge compliance with municipal ordinances and Republic Act No. 10173 (Data Privacy Act of 2012). <a href="../terms" target="_blank" rel="noopener noreferrer">Read the full Terms and Conditions</a>.</p>
                    </div>

                    <h6 class="fw-bold text-danger mt-3 mb-2">1. Acceptance of Terms & Eligibility</h6>
                    <p class="mb-3">By registering for an account on the Santa Rosa eRequest Portal, you confirm that you are at least 18 years of age (or registering with legal parental/guardian consent) and that all information supplied is truthful, accurate, and belonging to your legal identity.</p>

                    <h6 class="fw-bold text-danger mt-3 mb-2">2. User Registration & Security</h6>
                    <p class="mb-3">To ensure system integrity, users must complete One-Time Password (OTP) mobile verification. You are solely responsible for maintaining the confidentiality of your login credentials and password. Any document request or inquiry initiated through your account will be deemed legally submitted by you.</p>

                    <h6 class="fw-bold text-danger mt-3 mb-2">3. Data Privacy & Compliance (RA 10173)</h6>
                    <p class="mb-3">The City Government collects and processes your personal data (Name, Contact Details, Barangay Residency, and Government ID documents) strictly for official municipal services, document verification, and status notifications. Your data is encrypted and protected under the <strong>Data Privacy Act of 2012</strong>. We do not share or sell your data to third-party commercial entities.</p>

                    <h6 class="fw-bold text-danger mt-3 mb-2">4. Acceptable Portal Use & Prohibited Actions</h6>
                    <p class="mb-2">Users are strictly prohibited from engaging in any of the following activities:</p>
                    <ul class="mb-3 ps-3 text-muted">
                        <li>Submitting fake or fraudulent government documents, clearances, or personal details.</li>
                        <li>Attempting to bypass authentication, reverse-engineer, or execute automated malicious scripts.</li>
                        <li>Impersonating another resident or government official.</li>
                    </ul>

                    <h6 class="fw-bold text-danger mt-3 mb-2">5. Account Suspension & Termination</h6>
                    <p class="mb-3">The City Government of Santa Rosa reserves the right to suspend or permanently terminate user accounts involved in fraudulent inquiries, submission of falsified records, or violations of portal security policies without prior notice.</p>

                    <h6 class="fw-bold text-danger mt-3 mb-2">6. Service Availability & Modifications</h6>
                    <p class="mb-3">While we strive for continuous portal availability, system updates, server maintenance, or emergency downtime may occur. The City Government reserves the right to modify these Terms and Conditions at any time.</p>

                    <div class="p-3 bg-light rounded text-center border mt-4">
                        <i class="fa fa-shield-alt text-danger me-1"></i>
                        <small class="fw-bold text-secondary">By checking the agreement box during registration, you legally consent to these Terms and Conditions.</small>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" id="acceptTermsBtn" class="btn btn-danger px-4 fw-bold" data-bs-dismiss="modal">I Understand</button>
                </div>

            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if ($success && (strpos($success, 'OTP sent') === 0 || strpos($success, 'OTP already sent') === 0 || strpos($success, 'Sending OTP') === 0)): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const matchOverlay = document.getElementById('matchSuccessOverlay');
        const otpModal = new bootstrap.Modal(document.getElementById('otpModal'));

        if (matchOverlay) {
            matchOverlay.classList.add('visible');
            matchOverlay.setAttribute('aria-hidden', 'false');

            setTimeout(() => {
                matchOverlay.classList.remove('visible');
                matchOverlay.setAttribute('aria-hidden', 'true');
                otpModal.show();
            }, 1200);
            return;
        }

        otpModal.show();
    });
    </script>
    <?php endif; ?>

    <script>
    window.otpExpireTime = <?= (int)($_SESSION['otp_expire'] ?? 0) ?>;
    </script>

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
 
    <script src="../js/otp.js"></script>
    <script src="../js/validation.js"></script>

    <!-- Password Strength Meter -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const pw = document.getElementById('regPassword');
        if (!pw) return;
        const bars = [document.getElementById('str1'), document.getElementById('str2'), document.getElementById('str3'), document.getElementById('str4')];
        const label = document.getElementById('strLabel');

        pw.addEventListener('input', () => {
            const v = pw.value;
            let score = 0;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v)) score++;
            if (/[a-z]/.test(v)) score++;
            if (/[0-9]/.test(v)) score++;

            const colors = ['#e0e0e0', '#ef4444', '#f59e0b', '#3b82f6', '#22c55e'];
            const labels = ['Min 8 chars, 1 upper, 1 lower, 1 number', '⚠️ Weak', '⚠️ Fair', '✅ Good', '✅ Strong'];
            const labelColors = ['#999', '#ef4444', '#f59e0b', '#3b82f6', '#22c55e'];

            bars.forEach((b, i) => { b.style.background = i < score ? colors[score] : '#e0e0e0'; });
            label.textContent = labels[score];
            label.style.color = labelColors[score];
        });

        // Automatically check the Terms & Conditions checkbox when 'I Understand' button is clicked
        const acceptBtn = document.getElementById('acceptTermsBtn');
        const termsCb = document.getElementById('termsCheckbox');
        if (acceptBtn && termsCb) {
            acceptBtn.addEventListener('click', () => {
                termsCb.checked = true;
            });
        }

        // Fix: Bootstrap sets aria-hidden="true" on the modal as soon as it
        // starts closing, but if a button/element inside the modal (like
        // "I Understand") still has focus at that exact moment, the browser
        // blocks it — accessibility rules don't allow hiding a focused
        // element from screen readers. This left the modal stuck mid-close
        // (backdrop stays, body stays locked with overflow:hidden). Blurring
        // the focused element right as the modal starts hiding avoids the
        // conflict entirely.
        const termsModalEl = document.getElementById('termsModal');
        if (termsModalEl) {
            termsModalEl.addEventListener('hide.bs.modal', () => {
                if (document.activeElement && termsModalEl.contains(document.activeElement)) {
                    document.activeElement.blur();
                }
            });
        }

        const idImageInput = document.getElementById('idImageInput');
        const previewBox = document.getElementById('idPreviewBox');
        const imageModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
        const imageModalImg = document.getElementById('imagePreviewModalImg');

        // Two explicit buttons instead of relying on the OS's file-picker
        // action sheet — Android Chrome (13+) skips straight to its Photo
        // Picker when the input has no "capture" attribute, so there's no
        // consistent cross-platform "choose Camera or Gallery" dialog we
        // can rely on. Setting/removing "capture" ourselves right before
        // triggering the click gives us deterministic control instead.
        const idCameraBtn = document.getElementById('idCameraBtn');
        const idGalleryBtn = document.getElementById('idGalleryBtn');

        if (idCameraBtn && idImageInput) {
            idCameraBtn.addEventListener('click', () => {
                idImageInput.setAttribute('capture', 'environment');
                idImageInput.click();
            });
        }

        if (idGalleryBtn && idImageInput) {
            idGalleryBtn.addEventListener('click', () => {
                idImageInput.removeAttribute('capture');
                idImageInput.click();
            });
        }

        if (idImageInput && previewBox) {
            idImageInput.addEventListener('change', () => {
                const file = idImageInput.files[0];
                previewBox.innerHTML = '';

                if (!file) {
                    previewBox.innerHTML = '<div class="preview-placeholder">Select an image to preview your upload here.</div>';
                    imageModalImg.src = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (event) {
                    if (file.type.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.src = event.target.result;
                        img.alt = 'ID preview';
                        img.style.cursor = 'pointer';
                        img.style.maxHeight = '100%';
                        img.style.width = 'auto';
                        previewBox.appendChild(img);

                        imageModalImg.src = event.target.result;
                        previewBox.onclick = () => {
                            if (imageModalImg.src) imageModal.show();
                        };
                        previewBox.onkeypress = (e) => { if ((e.key === 'Enter' || e.key === ' ') && imageModalImg.src) imageModal.show(); };
                    } else {
                        const info = document.createElement('div');
                        info.className = 'preview-placeholder';
                        info.textContent = file.name;
                        previewBox.appendChild(info);
                        previewBox.onclick = null;
                        previewBox.onkeypress = null;
                    }
                };
                reader.readAsDataURL(file);
            });
        }

        const scanOverlay = document.getElementById('scanLoadingOverlay');
        const registrationForm = document.querySelector('form');
        const submitBtn = registrationForm ? registrationForm.querySelector('button[type="submit"]') : null;

        if (registrationForm && scanOverlay) {
            registrationForm.addEventListener('submit', (event) => {
                const captchaResponse = typeof grecaptcha !== 'undefined' ? grecaptcha.getResponse() : '';
                if (!captchaResponse || captchaResponse.length === 0) {
                    event.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Captcha required',
                        text: 'Please check "I\'m not a robot" before registering.',
                        confirmButtonColor: '#C62828'
                    });
                    return;
                }

                if (!scanOverlay.classList.contains('visible')) {
                    event.preventDefault();
                    scanOverlay.classList.add('visible');
                    scanOverlay.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('no-scroll');
                    if (submitBtn) submitBtn.disabled = true;

                    window.requestAnimationFrame(() => {
                        registrationForm.submit();
                    });
                }
            });
        }
    });
</script>

</body>
</html>