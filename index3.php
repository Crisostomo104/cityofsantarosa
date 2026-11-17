<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0'); 

if (session_status() === PHP_SESSION_NONE) session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/announcements.php';
require_once __DIR__ . '/core/tracking.php';
require_once __DIR__ . '/core/chatbot.php';
require_once __DIR__ . '/config/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
$hasPendingLoginOtp = !empty($_SESSION['pending_user_id']);

// Cache key scoped to hour; short TTL so announcements update reasonably often
$idx2_cache_key = 'e_inquire_page:' . date('Y-m-d-H');
$cached = get_cached_data($idx2_cache_key, 300, function() use ($pdo) {
    // announcements and stats
    $public_announcements = array_values(array_filter(
        get_public_announcements($pdo, 6),
        fn($a) => !empty($a['image'])
    ));

    try {
        $stat_resolved = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status='Completed'")->fetchColumn();
        $stat_users    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE user_type='resident'")->fetchColumn();
    } catch (PDOException $e) {
        $stat_resolved = 0; $stat_users = 0;
    }

    return [
        'announcements' => $public_announcements,
        'stat_resolved' => $stat_resolved,
        'stat_users'    => $stat_users,
    ];
});

$public_announcements = $cached['announcements'] ?? [];
$stat_resolved = $cached['stat_resolved'] ?? 0;
$stat_users = $cached['stat_users'] ?? 0;

$chatbot_settings = get_chatbot_settings($pdo);
$maintenance_mode = ($chatbot_settings['maintenance_mode'] ?? '0') === '1';
$maintenance_message = $chatbot_settings['maintenance_message'] ?? 'The system is currently under maintenance. Please try again later.';

// Compact display for large counts (returns label without trailing plus)
function compact_count_label(int $n): string {
    if ($n < 1000) return (string)$n;
    if ($n < 10000) return (string)floor($n / 1000) . 'k';
    if ($n < 100000) return (string)(floor($n / 10000) * 10) . 'k';
    if ($n < 1000000) return (string)(floor($n / 100000) * 100) . 'k';
    return (string)floor($n / 1000000) . 'M';
}

$stat_users_compact = compact_count_label((int)$stat_users);
$stat_resolved_compact = compact_count_label((int)$stat_resolved);

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="eRequest — Submit and track barangay requests online for Santa Rosa City." name="description">
    <meta content="" name="keywords">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <meta name="csrf_token" value="<?= htmlspecialchars($csrf) ?>" />

    <link rel="icon" href="images/logos/erequest-icon.png">
    <title>eRequest Mo: A Centralized Web-Based Barangay Document Request for City of Santa Rosa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/index.css?v=3">
    <link rel="stylesheet" type="text/css" href="css/index3.css?v=32">
    <link rel="stylesheet" type="text/css" href="css/index3-auth-modal.css?v=4">
</head>

<body>

    <!-- skip-link removed per user request -->

    <div id="app">

        <!-- Cookie Consent Banner -->
        <?php if (empty($_COOKIE['ei_cookie_consent'])): ?>
        <div id="cookieBanner" class="cookie-banner">
            <div class="cookie-banner-content">
                <div class="cookie-banner-text">
                    <strong>We use cookies</strong>
                    <div style="font-size:13px;color:#374151;margin-top:6px;">We use a session cookie and a cookie that remembers this notice. Google reCAPTCHA may use cookies on sign-in and registration pages. See our Privacy Policy for details.</div>
                </div>
                <div class="cookie-banner-actions">
                    <button id="acceptCookiesBtn" class="btn btn-danger" style="padding:6px 10px;font-size:13px;">Accept</button>
                    <button id="declineCookiesBtn" class="btn btn-outline-secondary" style="padding:6px 10px;font-size:13px;">Decline</button>
                    <a href="privacy" rel="noopener noreferrer" style="font-size:12px;color:#6b7280;text-decoration:underline;">Privacy</a>
                    <a href="cookies" style="font-size:12px;color:#6b7280;text-decoration:underline;">Cookies</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Navbar Start -->
        <nav class="navbar bg-theme navbar-light sticky-top shadow p-0">
            <a href="#home" class="navbar-brand d-flex align-items-center">
                <img class="h-95" src="/cityofsantarosa/images/logos/erequest-logo.png" alt="eRequest Mo">
            </a>
            <div class="section-nav" aria-label="Page sections">
                <a href="#home">Home</a>
                <a href="#about-system">About the System</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#faq">FAQ</a>
            </div>
            <div class="navbar-actions">
                <a class="nav-login" href="auth/index3-login" data-auth-open="login">Log in</a>
                <a class="nav-cta" href="auth/index3-registration" data-auth-open="register">Get Started</a>
                <button type="button" class="navbar-toggler me-4" data-bs-toggle="offcanvas"
                    data-bs-target="#offcanvasExample" aria-label="Open navigation menu">
                    <span class="menu-icon" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>
            </div>
        </nav>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasExample" aria-labelledby="offcanvasExampleLabel"
            data-bs-backdrop="true" data-bs-scroll="false">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title text-theme nexabook" id="offcanvasExampleLabel">Navigation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body bg-white navbar nav-tabs flex-grow-0">
                <div class="navbar-collapse collapse show" id="navbarCollapse" style="">
                    <div class="navbar-nav ms-auto p-4 p-lg-0 nexabold">
                        <div class="section-nav-mobile" aria-label="Page sections">
                            <a href="#home">Home</a>
                            <a href="#about-system">About the System</a>
                            <a href="#how-it-works">How It Works</a>
                            <a href="#faq">FAQ</a>
                        </div>
                        <div class="section-auth-mobile" aria-label="Account access">
                            <a href="auth/index3-login" data-auth-open="login">Log in</a>
                            <a href="auth/index3-registration" data-auth-open="register">Create account</a>
                        </div>
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                                aria-expanded="false">About Us</a>
                            <div class="dropdown-menu fade-up m-0">
                                <a href="https://santarosacity.gov.ph/about-us#historical" class="dropdown-item">Historical
                                    Background</a>
                                <a href="https://santarosacity.gov.ph/about-us#barangays" class="dropdown-item">Barangays</a>
                                <a href="https://santarosacity.gov.ph/about-us#departments" class="dropdown-item">Departments</a>
                            </div>
                        </div>
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                                aria-expanded="false">Government</a>
                            <div class="dropdown-menu fade-up m-0">
                                <a href="https://santarosacity.gov.ph/government-officials" class="dropdown-item">Government
                                    Officials</a>
                                <a href="https://santarosacity.gov.ph/governance-model" class="dropdown-item">Governance Model</a>
                            </div>
                        </div>

                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                                aria-expanded="false">Transparency</a>
                            <div class="dropdown-menu fade-up m-0">
                                <a href="https://santarosacity.gov.ph/city-ordinances" class="dropdown-item">City Ordinances</a>
                                <a href="https://santarosacity.gov.ph/resolutions" class="dropdown-item">Resolutions</a>
                            </div>
                        </div>



                        <a href="https://santarosacity.gov.ph/online-forms" class="nav-item nav-link">Online Forms</a>
                        <a href="https://santarosacity.gov.ph/bid-opportunities" class="nav-item nav-link">Bid Opportunities</a>
                        <a href="https://santarosacity.gov.ph/e-services" class="nav-item nav-link">eServices</a>
                        
                        <a href="https://santarosacity.gov.ph/gallery" class="nav-item nav-link">City Gallery</a>
                        <a href="https://santarosacitysp.com/" target="_blank" class="nav-item nav-link">Sangguniang
                            Panlungsod</a>

                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                                aria-expanded="false">Contact Us</a>
                            <div class="dropdown-menu fade-up m-0">
                                <a href="https://santarosacity.gov.ph/city-directory" class="dropdown-item">City Directory</a>
                                <a href="https://santarosacity.gov.ph/contact-us" class="dropdown-item">Send Us A Message</a>
                            </div>
                        </div>
                        <a href="https://santarosacity.gov.ph/faqs" class="nav-item nav-link">FAQs</a>
                    </div>
                    
                </div>
            </div>
        </div>
        <!-- Navbar End -->

        <!-- Page Header Start -->
        <section class="page-header" id="home" aria-labelledby="heroTitle">
            <div class="container page-header-inner">
                <div class="hero-media">
                    <div class="hero-video-frame">
                        <div id="promoVideo" class="hero-video" data-video-id="jvLZn9s2mcw" role="region" aria-label="eRequest Mo promotional video">
                            <div id="youtubePlayer" class="youtube-player"></div>
                        </div>
                        <button class="video-surface-toggle" id="videoSurfaceToggle" type="button" aria-label="Expand promotional video">
                            <span class="visually-hidden">Expand promotional video</span>
                        </button>
                        <div class="video-controls">
                            <button class="video-control" id="videoPlayToggle" type="button" aria-pressed="false" aria-label="Pause promotional video">
                                <i class="bi bi-pause-fill" aria-hidden="true"></i><span>Pause</span>
                            </button>
                            <div class="video-sound-controls">
                                <button class="video-control" id="videoSoundToggle" type="button" aria-pressed="false" aria-label="Enable video sound">
                                    <i class="bi bi-volume-mute-fill" aria-hidden="true"></i>
                                </button>
                                <label class="video-volume-control" for="videoVolume">
                                    <span class="visually-hidden">Promotional video volume</span>
                                    <input id="videoVolume" type="range" min="0" max="100" value="0" aria-label="Promotional video volume" aria-valuetext="0%, muted">
                                </label>
                            </div>
                        </div>
                        <p class="video-error" id="videoError" role="status" hidden>
                            Video unavailable here. <a href="https://www.youtube.com/watch?v=jvLZn9s2mcw" target="_blank" rel="noopener noreferrer">Watch on YouTube</a>
                        </p>
                    </div>
                </div>
                <div class="hero-copy">
                    <h1 id="heroTitle" class="nexablack responsive-title hero-project-title" aria-label="eRequest Mo: A Centralized Web-Based Barangay Document Request for City of Santa Rosa">
                        <span class="hero-title-product">eRequest Mo</span>
                        <span class="hero-title-main">A Centralized Web-Based Barangay Document Request for City of Santa Rosa</span>
                    </h1>
                    <p class="hero-tagline">Barangay requests, made easier.</p>
                    <p class="lead">A centralized online system for requesting barangay documents, tracking progress, and staying updated—wherever you are.</p>
                    <div class="btn-center">
                        <a href="auth/index3-registration" class="btn-custom" data-auth-open="register">Create your account <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        <a href="auth/index3-login" class="hero-login" data-auth-open="login">Already registered? Log in</a>
                    </div>
                </div>
            </div>
        </section>
        <!-- Page Header End -->

        <dialog class="video-expand-dialog" id="videoExpandDialog" aria-labelledby="videoExpandTitle">
            <div class="video-dialog-header">
                <h2 id="videoExpandTitle"></h2>
                <button class="video-dialog-close" id="videoDialogClose" type="button" aria-label="Close expanded video">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="video-dialog-content" id="videoDialogContent"></div>
        </dialog>

        <dialog class="auth-dialog" id="authDialog" aria-labelledby="authDialogTitle"
            data-pending-otp="<?= $hasPendingLoginOtp ? '1' : '0' ?>">
            <div class="auth-dialog-header">
                <div>
                    <p class="auth-dialog-eyebrow">eRequest Mo</p>
                    <h2 id="authDialogTitle">Access your account</h2>
                </div>
                <button class="auth-dialog-close" id="authDialogClose" type="button" aria-label="Close account dialog">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="auth-dialog-tabs" role="tablist" aria-label="Account options">
                <button id="authLoginTab" type="button" role="tab" aria-selected="true"
                    aria-controls="authPanel" data-auth-mode="login">Log in</button>
                <button id="authRegisterTab" type="button" role="tab" aria-selected="false"
                    aria-controls="authPanel" data-auth-mode="register">Create account</button>
                <a id="authFullPageLink" href="auth/index3-login" target="_blank" rel="noopener noreferrer">Open full page</a>
            </div>
            <p class="auth-dialog-status" id="authDialogStatus" role="status" hidden></p>
            <div id="authPanel" role="tabpanel" aria-labelledby="authLoginTab" tabindex="0">
                <iframe id="authFrame" title="Log in to eRequest Mo" loading="lazy"
                    data-login-src="auth/index3-login" data-register-src="auth/index3-registration"
                    data-otp-src="auth/index3-verify-otp"
                    referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        </dialog>

        <!-- ══ TRUST STATS COUNTER ══ -->
            <div class="stats-section">
                <div class="container">
                    <div class="row animated slideInDown">
                    <div class="col-4">
                        <div class="stat-box">
                        <div class="stat-number"><span><?= htmlspecialchars($stat_resolved_compact) ?></span>+</div>
                        <div class="stat-label nexabold">Requests Resolved</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                        <div class="stat-number"><span><?= htmlspecialchars($stat_users_compact) ?></span>+</div>
                        <div class="stat-label nexabold">Registered Residents</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                        <div class="stat-number"><span>18</span></div>
                        <div class="stat-label nexabold">Barangays Connected</div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
            
        <div id="maincontent" class="pb-0">
<?php
    $pic_announcements  = array_filter($public_announcements, fn($a) => !empty($a['image']));
    $text_announcements = array_filter($public_announcements, fn($a) => empty($a['image']));
?>
<!-- ══ MAIN CONTENT ══ -->
<section style="padding:30px 0 40px;">
  <div class="container">

    <!-- ROW 1: News (big left) | About + Track (stacked right) -->
    <div class="row g-4">

      <!-- LEFT: News & Announcements (large) -->
      <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.3s">
        <div class="ei-card" style="height:100%;">
          <div class="ei-card-header">
              <h4 class="nexabold">News & Announcements</h4>
          </div>
          <div class="ei-card-body" style="position:relative;min-height:200px;">
            <?php if (!empty($public_announcements)): ?>
              <?php $annCount = count($public_announcements); ?>
              <div id="annCarousel" class="ann-carousel">
                <?php foreach ($public_announcements as $idx => $ann): ?>
                <div class="ann-slide<?= $idx === 0 ? ' active' : '' ?>" aria-hidden="<?= $idx === 0 ? 'false' : 'true' ?>">
                    <h6 class="ann-slide-title"><?= htmlspecialchars($ann['title']) ?></h6>
                    <?php if (!empty($ann['image'])): ?>
                    <img src="<?= htmlspecialchars($ann['image']) ?>" alt="<?= htmlspecialchars($ann['title']) ?>" loading="lazy">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
              <?php if ($annCount > 1): ?>
              <button class="ann-nav-btn prev" onclick="changeSlide(-1)" aria-label="Previous">❮</button>
              <button class="ann-nav-btn next" onclick="changeSlide(1)" aria-label="Next">❯</button>
              <div class="ann-dots">
                <?php for ($i = 0; $i < $annCount; $i++): ?>
                <button class="ann-dot<?= $i === 0 ? ' active' : '' ?>" onclick="goToSlide(<?= $i ?>)" aria-label="Slide <?= $i+1 ?>"></button>
                <?php endfor; ?>
              </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="ann-empty">
                  <div class="ann-empty-icon">📢</div>
                  <p class="ann-empty-text">No announcements at this time.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- RIGHT: About + Track (stacked) -->
      <div class="col-lg-6">
        <div class="row g-4">
          <!-- About the System -->
          <div class="col-12 wow fadeInUp" data-wow-delay="0.5s">
            <div class="ei-card" id="about-system">
              <div class="ei-card-header">
                  <h4 class="nexabold">About the System</h4>
              </div>
              <div class="ei-card-body">
                <p class="about-text">
                  Need a barangay document? Request it online with <strong style="color:#8E1B1B;">eRequest Mo</strong> it lets you submit online — no need to visit the office or wait in line. Just send your request and track its progress anytime, from anywhere.
                  <br><br>
				  Whether it’s a Barangay Clearance, Certificate of Residency, or any other document, everything goes through one simple system. You’ll always know when your request is pending, approved, or completed — with updates at every step.
                  <br><br>
                  A better way to connect communities with the people who serve them.                
                </p>


              </div>
            </div>
          </div>
          <!-- Track My Request -->
          <div class="col-12 wow fadeInUp" data-wow-delay="0.7s">
            <div class="ei-card">
              <div class="ei-card-header">
                <h4 class="nexabold">Track My Request</h4>
              </div>
              <div class="track-widget">
                <p>Enter your tracking code to check status — no login required.</p>
                <div class="track-input-group">
                  <input type="text" id="trackCodeInput" class="track-input" placeholder="e.g. REQ20260101001-001" maxlength="30">
                  <button id="trackBtn" class="track-btn" onclick="trackRequest()">Track</button>
                </div>
                <div id="trackResult" class="track-result"></div>
              </div>
            </div>
            <br><br>
          </div>
        </div>
      </div>

    </div>

    <!-- ROW 2: HOW IT WORKS — single card, matches the ei-card/ei-card-header
         treatment used by the other sections (About, Track, FAQ, etc.) -->
    <div class="row g-4 mt-2">
      <div class="col-12 wow fadeInUp" data-wow-delay="0.2s">
        <div class="ei-card" id="how-it-works">
          <div class="ei-card-header">
              <h4 class="nexabold">How It Works</h4>
          </div>
          <div class="ei-card-body p-0">
            <p class="text-center about-text" style="padding:18px 20px 0;">Four simple steps from registering to getting your request resolved.</p>
            <div class="how-it-works-steps">
              <?php
                $steps = [
                  ['1','Register','Create an account to access the system.'],
                  ['2','Submit Request','Send your requests directly to your barangay.'],
                  ['3','Track Updates','Monitor progress and receive real-time updates.'],
                  ['4','Get Resolved','Receive the official response from your barangay.'],
                ];
                foreach ($steps as $s):
              ?>
              <div class="how-it-works-step">
                <div class="step-number"><?= $s[0] ?></div>
                <h6 class="nexabold"><?= $s[1] ?></h6>
                <p class="mb-0"><?= $s[2] ?></p>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ROW 3: Features + FAQ -->
    <div class="row g-4 mt-2">
      <!-- What you can do with eRequest -->
      <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.3s">
        <div class="ei-card">
          <div class="ei-card-header">
            <h4 class="nexabold">What you can do with eRequest</h4>
          </div>
          <div class="ei-card-body">
            <ul class="about-features" style="font-size:1.1rem;">
              <li>✦ Submit requests online — anytime, anywhere</li>
              <li>✦ Track your request status in real time</li>
              <li>✦ Stay updated with barangay announcements</li>
              <li>✦ Secure personal account for your records</li>
              <li>✦ Get notified when your request is resolved</li>
              <li>✦ Attach files and images to your requests</li>
              <li>✦ View complete history of all your requests</li>
            </ul>
          </div>
        </div>
      </div>
      <!-- Frequently Asked Questions -->
      <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.5s">
        <div class="ei-card" id="faq">
          <div class="ei-card-header">
            <h4 class="nexabold">Frequently Asked Questions</h4>
          </div>
          <div class="ei-card-body">
            <?php
              $faqs = [
                ['How do I submit a request?', 'Register for a free account, log in, and fill out the request form. You can attach files and choose your barangay. You\'ll receive a tracking code once submitted.'],
                ['How long does it take to get a response?', 'Processing times vary by barangay and document type. Most inquiries are reviewed within 1–3 business days. You can track your status in real time.'],
                ['What documents can I request?', 'You can request barangay clearances, certificates of residency, indigency, and other barangay-level documents available in your area.'],
                ['Is my personal information safe?', 'Yes. All data is stored securely and only accessible to authorized barangay personnel handling your request. We never share your information with third parties.'],
                ['I forgot my password. What do I do?', 'Click "Login" then use the "Forgot Password" link. You\'ll receive a password reset email within minutes.'],
              ];
              foreach ($faqs as $fi => $faq):
            ?>
            <div class="faq-item">
              <button class="faq-question" id="faqQuestion<?= $fi ?>" aria-expanded="false"
                aria-controls="faqAnswer<?= $fi ?>" onclick="toggleFaq(this)">
                <span><?= $faq[0] ?></span>
                <span class="faq-icon">+</span>
              </button>
              <div class="faq-answer" id="faqAnswer<?= $fi ?>" role="region" aria-labelledby="faqQuestion<?= $fi ?>">
                <div class="faq-answer-inner"><?= $faq[1] ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

        <!-- Footer Start -->
        <footer id="footer" class="footer pt-0 pb-0">
            <div class="container-fluid bg-theme text-light footer pt-2 mt-0 wow fadeIn" data-wow-delay="0.1s">
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
                                <a class="btn btn-outline-light btn-social"
                                    href="https://www.facebook.com/citygovernmentofsantarosa" target="_blank"><i
                                        class="fab fa-facebook-f"></i></a>
                                <a class="btn btn-outline-light btn-social" href="https://twitter.com/santarosacity"
                                    target="_blank"><i class="fab fa-twitter"></i></a>
                                <a class="btn btn-outline-light btn-social"
                                    href="https://www.youtube.com/user/SantaRosaPhils" target="_blank"><i
                                        class="fab fa-youtube"></i></a>
                                <a class="btn btn-outline-light btn-social"
                                    href="https://www.instagram.com/cityofsantarosalaguna/" target="_blank"><i
                                        class="fab fa-instagram"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <p class="text-light mb-3 fs-3">Quick Links</p>
                            <a class="btn btn-link" href="index3">Home</a>
                            <a class="btn btn-link request-footer-brand-link" href="index3">eRequest Mo</a>
                            <a class="btn btn-link" href="auth/index3-login" data-auth-open="login">Login</a>
                            <a class="btn btn-link" href="auth/index3-registration" data-auth-open="register">Registration</a>
                            <a class="btn btn-link" href="terms">Terms and Conditions</a>
                            <a class="btn btn-link" href="cookies">Cookie Policy</a>
                            <a class="btn btn-link" href="privacy">Privacy Policy</a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <p class="text-light mb-3 fs-3">Government Links</p>
                            <a class="btn btn-link" target="_blank" href="https://op-proper.gov.ph/">Office of the
                                President</a>
                            <a class="btn btn-link" target="_blank" href="https://ovp.gov.ph/">Office of the Vice
                                President</a>
                            <a class="btn btn-link" target="_blank" href="http://legacy.senate.gov.ph/">Senate of the
                                Philippines</a>
                            <a class="btn btn-link" target="_blank" href="https://www.congress.gov.ph/">House of the
                                Representatives</a>
                            <a class="btn btn-link" target="_blank" href="https://sc.judiciary.gov.ph/">Supreme
                                Court</a>
                            <a class="btn btn-link" target="_blank" href="http://ca.judiciary.gov.ph/">Court of
                                Appeals</a>
                            <a class="btn btn-link" target="_blank"
                                href="https://sb.judiciary.gov.ph/">Sandiganbayan</a>
                        </div>
                    </div>
                    <hr>
                    <div class="request-footer-bar">
                        <small>&copy; <?= date('Y') ?> City Government of Santa Rosa. All Rights Reserved.</small>
                        <button type="button" class="request-footer-powered" id="openLoadsssProfile" aria-haspopup="dialog" aria-controls="loadsssProfileDialog">
                            Powered By: <strong>Loadssss</strong>
                        </button>
                        <div style="display:flex;gap:.5rem;align-items:center;">
                            <a class="request-footer-privacy" href="terms">Terms</a>
                            <a class="request-footer-privacy" href="privacy">Privacy Policy</a>
                            <a class="request-footer-privacy" href="cookies">Cookies</a>
                        </div>
                    </div>
                </div>
            </div>
        </footer><!-- End Footer -->
        <!-- Footer End -->

<!-- BUTTON -->
<button id="einq-chat-toggle" aria-label="Open chat" role="button" tabindex="0" aria-controls="einq-chat-box" aria-expanded="false" style="border:none; outline:none;">
    <svg width="28" height="28" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="9" cy="12" r="1.15" fill="currentColor"/>
        <circle cx="12" cy="12" r="1.15" fill="currentColor"/>
        <circle cx="15" cy="12" r="1.15" fill="currentColor"/>
    </svg>
</button>

<!-- CHATBOX -->
<div id="einq-chat-box">

    <div id="einq-chat-header">

        <div class="chat-header-left">
            <img src="images/jazmine.png" alt="Jazmine" class="chat-header-avatar">

            <div>
                <strong>Jazmine</strong><br>
                <small style="font-size:12px; opacity:0.8;">
                    Your eRequest Assistant
                </small>
            </div>
        </div>

        <button id="einq-close-chat">&times;</button>

    </div>

    <div id="einq-chat-body">
        <!-- messages will be loaded here dynamically -->
    </div>
    
    <div id="einq-chat-footer">
        <div id="einq-input-row" class="einq-input-row">
            <input type="text" id="einq-chat-input" placeholder="Type a message...">
            <button id="einq-send-btn" aria-label="Send message">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M3 20l18-8L3 4v6l12 2-12 2v6z" fill="currentColor"/>
                </svg>
            </button>
        </div>
    </div>
    
</div>
        <!-- Back to Top -->
        <a href="#" class="btn btn-lg btn-warning btn-lg-square text-white rounded-circle back-to-top" aria-label="Back to top">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M6 15l6-6 6 6" stroke="currentColor" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M6 11l6-6 6 6" stroke="currentColor" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>

    </div>


    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.MAINTENANCE_MODE = <?= $maintenance_mode ? 'true' : 'false' ?>;
        window.MAINTENANCE_MESSAGE = <?= json_encode($maintenance_message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    </script>
    <script src="/cityofsantarosa/js/support-widget.js?v=4"></script>

    <!-- Messenger Chat Plugin (guarded — element only exists in index.php) -->
    <script>
        var chatbox = document.getElementById('fb-customer-chat');
        if (chatbox) {
            chatbox.setAttribute("page_id", "141286865889654");
            chatbox.setAttribute("attribution", "biz_inbox");
        }
    </script>

<script>
// Remove heavy entry animations for FAQ on small screens to save CPU
document.addEventListener('DOMContentLoaded', function(){
    try {
        if (window.innerWidth <= 768) {
            var faqCard = document.querySelector('.col-lg-6.wow.fadeInUp[data-wow-delay]');
            if (!faqCard) faqCard = document.querySelector('.col-lg-6 .ei-card');
            if (faqCard) {
                faqCard.classList.remove('wow','fadeInUp');
                faqCard.removeAttribute('data-wow-delay');
            }
        }
    } catch (e) {
        // graceful fallback
    }
});

// ── Announcement Auto-Slider ──
(function() {
    const slides = document.querySelectorAll('.ann-slide');
    const dots   = document.querySelectorAll('.ann-dot');
    if (slides.length <= 1) return;

    let current = 0;
    let timer;

    function showSlide(index) {
        slides.forEach((s, i) => {
            s.classList.toggle('active', i === index);
            s.setAttribute('aria-hidden', String(i !== index));
        });
        dots.forEach((d, i) => {
            d.classList.toggle('active', i === index);
            d.setAttribute('aria-current', i === index ? 'true' : 'false');
        });
        current = index;
    }

    window.changeSlide = function(dir) {
        clearInterval(timer);
        showSlide((current + dir + slides.length) % slides.length);
        startTimer();
    };

    window.goToSlide = function(idx) {
        clearInterval(timer);
        showSlide(idx);
        startTimer();
    };

    function startTimer() {
        timer = setInterval(() => {
            showSlide((current + 1) % slides.length);
        }, 5000);
    }

    startTimer();
})();

// ── Track My Request ──
function trackRequest() {
    const input = document.getElementById('trackCodeInput');
    const btn   = document.getElementById('trackBtn');
    const result = document.getElementById('trackResult');
    const code  = input.value.trim().toUpperCase();

    if (!code) {
        result.className = 'track-result error';
        result.innerHTML = 'Please enter a tracking code.';
        result.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Searching...';
    result.style.display = 'none';

    fetch('/cityofsantarosa/action/track_request', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: code })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Track';

        if (data.success) {
            const d = data.data;
            result.className = 'track-result success';
            result.innerHTML = `
                <div><span class="track-label">Tracking Code:</span> ${d.tracking_code}</div>
                <div><span class="track-label">Document:</span> ${d.document_type}</div>
                <div><span class="track-label">Barangay:</span> ${d.barangay}</div>
                <div><span class="track-label">Submitted:</span> ${d.submitted_at}</div>
                <div><span class="track-label">Status:</span> <span class="track-status ${d.status}">${d.status}</span></div>
                <div><span class="track-label">Payment:</span> <span style="text-transform: capitalize;">${d.payment_status}</span></div>
            `;
        } else {
            result.className = 'track-result error';
            result.innerHTML = data.message || 'Tracking is currently unavailable during maintenance.';
        }
        result.style.display = 'block';
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'Track';
        result.className = 'track-result error';
        result.innerHTML = 'Something went wrong. Please try again.';
        result.style.display = 'block';
    });
}

// Allow Enter key on tracking input
document.getElementById('trackCodeInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') trackRequest();
});

// ── FAQ Accordion ──
function toggleFaq(btn) {
    const answer = btn.nextElementSibling;
    const isOpen = answer.classList.contains('open');

    document.querySelectorAll('#faq .faq-answer.open').forEach(openAnswer => {
        if (openAnswer !== answer) {
            openAnswer.classList.remove('open');
            openAnswer.previousElementSibling.classList.remove('active');
            openAnswer.previousElementSibling.setAttribute('aria-expanded', 'false');
        }
    });

    answer.classList.toggle('open', !isOpen);
    btn.classList.toggle('active', !isOpen);
    btn.setAttribute('aria-expanded', String(!isOpen));
}

// ── End FAQ Accordion ──
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
    <script src="js/index3.js?v=21"></script>
<script src="js/index3-auth-modal.js?v=6"></script>
</body>
</html>