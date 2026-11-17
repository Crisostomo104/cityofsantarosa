<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0'); 

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/announcements.php';
require_once __DIR__ . '/core/tracking.php';
require_once __DIR__ . '/core/chatbot.php';
require_once __DIR__ . '/config/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

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

// Page-header background slideshow. Add/remove filenames here -- everything
// else (preloading, crossfade timing) adapts automatically, same pattern
// used on the login page's background slideshow.
$headerBgImages = [
    'images/city-hall.png',
    'images/museo-de-santa-rosa.png',
    'images/santa-rosa-church.png',
    'images/santa-rosa-plaza.png',
];
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

    <!-- Favicons -->
    <link href="https://santarosacity.gov.ph/assets/favicon.png" rel="apple-touch-icon">
    <link href="https://santarosacity.gov.ph/assets/favicon.png" rel="icon">

    <title>Santa Rosa</title>

    <!-- Favicon -->
    <link href="https://santarosacity.gov.ph/assets/img/logo-footer.png" rel="icon">

    <!-- Nexa Fonts (City of Santa Rosa) -->
    <link href="https://santarosacity.gov.ph/assets/fonts/stylesheet.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Icon Font Stylesheet -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Libraries Stylesheet -->
    <link href="https://santarosacity.gov.ph/assets/lib/animate/animate.min.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/assets/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="https://santarosacity.gov.ph/assets/css/bootstrap.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="https://santarosacity.gov.ph/assets/css/style.css" rel="stylesheet">
    <link href="https://santarosacity.gov.ph/css/custom.css" rel="stylesheet">

    <!-- eRequest Custom Stylesheet (MUST load last) -->
    <link rel="stylesheet" type="text/css" href="css/index.css?v=3">
    <style>
        /* Ensure the Nexa site font applies to this page as a fallback and to classes used in templates */
        body, h1, h2, h3, h4, h5, h6, p, a, span, div {
            font-family: 'Nexa', 'Inter', sans-serif !important;
        }
        .nexabook { font-family: 'Nexa', 'Inter', sans-serif !important; font-weight: 400; }
        .nexabold { font-family: 'Nexa', 'Inter', sans-serif !important; font-weight: 600; }
        .nexablack { font-family: 'Nexa', 'Inter', sans-serif !important; font-weight: 800; }
    </style>
    <style>
        /* Prevent horizontal swipe-gap on mobile */
        html, body { overflow-x: clip !important; max-width: 100vw !important; }
        /* Ensure fixed cookie banner doesn't push layout horizontally */
        #cookieBanner { box-sizing: border-box; }
        .quicknav-icon {width: 55px;height: 55px;object-fit: contain;}
    </style>
    <style>
        /* ---- Page-header background slideshow ----
           Same pattern as the login page: every photo is preloaded and
           already painted in the DOM as its own layer, so swapping the
           active one is a pure opacity transition (cheap, no flicker,
           no re-fetch). Darkened via an overlay (not a lowered image
           opacity) so the white heading text on top stays fully
           readable no matter which photo is showing. */
        .page-header {
            position: relative;
            background-image: none !important; /* the slides below replace this */
            overflow: hidden;
        }
        .page-header-bg-slide {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.8s ease-in-out;
            z-index: 0;
        }
        .page-header-bg-slide.active {
            opacity: 1;
        }
        /* Darkening overlay -- this is what actually lowers the
           image's effective opacity/visibility, layered above the
           slides but below the header's real content. */
        .page-header::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(10, 15, 12, 0.55);
            z-index: 1;
        }
        .page-header-inner {
            position: relative;
            z-index: 2;
        }
        .page-header-bg-dots {
            position: absolute;
            bottom: 14px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            display: flex;
            gap: 6px;
        }
        .page-header-bg-dots span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            transition: background 0.3s ease, transform 0.3s ease;
        }
        .page-header-bg-dots span.active {
            background: #fff;
            transform: scale(1.3);
        }
    </style>
    

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
                    <button id="acceptCookiesBtn" class="btn btn-primary" style="padding:6px 10px;font-size:13px;">Accept</button>
                    <button id="declineCookiesBtn" class="btn btn-outline-secondary" style="padding:6px 10px;font-size:13px;">Decline</button>
                    <a href="privacy" rel="noopener noreferrer" style="font-size:12px;color:#6b7280;text-decoration:underline;">Privacy</a>
                    <a href="cookies" style="font-size:12px;color:#6b7280;text-decoration:underline;">Cookies</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Navbar Start -->
        <nav class="navbar bg-theme navbar-light sticky-top shadow p-0">
            <a href="erequest-mo" class="navbar-brand d-flex align-items-center px-4 px-lg-5 mx-auto">
                <img class="h-95" src="/cityofsantarosa/images/logos/erequest-logo.png" alt="eRequest Mo">
            </a>
            <div class="position-absolute end-0">
                <button type="button" class="navbar-toggler me-4" data-bs-toggle="offcanvas"
                    data-bs-target="#offcanvasExample">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
        </nav>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasExample" aria-labelledby="offcanvasRightLabel"
            data-bs-backdrop="false" data-bs-scroll="true">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title text-theme nexabook" id="offcanvasExampleLabel">Navigation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body bg-white navbar nav-tabs flex-grow-0">
                <div class="navbar-collapse collapse show" id="navbarCollapse" style="">
                    <div class="navbar-nav ms-auto p-4 p-lg-0 nexabold">
                        <a href="./" class="nav-item nav-link">Home</a>
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
        <div class="container-fluid page-header p-0 px-2 pb-0">
            <!-- Background photo slideshow. Every photo in $headerBgImages
                 is rendered as its own layer here and preloaded via
                 <link rel="preload"> below -- adding a new filename to the
                 PHP array above is the only thing needed to include it in
                 the rotation. -->
            <?php foreach ($headerBgImages as $i => $img): ?>
            <div class="page-header-bg-slide<?= $i === 0 ? ' active' : '' ?>" style="background-image:url('<?= htmlspecialchars($img, ENT_QUOTES) ?>');" aria-hidden="true"></div>
            <?php endforeach; ?>
            <?php if (count($headerBgImages) > 1): ?>
            <div class="page-header-bg-dots">
                <?php foreach ($headerBgImages as $i => $img): ?>
                <span class="<?= $i === 0 ? 'active' : '' ?>"></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="container-fluid page-header-inner py-5">
                <div class="container">
                    <div class="col-lg-12 mx-auto text-center">
                        <h1 class="nexablack display-1 text-white mb-3 animated slideInDown responsive-title">eRequest Mo</h1>
                        <p class="lead text-white mb-4 animated slideInDown">A Centralized Web-Based Barangay Document Request System for City of Santa Rosa</p>
                        <div class="btn-center">

                            <a href="auth/registration" class="btn-custom">Get Started</a>

                            <a href="auth/login" class="btn-custom">Login</a>

                        </div>    
                    </div>
                </div>
            </div>
        </div>
        <!-- Page Header End -->

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
            <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>

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
                <div class="ann-slide" style="display:<?= $idx === 0 ? 'block' : 'none' ?>;">
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
            <div class="ei-card">
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
    <style>
        .how-it-works-steps {
            display: flex;
            flex-wrap: wrap;
        }
        .how-it-works-step {
            flex: 1 1 100%;
            padding: 22px 20px;
            text-align: center;
            border-bottom: 1px solid #f0e6e6;
        }
        /* Vertical dividers between steps on desktop; last one has none.
           Mobile stays full-width per step (one per row) so text never
           has to squeeze into a narrow column. */
        @media (min-width: 992px) {
            .how-it-works-step {
                flex: 1 1 0;
                border-bottom: none;
                border-right: 1px solid #f0e6e6;
            }
            .how-it-works-step:last-child {
                border-right: none;
            }
        }
        .how-it-works-step:last-child {
            border-bottom: none;
        }
        .how-it-works-step .step-number {
            margin: 0 auto 12px;
        }
    </style>
    <div class="row g-4 mt-2">
      <div class="col-12 wow fadeInUp" data-wow-delay="0.2s">
        <div class="ei-card">
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
        <div class="ei-card">
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
              <button class="faq-question" onclick="toggleFaq(this)">
                <span><?= $faq[0] ?></span>
                <span class="faq-icon">+</span>
              </button>
              <div class="faq-answer"><?= $faq[1] ?></div>
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
                                <a class="btn btn-outline-light btn-social p-4"
                                    href="https://www.facebook.com/citygovernmentofsantarosa" target="_blank"><i
                                        class="fab fa-facebook-f"></i></a>
                                <a class="btn btn-outline-light btn-social p-4" href="https://twitter.com/santarosacity"
                                    target="_blank"><i class="fab fa-twitter"></i></a>
                                <a class="btn btn-outline-light btn-social p-4"
                                    href="https://www.youtube.com/user/SantaRosaPhils" target="_blank"><i
                                        class="fab fa-youtube"></i></a>
                                <a class="btn btn-outline-light btn-social p-4"
                                    href="https://www.instagram.com/cityofsantarosalaguna/" target="_blank"><i
                                        class="fab fa-instagram"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <p class="text-light mb-3 fs-3">Quick Links</p>
                            <a class="btn btn-link" href="https://bis.xo.je/cityofsantarosa/">Home</a>
                            <a class="btn btn-link request-footer-brand-link" href="erequest-mo">eRequest Mo</a>
                            <a class="btn btn-link" href="https://bis.xo.je/cityofsantarosa/auth/login">Login</a>
                            <a class="btn btn-link" href="https://bis.xo.je/cityofsantarosa/auth/registration">Registration</a>
                            <a class="btn btn-link" href="terms">Terms and Conditions</a>
                            <a class="btn btn-link" href="cookies">Cookie Policy</a>
                            <a class="btn btn-link" href="https://bis.xo.je/cityofsantarosa/privacy" target="_blank">Privacy Policy</a>
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
    <svg width="22" height="22" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="9" cy="12" r="1" fill="currentColor"/>
        <circle cx="12" cy="12" r="1" fill="currentColor"/>
        <circle cx="15" cy="12" r="1" fill="currentColor"/>
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
    <script src="https://santarosacity.gov.ph/js/jquery-3.4.1.js"></script>
    <script src="https://santarosacity.gov.ph/js/bootstrap.bundle.js"></script>
    <script src="https://santarosacity.gov.ph/assets/lib/wow/wow.min.js"></script>
    <script src="https://santarosacity.gov.ph/assets/lib/easing/easing.min.js"></script>
    <script src="https://santarosacity.gov.ph/assets/lib/waypoints/waypoints.min.js"></script>
    <script src="https://santarosacity.gov.ph/assets/lib/counterup/counterup.min.js"></script>
    <script src="https://santarosacity.gov.ph/assets/lib/owlcarousel/owl.carousel.min.js"></script>

    <!-- Template Javascript -->
    <script src="https://santarosacity.gov.ph/assets/js/main.js"></script>
    <script>
    // Sticky Navbar Directional Transition
    $(window).off('scroll');
    var lastScrollTop = 0;
    $(window).scroll(function () {
        var st = $(this).scrollTop();
        
        // Navbar direction logic
        if (st > 100) {
            if (st > lastScrollTop) {
                // Scrolling down
                $('.sticky-top').css('top', '-100px');
            } else {
                // Scrolling up
                $('.sticky-top').css('top', '0px');
            }
        } else {
            // At the very top of the page
            $('.sticky-top').css('top', '0px');
        }
        
        lastScrollTop = st;

        // Back-to-top button
        if (st > 300) {
            $('.back-to-top').fadeIn('slow');
        } else {
            $('.back-to-top').fadeOut('slow');
        }
    });
    </script>
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
        $(function () {
            $('[data-toggle=tooltip]').tooltip();
        });
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
            s.style.display = i === index ? 'block' : 'none';
            if (i === index) s.style.animation = 'fadeIn 0.5s';
        });
        dots.forEach((d, i) => {
            d.classList.toggle('active', i === index);
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

// ── Page-header background slideshow ──
// All slides are already in the DOM, so switching "active" is a pure
// opacity transition -- no fetch, no re-decode. Only runs if there's
// more than one image in $headerBgImages.
(function() {
    const slides = document.querySelectorAll('.page-header-bg-slide');
    const dots = document.querySelectorAll('.page-header-bg-dots span');
    if (slides.length <= 1) return;

    let current = 0;
    const intervalMs = 6000;
    let timer = null;

    function goTo(index) {
        slides[current].classList.remove('active');
        dots[current] && dots[current].classList.remove('active');
        current = index;
        slides[current].classList.add('active');
        dots[current] && dots[current].classList.add('active');
    }

    function next() {
        goTo((current + 1) % slides.length);
    }

    function start() {
        stop();
        timer = setInterval(next, intervalMs);
    }
    function stop() {
        if (timer) clearInterval(timer);
        timer = null;
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop();
        else start();
    });

    start();
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

    // Close previously open item (if any)
    const prevOpen = document.querySelector('.faq-answer.open');
    if (prevOpen && prevOpen !== answer) {
        // animate closed
        collapseFaq(prevOpen, prevOpen.previousElementSibling);
    }

    if (!isOpen) {
        expandFaq(answer, btn);
    } else {
        collapseFaq(answer, btn);
    }
}

function expandFaq(answer, btn) {
    // measure and animate to natural height
    answer.classList.add('open');
    btn.classList.add('active');
    // remove any previously-set maxHeight to measure natural height
    answer.style.maxHeight = 'none';
    const natural = answer.scrollHeight;
    // set starting point then animate to measured height
    answer.style.maxHeight = '0px';
    requestAnimationFrame(() => {
        answer.style.maxHeight = natural + 'px';
    });
    // Keep the measured pixel value after transition to avoid a reflow jump
    // (we'll update this on window resize below)
}

function collapseFaq(answer, btn) {
    // animate from current height to 0
    const current = answer.scrollHeight;
    answer.style.maxHeight = current + 'px';
    // force a frame then collapse
    requestAnimationFrame(() => {
        answer.style.maxHeight = '0px';
    });
    answer.classList.remove('open');
    if (btn && btn.classList) btn.classList.remove('active');
}

// Update open FAQ heights on resize to keep them fitting content without jump
(function() {
    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            document.querySelectorAll('.faq-answer.open').forEach(function(el) {
                // set to current scrollHeight to match content
                el.style.maxHeight = el.scrollHeight + 'px';
            });
        }, 120);
    });
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

<script>
// Cookie consent handling
document.addEventListener('DOMContentLoaded', function(){
    const buttons = [
        { id: 'acceptCookiesBtn', value: 'accepted' },
        { id: 'declineCookiesBtn', value: 'declined' }
    ];
    buttons.forEach(function(choice) {
        const button = document.getElementById(choice.id);
        if (!button) return;
        button.addEventListener('click', function(){
            const maxAge = 60 * 60 * 24 * 365;
            document.cookie = 'ei_cookie_consent=' + choice.value + '; max-age=' + maxAge + '; path=/; samesite=Lax';
            const banner = document.getElementById('cookieBanner');
            if (banner) banner.remove();
        });
    });
});
</script>