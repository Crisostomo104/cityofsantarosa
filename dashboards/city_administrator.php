<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../data/city_admin_data.php';
require_once __DIR__ . '/../config/barangays.php';
require_once __DIR__ . '/../core/archiving.php';
run_expiry_check($pdo);
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>City Administrator - Santa Rosa</title>
<!-- ICON -->
<link rel="icon" href="../images/ibaba2.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../css/dashboard.css?v=<?= time() ?>">
<!-- <link rel="stylesheet" href="../css/city_administrator.css?v=<?= time() ?>">
<link rel="stylesheet" href="../css/shared_dashboard.css?v=<?= time() ?>"> -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css?v=<?= time() ?>">
<link rel="stylesheet" href="../css/prerequisites.css?v=<?= time() ?>">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
</head>
<body>
<div class="dashboard-loading-overlay" id="dashboardLoadingOverlay" aria-live="polite">
    <div class="dashboard-loading-card">
        <div class="dashboard-loading-spinner"></div>
        <div class="dashboard-loading-text">Loading dashboard…</div>
    </div>
</div>

<script>
function hideLoadingOverlay() {
    const overlay = document.getElementById('dashboardLoadingOverlay');
    if (!overlay) return;
    setTimeout(() => {
        overlay.classList.add('is-hidden');
        setTimeout(() => overlay.style.display = 'none', 250);
    }, 400); // slight delay so the user sees a smooth fade-out transition
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hideLoadingOverlay);
} else {
    hideLoadingOverlay();
}

// Show overlay immediately when page unloads (navigating or refreshing)
window.addEventListener('beforeunload', () => {
    const overlay = document.getElementById('dashboardLoadingOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
        overlay.classList.remove('is-hidden');
    }
});

// Safety: hide overlay after 3 seconds max even if page is still loading
setTimeout(hideLoadingOverlay, 3000);
</script>

<!-- TOP BAR -->
<div class="top-bar">
    <div class="d-flex align-items-center gap-2">
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">☰</button>
    </div>

    <div class="top-bar-logo">
        <img class="h-65" src="../images/logos/erequest-logo.png" alt="eRequest Mo">
    </div>
    
    <div class="d-flex align-items-center gap-3">
        
        <!-- LIVE CLOCK (desktop only; mobile uses the sidebar clock) -->
        <div class="topbar-clock" aria-hidden="true">
            <span class="tc-date" id="topbarDate"></span>
            <span class="tc-time" id="topbarTime"></span>
        </div>
        <script>
        (function () {
            const d = document.getElementById('topbarDate');
            const t = document.getElementById('topbarTime');
            const tz = { timeZone: 'Asia/Manila' };
            function tick() {
                const now = new Date();
                d.textContent = now.toLocaleDateString('en-US', { ...tz, weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
                t.textContent = now.toLocaleTimeString('en-US', { ...tz, hour: 'numeric', minute: '2-digit', second: '2-digit' });
            }
            tick();
            setInterval(tick, 1000);
        })();
        </script>
        
        <!-- NOTIFICATION BELL -->
        <div class="notif-wrapper">
            <button class="notif-bell" id="notifBell" aria-label="Notifications">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span>Notifications</span>
                    <div class="notif-actions">
                        <button id="notifMarkAll">Mark all read</button>
                        <button id="notifDeleteAll">Delete all</button>
                    </div>
                </div>
                <div class="notif-list" id="notifList">
                    <div class="notif-empty">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="bg-secondary text-white d-flex align-items-center p-2">
        <img
            src="../images/ibaba2.png"
            alt="Barangay Logo"
            width="70"
            height="70"
            class="rounded-circle me-3"
            loading="lazy"
        >

        <div>
            <strong style="color: #ffffff;">City of Santa Rosa</strong>
            City Adminsitrator
        </div>
    </div>
    <div class="user-dropdown-container">
        <button type="button" class="sidebar-user" onclick="toggleUserDropdown(this)">
            <span class="sidebar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
            <svg style="width: 16px; height: 16px; margin-left: auto;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>
        <div id="userDropdown" class="user-dropdown-menu">
            <a href="../auth/logout" class="dropdown-logout">
                <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Logout
            </a>
        </div>
    </div>
    <hr style="margin: 10px 0; border-color: #ccc;">

    <a href="#" class="active" onclick="showOvTab('dashboard',this)">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 3v18h18"/>
            <path d="M7 15v-4"/>
            <path d="M12 15V8"/>
            <path d="M17 15v-7"/>
        </svg>
        Dashboard Overview
    </a>

    <br>

    <a href="#" data-group="system" onclick="showGroupTab('requests',this,'system')">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        </svg>
        Manage Requests
    </a>

    <br>

    <a href="#" data-group="accounts" onclick="showGroupTab('approvals',this,'accounts')">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Manage Accounts

        <?php if ($stat_pending_emp > 0): ?>
            <span class="pending-badge"><?= $stat_pending_emp ?></span>
        <?php endif; ?>
    </a>

    <br>

    <a href="#" data-group="officials" onclick="showGroupTab('officials-Aplaya',this,'officials')">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 10l9-6 9 6"/>
            <path d="M5 10v9"/>
            <path d="M10 10v9"/>
            <path d="M14 10v9"/>
            <path d="M19 10v9"/>
            <path d="M2 19h20"/>
        </svg>
        Barangay Officials

        <?php if ($stat_pending_edits > 0): ?>
            <span class="pending-badge"><?= $stat_pending_edits ?></span>
        <?php endif; ?>
    </a>

    <br>

    <a href="#" data-group="sys-settings" onclick="showGroupTab('announcements',this,'sys-settings')">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
        System Settings
    </a>

    <hr style="margin: 10px 0; border-color: #ccc;">

    <div class="sidebar-footer" style="padding-left: 16px;">
        <small style="color: #c2c0c0ff;">
            <a href="https://loadssss.vercel.app" target="_blank">Powered By Loadssss</a><a style="text-decoration: none">Version 1.0.0</a>
        </small>
    </div>


    <div class="sidebar-clock">
        <div class="clock-label">Current time</div>
        <div id="sidebarClock" class="clock-value">Loading...</div>
    </div>


</div>



<!-- SIDEBAR OVERLAY (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

<!-- ══════════════════════════════════════════════
     PANEL: OVERVIEW
══════════════════════════════════════════════ -->
<div id="panel-overview" class="panel active">
    <div class="ov-tabs">
        <button class="ov-tab active" data-tab="dashboard" data-group="overview" onclick="showOvTab('dashboard',this)">Dashboard</button>
        <button class="ov-tab" data-tab="barangay" data-group="overview" onclick="showOvTab('barangay',this)">Barangay Report</button>
        <button class="ov-tab" data-tab="top-documents" data-group="overview" onclick="showOvTab('top-documents',this)">Top Documents</button>
    </div>
    <div id="ov-dashboard" class="ov-tab-content">
    <?php
        $selected_dashboard_year = $dashboard_year_param === 'all' ? '' : $dashboard_year_param;
        $selected_dashboard_month = $dashboard_month_param === 'all' ? '' : $dashboard_month_param;
        $dashboard_all_mode = $dashboard_year_param === 'all';
        $dashboard_request_url = '?' . http_build_query([
            'panel' => 'requests',
            'req_filter' => 'dashboard',
            'dashboard_year' => $selected_dashboard_year,
            'dashboard_month' => $selected_dashboard_month,
        ]);
        $today_requests_url = '?' . http_build_query([
            'panel' => 'requests',
            'req_filter' => 'today',
        ]);
    ?>
    <h4 class="mb-1">Dashboard Overview</h4>
    <p class="text-muted mb-4">City of Santa Rosa — All Barangays</p>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <small class="text-muted">Request totals and analytics use the selected period. Account totals and Today's Requests are current.</small>
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-0">
            <input type="hidden" name="panel" value="dashboard">
            <label for="cityDashboardYear" class="fw-semibold small text-muted mb-0">Filter:</label>
            <select name="dashboard_year" id="cityDashboardYear" class="form-select form-select-sm w-auto" onchange="applyCityDashboardFilter()">
                <option value="">All Years</option>
                <?php for ($year = (int)date('Y'); $year >= 2020; $year--): ?>
                    <option value="<?= $year ?>" <?= $selected_dashboard_year == $year ? 'selected' : '' ?>><?= $year ?></option>
                <?php endfor; ?>
            </select>
            <select name="dashboard_month" id="cityDashboardMonth" class="form-select form-select-sm w-auto" onchange="applyCityDashboardFilter()" <?= $dashboard_all_mode ? 'disabled' : '' ?>>
                <option value="">All Months</option>
                <?php
                    $dashboard_months = [
                        '01'=>'January','02'=>'February','03'=>'March','04'=>'April',
                        '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
                        '09'=>'September','10'=>'October','11'=>'November','12'=>'December',
                    ];
                    foreach ($dashboard_months as $month => $monthName):
                ?>
                    <option value="<?= $month ?>" <?= $selected_dashboard_month === $month ? 'selected' : '' ?>><?= $monthName ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($dashboard_all_mode): ?>
                <a href="?panel=dashboard&amp;dashboard_year=<?= date('Y') ?>&amp;dashboard_month=<?= date('m') ?>" class="btn btn-sm btn-outline-dark">Filter This Month</a>
            <?php else: ?>
                <a href="?panel=dashboard&amp;dashboard_year=&amp;dashboard_month=" class="btn btn-sm btn-outline-dark">Filter All</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── TOP STAT CARDS ── -->
 
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <a href="?panel=residents" class="dashboard-stat-link">
                <div class="stat-card" style="border-top:3px solid #3b82f6;">
                    <div class="num text-primary"><?= $stat_residents ?></div>
                    <small class="text-muted">Total Residents</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-2">
            <a href="?panel=residents" class="dashboard-stat-link">
                <div class="stat-card" style="border-top:3px solid #06b6d4;">
                    <div class="num text-info"><?= $stat_residents_ver ?></div>
                    <small class="text-muted">Verified Residents</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-2">
            <a href="?panel=employees" class="dashboard-stat-link">
                <div class="stat-card" style="border-top:3px solid #22c55e;">
                    <div class="num text-success"><?= $stat_employees ?></div>
                    <small class="text-muted">Active Employees</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-2">
            <a href="?panel=approvals" class="dashboard-stat-link">
                <div class="stat-card" style="border-top:3px solid #f59e0b;">
                    <div class="num text-warning"><?= $stat_pending_emp ?></div>
                    <small class="text-muted">Pending Approvals</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-2">
            <a href="<?= htmlspecialchars($dashboard_request_url, ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link">
                <div class="stat-card" style="border-top:3px solid #8b5cf6;">
                    <div class="num" style="color:#8b5cf6;"><?= $stat_requests ?></div>
                    <small class="text-muted">Total Requests</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-2">
            <a href="<?= htmlspecialchars($today_requests_url, ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link">
                <div class="stat-card" style="border-top:3px solid #0ea5e9;">
                    <div class="num" style="color:#0ea5e9;"><?= $stat_today_req ?></div>
                    <small class="text-muted">Today's Requests</small>
                </div>
            </a>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <a href="<?= htmlspecialchars($dashboard_request_url . '&req_status=Pending', ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link">
                <div class="stat-card" style="border-top:3px solid #f59e0b;">
                    <div class="num text-warning"><?= $stat_pending_req ?></div>
                    <small class="text-muted">Pending Requests</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="<?= htmlspecialchars($dashboard_request_url . '&req_status=Approved', ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link">
                <div class="stat-card" style="border-top:3px solid #3b82f6;">
                    <div class="num text-primary"><?= $stat_approved_req ?></div>
                    <small class="text-muted">Approved Requests</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="<?= htmlspecialchars($dashboard_request_url . '&req_status=To%20be%20Claim', ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link">
                <div class="stat-card" style="border-top:3px solid #6366f1;">
                    <div class="num" style="color:#6366f1;"><?= $stat_to_be_claim ?></div>
                    <small class="text-muted">To be Claimed</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="<?= htmlspecialchars($dashboard_request_url . '&req_status=Rejected', ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link">
                <div class="stat-card" style="border-top:3px solid #ef4444;">
                    <div class="num text-danger"><?= $stat_rejected_req ?></div>
                    <small class="text-muted">Rejected Requests</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="<?= htmlspecialchars($dashboard_request_url . '&req_status=Completed', ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link">
                <div class="stat-card" style="border-top:3px solid #22c55e;">
                    <div class="num text-success"><?= $stat_completed ?></div>
                    <small class="text-muted">Completed Requests</small>
                </div>
            </a>
        </div>
    </div>

    <!-- ── PENDING ALERT ── -->
    <?php if ($stat_pending_emp > 0): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
        <span style="font-size:22px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 24">
              <path fill="orange" d="M1 21h22L12 2 1 21z"/>
              <path fill="white" d="M12 16a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm1-7h-2v6h2V9z"/>
            </svg>
        </span>
        <div>
            <strong><?= $stat_pending_emp ?> pending employee account(s)</strong> need your review.
            <a href="#" onclick="showGroupTab('approvals', null, 'accounts'); return false;" class="alert-link ms-2" style="text-decoration:none">Review now →</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── ANALYTICS (inline in Dashboard tab) ── -->
    <h5 class="mb-3 mt-2">Analytics</h5>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Request Status Breakdown</div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <canvas id="chartStatus" style="max-height:280px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold"><?= htmlspecialchars($chart_daily_title, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="card-body">
                    <canvas id="chartDaily" style="max-height:280px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /ov-dashboard -->
    
    <div id="ov-barangay" class="ov-tab-content" style="display:none">
    <?php
        // Mirror the employee dashboard's default: no brgy_year in the URL
        // at all means "first load" -> default to current year/month.
        // Once the filter form has been submitted (brgy_year present,
        // even as '' for "All Years"), that explicit choice is respected.
        $selected_brgy_year  = isset($_GET['brgy_year'])  ? $_GET['brgy_year']  : date('Y');
        $selected_brgy_month = isset($_GET['brgy_month']) ? $_GET['brgy_month'] : (($selected_brgy_year === date('Y')) ? date('m') : '');
        $brgy_month_disabled = ($selected_brgy_year === '' || $selected_brgy_year === null);
        $brgy_is_all_mode    = ($selected_brgy_year === '' || $selected_brgy_year === null);

        $monthOptions = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthOptions[sprintf('%02d', $i)] = date('F', mktime(0, 0, 0, $i, 1));
        }
        $yearOptions = [];
        $startYear = date('Y') - 2;
        $endYear = date('Y');
        for ($y = $startYear; $y <= $endYear; $y++) {
            $yearOptions[$y] = (string)$y;
        }
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Barangay Report</h4>
            <small class="text-muted">Per-barangay breakdown of residents, employees, and requests.</small>
            <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-0">
                    <input type="hidden" name="panel" value="overview">
                    <input type="hidden" name="tab" value="barangay">
                    <label class="fw-semibold small text-muted mb-0">Filter:</label>
                    <select name="brgy_year" id="brgyFilterYear" class="form-select form-select-sm w-auto" onchange="applyBarangayReportFilter()">
                        <option value="">All Years</option>
                        <?php foreach ($yearOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $selected_brgy_year == $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="brgy_month" id="brgyFilterMonth" class="form-select form-select-sm w-auto" onchange="applyBarangayReportFilter()" <?= $brgy_month_disabled ? 'disabled' : '' ?>>
                        <option value="">All Months</option>
                        <?php foreach ($monthOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $selected_brgy_month == $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="brgy_limit" id="brgyFilterLimit" class="form-select form-select-sm w-auto" onchange="applyBarangayReportFilter()" aria-label="Number of barangays shown in chart">
                        <option value="5" <?= $brgyLimit === '5' ? 'selected' : '' ?>>Top 5</option>
                        <option value="10" <?= $brgyLimit === '10' ? 'selected' : '' ?>>Top 10</option>
                        <option value="all" <?= $brgyLimit === 'all' ? 'selected' : '' ?>>All</option>
                    </select>
                    <!-- Always visible — toggles depending on current state. -->
                    <?php if ($brgy_is_all_mode): ?>
                        <a href="?panel=overview&tab=barangay&brgy_year=<?= date('Y') ?>&brgy_month=<?= date('m') ?>&brgy_limit=<?= urlencode($brgyLimit) ?>" class="btn btn-sm btn-outline-dark">Filter Today</a>
                    <?php else: ?>
                        <a href="?panel=overview&tab=barangay&brgy_year=&brgy_month=&brgy_limit=<?= urlencode($brgyLimit) ?>" class="btn btn-sm btn-outline-dark">Filter All</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="../action/export_requests" class="btn btn-outline-success btn-sm d-flex align-items-center gap-2">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 3v12"/>
                    <path d="M7 10l5 5 5-5"/>
                    <path d="M5 21h14"/>
                </svg>
                Export CSV
            </a>
            <a href="print_report?rep_filter=<?= htmlspecialchars($rep_filter) ?>" target="_blank" class="btn btn-outline-dark btn-sm d-flex align-items-center gap-2">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                View Report
            </a>
            <a href="narrative_report?rep_filter=<?= htmlspecialchars($rep_filter) ?>" target="_blank" class="btn btn-outline-dark btn-sm d-flex align-items-center gap-2">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                View Narrative Report
            </a>
        </div>
    </div>

    <?php
        if ($brgy_is_all_mode) {
            $brgyChartLabel = 'Requests per Barangay — All Time';
        } elseif ($selected_brgy_month === '' || $selected_brgy_month === null) {
            $brgyChartLabel = 'Requests per Barangay for ' . htmlspecialchars($selected_brgy_year);
        } else {
            $brgyChartLabel = 'Requests per Barangay for ' . date('F Y', mktime(0, 0, 0, (int)$selected_brgy_month, 1, (int)$selected_brgy_year));
        }
    ?>
    <div class="card shadow-sm mb-4 brgy-report-card">
        <div class="card-header fw-semibold"><?= htmlspecialchars($brgyChartLabel) ?></div>
        <div class="card-body">
            <canvas id="chartBrgy" style="max-height:220px;"></canvas>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span>Per-Barangay Summary</span>
            <small class="text-muted" id="brgy-count-info"></small>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0" id="brgyTable">
                <thead class="table-dark">
                    <tr>
                        <th>Barangay</th>
                        <th class="text-center">Residents</th>
                        <th class="text-center">Employees</th>
                        <th class="text-center">Total Req.</th>
                        <th class="text-center">🟡 Pending</th>
                        <th class="text-center">🔵 Approved</th>
                        <th class="text-center">🟣 To be Claimed</th>
                        <th class="text-center">🟢 Completed</th>
                        <th class="text-center">🔴 Rejected</th>
                    </tr>
                </thead>
                <tbody id="brgyTbody">
                <?php foreach ($barangay_stats as $bs): ?>
                <tr
                    data-brgy="<?= strtolower(htmlspecialchars($bs['barangay'])) ?>"
                    data-res="<?= (int)$bs['residents'] ?>"
                    data-emp="<?= (int)$bs['employees'] ?>"
                    data-req="<?= (int)$bs['total_requests'] ?>"
                    data-pend="<?= (int)$bs['pending'] ?>"
                    data-appr="<?= (int)$bs['approved'] ?>"
                    data-to-claim="<?= (int)$bs['to_be_claim'] ?>"
                    data-comp="<?= (int)$bs['completed'] ?>"
                    data-rej="<?= (int)$bs['rejected'] ?>"
                >
                    <td><strong><?= htmlspecialchars($bs['barangay']) ?></strong></td>
                    <td class="text-center"><?= $bs['residents'] ?></td>
                    <td class="text-center"><?= $bs['employees'] ?></td>
                    <td class="text-center"><strong><?= $bs['total_requests'] ?></strong></td>
                    <td class="text-center"><?= $bs['pending']   ? '<span class="badge bg-warning text-dark">'.$bs['pending'].'</span>'  : '—' ?></td>
                    <td class="text-center"><?= $bs['approved']  ? '<span class="badge bg-primary">'.$bs['approved'].'</span>'            : '—' ?></td>
                    <td class="text-center"><?= $bs['to_be_claim'] ? '<span class="badge bg-info text-dark">'.$bs['to_be_claim'].'</span>' : '—' ?></td>
                    <td class="text-center"><?= $bs['completed'] ? '<span class="badge bg-success">'.$bs['completed'].'</span>'           : '—' ?></td>
                    <td class="text-center"><?= $bs['rejected']  ? '<span class="badge bg-danger">'.$bs['rejected'].'</span>'             : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- PAGINATION -->
        <div class="card-footer d-flex justify-content-between align-items-center" id="brgy-pagination"></div>
    </div>
    </div><!-- /ov-barangay -->

</div><!-- /panel-overview -->


<!-- ══════════════════════════════════════════════
     PANEL: MY PROFILE
══════════════════════════════════════════════ -->
<div id="panel-profile" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="approvals" data-group="accounts" onclick="showGroupTab('approvals',this,'accounts')">Approvals</button>
        <button class="ov-tab" data-tab="employees" data-group="accounts" onclick="showGroupTab('employees',this,'accounts')">Employees</button>
        <button class="ov-tab" data-tab="residents" data-group="accounts" onclick="showGroupTab('residents',this,'accounts')">Residents</button>
        <button class="ov-tab active" data-tab="profile" data-group="accounts" onclick="showGroupTab('profile',this,'accounts')">My Profile</button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h5 class="mb-3">Personal Information</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <strong>Account Type:</strong><br>
                    City Administrator
                </div>
                <div class="col-md-6">
                    <strong>Your ID:</strong><br>
                    ADM-<?= str_pad($user_id, 3, '0', STR_PAD_LEFT) ?>
                </div>
                <div class="col-md-6">
                    <strong>Full Name:</strong><br>
                    <?= htmlspecialchars($user_account['full_name'] ?? $fullname) ?>
                </div>
                <div class="col-md-6">
                    <strong>Email:</strong><br>
                    <?= htmlspecialchars($user_account['email'] ?? ($_SESSION['email'] ?? 'N/A')) ?>
                </div>
                <div class="col-md-6">
                    <strong>Contact Number:</strong><br>
                    <?= htmlspecialchars($user_account['phone'] ?? 'N/A') ?>
                </div>
                <div class="col-md-6">
                    <strong>Date Registered:</strong><br>
                    <?= !empty($user_account['created_at']) ? date('M d, Y', strtotime($user_account['created_at'])) : 'N/A' ?>
                </div>
            </div>
            <hr>
            <button class="btn btn-warning btn-sm" id="adminResetBtn">Forgot Password</button>
        </div>
    </div>
</div>
    
<!-- ══════════════════════════════════════════════
     PANEL: EMPLOYEE APPROVALS
══════════════════════════════════════════════ -->
<div id="panel-approvals" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab active" data-tab="approvals" data-group="accounts" onclick="showGroupTab('approvals',this,'accounts')">Approvals</button>
        <button class="ov-tab" data-tab="employees" data-group="accounts" onclick="showGroupTab('employees',this,'accounts')">Employees</button>
        <button class="ov-tab" data-tab="residents" data-group="accounts" onclick="showGroupTab('residents',this,'accounts')">Residents</button>
        <button class="ov-tab" data-tab="profile" data-group="accounts" onclick="showGroupTab('profile',this,'accounts')">My Profile</button>
    </div>
    <h4 class="mb-1">Employee Account Approvals</h4>
    <p class="text-muted mb-4">Review and approve new employee accounts submitted by barangay personnel.</p>

    <?php if (empty($pending_employees)): ?>
        <div class="alert" style="display:flex;align-items:center;gap:8px; background-color:#d1e7dd;color:#0f5132;border:1px solid #badbcc;border-radius:4px;padding:12px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                xmlns="http://www.w3.org/2000/svg"
                style="vertical-align:middle;margin-right:6px;">
                <circle cx="12" cy="12" r="10"
                        stroke="#198754"
                        stroke-width="2"/>
                <path d="M8 12L11 15L16 9"
                    stroke="#198754"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"/>
            </svg>
            No pending employee accounts. All accounts are approved.
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Barangay</th>
                    <th>Phone</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pending_employees as $emp): ?>
            <tr>
                <td><strong><?= htmlspecialchars($emp['full_name']) ?></strong></td>
                <td><?= htmlspecialchars($emp['email']) ?></td>
                <td><?= htmlspecialchars($emp['barangay'] ?? '—') ?></td>
				<td><?= htmlspecialchars(format_phone_display($emp['phone'] ?? '')) ?></td>
                <td><small><?= date('M d, Y h:i A', strtotime($emp['created_at'])) ?></small></td>
                <td class="d-flex gap-2">
                    <button class="icon-action-btn icon-success" title="Approve"
                            onclick="swalGo('Approve Account', 'Approve account for <?= htmlspecialchars(addslashes($emp['full_name'])) ?>?', 'question', 'Approve', '#22c55e', '../action/approve_employee?id=<?= $emp['user_id'] ?>')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </button>
                    <button class="icon-action-btn icon-danger" title="Reject"
                            onclick="rejectEmployee(<?= $emp['user_id'] ?>, '<?= htmlspecialchars(addslashes($emp['full_name'])) ?>')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════
     PANEL: ALL EMPLOYEES
══════════════════════════════════════════════ -->
<div id="panel-employees" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="approvals" data-group="accounts" onclick="showGroupTab('approvals',this,'accounts')">Approvals</button>
        <button class="ov-tab active" data-tab="employees" data-group="accounts" onclick="showGroupTab('employees',this,'accounts')">Employees</button>
        <button class="ov-tab" data-tab="residents" data-group="accounts" onclick="showGroupTab('residents',this,'accounts')">Residents</button>
        <button class="ov-tab" data-tab="profile" data-group="accounts" onclick="showGroupTab('profile',this,'accounts')">My Profile</button>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">All Employee Accounts</h4>
        <input type="text" id="empSearch" class="form-control form-control-sm w-auto emp-search" placeholder="Search name, email, barangay..." oninput="filterTable('empTable', this.value, 'empSearch')">
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <small id="empPageInfo" class="text-muted"></small>
        <div id="empPagination" class="d-flex gap-2"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle" id="empTable">
            <thead class="table-dark">
                <tr><th>Employee ID</th><th>Name</th><th>Email</th><th>Barangay</th><th>Phone</th><th>Online</th><th>Joined</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($all_employees as $emp): ?>
            <tr>
                <td>EMP-<?= str_pad($emp['user_id'],3,'0',STR_PAD_LEFT) ?></td>
                <td><?= htmlspecialchars($emp['full_name']) ?></td>
                <td><?= htmlspecialchars($emp['email']) ?></td>
                <td><?= htmlspecialchars($emp['barangay'] ?? '—') ?></td>
				<td><?= htmlspecialchars(format_phone_display($emp['phone'] ?? '')) ?></td>
                <td>
                    <?php if ($emp['session_id'] === 'DEACTIVATED'): ?>
                        <span class="text-danger">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                style="vertical-align:middle;margin-right:4px;">
                                <circle cx="12" cy="12" r="10"
                                        stroke="currentColor"
                                        stroke-width="2"/>
                                <path d="M8 8L16 16M16 8L8 16"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"/>
                            </svg>
                            Deactivated
                        </span>

                    <?php elseif (!empty($emp['session_id'])): ?>
                        <span class="text-success">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                style="vertical-align:middle;margin-right:4px;">
                                <circle cx="12" cy="12" r="10"
                                        fill="currentColor"/>
                            </svg>
                            Online
                        </span>

                    <?php else: ?>
                        <span class="text-muted">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                style="vertical-align:middle;margin-right:4px;">
                                <circle cx="12" cy="12" r="10"
                                        fill="currentColor"/>
                            </svg>
                            Offline
                        </span>
                    <?php endif; ?>
                </td>
                <td><small><?= date('M d, Y', strtotime($emp['created_at'])) ?></small></td>
                <td class="d-flex gap-1 flex-wrap">

                    <?php if ($emp['session_id'] === 'DEACTIVATED'): ?>

                        <!-- Approve/Activate -->
                        <button class="icon-action-btn icon-success" title="Activate"
                                onclick="swalGo('Re-activate Account', 'Re-activate <?= htmlspecialchars(addslashes($emp['full_name'])) ?>?', 'question', 'Activate', '#22c55e', '../action/activate_employee?id=<?= $emp['user_id'] ?>')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 13L9 17L19 7"/>
                            </svg>
                        </button>


                    <?php else: ?>

                        <?php if ($emp['is_verified'] == 0): ?>

                        <button class="btn btn-success btn-sm"
                                onclick="swalGo('Approve Account', 'Approve <?= htmlspecialchars(addslashes($emp['full_name'])) ?>?', 'question', 'Approve', '#22c55e', '../action/approve_employee?id=<?= $emp['user_id'] ?>')">

                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                style="vertical-align:middle;">
                                <path d="M5 13L9 17L19 7"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <?php endif; ?>


                        <?php if (!empty($emp['session_id'])): ?>

                        <!-- Force Logout -->
                        <button class="icon-action-btn icon-warning" title="Force Logout"
                                onclick="swalGo('Force Logout', 'Force logout <?= htmlspecialchars(addslashes($emp['full_name'])) ?>? Their session will end immediately.', 'warning', 'Force Logout', '#f59e0b', '../action/force_logout_employee?id=<?= $emp['user_id'] ?>')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                                <path d="M13 2L3 14H11L10 22L21 9H13L13 2Z"/>
                            </svg>
                        </button>

                        <?php endif; ?>

                        <!-- Deactivate -->
                        <button class="icon-action-btn icon-danger" title="Deactivate"
                                onclick="swalGo('Deactivate Account', 'Deactivate <?= htmlspecialchars(addslashes($emp['full_name'])) ?>? They will not be able to log in.', 'warning', 'Deactivate', '#ef4444', '../action/deactive_employee?id=<?= $emp['user_id'] ?>')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M8 8L16 16M16 8L8 16"/>
                            </svg>
                        </button>

                    <?php endif; ?>

                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     PANEL: RESIDENTS
══════════════════════════════════════════════ -->
<div id="panel-residents" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="approvals" data-group="accounts" onclick="showGroupTab('approvals',this,'accounts')">Approvals</button>
        <button class="ov-tab" data-tab="employees" data-group="accounts" onclick="showGroupTab('employees',this,'accounts')">Employees</button>
        <button class="ov-tab active" data-tab="residents" data-group="accounts" onclick="showGroupTab('residents',this,'accounts')">Residents</button>
        <button class="ov-tab" data-tab="profile" data-group="accounts" onclick="showGroupTab('profile',this,'accounts')">My Profile</button>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Registered Residents</h4>
        <input type="text" id="resSearch" class="form-control form-control-sm w-auto res-search" placeholder="Search name, email, barangay..." oninput="filterTable('resTable', this.value)">
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <small id="resPageInfo" class="text-muted"></small>
        <div id="resPagination" class="d-flex gap-2"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle" id="resTable">
            <thead class="table-dark">
                <tr>
                    <th>Resident ID</th><th>Name</th><th>Email</th><th>Barangay</th>
                    <th>Contact No.</th><th>Online</th><th>Joined</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($all_residents as $r): ?>
            <tr>
                <td>
                    <span style="font-size:13px; font-weight:700; letter-spacing:0.3px; color:#8E1B1B;">
                        <?= 'RES-' . date('Ymd', strtotime($r['created_at'])) . str_pad($r['user_id'],3,'0',STR_PAD_LEFT) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($r['full_name']) ?></td>
                <td><?= htmlspecialchars($r['email']) ?></td>
                <td><?= htmlspecialchars($r['barangay'] ?? '—') ?></td>
                <td><?= htmlspecialchars(format_phone_display($r['phone'] ?? '')) ?></td>
                <td>
                    <?php if ($r['session_id'] === 'DEACTIVATED'): ?>
                        <span class="text-danger">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                style="vertical-align:middle;margin-right:4px;">
                                <circle cx="12" cy="12" r="10"
                                        stroke="currentColor"
                                        stroke-width="2"/>
                                <path d="M8 8L16 16M16 8L8 16"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"/>
                            </svg>
                            Deactivated
                        </span>

                    <?php elseif (!empty($r['session_id'])): ?>
                        <span class="text-success">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                style="vertical-align:middle;margin-right:4px;">
                                <circle cx="12" cy="12" r="10"
                                        fill="currentColor"/>
                            </svg>
                            Online
                        </span>

                    <?php else: ?>
                        <span class="text-muted">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                style="vertical-align:middle;margin-right:4px;">
                                <circle cx="12" cy="12" r="10"
                                        fill="currentColor"/>
                            </svg>
                            Offline
                        </span>
                    <?php endif; ?>
                </td>
                <td><small><?= date('M d, Y', strtotime($r['created_at'])) ?></small></td>
                <td class="d-flex gap-1 flex-wrap">

                    <?php if ($r['session_id'] === 'DEACTIVATED'): ?>

                        <!-- Activate -->
                        <button class="icon-action-btn icon-success" title="Activate"
                                onclick="swalGo('Re-activate Account', 'Re-activate <?= htmlspecialchars(addslashes($r['full_name'])) ?>?', 'question', 'Activate', '#22c55e', '../action/activate_resident?id=<?= $r['user_id'] ?>')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 13L9 17L19 7"/>
                            </svg>
                        </button>

                    <?php else: ?>

                        <?php if (!empty($r['session_id'])): ?>

                        <!-- Force Logout -->
                        <button class="icon-action-btn icon-warning" title="Force Logout"
                                onclick="swalGo('Force Logout', 'Force logout <?= htmlspecialchars(addslashes($r['full_name'])) ?>? Their session will end immediately.', 'warning', 'Force Logout', '#f59e0b', '../action/force_logout_resident?id=<?= $r['user_id'] ?>')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                                <path d="M13 2L3 14H11L10 22L21 9H13L13 2Z"/>
                            </svg>
                        </button>

                        <?php endif; ?>

                        <!-- Deactivate -->
                        <button class="icon-action-btn icon-danger" title="Deactivate"
                                onclick="swalGo('Deactivate Account', 'Deactivate <?= htmlspecialchars(addslashes($r['full_name'])) ?>? They will not be able to log in.', 'warning', 'Deactivate', '#ef4444', '../action/deactive_resident?id=<?= $r['user_id'] ?>')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M8 8L16 16M16 8L8 16"/>
                            </svg>
                        </button>

                    <?php endif; ?>

                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     PANEL: ALL REQUESTS
══════════════════════════════════════════════ -->
<div id="panel-requests" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab active" data-tab="requests" data-group="system" onclick="showGroupTab('requests',this,'system')">Requests</button>
        <button class="ov-tab" data-tab="payments" data-group="system" onclick="showGroupTab('payments',this,'system')">Payments</button>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3 request-table-header">
        <h4 class="mb-0"><?= $req_status === '' ? 'All Requests' : htmlspecialchars($req_status . ' Requests', ENT_QUOTES, 'UTF-8') ?></h4>
        <div class="d-flex gap-2 align-items-center request-table-controls">
            <form method="GET" class="mb-0 d-flex gap-2">
                <input type="hidden" name="panel" value="requests">
                <input type="hidden" name="dashboard_year" value="<?= htmlspecialchars($selected_dashboard_year, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="dashboard_month" value="<?= htmlspecialchars($selected_dashboard_month, ENT_QUOTES, 'UTF-8') ?>">
                <input type="search" id="reqSearch" name="req_search" value="<?= htmlspecialchars($req_search, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm request-search" placeholder="Search resident, barangay, document..." aria-label="Search requests" maxlength="100" oninput="handleRequestSearch(this, 'req_filter')" onkeydown="submitRequestSearchOnEnter(event, this, 'req_filter')">
                <select name="req_filter" class="form-select form-select-sm w-auto request-filter" onchange="this.form.submit()">
                    <option value="today" <?= $req_filter==='today'?'selected':'' ?>>Today</option>
                    <option value="week"  <?= $req_filter==='week' ?'selected':'' ?>>Last 7 Days</option>
                    <option value="month" <?= $req_filter==='month'?'selected':'' ?>>This Month</option>
                    <option value="all"   <?= $req_filter==='all'  ?'selected':'' ?>>Last 100 Requests</option>
                    <option value="all_requests" <?= $req_filter==='all_requests'?'selected':'' ?>>All Requests</option>
                    <option value="dashboard" <?= $req_filter==='dashboard'?'selected':'' ?>>Selected Period (<?= htmlspecialchars($dashboard_filter_label, ENT_QUOTES, 'UTF-8') ?>)</option>
                </select>
                <select name="req_status" class="form-select form-select-sm w-auto request-filter" onchange="this.form.submit()" aria-label="Filter requests by status">
                    <option value="" <?= $req_status===''?'selected':'' ?>>All Statuses</option>
                    <option value="Pending" <?= $req_status==='Pending'?'selected':'' ?>>Pending</option>
                    <option value="Approved" <?= $req_status==='Approved'?'selected':'' ?>>Approved</option>
                    <option value="To be Claim" <?= $req_status==='To be Claim'?'selected':'' ?>>To be Claimed</option>
                    <option value="Rejected" <?= $req_status==='Rejected'?'selected':'' ?>>Rejected</option>
                    <option value="Completed" <?= $req_status==='Completed'?'selected':'' ?>>Completed</option>
                </select>
            </form>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <?php if ($serverPaginatedRequests): ?>
            <?php
                $req_first_result = $req_total > 0 ? (($req_page - 1) * $req_page_size) + 1 : 0;
                $req_last_result = min(($req_page - 1) * $req_page_size + $req_page_size, $req_total);
                $req_pagination_params = [
                    'panel' => 'requests',
                    'req_filter' => $req_filter,
                    'req_status' => $req_status,
                    'req_search' => $req_search,
                    'dashboard_year' => $dashboard_year_param === 'all' ? '' : $dashboard_year_param,
                    'dashboard_month' => $dashboard_month_param === 'all' ? '' : $dashboard_month_param,
                ];
            ?>
            <small id="reqPageInfo" class="text-muted">Showing <?= $req_first_result ?>–<?= $req_last_result ?> of <?= $req_total ?></small>
            <div id="reqPagination" class="d-flex gap-2">
                <?php if ($req_page > 1): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="?<?= htmlspecialchars(http_build_query($req_pagination_params + ['req_page' => $req_page - 1]), ENT_QUOTES, 'UTF-8') ?>">← Prev</a>
                <?php endif; ?>
                <span class="text-muted small px-2 align-self-center">Page <?= $req_page ?> of <?= $req_page_count ?></span>
                <?php if ($req_page < $req_page_count): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="?<?= htmlspecialchars(http_build_query($req_pagination_params + ['req_page' => $req_page + 1]), ENT_QUOTES, 'UTF-8') ?>">Next →</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <small id="reqPageInfo" class="text-muted"></small>
            <div id="reqPagination" class="d-flex gap-2"></div>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle" id="reqTable" <?= $serverPaginatedRequests ? 'data-server-paginated="true"' : '' ?>>
            <thead class="table-dark">
                <tr>
                    <th>Request No.</th>
                    <th>Resident</th>
                    <th>Barangay</th>
                    <th>Document</th>
                    <th>Status</th>
                    <th>Processed By</th>
                    <th>Payment</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($requests)): ?>
                <?php foreach ($requests as $i => $rq): 
                    $status = strtolower(trim($rq['status']));
                    $statusClass = strtolower(str_replace(' ', '-', (string) $rq['status']));
                    $requestDate = date('Ymd', strtotime($rq['created_at']));
                    $userId = $rq['user_id'] ?? '0';
                    $reqId = str_pad($rq['request_id'], 3, '0', STR_PAD_LEFT);
                    $displayRequestID = 'REQ' . $requestDate . $userId . '-' . $reqId;
                ?>
                <tr>
                    <td>
                        <span style="font-size:13px; font-weight:700; letter-spacing:0.3px; color:#8E1B1B;">
                            <?= htmlspecialchars($displayRequestID) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($rq['full_name']) ?></td>
                    <td><?= htmlspecialchars($rq['barangay'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($rq['document_type']) ?></td>
					<td><span class="status-pill status-<?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($rq['status']) ?></span></td>                    <td><?= htmlspecialchars($rq['processed_by_name'] ?? '—') ?></td>
                    <td data-label="Payment">
                        <span style="font-weight: 600; cursor: pointer;"
                            title="<?= !empty($rq['payment_reference']) ? 'Reference No: ' . htmlspecialchars($rq['payment_reference']) : 'No Reference Number' ?>">
							<?= htmlspecialchars(payment_method_label($rq['preferred_payment_method'] ?? '', $rq['payment_status'] ?? null)) ?>                        </span>
                        <?php if (strtolower(trim((string)($rq['payment_status'] ?? ''))) !== 'free'): ?>
                        <span style="font-size: 12px;">
                            (<?= htmlspecialchars(ucfirst($rq['payment_status'] ?? 'unpaid')) ?>)
                        </span>
                        <?php endif; ?>
                        <?php
                            $refundRequested = !empty($rq['refund_requested_at']);
                            $refundStatus = strtolower(trim((string)($rq['refund_status'] ?? 'none')));
                            $requestStatus = strtolower(trim((string)($rq['status'] ?? '')));

                            $refundRejectedByFinalStatus = $refundRequested && in_array($requestStatus, ['approved', 'completed'], true);

                            if ($refundRejectedByFinalStatus):
                        ?>
                            <br><span class="badge bg-danger" style="font-size:10px;">Refund Rejected</span>
                        <?php elseif ($refundRequested && $requestStatus === 'rejected' && !in_array($refundStatus, ['approved', 'denied', 'completed', 'failed'], true)): ?>
                            <br><span class="badge bg-warning text-dark" style="font-size:10px;">Refund Requested</span>
                        <?php elseif ($refundStatus === 'completed'): ?>
                            <br><span class="badge bg-success" style="font-size:10px;">Already Refunded</span>
                        <?php elseif ($refundStatus === 'failed' || $refundStatus === 'denied'): ?>
                            <br><span class="badge bg-danger" style="font-size:10px;">Refund Denied</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?= date('M d, Y', strtotime($rq['created_at'])) ?></small></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center text-muted py-3">No requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     PANEL: PAYMENTS
══════════════════════════════════════════════ -->
<div id="panel-payments" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab active" data-tab="requests" data-group="system" onclick="showGroupTab('requests',this,'system')">Requests</button>
        <button class="ov-tab" data-tab="payments" data-group="system" onclick="showGroupTab('payments',this,'system')">Payments</button>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Centralized Payments</h4>
            <p class="text-muted mb-0">Santa Rosa manages centralized payments. Barangays request manual payout for the collected amount.</p>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="panel" value="payments">
            <input type="month" name="pay_month" class="form-control form-control-sm" value="<?= htmlspecialchars($pay_month) ?>">
            <button class="btn btn-outline-dark btn-sm">View</button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4 col-12">
            <div class="stat-card" style="border-top:3px solid #3b82f6;">
                <small class="text-muted">Transactions</small>
                <div class="fs-5 fw-bold text-primary"><?= $city_payment_summary['transaction_count'] ?></div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="stat-card" style="border-top:3px solid #0d6efd;">
                <small class="text-muted">Gross Collected</small>
                <div class="fs-5 fw-bold text-primary"><?= money_format_php($city_payment_summary['gross_amount']) ?></div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="stat-card" style="border-top:3px solid #198754;">
                <small class="text-muted">Payable to Barangays (100%)</small>
                <div class="fs-5 fw-bold text-success"><?= money_format_php($city_payment_summary['net_amount']) ?></div>
            </div>
        </div>
    </div>

    <!-- Barangay Totals -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Barangay Totals for <?= htmlspecialchars($pay_month) ?></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="brgyTotalsTable" class="table table-sm table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Barangay</th>
                            <th>Transactions</th>
                            <th>Gross Amount</th>
                            <th>Barangay Net</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($payment_by_barangay)): ?>
                        <?php foreach ($payment_by_barangay as $pb): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($pb['barangay']) ?></strong></td>
                            <td><?= (int)$pb['transaction_count'] ?></td>
                            <td><?= money_format_php((float)$pb['gross_amount']) ?></td>
                            <td class="text-success fw-semibold"><?= money_format_php((float)$pb['net_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No payment transactions for this month.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">
                <span id="brgyTotalsInfo" class="text-muted small"></span>
                <div id="brgyTotalsPager" class="d-flex gap-1"></div>
            </div>
        </div>
    </div>


    <!-- Payout Requests -->
    <!-- <div class="card shadow-sm">
        <div class="card-header fw-semibold">Payout Requests</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="payoutRequestsTable" class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                        <tr>
                            <th>Barangay</th>
                            <th>Month</th>
                            <th>Requested By</th>
                            <th>Gross Amount</th>
                            <th>Net Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($payout_requests)): ?>
                        <?php foreach ($payout_requests as $po): ?>
                        <tr>
                            <td><?= htmlspecialchars($po['barangay']) ?></td>
                            <td><?= htmlspecialchars($po['month_year']) ?></td>
                            <td><?= htmlspecialchars($po['requested_by_name'] ?? '') ?></td>
                            <td><?= money_format_php((float)$po['gross_amount']) ?></td>
                            <td class="fw-semibold"><?= money_format_php((float)$po['net_amount']) ?></td>
                            <td><span class="badge bg-<?= $po['status'] === 'Approved' || $po['status'] === 'Released' ? 'success' : ($po['status'] === 'Rejected' ? 'danger' : 'warning') ?>"><?= htmlspecialchars($po['status']) ?></span></td>
                            <td>
                                <?php if ($po['status'] === 'Pending'): ?>
                                    <button class="btn btn-outline-success btn-sm" onclick="reviewPayout(<?= (int)$po['payout_id'] ?>, 'Approved')">Approve</button>
                                    <button class="btn btn-outline-danger btn-sm" onclick="reviewPayout(<?= (int)$po['payout_id'] ?>, 'Rejected')">Reject</button>
                                <?php elseif ($po['status'] === 'Approved'): ?>
                                    <button class="btn btn-outline-primary btn-sm" onclick="reviewPayout(<?= (int)$po['payout_id'] ?>, 'Released')">Mark Released</button>
                                <?php else: ?>
                                    <small class="text-muted"><?= htmlspecialchars($po['admin_note'] ?? '') ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-3">No payout requests found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">
                <span id="payoutRequestsInfo" class="text-muted small"></span>
                <div id="payoutRequestsPager" class="d-flex gap-1"></div>
            </div>
        </div>
    </div> -->
</div>

    <?php
    $doc_revenue_labels = json_encode(array_column($doc_revenue_summary, 'document_type'));
    $doc_revenue_values = json_encode(array_map('floatval', array_column($doc_revenue_summary, 'total_revenue')));
    ?>    

<div id="panel-top-documents" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="dashboard" data-group="overview" onclick="showOvTab('dashboard',this)">Dashboard</button>
        <button class="ov-tab" data-tab="barangay" data-group="overview" onclick="showOvTab('barangay',this)">Barangay Report</button>
        <button class="ov-tab active" data-tab="top-documents" data-group="overview" onclick="showOvTab('top-documents',this)">Top Documents</button>
    </div>

    <?php
        $selected_doc_year  = $docYear  ?? '';
        $selected_doc_month = $docMonth ?? '';
        $doc_month_disabled = ($selected_doc_year === '');
        $doc_is_all_mode    = ($selected_doc_year === '');
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Top Earning Documents</h4>
            <p class="text-muted mb-0">Revenue breakdown by document type.</p>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
            <input type="hidden" name="panel" value="top-documents">
            <select name="doc_year" id="docFilterYear" class="form-select form-select-sm w-auto" onchange="applyDocFilter()">
                <option value="">All Years</option>
                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?= $y ?>" <?= $selected_doc_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <select name="doc_month" id="docFilterMonth" class="form-select form-select-sm w-auto" onchange="applyDocFilter()" <?= $doc_month_disabled ? 'disabled' : '' ?>>
                <option value="">All Months</option>
                <?php $months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'];
                foreach ($months as $m => $name): ?>
                    <option value="<?= $m ?>" <?= $selected_doc_month == $m ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
            <select name="doc_limit" id="docFilterLimit" class="form-select form-select-sm w-auto" onchange="applyDocFilter()" aria-label="Number of top documents">
                <option value="5" <?= $docLimit === '5' ? 'selected' : '' ?>>Top 5</option>
                <option value="10" <?= $docLimit === '10' ? 'selected' : '' ?>>Top 10</option>
                <option value="all" <?= $docLimit === 'all' ? 'selected' : '' ?>>All</option>
            </select>
            <?php if ($doc_is_all_mode): ?>
                <a href="?panel=top-documents&doc_year=<?= date('Y') ?>&doc_month=<?= date('m') ?>&doc_limit=<?= urlencode($docLimit) ?>" class="btn btn-sm btn-outline-dark">Filter Today</a>
            <?php else: ?>
                <a href="?panel=top-documents&doc_year=&doc_month=&doc_limit=<?= urlencode($docLimit) ?>" class="btn btn-sm btn-outline-dark">Filter All</a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Revenue by Document Type</div>
        <div class="card-body">
            <canvas id="chartDocRevenue" style="height:<?= max(140, min(800, count($doc_revenue_summary) * 32 + 80)) ?>px;max-height:800px;"></canvas>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Revenue by Document Type</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Rank</th>
                            <th>Document Type</th>
                            <th class="text-center">Transactions</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($doc_revenue_summary)): ?>
                        <?php foreach ($doc_revenue_summary as $i => $dr): ?>
                        <tr>
                            <td><?= $i + 1 ?><?= $i === 0 ? ' <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16 9L20 5V16H4V5L6 7M8 9L12 5L14 7M4 19H20" stroke="#001A72" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>' : '' ?></td>
                            <td><strong><?= htmlspecialchars($dr['document_type']) ?></strong></td>
                            <td class="text-center"><?= (int)$dr['transaction_count'] ?></td>
                            <td class="fw-semibold text-success"><?= money_format_php($dr['total_revenue']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No paid transactions for this month.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     PANEL: FORCE LOGOUT
══════════════════════════════════════════════ -->
<div id="panel-force-logout" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="announcements" data-group="sys-settings" onclick="showGroupTab('announcements',this,'sys-settings')">Announcements</button>
        <button class="ov-tab" data-tab="doc-settings" data-group="sys-settings" onclick="showGroupTab('doc-settings',this,'sys-settings')">Document Settings</button>
        <button class="ov-tab" data-tab="chatbot" data-group="sys-settings" onclick="showGroupTab('chatbot',this,'sys-settings')">Chatbot</button>
        <button class="ov-tab" data-tab="maintenance" data-group="sys-settings" onclick="showGroupTab('maintenance',this,'sys-settings')">Maintenance</button>
        <button class="ov-tab" data-tab="data-management" data-group="sys-settings" onclick="showGroupTab('data-management',this,'sys-settings')">Data Management</button>
        <button class="ov-tab active" data-tab="force-logout" data-group="sys-settings" onclick="showGroupTab('force-logout',this,'sys-settings')">Force Logout</button>
        <button class="ov-tab" data-tab="logs" data-group="sys-settings" onclick="showGroupTab('logs',this,'sys-settings')">Logs</button>
    </div>
    <h4 class="mb-1">Force Logout</h4>
    <p class="text-muted mb-4">All currently active (online) sessions. Force-logout any user instantly.</p>

    <?php
    // Fetch all online users (session_id not null and not DEACTIVATED)
    $online_users = $pdo->query("
        SELECT user_id, full_name, email, barangay, user_type, session_id
        FROM users
        WHERE session_id IS NOT NULL AND session_id != 'DEACTIVATED' AND session_id != ''
        ORDER BY user_type, full_name
        LIMIT 500
    ")->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (empty($online_users)): ?>
    <div class="alert alert-success">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                xmlns="http://www.w3.org/2000/svg"
                style="vertical-align:middle;margin-right:6px;">
                <circle cx="12" cy="12" r="10"
                        stroke="#198754"
                        stroke-width="2"/>
                <path d="M8 12L11 15L16 9"
                    stroke="#198754"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"/>
            </svg> No users are currently online.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Barangay</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($online_users as $ou): ?>
            <tr>
                <td><strong><?= htmlspecialchars($ou['full_name']) ?></strong></td>
                <td><?= htmlspecialchars($ou['email']) ?></td>
                <td><?= htmlspecialchars($ou['barangay'] ?? '—') ?></td>
                <td>
                    <?php if($ou['user_type'] === 'employee') { ?>
                        <span class="badge bg-success">Barangay Employee</span>
                    <?php } elseif ($ou['user_type'] === 'resident') { ?>
                        <span class="badge bg-info">Resident</span>
                    <?php } elseif ($ou['user_type'] === 'city_administrator') { ?>
                        <span class="badge bg-primary">City Administrator</span>
                    <?php } ?>
                </td>
                <td>
                    <?php
                    if ($ou['user_type'] === 'employee') {
                        $logout_url = "../action/force_logout_employee?id={$ou['user_id']}";
                    } elseif ($ou['user_type'] === 'resident') {
                        $logout_url = "../action/force_logout_resident?id={$ou['user_id']}";
                    } else {
                        $logout_url = null;
                    }
                    ?>
                    <?php if ($logout_url): ?>
                        <button class="icon-action-btn icon-warning" title="Force Logout"
                                onclick="swalGo('Force Logout', 'Force logout <?= htmlspecialchars(addslashes($ou['full_name'])) ?>? Their session will end immediately.', 'warning', 'Force Logout', '#f59e0b', '<?= $logout_url ?>')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                                <path d="M13 2L3 14H11L10 22L21 9H13L13 2Z"/>
                            </svg>
                        </button>
                    <?php else: ?>
                    <span class="text-muted">No force-logout action</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-muted mt-2"><small>🟢 <?= count($online_users) ?> user(s) currently online.</small></p>
    <?php endif; ?>
</div>


<div id="panel-logs" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="announcements" data-group="sys-settings" onclick="showGroupTab('announcements',this,'sys-settings')">Announcements</button>
        <button class="ov-tab" data-tab="doc-settings" data-group="sys-settings" onclick="showGroupTab('doc-settings',this,'sys-settings')">Document Settings</button>
        <button class="ov-tab" data-tab="chatbot" data-group="sys-settings" onclick="showGroupTab('chatbot',this,'sys-settings')">Chatbot</button>
        <button class="ov-tab" data-tab="maintenance" data-group="sys-settings" onclick="showGroupTab('maintenance',this,'sys-settings')">Maintenance</button>
        <button class="ov-tab" data-tab="data-management" data-group="sys-settings" onclick="showGroupTab('data-management',this,'sys-settings')">Data Management</button>
        <button class="ov-tab" data-tab="force-logout" data-group="sys-settings" onclick="showGroupTab('force-logout',this,'sys-settings')">Force Logout</button>
        <button class="ov-tab active" data-tab="logs" data-group="sys-settings" onclick="showGroupTab('logs',this,'sys-settings')">Logs</button>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">System Logs</h4>
        <div>
            <select id="logFilterSelect" class="form-select form-select-sm w-auto">
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="week">Last 7 Days</option>
                <option value="month">This Month</option>
                <option value="all">All Time</option>
            </select>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <small id="logsPageInfo" class="text-muted"></small>
        <div id="logsPagination" class="d-flex gap-2"></div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead class="table-dark">
                <tr><th>Log ID</th><th>User</th><th>Role</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th><th>Time</th></tr>
            </thead>
            <tbody id="logsTbody">
                <tr><td colspan="8" class="text-center text-muted py-3">Loading logs...</td></tr>
            </tbody>
        </table>
    </div>
</div><!-- /panel-logs -->

<!-- ══════════════════════════════════════════════
     PANEL: DATA MANAGEMENT (City Admin only)
══════════════════════════════════════════════ -->
<div id="panel-data-management" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="announcements" data-group="sys-settings" onclick="showGroupTab('announcements',this,'sys-settings')">Announcements</button>
        <button class="ov-tab" data-tab="doc-settings" data-group="sys-settings" onclick="showGroupTab('doc-settings',this,'sys-settings')">Document Settings</button>
        <button class="ov-tab" data-tab="chatbot" data-group="sys-settings" onclick="showGroupTab('chatbot',this,'sys-settings')">Chatbot</button>
        <button class="ov-tab" data-tab="maintenance" data-group="sys-settings" onclick="showGroupTab('maintenance',this,'sys-settings')">Maintenance</button>
        <button class="ov-tab active" data-tab="data-management" data-group="sys-settings" onclick="showGroupTab('data-management',this,'sys-settings')">Data Management</button>
        <button class="ov-tab" data-tab="force-logout" data-group="sys-settings" onclick="showGroupTab('force-logout',this,'sys-settings')">Force Logout</button>
        <button class="ov-tab" data-tab="logs" data-group="sys-settings" onclick="showGroupTab('logs',this,'sys-settings')">Logs</button>
    </div>
    <h4 class="mb-1">Data Management</h4>
    <p class="text-muted mb-4">Export, import, and cloud backup of eRequest Mo data. <strong>City Administrator access only.</strong></p>

<div class="row g-4">

    <!-- EXPORT -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center p-4">

                <div style="margin-bottom:12px;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                         xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 16V3M12 3L7 8M12 3L17 8"
                              stroke="#198754" stroke-width="2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M5 13V19C5 20.1 5.9 21 7 21H17C18.1 21 19 20.1 19 19V13"
                              stroke="#198754" stroke-width="2"
                              stroke-linecap="round"/>
                    </svg>
                </div>

                <h5 class="fw-bold">Export All Requests</h5>
                <p class="text-muted" style="font-size:13px;">
                    Download a full CSV of all barangay requests across the entire city.
                </p>

                <a href="../action/export_requests"
                   class="btn btn-outline-success w-100 mt-2">
                    Download CSV
                </a>
            </div>
        </div>
    </div>


    <!-- IMPORT -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center p-4">

                <div style="margin-bottom:12px;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                         xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 8V21M12 21L7 16M12 21L17 16"
                              stroke="#0d6efd" stroke-width="2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M5 11V5C5 3.9 5.9 3 7 3H17C18.1 3 19 3.9 19 5V11"
                              stroke="#0d6efd" stroke-width="2"
                              stroke-linecap="round"/>
                    </svg>
                </div>

                <h5 class="fw-bold">Import Requests (CSV)</h5>
                <p class="text-muted" style="font-size:13px;">
                    Bulk-upload requests from a CSV file. Duplicates are automatically skipped.
                </p>

                <form method="POST" action="../action/import_requests" enctype="multipart/form-data">
                    <div class="mb-2">
                        <input type="file" name="csv_file"
                               class="form-control form-control-sm"
                               accept=".csv" required>
                    </div>

                    <button type="submit" class="btn btn-outline-primary w-100">
                        Import CSV
                    </button>
                </form>

            </div>
        </div>
    </div>


    <!-- CLOUD BACKUP -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center p-4">

                <div style="margin-bottom:12px;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 18H17C19.2 18 21 16.2 21 14C21 11.8 19.2 10 17 10C16.6 6.6 13.8 4 10.5 4C7.5 4 5 6.5 5 9.5C3.3 10.1 2 11.7 2 13.5C2 15.7 4 18 7 18Z"
                              stroke="#0dcaf0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <h5 class="fw-bold">Cloud Backup</h5>
                <p class="text-muted" style="font-size:13px;">
                    Save all request data as a timestamped, ZIP-compressed CSV file.
                </p>

                <button type="button" class="btn btn-outline-info w-100"
                        data-bs-toggle="modal" data-bs-target="#cloudBackupModal">
                    Backup to AWS S3
                </button>
                <small class="d-block text-muted mt-2" style="font-size:12px;">Choose which data to include before uploading.</small>

            </div>
        </div>
    </div>

</div>

</div>

<!-- ══════════════════════════════════════════════
     MODAL: CLOUD BACKUP — SELECT SECTIONS
══════════════════════════════════════════════ -->
<div class="modal fade" id="cloudBackupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cloud Backup — Select Data to Save</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <p class="text-muted mb-3" style="font-size:13px;">
					Choose which data to include in the cloud backup. The items already checked below are the recommended ones (Full Backup / Priority), but you can still adjust them based on your needs.
                </p>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-dark d-flex align-items-center gap-1" onclick="cbSelectPreset('priority')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Backup Priority
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="cbSelectPreset('all')">Select All</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="cbSelectPreset('none')">Clear All</button>
                </div>

                <div class="row g-3">

                    <!-- Requests & Transactions -->
                    <div class="col-md-6">
                        <label class="prereq-config-card d-flex gap-3 align-items-start mb-0" style="cursor:pointer;">
                            <input type="checkbox" class="form-check-input mt-1 cb-section" value="requests" checked style="flex-shrink:0;">
                            <span>
                                <span class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#8E1B1B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="6" y="4" width="12" height="16" rx="2"/>
                                        <path d="M9 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1"/>
                                        <path d="M9 10h6M9 14h6M9 18h3"/>
                                    </svg>
                                    <strong>Requests & Transactions</strong>
                                    <span class="badge bg-secondary" style="font-size:10px;">Priority</span>
                                </span>
                                <small class="text-muted d-block">requests, payment_transactions, request_attachments</small>
                            </span>
                        </label>
                    </div>

                    <!-- Payouts -->
                    <div class="col-md-6">
                        <label class="prereq-config-card d-flex gap-3 align-items-start mb-0" style="cursor:pointer;">
                            <input type="checkbox" class="form-check-input mt-1 cb-section" value="payouts" checked style="flex-shrink:0;">
                            <span>
                                <span class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#198754" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="6" width="20" height="12" rx="2"/>
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="M6 6v.01M18 18v-.01"/>
                                    </svg>
                                    <strong>Payouts</strong>
                                    <span class="badge bg-secondary" style="font-size:10px;">Priority</span>
                                </span>
                                <small class="text-muted d-block">payout_requests</small>
                            </span>
                        </label>
                    </div>

                    <!-- Users & Officials -->
                    <div class="col-md-6">
                        <label class="prereq-config-card d-flex gap-3 align-items-start mb-0" style="cursor:pointer;">
                            <input type="checkbox" class="form-check-input mt-1 cb-section" value="accounts" checked style="flex-shrink:0;">
                            <span>
                                <span class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#0d6efd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    <strong>Users & Officials</strong>
                                    <span class="badge bg-secondary" style="font-size:10px;">Priority</span>
                                </span>
                                <small class="text-muted d-block">users, barangay_officials, official_update_requests</small>
                            </span>
                        </label>
                    </div>

                    <!-- Chatbot -->
                    <div class="col-md-6">
                        <label class="prereq-config-card d-flex gap-3 align-items-start mb-0" style="cursor:pointer;">
                            <input type="checkbox" class="form-check-input mt-1 cb-section" value="chatbot" checked style="flex-shrink:0;">
                            <span>
                                <span class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                    </svg>
                                    <strong>Chatbot</strong>
                                    <span class="badge bg-secondary" style="font-size:10px;">Priority</span>
                                </span>
                                <small class="text-muted d-block">chatbot_settings, chatbot_responses</small>
                            </span>
                        </label>
                    </div>

                    <!-- Document Configuration -->
                    <div class="col-md-6">
                        <label class="prereq-config-card d-flex gap-3 align-items-start mb-0" style="cursor:pointer;">
                            <input type="checkbox" class="form-check-input mt-1 cb-section" value="docsettings" checked style="flex-shrink:0;">
                            <span>
                                <span class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#212529" stroke-width="2" stroke-linejoin="round">
                                        <path d="M6 2H14L20 8V21C20 21.6 19.6 22 19 22H6C5.4 22 5 21.6 5 21V3C5 2.4 5.4 2 6 2Z"/>
                                        <path d="M14 2V8H20"/>
                                        <path d="M8 13H16M8 17H16" stroke-linecap="round"/>
                                    </svg>
                                    <strong>Document Configuration</strong>
                                    <span class="badge bg-secondary" style="font-size:10px;">Priority</span>
                                </span>
                                <small class="text-muted d-block">document_settings, barangay_document_settings, document_prerequisites</small>
                            </span>
                        </label>
                    </div>

                    <!-- Logs & Activity (optional) -->
                    <div class="col-md-6">
                        <label class="prereq-config-card d-flex gap-3 align-items-start mb-0" style="cursor:pointer;">
                            <input type="checkbox" class="form-check-input mt-1 cb-section" value="logs" style="flex-shrink:0;">
                            <span>
                                <span class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"/>
                                        <path d="M12 6v6l4 2"/>
                                    </svg>
                                    <strong>Logs & Activity</strong>
                                    <span class="badge bg-light text-muted border" style="font-size:10px;">Optional</span>
                                </span>
                                <small class="text-muted d-block">system_logs, notifications, announcements, otp_requests</small>
                            </span>
                        </label>
                    </div>

                </div>

                <div id="cloudBackupS3Result" class="mt-3" style="font-size:13px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="cloudBackupSubmitBtn" class="btn btn-outline-info" onclick="submitCloudBackup()">
                    Backup Selected to AWS S3
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Sections considered "priority" for the Full Backup (Priority) quick-select.
// Keep this list in sync with the section checkboxes above.
const CB_PRIORITY_SECTIONS = ['requests', 'payouts', 'accounts', 'chatbot', 'docsettings'];

function cbSelectPreset(mode) {
    document.querySelectorAll('.cb-section').forEach(cb => {
        if (mode === 'all') {
            cb.checked = true;
        } else if (mode === 'none') {
            cb.checked = false;
        } else if (mode === 'priority') {
            cb.checked = CB_PRIORITY_SECTIONS.includes(cb.value);
        }
    });
}

function submitCloudBackup() {
    const selected = Array.from(document.querySelectorAll('.cb-section:checked')).map(cb => cb.value);
    const resultEl = document.getElementById('cloudBackupS3Result');
    const btn = document.getElementById('cloudBackupSubmitBtn');

    if (selected.length === 0) {
        resultEl.innerHTML = '<span class="text-danger">Pumili muna ng kahit isang section bago mag-backup.</span>';
        return;
    }

    const originalLabel = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Backing up…';
    resultEl.innerHTML = '<span class="text-muted">Uploading to AWS S3…</span>';

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');

    fetch('../action/cloud_backup_s3', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            sections: selected,
            csrf_token: csrfMeta ? csrfMeta.content : ''
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            resultEl.innerHTML = '<span class="text-success">✅ ' + data.message +
                (data.link ? ' — <a href="' + data.link + '" target="_blank" rel="noopener">Open file</a>' : '') +
                '</span>';
        } else {
            resultEl.innerHTML = '<span class="text-danger">❌ ' + data.message + '</span>';
        }
    })
    .catch(err => {
        resultEl.innerHTML = '<span class="text-danger">❌ Request failed: ' + err.message + '</span>';
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = originalLabel;
    });
}
</script>

<!-- ══════════════════════════════════════════════
     PANELS: BARANGAY OFFICIALS
══════════════════════════════════════════════ -->
<?php
$barangays_list = ['Aplaya', 'Balibago', 'Caingin','Dila', 'Dita', 'Don Jose', 'Ibaba', 'Kanluran', 'Labas', 'Macabling', 'Malitlit', 'Malusak', 'Market Area', 'Pooc', 'Pulong Santa Cruz', 'Santo Domingo', 'Sinalhan', 'Tagapo'];

try {
    $stmt_officials = $pdo->query("SELECT id, barangay, position, full_name FROM barangay_officials ORDER BY FIELD(position, 'Punong Barangay', 'Sangguniang Barangay Member', 'SK Chairperson', 'Barangay Secretary', 'Barangay Treasurer') ASC");
    $all_officials = $stmt_officials->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_officials = [];
}

// Group officials by barangay
$grouped_officials = [];
foreach ($all_officials as $off) {
    $grouped_officials[$off['barangay']][] = $off;
}

// Build the tab bar ONCE (shared across all barangay panels + pending edits)
// instead of re-rendering all 19 buttons inside every one of the 19 panels.
$officials_tab_bar = '<div class="ov-tabs mb-4" style="flex-wrap: wrap;">';
foreach ($barangays_list as $i => $tab_brgy) {
    $tab_id = 'officials-' . str_replace(' ', '-', $tab_brgy);
    // First barangay tab starts active, matching the previously-default visible panel.
    $active_cls = ($i === 0) ? 'active' : '';
    $officials_tab_bar .= '<button class="ov-tab ' . $active_cls . '" data-tab="' . $tab_id . '" data-group="officials" onclick="showGroupTab(\'' . $tab_id . '\',this,\'officials\')">'
        . htmlspecialchars($tab_brgy) . '</button>';
}
$officials_tab_bar .= '<button class="ov-tab bg-warning text-dark border-warning" data-tab="officials-requests" data-group="officials" onclick="showGroupTab(\'officials-requests\',this,\'officials\')">'
    . 'Pending Edits ' . ($stat_pending_edits > 0 ? "($stat_pending_edits)" : '') . '</button>';
$officials_tab_bar .= '</div>';
?>

<?php foreach ($barangays_list as $brgy): ?>
<div id="panel-officials-<?= htmlspecialchars(str_replace(' ', '-', $brgy)) ?>" class="panel">

    <?= $officials_tab_bar ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Officials — <?= htmlspecialchars($brgy) ?></h4>
            <p class="text-muted mb-0">Manage and view the official positions for Barangay <?= htmlspecialchars($brgy) ?>.</p>
        </div>
        <button class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#editBulkModal-<?= htmlspecialchars(str_replace(' ', '-', $brgy)) ?>">
            Edit Officials
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Position</th>
                    <th>Full Name</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $brgy_officials = $grouped_officials[$brgy] ?? [];
                if (empty($brgy_officials)): ?>
                <tr>
                    <td colspan="3" class="text-center text-muted py-3">No officials recorded for this barangay.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($brgy_officials as $official): ?>
                    <tr>
                        <td>
                            <span class="badge bg-danger"><?= htmlspecialchars($official['position']) ?></span>
                        </td>
                        <td><strong class="badge bg-warning"><?= htmlspecialchars($official['full_name']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Officials Modal for <?= htmlspecialchars($brgy) ?> -->
<div class="modal fade" id="editBulkModal-<?= htmlspecialchars(str_replace(' ', '-', $brgy)) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" action="../action/edit_official_bulk">
            <div class="modal-header">
                <h5 class="modal-title">Edit Officials — <?= htmlspecialchars($brgy) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <input type="hidden" name="barangay" value="<?= htmlspecialchars($brgy) ?>">
                <?php if (empty($brgy_officials)): ?>
                    <p class="text-muted">No officials recorded. Please insert them directly in the database.</p>
                <?php else: ?>
                    <div class="row g-3">
                    <?php foreach ($brgy_officials as $off): ?>
                        <div class="col-md-6">
                            <div class="card p-3 shadow-sm h-100 border-0">
                                <h6><span class="badge bg-secondary mb-3"><?= htmlspecialchars($off['position']) ?></span></h6>
                                <input type="hidden" name="official_ids[]" value="<?= $off['id'] ?>">
                                <div class="mb-2">
                                    <label class="form-label mb-1" style="font-size:12px; font-weight:600;">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" name="full_names[]" value="<?= htmlspecialchars($off['full_name']) ?>" required>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                <?php if (!empty($brgy_officials)): ?>
                <button type="submit" class="btn btn-outline-primary">Save All Changes</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- PANEL: PENDING EDITS -->
<div id="panel-officials-requests" class="panel">
    <?= $officials_tab_bar ?>

    <h4 class="mb-1">Pending Edit Requests</h4>
    <p class="text-muted mb-4">Review and approve changes to barangay officials proposed by barangay employees.</p>
    
    <?php
    $stmt_pending = $pdo->query("
        SELECT r.*, o.barangay, o.position, o.full_name as current_name,
               u.firstname, u.lastname
        FROM official_update_requests r
        JOIN barangay_officials o ON r.official_id = o.id
        JOIN users u ON r.requested_by = u.user_id
        WHERE r.status = 'Pending'
        ORDER BY r.created_at ASC
    ");
    $pending_requests = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Requested By</th>
                    <th>Barangay & Position</th>
                    <th>Current Details</th>
                    <th>Proposed Details</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pending_requests)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No pending edit requests.</td></tr>
                <?php else: ?>
                    <?php foreach ($pending_requests as $req): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($req['firstname'] . ' ' . $req['lastname']) ?></strong><br>
                            <small class="text-muted"><?= date('M d, Y h:i A', strtotime($req['created_at'])) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($req['barangay']) ?></span><br>
                            <?= htmlspecialchars($req['position']) ?>
                        </td>
                        <td>
                            Name: <strong><?= htmlspecialchars($req['current_name']) ?></strong>
                        </td>
                        <td class="bg-light">
                            Name: <strong class="text-primary"><?= htmlspecialchars($req['proposed_name']) ?></strong>
                        </td>
                        <td>
                            <form action="../action/process_official_edit" method="POST" class="d-flex gap-2">
                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endforeach; ?>

<!-- ══════════════════════════════════════════════
     PANEL: DOCUMENT SETTINGS
══════════════════════════════════════════════ -->
<div id="panel-doc-settings" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="announcements" data-group="sys-settings" onclick="showGroupTab('announcements',this,'sys-settings')">Announcements</button>
        <button class="ov-tab active" data-tab="doc-settings" data-group="sys-settings" onclick="showGroupTab('doc-settings',this,'sys-settings')">Document Settings</button>
        <button class="ov-tab" data-tab="chatbot" data-group="sys-settings" onclick="showGroupTab('chatbot',this,'sys-settings')">Chatbot</button>
        <button class="ov-tab" data-tab="maintenance" data-group="sys-settings" onclick="showGroupTab('maintenance',this,'sys-settings')">Maintenance</button>
        <button class="ov-tab" data-tab="data-management" data-group="sys-settings" onclick="showGroupTab('data-management',this,'sys-settings')">Data Management</button>
        <button class="ov-tab" data-tab="force-logout" data-group="sys-settings" onclick="showGroupTab('force-logout',this,'sys-settings')">Force Logout</button>
        <button class="ov-tab" data-tab="logs" data-group="sys-settings" onclick="showGroupTab('logs',this,'sys-settings')">Logs</button>
    </div>
    <h4 class="mb-1">Document Prerequisites & Fees</h4>
    <p class="text-muted mb-4">Configure prerequisites and set the price for each document (0 means free for residents).</p>

    <?php $lastUpdatedDoc = $_SESSION['last_updated_doc'] ?? null; ?>

    <div class="doc-settings-grid mb-4">
    <?php foreach ($doc_types as $dtype): ?>
    <?php
        $docSetting   = $document_settings[$dtype] ?? ['price' => 0.0, 'fee_amount' => 0.0, 'is_free' => true];
        $dtypeHash    = md5($dtype);
        $currentPrice = (float) ($docSetting['price'] ?? $docSetting['fee_amount'] ?? 0.0);
        $isRecent     = ($lastUpdatedDoc !== null && $lastUpdatedDoc === $dtype);
    ?>
    <div class="prereq-config-card<?= $isRecent ? ' doc-recent' : '' ?>">

        <!-- ONE FORM: rename + price + add new prerequisites -->
        <form action="../action/save_prerequisites" method="POST" class="doc-full-save-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="prereq_action" value="save_document_full">
            <input type="hidden" name="original_document_type" value="<?= htmlspecialchars($dtype) ?>">

            <div class="row g-2 mb-2">
                <div class="col-8">
                    <label class="form-label mb-1" style="font-size:11px;font-weight:600;">Document Name</label>
                    <input type="text" name="new_document_type" value="<?= htmlspecialchars($dtype) ?>" required class="form-control form-control-sm">
                </div>
                <div class="col-4">
                    <label class="form-label mb-1" style="font-size:11px;font-weight:600;">Price (PHP)</label>
                    <input type="number" name="price" min="0" step="0.01" value="<?= number_format($currentPrice, 2, '.', '') ?>" class="form-control form-control-sm">
                </div>
            </div>
            <div class="form-check form-switch mt-1 mb-2">
                <input class="form-check-input" type="checkbox" name="pwd_senior_enabled" id="pwd_<?= $dtypeHash ?>" value="1" <?= !empty($docSetting['pwd_senior_enabled']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="pwd_<?= $dtypeHash ?>" style="font-size:12px;font-weight:600;">
                    🏷️ Enable PWD/Senior 20% Discount
                </label>
            </div>
            <div class="mb-2">
                <label class="form-label mb-1" style="font-size:11px;font-weight:600;">Document Validity (months)</label>
                <input type="number" name="validity_months" min="0" step="1"
                       value="<?= (int)($docSetting['validity_months'] ?? 0) ?>"
                       class="form-control form-control-sm" placeholder="0 = no expiry">
                <small class="text-muted" style="font-size:10px;">0 means the document never expires.</small>
            </div>

            <!-- Prerequisites: tag + inline delete, single row (no duplicate list) -->
            <div class="d-flex flex-wrap mb-1">
                <?php if (!empty($all_prereqs[$dtype])): ?>
                    <?php foreach ($all_prereqs[$dtype] as $p): ?>
                        <div class="prereq-tag">
                            <?= htmlspecialchars($p['prereq_name']) ?>
                            <?php if ($p['is_required']): ?><span class="required-dot">*</span><?php endif; ?>
                            <button type="button"
                                    onclick="Swal.fire({
                                        text: 'Remove prerequisite: <?= htmlspecialchars(addslashes($p['prereq_name'])) ?>?',
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonText: 'Yes, remove',
                                        cancelButtonText: 'Cancel'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            deletePrereq(<?= (int)$p['id'] ?>, '<?= htmlspecialchars(addslashes($p['prereq_name'])) ?>');
                                        }
                                    })"
                                    class="remove-btn" title="Remove">&times;</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-prereq-msg mb-1">No prerequisites yet.</p>
                <?php endif; ?>
            </div>

            <!-- Dynamic: add as many new prerequisites as needed before saving -->
            <div id="newRows-<?= $dtypeHash ?>" class="mb-2"></div>
            <button type="button" class="btn btn-sm btn-outline-dark mb-2" onclick="addPrereqRow('<?= $dtypeHash ?>')">
                + Add Prerequisite
            </button>

            <div class="d-flex justify-content-between align-items-center mt-2">
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="Swal.fire({
                            text: 'Remove this document type and its fee settings?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, remove',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                submitRemoveDoc('<?= htmlspecialchars(addslashes($dtype)) ?>');
                            }
                        })">
                    Remove
                </button>
                <button type="submit" class="btn btn-outline-primary btn-sm">Save Changes</button>
            </div>
        </form>

    </div>
    <?php endforeach; ?>
    </div>

    <div class="prereq-config-card" style="max-width:480px;">
        <h6>Add New Document Type</h6>
        <form action="../action/save_prerequisites" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="prereq_action" value="add_document_type_full">

            <div class="row g-2 mb-3">
                <div class="col-md-8">
                    <label class="form-label mb-1" style="font-size:12px;font-weight:600;">Document Type Name</label>
                    <input type="text" name="document_type" placeholder="e.g. Barangay Business Clearance" required class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1" style="font-size:12px;font-weight:600;">Price (PHP)</label>
                    <input type="number" name="price" min="0" step="0.01" value="0.00" class="form-control form-control-sm" placeholder="0.00 = Free">
                </div>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="pwd_senior_enabled" id="pwd_newdoc" value="1">
                <label class="form-check-label" for="pwd_newdoc" style="font-size:12px;font-weight:600;">
                    🏷️ Enable PWD/Senior 20% Discount
                </label>
            </div>
            <div class="mb-3">
                <label class="form-label mb-1" style="font-size:12px;font-weight:600;">Document Validity (months)</label>
                <input type="number" name="validity_months" min="0" step="1" value="0" class="form-control form-control-sm" placeholder="0 = no expiry">
            </div>

            <label class="form-label mb-1" style="font-size:12px;font-weight:600;">Prerequisites</label>
            <div id="newRows-newdoc" class="mb-2"></div>
            <button type="button" class="btn btn-sm btn-outline-dark mb-3" onclick="addPrereqRow('newdoc')">
                + Add Prerequisite Field
            </button>

            <div class="text-end">
                <button type="submit" class="btn btn-outline-success btn-sm">+ Create Document Type</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden helper form for "Remove Document Type" button (kept outside the main save form so it doesn't fight over one <form> submit target) -->
<form id="removeDocForm" action="../action/save_prerequisites" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="prereq_action" value="remove_document_type">
    <input type="hidden" name="document_type" id="removeDocFormType" value="">
</form>

<script>
function submitRemoveDoc(docType) {
    document.getElementById('removeDocFormType').value = docType;
    document.getElementById('removeDocForm').submit();
}

let prereqRowCounter = {};
function addPrereqRow(key) {
    if (!prereqRowCounter[key]) prereqRowCounter[key] = 0;
    const idx = prereqRowCounter[key]++;
    const container = document.getElementById('newRows-' + key);
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 align-items-center mb-2 new-prereq-row';
    row.innerHTML = `
        <input type="text" name="new_prereqs[${idx}][name]" class="form-control form-control-sm" placeholder="e.g. DTI Registration" style="flex:1;">
        <label style="font-size:12px;display:flex;align-items:center;gap:4px;white-space:nowrap;">
            <input type="checkbox" name="new_prereqs[${idx}][required]" value="1" checked> Required
        </label>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.new-prereq-row').remove()">&times;</button>
    `;
    container.appendChild(row);
}
</script>

<form id="deletePrereqForm" action="../action/save_prerequisites" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="prereq_action" value="delete">
    <input type="hidden" name="prereq_id" id="deletePrereqId" value="">
</form>

<script>
function deletePrereq(id, name) {
    document.getElementById('deletePrereqId').value = id;
    document.getElementById('deletePrereqForm').submit();
}
</script>
    
<!-- ══════════════════════════════════════════════
     PANEL: CHATBOT INTEGRATION
══════════════════════════════════════════════ -->
<div id="panel-chatbot" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="announcements" data-group="sys-settings" onclick="showGroupTab('announcements',this,'sys-settings')">Announcements</button>
        <button class="ov-tab" data-tab="doc-settings" data-group="sys-settings" onclick="showGroupTab('doc-settings',this,'sys-settings')">Document Settings</button>
        <button class="ov-tab active" data-tab="chatbot" data-group="sys-settings" onclick="showGroupTab('chatbot',this,'sys-settings')">Chatbot</button>
        <button class="ov-tab" data-tab="maintenance" data-group="sys-settings" onclick="showGroupTab('maintenance',this,'sys-settings')">Maintenance</button>
        <button class="ov-tab" data-tab="data-management" data-group="sys-settings" onclick="showGroupTab('data-management',this,'sys-settings')">Data Management</button>
        <button class="ov-tab" data-tab="force-logout" data-group="sys-settings" onclick="showGroupTab('force-logout',this,'sys-settings')">Force Logout</button>
        <button class="ov-tab" data-tab="logs" data-group="sys-settings" onclick="showGroupTab('logs',this,'sys-settings')">Logs</button>
    </div>
    <h4 class="mb-1">Chatbot Integration</h4>
    <p class="text-muted mb-4">Control and monitor the chatbot assistant available on the public site. Jazmine is served by <code>/action/support_widget</code> and the widget is loaded from <code>js/support-widget.js</code>.</p>
    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm p-3 h-100">
                <h6 class="mb-3">Integration Status</h6>
                <p class="mb-3">The chatbot is currently available to users on the public landing page as a tap-driven menu, plus tracking-code lookup and document-requirement lookups.</p>
                <div class="alert alert-success py-2">Enabled on <strong>eRequest</strong></div>
                <a href="../erequest-mo" target="_blank" class="btn btn-sm btn-outline-primary">Open Public Chat Preview</a>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card shadow-sm p-3 h-100">
                <h6 class="mb-3">Chatbot Settings</h6>
                <form action="../action/save_cb_settings" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="chatbotEnabled" name="chatbot_enabled" value="1" <?= $chatbot_enabled ? 'checked' : '' ?> <?= $maintenance_mode ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="chatbotEnabled">Chatbot enabled</label>
                    </div>
                    <?php if ($maintenance_mode): ?>
                    <div class="alert alert-warning py-2">
                        Chatbot is disabled automatically while maintenance mode is active and cannot be enabled until maintenance is turned off.
                    </div>
                    <?php endif; ?>
                    <label for="chatbotDisabledMessage" class="form-label">Disabled reply message</label>
                    <textarea id="chatbotDisabledMessage" name="chatbot_disabled_message" rows="4" class="form-control mb-3"><?= htmlspecialchars($chatbot_disabled_message) ?></textarea>
                    <input type="hidden" name="redirect_panel" value="chatbot">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Save Chatbot Settings</button>
                </form>
            </div>
        </div>

        <!-- ── Chatbot Menu & Responses ── -->
        <div class="col-12">
            <div class="card shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Chatbot Menu & Responses</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openChatbotModal()">+ Add Button</button>
                </div>

                <?php if (empty($chatbot_responses)): ?>
                    <div class="alert alert-info">No chatbot buttons yet.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Label</th>
                                <th>Intent Code</th>
                                <th>Parent Menu</th>
                                <th>Source</th>
                                <th class="text-center">Order</th>
                                <th class="text-center">Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($chatbot_responses as $cr): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cr['label'] ?? '—') ?></strong></td>
                                <td><code><?= htmlspecialchars($cr['intent_code'] ?? '—') ?></code></td>
                                <td><?= $cr['parent_intent'] ? '<code>' . htmlspecialchars($cr['parent_intent']) . '</code>' : '<em class="text-muted">Main Menu</em>' ?></td>
                                <td>
                                    <?php if (!empty($cr['linked_document_type'])): ?>
                                        <span class="badge bg-info text-dark">Linked: <?= htmlspecialchars($cr['linked_document_type']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Custom text</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= (int) $cr['sort_order'] ?></td>
                                <td class="text-center">
                                    <?= $cr['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?>
                                </td>
                                <td class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                        onclick='openChatbotModal(<?= htmlspecialchars(json_encode($cr), ENT_QUOTES, "UTF-8") ?>)'>Edit</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="swalGo('Delete Button', 'Delete &quot;<?= htmlspecialchars(addslashes($cr['label'] ?? 'this button')) ?>&quot;? This will also remove it from any menu it appears in.', 'warning', 'Delete', '#dc3545', '../action/save_cb_response', { response_action: 'delete', response_id: <?= (int) $cr['id'] ?> })">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Add/Edit Chatbot Button Modal -->
<div class="modal fade" id="chatbotResponseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="chatbotResponseForm" action="../action/save_cb_response" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="response_action" value="save">
            <input type="hidden" name="response_id" id="cbResponseId" value="0">

            <div class="modal-header">
                <h5 class="modal-title" id="cbModalTitle">Add Chatbot Button</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body bg-light">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Button Label <span class="text-danger">*</span></label>
                        <input type="text" name="label" id="cbLabel" class="form-control" placeholder="e.g. 🏢 Business Permit Info" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Intent Code <small class="text-muted">(optional — auto-generated if blank)</small></label>
                        <input type="text" name="intent_code" id="cbIntentCode" class="form-control" placeholder="e.g. BUSINESS_INFO">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Parent Menu</label>
                        <select name="parent_intent" id="cbParentIntent" class="form-select">
                            <option value="">— Main Menu (root) —</option>
                            <?php foreach ($chatbot_responses as $root): ?>
                                <?php if (empty($root['parent_intent']) && !empty($root['intent_code'])): ?>
                                    <option value="<?= htmlspecialchars($root['intent_code']) ?>">
                                        <?= htmlspecialchars($root['label']) ?> (<?= htmlspecialchars($root['intent_code']) ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" id="cbSortOrder" class="form-control" value="0">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="cbIsActive" checked>
                            <label class="form-check-label" for="cbIsActive">Active</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Reply Source</label>
                        <div class="btn-group d-block" role="group">
                            <input type="radio" class="btn-check" name="reply_mode" id="cbModeCustom" value="custom" checked onchange="toggleCbReplyMode()">
                            <label class="btn btn-outline-dark btn-sm" for="cbModeCustom">Custom Text</label>

                            <input type="radio" class="btn-check" name="reply_mode" id="cbModeDoc" value="document" onchange="toggleCbReplyMode()">
                            <label class="btn btn-outline-dark btn-sm" for="cbModeDoc">Linked to Document Type</label>
                        </div>
                    </div>

                    <div class="col-12" id="cbCustomReplyWrap">
                        <label class="form-label fw-semibold">Reply Text</label>
                        <textarea name="reply" id="cbReply" rows="4" class="form-control" placeholder="Bot reply text. Use <b>, <br> or \n for formatting."></textarea>
                    </div>

                    <div class="col-12" id="cbLinkedDocWrap" style="display:none;">
                        <label class="form-label fw-semibold">Document Type</label>
                        <select name="linked_document_type" id="cbLinkedDocType" class="form-select">
                            <option value="">— Select document type —</option>
                            <?php foreach ($doc_types as $dtype): ?>
                                <option value="<?= htmlspecialchars($dtype) ?>"><?= htmlspecialchars($dtype) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Reply is generated automatically from that document's requirements & fee in Document Settings — walang need i-type manually.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Keywords <small class="text-muted">(optional, comma-separated — also triggers this reply kapag nag-type ang user)</small></label>
                        <input type="text" name="keywords" id="cbKeywords" class="form-control" placeholder="e.g. business permit, permit requirement">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Button</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCbReplyMode() {
    const isDoc = document.getElementById('cbModeDoc').checked;
    document.getElementById('cbCustomReplyWrap').style.display = isDoc ? 'none' : 'block';
    document.getElementById('cbLinkedDocWrap').style.display = isDoc ? 'block' : 'none';
}

function openChatbotModal(data) {
    const form = document.getElementById('chatbotResponseForm');
    form.reset();
    document.getElementById('cbResponseId').value = data?.id || 0;
    document.getElementById('cbModalTitle').textContent = data ? 'Edit Chatbot Button' : 'Add Chatbot Button';
    document.getElementById('cbLabel').value = data?.label || '';
    document.getElementById('cbIntentCode').value = data?.intent_code || '';
    document.getElementById('cbParentIntent').value = data?.parent_intent || '';
    document.getElementById('cbSortOrder').value = data?.sort_order ?? 0;
    document.getElementById('cbIsActive').checked = data ? !!Number(data.is_active) : true;
    document.getElementById('cbKeywords').value = data?.keywords || '';

    const isLinked = !!(data && data.linked_document_type);
    document.getElementById('cbModeDoc').checked = isLinked;
    document.getElementById('cbModeCustom').checked = !isLinked;
    document.getElementById('cbLinkedDocType').value = data?.linked_document_type || '';
    document.getElementById('cbReply').value = isLinked ? '' : (data?.reply || '');
    toggleCbReplyMode();

    new bootstrap.Modal(document.getElementById('chatbotResponseModal')).show();
}
</script>

<!-- ══════════════════════════════════════════════
     PANEL: MAINTENANCE
══════════════════════════════════════════════ -->
<div id="panel-maintenance" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="announcements" data-group="sys-settings" onclick="showGroupTab('announcements',this,'sys-settings')">Announcements</button>
        <button class="ov-tab" data-tab="doc-settings" data-group="sys-settings" onclick="showGroupTab('doc-settings',this,'sys-settings')">Document Settings</button>
        <button class="ov-tab" data-tab="chatbot" data-group="sys-settings" onclick="showGroupTab('chatbot',this,'sys-settings')">Chatbot</button>
        <button class="ov-tab active" data-tab="maintenance" data-group="sys-settings" onclick="showGroupTab('maintenance',this,'sys-settings')">Maintenance</button>
        <button class="ov-tab" data-tab="data-management" data-group="sys-settings" onclick="showGroupTab('data-management',this,'sys-settings')">Data Management</button>
        <button class="ov-tab" data-tab="force-logout" data-group="sys-settings" onclick="showGroupTab('force-logout',this,'sys-settings')">Force Logout</button>
        <button class="ov-tab" data-tab="logs" data-group="sys-settings" onclick="showGroupTab('logs',this,'sys-settings')">Logs</button>
    </div>
    <h4 class="mb-1">Maintenance Mode</h4>
    <p class="text-muted mb-4">Toggle maintenance mode independently from chatbot availability. When enabled, only <strong>city administrators</strong> may log in and chatbot requests are blocked.</p>
    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm p-3 h-100">
                <h6 class="mb-3">Current Maintenance Status</h6>
                <div class="alert <?= $maintenance_mode ? 'alert-warning' : 'alert-success' ?> py-2">
                    <?= $maintenance_mode ? 'Maintenance mode is enabled. Regular users cannot log in.' : 'Maintenance mode is disabled. All permitted users may log in normally.' ?>
                </div>
                <p class="mb-0">The chatbot will also be blocked during maintenance even if it is enabled.</p>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm p-3 h-100">
                <h6 class="mb-3">Maintenance Settings</h6>
                <form action="../action/save_cb_settings" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="redirect_panel" value="maintenance">
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="maintenanceMode" name="maintenance_mode" value="1" <?= $maintenance_mode ? 'checked' : '' ?> >
                        <label class="form-check-label" for="maintenanceMode">Enable maintenance mode</label>
                    </div>
                    <label for="maintenanceMessage" class="form-label">Maintenance notification message</label>
                    <textarea id="maintenanceMessage" name="maintenance_message" rows="4" class="form-control mb-3"><?= htmlspecialchars($maintenance_message) ?></textarea>
                    <button type="submit" class="btn btn-sm btn-outline-primary">Save Maintenance Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     PANEL: ANNOUNCEMENTS
══════════════════════════════════════════════ -->
<div id="panel-announcements" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab active" data-tab="announcements" data-group="sys-settings" onclick="showGroupTab('announcements',this,'sys-settings')">Announcements</button>
        <button class="ov-tab" data-tab="doc-settings" data-group="sys-settings" onclick="showGroupTab('doc-settings',this,'sys-settings')">Document Settings</button>
        <button class="ov-tab" data-tab="chatbot" data-group="sys-settings" onclick="showGroupTab('chatbot',this,'sys-settings')">Chatbot</button>
        <button class="ov-tab" data-tab="maintenance" data-group="sys-settings" onclick="showGroupTab('maintenance',this,'sys-settings')">Maintenance</button>
        <button class="ov-tab" data-tab="data-management" data-group="sys-settings" onclick="showGroupTab('data-management',this,'sys-settings')">Data Management</button>
        <button class="ov-tab" data-tab="force-logout" data-group="sys-settings" onclick="showGroupTab('force-logout',this,'sys-settings')">Force Logout</button>
        <button class="ov-tab" data-tab="logs" data-group="sys-settings" onclick="showGroupTab('logs',this,'sys-settings')">Logs</button>
    </div>
    <h4 class="mb-1">Announcements</h4>
    <p class="text-muted mb-4">Create and manage system-wide announcements for employees and residents.</p>

    <!-- ── Stat Cards ── -->
    <?php
        $ann_total    = count($admin_announcements);
        $ann_active   = count(array_filter($admin_announcements, fn($a) => $a['is_active']));
        $ann_inactive = $ann_total - $ann_active;
        $ann_expired  = count(array_filter($admin_announcements, fn($a) => $a['expires_at'] && strtotime($a['expires_at']) < time()));
    ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card" style="border-top:3px solid #8b5cf6;">
                <div class="num" style="color:#8b5cf6;"><?= $ann_total ?></div>
                <small class="text-muted">Total</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" style="border-top:3px solid #22c55e;">
                <div class="num text-success"><?= $ann_active ?></div>
                <small class="text-muted">Active</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" style="border-top:3px solid #64748b;">
                <div class="num text-secondary"><?= $ann_inactive ?></div>
                <small class="text-muted">Inactive</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" style="border-top:3px solid #ef4444;">
                <div class="num text-danger"><?= $ann_expired ?></div>
                <small class="text-muted">Expired</small>
            </div>
        </div>
    </div>

    <!-- ── Create Form ── -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>➕ Post New Announcement</span>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-dark active" id="tabTextOnly" onclick="switchAnnTab('text')">Text Only</button>
                <button type="button" class="btn btn-sm btn-outline-dark" id="tabWithPic" onclick="switchAnnTab('picture')">With Picture</button>
            </div>
        </div>
        <div class="card-body">

            <!-- ─── TEXT ONLY FORM ─── -->
            <form action="../action/save_announcement" method="POST" id="annFormText">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Announcement title" required maxlength="200">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold d-flex align-items-center gap-2">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            Target Audience
                        </label>
                        <select name="target_role" class="form-select">
                            <option value="all">All Users</option>
                            <option value="employee">Employees Only</option>
                            <option value="resident">Residents Only</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
                        <textarea name="body" class="form-control" rows="4" placeholder="Write your announcement here..." required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Barangay <small class="text-muted">(leave blank for all)</small></label>
                        <select name="barangay" class="form-select">
                            <option value="">All Barangays</option>
                            <?php foreach (get_barangays() as $brgy): ?>
                                <option value="<?= htmlspecialchars($brgy) ?>"><?= htmlspecialchars(ucwords(strtolower($brgy))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Expires At <small class="text-muted">(optional)</small></label>
                        <input type="datetime-local" name="expires_at" class="form-control">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Post Text Announcement</button>
                    </div>
                </div>
            </form>

            <!-- ─── WITH PICTURE FORM ─── -->
            <form id="annFormPicture" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="body" value="(Image Announcement)">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Image <span class="text-danger">*</span> <small class="text-muted">(JPG, PNG, GIF, WEBP — max 5MB)</small></label>
                        <input type="file" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" id="annImageInput" required>
                    </div>
                    <div class="col-12" id="cropperContainer" style="display:none;">
                        <label class="form-label fw-semibold">✂️ Select the area to show <small class="text-muted">(drag to select visible area)</small></label>
                        <div style="max-height:400px;background:#f1f5f9;border-radius:10px;overflow:hidden;border:2px solid #e2e8f0;">
                            <img id="cropperImage" src="" alt="Crop" style="max-width:100%;display:block;">
                        </div>
                        <div class="mt-2 d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="annCropper.reset()">🔄 Reset</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAnnImage()">✕ Remove</button>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" id="picTitle" placeholder="Announcement title" required maxlength="200">
                    </div>
                    <div class="col-md-4">

                        <label class="form-label fw-semibold d-flex align-items-center gap-2">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                            </svg>
                            Target Audience
                        </label>

                        <select name="target_role" class="form-select" id="picRole">
                            <option value="dashboard">Dashboard Only (Public Page)</option>
                        </select>

                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Expires At <small class="text-muted">(optional)</small></label>
                        <input type="datetime-local" name="expires_at" class="form-control" id="picExpires">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success" id="btnPostPic">Post Announcement with Picture</button>
                    </div>
                </div>
            </form>

        </div>
    </div>

    <!-- ── Existing Announcements ── -->
    <div class="card shadow-sm">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span>All Announcements</span>
            <small class="text-muted"><?= $ann_total ?> total</small>
        </div>
        <div class="card-body p-0">
            <?php if (empty($admin_announcements)): ?>
                <div class="text-center text-muted py-5">
                    <div style="font-size:48px;margin-bottom:10px;">📢</div>
                    <p class="mb-0">No announcements yet.</p>
                    <small>Post your first announcement using the form above.</small>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="annTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Title</th>
                            <th>Image</th>
                            <th>Audience</th>
                            <th>Barangay</th>
                            <th>Posted By</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($admin_announcements as $ann): ?>
                        <?php
                            $isExpired = $ann['expires_at'] && strtotime($ann['expires_at']) < time();
                            $rowClass  = !$ann['is_active'] ? 'table-secondary' : ($isExpired ? 'table-warning' : '');
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td style="max-width:220px;">
                                <strong><?= htmlspecialchars($ann['title']) ?></strong>
                                <br><small class="text-muted"><?= $ann['body'] === '(Image Announcement)' ? '(Image Announcement)' : '(Text Announcement)' ?></small>
                            </td>
                            <td>
                                <?php if (!empty($ann['image'])): ?>
                                    <img src="../<?= htmlspecialchars($ann['image']) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;cursor:pointer;border:1px solid #dee2e6;" onclick="Swal.fire({imageUrl:'../<?= htmlspecialchars($ann['image']) ?>',imageAlt:'Announcement image',showConfirmButton:false,showCloseButton:true,width:600})">
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:11px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ann['target_role'] === 'all'): ?>
                                    <span class="badge bg-primary">All</span>
                                <?php elseif ($ann['target_role'] === 'employee'): ?>
                                    <span class="badge bg-warning text-dark">Employee</span>
                                <?php elseif ($ann['target_role'] === 'dashboard'): ?>
                                    <span class="badge bg-info text-dark">Dashboard</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Resident</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $ann['barangay'] ? htmlspecialchars(ucwords(strtolower($ann['barangay']))) : '<em class="text-muted">All</em>' ?></td>
                            <td><small><?= htmlspecialchars($ann['author'] ?? '—') ?></small></td>
                            <td>
                                <?php if ($isExpired): ?>
                                    <span class="badge bg-danger">Expired</span>
                                <?php elseif ($ann['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= date('M j, Y', strtotime($ann['created_at'])) ?></small></td>
                            <td style="white-space:nowrap;">
                                <!-- VIEW -->
                                <button type="button" class="icon-action-btn icon-primary" title="View"
                                    onclick="viewAnnouncement('<?= htmlspecialchars(addslashes($ann['title'])) ?>', <?= htmlspecialchars(json_encode($ann['body'])) ?>, '<?= htmlspecialchars(addslashes($ann['author'] ?? 'Unknown')) ?>', '<?= date('M j, Y g:i A', strtotime($ann['created_at'])) ?>', '<?= !empty($ann['image']) ? '../' . htmlspecialchars($ann['image']) : '' ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>

                                <!-- EDIT -->
                                <button type="button" class="icon-action-btn icon-warning" title="Edit"
                                    onclick="editAnnouncement(<?= $ann['id'] ?>, '<?= htmlspecialchars(addslashes($ann['title'])) ?>', <?= htmlspecialchars(json_encode($ann['body'])) ?>, '<?= $ann['target_role'] ?>', '<?= htmlspecialchars(addslashes($ann['barangay'] ?? '')) ?>', '<?= $ann['expires_at'] ? date('Y-m-d\TH:i', strtotime($ann['expires_at'])) : '' ?>', <?= $ann['is_active'] ?>, '<?= !empty($ann['image']) ? '../' . htmlspecialchars($ann['image']) : '' ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 20h9"/>
                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                    </svg>
                                </button>

                                <!-- ACTIVATE / DEACTIVATE -->
                                <button type="button" class="icon-action-btn <?= $ann['is_active'] ? 'icon-warning' : 'icon-success' ?>"
                                    title="<?= $ann['is_active'] ? 'Deactivate' : 'Activate' ?>"
                                    onclick="toggleAnnouncement(<?= $ann['id'] ?>, <?= $ann['is_active'] ? 'true' : 'false' ?>)">
                                    <?php if ($ann['is_active']): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="6" y="4" width="4" height="16"/>
                                            <rect x="14" y="4" width="4" height="16"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polygon points="5 3 19 12 5 21 5 3"/>
                                        </svg>
                                    <?php endif; ?>
                                </button>

                                <!-- DELETE -->
                                <button type="button" class="icon-action-btn icon-danger" title="Delete"
                                    onclick="deleteAnnouncement(<?= $ann['id'] ?>, '<?= htmlspecialchars(addslashes($ann['title'])) ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6l-2 14H7L5 6"/>
                                        <path d="M10 11v6"/>
                                        <path d="M14 11v6"/>
                                    </svg>
                                </button>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- /main-content -->

<?php
$showWelcome = !empty($_SESSION['show_welcome']);
if ($showWelcome) {
    unset($_SESSION['show_welcome']);
}
$welcomeName = htmlspecialchars(explode(' ', $_SESSION['fullname'])[0]);
$roleLabels = [
    'resident' => 'Resident',
    'employee' => 'Barangay Personnel',
    'city_administrator' => 'City Administrator',
];
$welcomeRole = htmlspecialchars($roleLabels[$_SESSION['user_type'] ?? 'city_administrator'] ?? 'User');
$userType = $_SESSION['user_type'] ?? 'city_administrator';
$barangayLabel = !empty($_SESSION['barangay']) ? htmlspecialchars($_SESSION['barangay']) : null;
if ($userType === 'city_administrator') {
    $barangayLabel = 'Santa Rosa';
}
$barangayImages = [
    'APLAYA' => '../images/brgy1.png',
    'BALIBAGO' => '../images/brgy2.png',
    'CAINGIN' => '../images/brgy3.png',
    'DILA' => '../images/brgy4.png',
    'DITA' => '../images/brgy5.png',
    'DON JOSE' => '../images/brgy6.png',
    'IBABA' => '../images/brgy7.png',
    'KANLURAN' => '../images/brgy8.png',
    'LABAS' => '../images/brgy9.png',
    'MACABLING' => '../images/brgy10.png',
    'MALITLIT' => '../images/brgy11.png',
    'MALUSAK' => '../images/brgy12.png',
    'MARKET AREA' => '../images/brgy13.png',
    'POOC' => '../images/brgy14.png',
    'PULONG SANTA CRUZ' => '../images/brgy15.png',
    'SANTO DOMINGO' => '../images/brgy16.png',
    'SINALHAN' => '../images/brgy17.png',
    'TAGAPO' => '../images/brgy18.png',
    'SANTA ROSA IBABA 2' => '../images/ibaba2.png',
];
$barangayImage = $barangayLabel ? ($barangayImages[strtoupper($barangayLabel)] ?? '../images/ibaba2.png') : '../images/ibaba2.png';
?>
<div class="modal fade" id="welcomeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg welcome-modal">
            <div class="modal-body text-center p-4">
                <div class="position-relative mx-auto mb-3 welcome-logo-wrapper">
                    <svg class="welcome-ring-svg" viewBox="0 0 120 120" aria-hidden="true">
                        <circle class="welcome-ring-bg" cx="60" cy="60" r="54" />
                        <circle class="welcome-ring-front" cx="60" cy="60" r="54" />
                    </svg>
                    <img src="<?= $barangayImage ?>" alt="<?= $barangayLabel ? 'Barangay '.$barangayLabel : 'Barangay logo' ?>" class="rounded-circle welcome-logo">
                </div>
                <h5 class="fw-semibold mb-2">Welcome back, <?= $welcomeName ?></h5>
                <p class="text-muted mb-2">Logged in as <strong><?= $welcomeRole ?></strong></p>
                <?php if ($barangayLabel): ?>
                    <p class="text-muted mb-3">City of <?= $barangayLabel ?></p>
                <?php endif; ?>
                <button type="button" class="btn btn-primary btn-block px-4" data-bs-dismiss="modal">Continue to dashboard</button>
            </div>
        </div>
    </div>
</div>

<style>
button.view-btn:hover,
button.view-btn:focus-visible,
button.view-btn:active {
    color: #fff;
    background-color: #0d6efd;
    border-color: #0d6efd;
}
.welcome-logo-wrapper {
    width: 92px;
    height: 92px;
}
.welcome-logo {
    width: 84px;
    height: 84px;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 0 8px 20px rgba(0,0,0,.15);
    position: relative;
    z-index: 2;
    display: block;
    margin: auto;
    top: 50%;
    transform: translateY(-50%);
}
.welcome-ring-svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
    z-index: 1;
}
.welcome-ring-bg,
.welcome-ring-front {
    fill: none;
    stroke-width: 10;
}
.welcome-ring-bg {
    stroke: rgba(13,110,253,.16);
}
.welcome-ring-front {
    stroke: #0d6efd;
    stroke-linecap: round;
    stroke-dasharray: 339.292;
    stroke-dashoffset: 0;
    animation: ringCountdown 15s linear forwards;
}
@keyframes ringCountdown {
    from { stroke-dashoffset: 0; }
    to { stroke-dashoffset: 339.292; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($showWelcome)): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const welcomeModal = document.getElementById('welcomeModal');
        const welcomeRingSvg = document.querySelector('.welcome-ring-svg');
        const modalInstance = welcomeModal ? new bootstrap.Modal(welcomeModal) : null;
        if (modalInstance) {
            modalInstance.show();
        }
        if (welcomeRingSvg && modalInstance) {
            setTimeout(() => {
                modalInstance.hide();
            }, 15000);
        }
    });
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script src="../js/session_guard.js"></script>
<script src="../js/heartbeat.js"></script>
<script src="../js/alert_autodismiss.js"></script>
<script src="../js/notifications.js"></script>
<script src="../js/cb_admin.js?v=<?= time() ?>"></script>


<script>
window.cityAdminConfig = {
    chartStatusData: <?= $chart_status_data ?>,
    chartDailyLabels: <?= $chart_daily_labels ?>,
    chartDailyData: <?= $chart_daily_data ?>,
    chartBrgyLabels: <?= $chart_brgy_labels ?>,
    chartBrgyData: <?= $chart_brgy_data ?>,
    initialPanel: <?= json_encode($_GET['panel'] ?? '') ?>,
    showWelcome: <?= !empty($showWelcome) ? 'true' : 'false' ?>,
    docRevenueLabels: <?= $doc_revenue_labels ?>,
    docRevenueValues: <?= $doc_revenue_values ?>,
    adminEmail: <?= json_encode($user_account['email'] ?? ($_SESSION['email'] ?? '')) ?>,
    adminUserId: <?= json_encode($user_id) ?>
};

    function applyCityDashboardFilter() {
        const yearSelect = document.getElementById('cityDashboardYear');
        const monthSelect = document.getElementById('cityDashboardMonth');
        const year = yearSelect.value;
        const month = monthSelect.value;

        monthSelect.disabled = !year;
        if (!year) monthSelect.value = '';

        const params = new URLSearchParams({
            panel: 'dashboard',
            dashboard_year: year,
            dashboard_month: year ? month : ''
        });
        window.location.href = '?' + params.toString();
    }

    function applyBarangayReportFilter() {
        const year = document.getElementById('brgyFilterYear').value;
        const month = document.getElementById('brgyFilterMonth').value;
    const monthSelect = document.getElementById('brgyFilterMonth');

    // Disable month if no year selected
    monthSelect.disabled = !year;
    if (!year) monthSelect.value = '';

    // Always send both params explicitly, even empty, so the backend's
    // isset() check can tell "All Years" apart from "filter untouched".
    let url = '?panel=overview&tab=barangay';
    url += '&brgy_year=' + encodeURIComponent(year);
    url += '&brgy_month=' + encodeURIComponent(year ? month : '');
    url += '&brgy_limit=' + encodeURIComponent(document.getElementById('brgyFilterLimit').value);

    window.location.href = url;
}
</script>

<script src="../js/city_admin.js?v=<?= time() ?>" defer></script>
</body>
</html>