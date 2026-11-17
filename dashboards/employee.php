<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../data/employee_data.php';
require_once __DIR__ . '/../core/archiving.php';
run_expiry_check($pdo);
$active_employees = array_filter($my_submissions, fn($s) => (int)$s['is_verified'] === 1);
$pending_or_rejected_employees = array_filter($my_submissions, fn($s) => in_array((int)$s['is_verified'], [0, 2], true));
$pending_requests_count = count(array_filter($my_submissions, fn($s) => (int)$s['is_verified'] === 0));
?>
    
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Barangay Personnel - Santa Rosa</title>
<link rel="icon" href="../images/ibaba2.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- <link rel="stylesheet" href="../css/employee.css?v=<?= time() ?>">
<link rel="stylesheet" href="../css/shared_dashboard.css?v=<?= time() ?>"> -->
<link rel="stylesheet" href="../css/dashboard.css?v=<?= time() ?>">
<link rel="stylesheet" href="../css/prerequisites.css?v=<?= time() ?>">
<meta name="csrf-token" content="<?= csrf_token() ?>">

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body>
<div class="dashboard-loading-overlay" id="dashboardLoadingOverlay" aria-live="polite">
    <div class="dashboard-loading-card">	
        <div class="dashboard-loading-spinner"></div>
        <div class="dashboard-loading-text">Loading dashboard…</div>
    </div>
</div>

<!-- TOP BAR -->
<div class="top-bar">
    <div class="d-flex align-items-center gap-2">
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">☰</button>
    </div>

    <div class="top-bar-logo">
        <img src="../images/logos/erequest-logo.png" alt="eRequest Mo">
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
            src="<?= $my_image ?>"
            alt="Barangay Logo"
            width="70"
            height="70"
            class="rounded-circle me-3"
            loading="lazy"
        >

        <div>
            Employee of
            Barangay: <strong style="color: #ffffff;"><?= htmlspecialchars($barangay) ?></strong>
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

    <a href="#" class="active" onclick="showEmployeeOverviewTab('dashboard',this)">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 3v18h18"/>
            <path d="M7 15v-4"/>
            <path d="M12 15V8"/>
            <path d="M17 15v-7"/>
        </svg>
        Dashboard Overview
    </a>

    <br>

    <a href="#" data-group="manage" onclick="showGroupTab('requests',this,'manage')">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        </svg>
        Manage Requests
        <?php if ($my_unviewed_count > 0): ?>
            <span class="pending-badge" id="manageRequestsBadge"><?= $my_unviewed_count ?></span>
        <?php endif; ?>
    </a>

    <br>

    <a href="#" data-group="accounts" onclick="showGroupTab('add-employee',this,'accounts')">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="10" cy="7" r="3"/>
            <path d="M20 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a3 3 0 0 1 0 5.75"/>
        </svg>
        Manage Accounts

        <?php if ($my_pending_count > 0): ?>
            <span class="pending-badge"><?= $my_pending_count ?></span>
        <?php endif; ?>
    </a>

    <br>

    <a href="#" onclick="showPanel('doc-settings',this)">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <path d="M14 2v6h6"/>
            <path d="M9 15l2 2 4-4"/>
        </svg>
        Document Settings
    </a>

    <br>

    <a href="#" onclick="showPanel('officials',this)">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 10l9-5 9 5"/>
            <path d="M5 10v8"/>
            <path d="M10 10v8"/>
            <path d="M14 10v8"/>
            <path d="M19 10v8"/>
            <path d="M2 18h20"/>
        </svg>
        Barangay Officials
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
<?php if(isset($_SESSION['sms_success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['sms_success']) ?></div>
    <?php unset($_SESSION['sms_success']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['sms_error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['sms_error']) ?></div>
    <?php unset($_SESSION['sms_error']); ?>
<?php endif; ?>
	
<!-- ══════════════════════════════════════════════
     PANEL: DASHBOARD
══════════════════════════════════════════════ -->
<div id="panel-dashboard" class="panel active">
    <div class="ov-tabs mb-4">
        <button class="ov-tab active" data-tab="dashboard" data-group="overview" onclick="showEmployeeOverviewTab('dashboard',this)">Dashboard</button>
        <button class="ov-tab" data-tab="top-documents" data-group="overview" onclick="showEmployeeOverviewTab('top-documents',this)">Top Documents</button>
    </div>

    <!-- ── TOP BAR ── -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0 fw-bold">Dashboard</h4>
            <small class="text-muted">Barangay <?= htmlspecialchars($barangay) ?></small>
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
            <a href="print_report?rep_filter=<?= htmlspecialchars($rep_filter ?? 'month') ?>" 
            target="_blank" 
            class="btn btn-outline-dark btn-sm d-flex align-items-center gap-2">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                View Report
            </a>
            <a href="narrative_report?rep_filter=<?= htmlspecialchars($rep_filter ?? 'month') ?>" 
            target="_blank" 
            class="btn btn-outline-dark btn-sm d-flex align-items-center gap-2">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                View Narrative Report
            </a>
        </div>
    </div>

<!-- FILTER: Year & Month -->
<?php
    // Mirror the data-layer default: no filter_year in the URL at all means
    // "first load" -> default to the current year/month. Once the user has
    // submitted the form (filter_year present, even as ''), respect exactly
    // what they picked.
    $selected_year  = isset($_GET['filter_year'])  ? $_GET['filter_year']  : date('Y');
    $selected_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : (($selected_year === date('Y')) ? date('m') : '');
    $month_disabled = ($selected_year === '' || $selected_year === null);

    // Shortcut button toggles: currently on "All" -> offer a quick jump back
    // to today's month/year; currently on any specific period (today or
    // otherwise) -> offer a quick jump to "All".
    $is_all_mode = ($selected_year === '' || $selected_year === null);
?>
    <form method="GET" class="d-flex gap-2 align-items-center mb-4 flex-wrap">
        <input type="hidden" name="panel" value="dashboard">
        <label class="fw-semibold small text-muted mb-0">Filter:</label>
        <select name="filter_year" id="filterYear" class="form-select form-select-sm w-auto" onchange="applyDashboardFilter()">
            <option value="">All Years</option>
            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <select name="filter_month" id="filterMonth" class="form-select form-select-sm w-auto" onchange="applyDashboardFilter()" <?= $month_disabled ? 'disabled' : '' ?>>
            <option value="">All Months</option>
            <?php $months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'];
            foreach ($months as $m => $name): ?>
                <option value="<?= $m ?>" <?= $selected_month == $m ? 'selected' : '' ?>><?= $name ?></option>
            <?php endforeach; ?>
        </select>
        <!-- Always visible — toggles depending on current state. -->
        <?php if ($is_all_mode): ?>
            <a href="?panel=dashboard&filter_year=<?= date('Y') ?>&filter_month=<?= date('m') ?>" class="btn btn-sm btn-outline-dark">Filter Today</a>
        <?php else: ?>
            <a href="?panel=dashboard&filter_year=&filter_month=" class="btn btn-sm btn-outline-dark">Filter All</a>
        <?php endif; ?>
    </form>

    <!-- Dashboard Summary Cards -->
    <?php
        $dashboard_period_params = [
            'panel' => 'requests',
            'inq_filter' => 'dashboard',
            'filter_year' => $dash_year === 'all' ? '' : $dash_year,
            'filter_month' => $dash_month === 'all' ? '' : $dash_month,
        ];
        $dashboard_requests_url = '?' . http_build_query($dashboard_period_params);
        $all_requests_url = '?' . http_build_query([
            'panel' => 'requests',
            'inq_filter' => 'all',
        ]);
    ?>
    <div class="row g-2 mb-4">
        <div class="col"><a href="<?= htmlspecialchars($all_requests_url, ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link"><div class="stat-card h-100" style="border-top:3px solid #3b82f6;"><div class="num text-primary" id="cardTotal"><?= $rpt_total ?></div><small class="text-muted">Total Requests</small></div></a></div>
        <div class="col"><a href="<?= htmlspecialchars($dashboard_requests_url . '&req_status=Pending', ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link"><div class="stat-card h-100" style="border-top:3px solid #f59e0b;"><div class="num text-warning" id="cardPending"><?= $rpt_summary['Pending'] ?></div><small class="text-muted">Pending Requests</small></div></a></div>
        <div class="col"><a href="<?= htmlspecialchars($dashboard_requests_url . '&req_status=Approved', ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link"><div class="stat-card h-100" style="border-top:3px solid #3b82f6;"><div class="num text-primary" id="cardApproved"><?= $rpt_summary['Approved'] ?></div><small class="text-muted">Approved Requests</small></div></a></div>
        <div class="col"><a href="<?= htmlspecialchars($dashboard_requests_url . '&req_status=To%20be%20Claim', ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link"><div class="stat-card h-100" style="border-top:3px solid #6366f1;"><div class="num" style="color:#6366f1;" id="cardToBeClaim"><?= $rpt_summary['To be Claim'] ?></div><small class="text-muted">To be Claimed</small></div></a></div>
        <div class="col"><a href="<?= htmlspecialchars($dashboard_requests_url . '&req_status=Rejected', ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link"><div class="stat-card h-100" style="border-top:3px solid #ef4444;"><div class="num text-danger" id="cardRejected"><?= $rpt_summary['Rejected'] ?></div><small class="text-muted">Rejected Requests</small></div></a></div>
        <div class="col"><a href="<?= htmlspecialchars($dashboard_requests_url . '&req_status=Completed', ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link"><div class="stat-card h-100" style="border-top:3px solid #22c55e;"><div class="num text-success" id="cardCompleted"><?= $rpt_summary['Completed'] ?></div><small class="text-muted">Completed Requests</small></div></a></div>
        <div class="col"><a href="<?= htmlspecialchars($dashboard_requests_url, ENT_QUOTES, 'UTF-8') ?>" class="dashboard-stat-link"><div class="stat-card h-100" style="border-top:3px solid #0ea5e9;"><div class="fs-6 fw-bold <?= $trend_color ?>" id="cardTrend"><?= $trend_display ?></div><small class="text-muted">vs Prev Month</small></div></a></div>
    </div>
    <div class="row g-2 mb-4">
        <div class="col"><a href="?panel=residents&amp;resident_status=all" class="dashboard-stat-link"><div class="stat-card h-100" style="border-top:3px solid #6366f1;"><div class="num" style="color:#6366f1;" id="cardResRegistered"><?= $rpt_residents_total ?></div><small class="text-muted">Registered Residents</small></div></a></div>
        <div class="col"><a href="?panel=residents&amp;resident_status=online" class="dashboard-stat-link"><div class="stat-card h-100" style="border-top:3px solid #22c55e;"><div class="num text-success" id="cardResOnline"><?= $rpt_residents_online ?></div><small class="text-muted">Online</small></div></a></div>
        <div class="col"><a href="?panel=residents&amp;resident_status=offline" class="dashboard-stat-link"><div class="stat-card h-100" style="border-top:3px solid #64748b;"><div class="num" style="color:#64748b;" id="cardResOffline"><?= $rpt_residents_offline ?></div><small class="text-muted">Offline</small></div></a></div>
        <div class="col"><a href="?panel=residents&amp;resident_status=deactivated" class="dashboard-stat-link"><div class="stat-card h-100" style="border-top:3px solid #ef4444;"><div class="num text-danger" id="cardResDeactivated"><?= $rpt_residents_deactivated ?></div><small class="text-muted">Deactivated</small></div></a></div>
    </div>

    <?php if ($my_pending_count > 0): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
        <span style="font-size:22px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 24">
              <path fill="orange" d="M1 21h22L12 2 1 21z"/>
              <path fill="white" d="M12 16a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm1-7h-2v6h2V9z"/>
            </svg>
        </span>
        <div>
            <strong><?= $my_pending_count ?> employee account(s)</strong> awaiting city admin approval.
            <a href="#" onclick="showGroupTab('my-submissions',null,'accounts')" class="alert-link ms-2" style="text-decoration:none">View →</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── TWO-COLUMN LAYOUT ── -->
    <div class="row g-4">

        <!-- LEFT: Report Summary + Charts -->
        <div class="col-md-8">

            <!-- Charts -->
            <div class="row g-3">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header fw-semibold">Requests by Document Type</div>
                        <div class="card-body"><canvas id="chartDocType" height="80"></canvas></div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header fw-semibold">Daily Requests (Last 30 Days)</div>
                        <div class="card-body"><canvas id="chartDaily" height="80"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Recent Request Activity + Announcement -->
        <div class="col-md-4 d-flex flex-column gap-4">

            <!-- Recent Request Activity -->
            <div class="card shadow-sm">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Recent Request </span>
                    <a href="#" onclick="showGroupTab('requests',null,'manage')" class="btn btn-sm btn-outline-dark">All →</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="table-dark"><tr><th>Resident</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>
                            <?php foreach ($recent_activity ?? [] as $rr): ?>
                            <tr>
                                <td style="max-width:120px;overflow-wrap:anywhere;"><?= htmlspecialchars(request_display_name($rr['resident_name'] ?? '', $rr['relationship'] ?? '')) ?><br><small class="text-muted"><?= htmlspecialchars($rr['document_type']) ?></small></td>
                                <td><span class="badge bg-<?= match($rr['status']??'') { 'Completed'=>'success','To be Claim'=>'info','Approved'=>'primary','Rejected'=>'danger',default=>'warning' } ?>"><?= htmlspecialchars($rr['status']) ?></span></td>
                                <td><small><?= date('M d', strtotime($rr['created_at'])) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_activity)): ?><tr><td colspan="3" class="text-center text-muted py-3">No recent activity.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Announcement to Barangay Employee -->
            <div class="card shadow-sm flex-grow-1">
                <div class="card-header fw-semibold">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 11v2l14-2V7L3 9v2z"/>
                        <path d="M17 7l4-2v12l-4-2"/>
                        <path d="M7 13l1 5"/>
                    </svg>    
                Announcements</div>
                <div class="card-body text-muted" style="min-height:120px;max-height:320px;overflow-y:auto;">
                    <?php if (empty($emp_announcements)): ?>
                        <small><em>No announcements at this time.</em></small>
                    <?php else: ?>
                        <?php foreach ($emp_announcements as $ann): ?>
                        <div style="border-bottom:1px solid #eee;padding:8px 0;">
                            <?php if (!empty($ann['image'])): ?>
                                <img src="../<?= htmlspecialchars($ann['image']) ?>" alt="" style="width:100%;max-height:140px;object-fit:cover;border-radius:8px;margin-bottom:8px;">
                            <?php endif; ?>
                            <strong style="color:#1e293b;"><?= htmlspecialchars($ann['title']) ?></strong>
                            <br><small><?= nl2br(htmlspecialchars(mb_strimwidth($ann['body'], 0, 150, '...'))) ?></small>
                            <br><small class="text-muted">🏛️ City of Santa Rosa &mdash; 📅 <?= date('M j, Y', strtotime($ann['created_at'])) ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

</div>


<!-- REQUESTS PANEL (panel-box → panel) -->
<div id="panel-requests" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab active" data-tab="requests" data-group="manage" onclick="showGroupTab('requests',this,'manage')">Requests</button>
        <button class="ov-tab" data-tab="payments" data-group="manage" onclick="showGroupTab('payments',this,'manage')">Payments</button>
    </div>

        <!-- SMS messages -->
        <?php if(isset($_SESSION['sms_success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['sms_success'] ?></div>
            <?php unset($_SESSION['sms_success']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['sms_error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['sms_error'] ?></div>
            <?php unset($_SESSION['sms_error']); ?>
        <?php endif; ?>

        <!-- FILTER + SEARCH -->
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3 request-table-header">
            <div class="d-flex align-items-center gap-2">
                <h4 class="mb-0"><?= $inq_status === '' ? 'Resident Requests' : htmlspecialchars($inq_status . ' Requests', ENT_QUOTES, 'UTF-8') ?></h4>
                <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" id="manualRequestBtn">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M12 5v14"/>
                        <path d="M5 12h14"/>
                    </svg>
                    Create Manual Request
                </button>
                <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" id="quickAddRequestBtn">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    Quick Add (Multiple)
                </button>
            </div>
            <div class="d-flex gap-2 align-items-center request-table-controls">
                <form method="GET" class="mb-0 d-flex gap-2">
                    <input type="hidden" name="panel" value="requests">
                    <input type="hidden" name="filter_year" value="<?= htmlspecialchars($dash_year === 'all' ? '' : $dash_year, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="filter_month" value="<?= htmlspecialchars($dash_month === 'all' ? '' : $dash_month, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="search" id="reqSearch" name="inq_search" value="<?= htmlspecialchars($inq_search, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm request-search" placeholder="Search Resident, Document, etc." aria-label="Search requests" maxlength="100" oninput="handleRequestSearch(this, 'inq_filter')" onsearch="handleRequestSearch(this, 'inq_filter')" onkeydown="submitRequestSearchOnEnter(event, this, 'inq_filter')">
                    <select name="inq_filter" class="form-select form-select-sm w-auto request-filter" onchange="this.form.submit()">
                        <option value="today" <?= $inq_filter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $inq_filter === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="month" <?= $inq_filter === 'month' ? 'selected' : '' ?>>This Month</option>
                        <option value="all" <?= $inq_filter === 'all' ? 'selected' : '' ?>>Last 100 Requests</option>
                        <option value="all_requests" <?= $inq_filter === 'all_requests' ? 'selected' : '' ?>>All Requests</option>
                        <option value="dashboard" <?= $inq_filter === 'dashboard' ? 'selected' : '' ?>>Selected Period</option>
                    </select>
                    <select name="req_status" class="form-select form-select-sm w-auto request-filter" onchange="this.form.submit()" aria-label="Filter requests by status">
                        <option value="" <?= $inq_status === '' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="Pending" <?= $inq_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Approved" <?= $inq_status === 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="To be Claim" <?= $inq_status === 'To be Claim' ? 'selected' : '' ?>>To be Claimed</option>
                        <option value="Rejected" <?= $inq_status === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="Completed" <?= $inq_status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <?php if ($serverPaginatedRequests): ?>
                <?php
                    $inq_first_result = $inq_total > 0 ? $inq_offset + 1 : 0;
                    $inq_last_result = min($inq_offset + $inq_page_size, $inq_total);
                    $inq_pagination_params = [
                        'panel' => 'requests',
                        'inq_filter' => $inq_filter,
                        'req_status' => $inq_status,
                        'inq_search' => $inq_search,
                        'filter_year' => $dashboard_year === 'all' ? '' : $dashboard_year,
                        'filter_month' => $dashboard_month === 'all' ? '' : $dashboard_month,
                    ];
                ?>
                <small id="reqPageInfo" class="text-muted">Showing <?= $inq_first_result ?>–<?= $inq_last_result ?> of <?= $inq_total ?></small>
                <div id="reqPagination" class="d-flex gap-2">
                    <?php if ($inq_page > 1): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="?<?= htmlspecialchars(http_build_query($inq_pagination_params + ['inq_page' => $inq_page - 1]), ENT_QUOTES, 'UTF-8') ?>">← Prev</a>
                    <?php endif; ?>
                    <span class="text-muted small px-2 align-self-center">Page <?= $inq_page ?> of <?= $inq_page_count ?></span>
                    <?php if ($inq_page < $inq_page_count): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="?<?= htmlspecialchars(http_build_query($inq_pagination_params + ['inq_page' => $inq_page + 1]), ENT_QUOTES, 'UTF-8') ?>">Next →</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <small id="reqPageInfo" class="text-muted"></small>
                <div id="reqPagination" class="d-flex gap-2"></div>
            <?php endif; ?>
        </div>

        <!-- TABLE -->
        <div class="table-responsive">
            <table id="reqTable" class="table table-striped table-sm align-middle" <?= $serverPaginatedRequests ? 'data-server-paginated="true"' : '' ?>>
                <thead class="table-dark">
                    <tr>
                        <th>Request No.</th>
                        <th>Resident</th>
                        <th>Document</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                    <!-- COLUMN FILTER ROW -->
                    <!-- <tr style="background:#1e293b;">
                        <th><input type="text" class="form-control form-control-sm" oninput="filterColTable('reqTable',0,this.value)" placeholder="REQUEST ID"></th>
                        <th><input type="text" class="form-control form-control-sm" oninput="filterColTable('reqTable',1,this.value)" placeholder="Resident"></th>
                        <th><input type="text" class="form-control form-control-sm" oninput="filterColTable('reqTable',2,this.value)" placeholder="Document"></th>
                        <th>
                            <select class="form-select form-select-sm" onchange="filterColTable('reqTable',3,this.value)">
                                <option value="">All</option>
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Completed">Completed</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </th>
                        <th>
                            <select class="form-select form-select-sm" onchange="filterColTable('reqTable',4,this.value)">
                                <option value="">All</option>
                                <option value="Unpaid">Unpaid</option>
                                <option value="Paid">Paid</option>
                            </select>
                        </th>
                        <th><input type="text" class="form-control form-control-sm" oninput="filterColTable('reqTable',4,this.value)" placeholder="Reason"></th>
                        <th><input type="text" class="form-control form-control-sm" oninput="filterColTable('reqTable',5,this.value)" placeholder="Date"></th>
                        <th></th>
                    </tr> -->
                </thead>

                <tbody>
                    <?php if(!empty($requests)): ?>
                        <?php foreach($requests as $r):
                            $status = strtolower(trim($r['status']));
                            $statusClass = strtolower(str_replace(' ', '-', (string) $r['status']));
                            $requestDate = date('Ymd', strtotime($r['created_at']));
                            $userId = $r['user_id'] ?? '0';
                            $reqId = str_pad($r['request_id'], 3, '0', STR_PAD_LEFT);
                            $displayRequestID = 'REQ' . $requestDate . $userId . '-' . $reqId;
                            $paymentReferenceTitle = strtolower(trim((string) ($r['preferred_payment_method'] ?? ''))) === 'walk-in'
                                ? (!empty($r['receipt_number']) ? 'Receipt No: ' . $r['receipt_number'] : 'Receipt not issued')
                                : (!empty($r['payment_reference']) ? 'Reference No: ' . $r['payment_reference'] : 'No Reference Number');
                        ?>
                        <tr>

                            <td>
                                <span style="font-size:13px; font-weight:700; letter-spacing:0.3px; color:#8E1B1B;">
                                    <?= htmlspecialchars($displayRequestID) ?>
                                </span>
                            </td>
                            <td class="request-name-cell"><?= htmlspecialchars(request_display_name($r['full_name'] ?? '', $r['relationship'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($r['document_type']) ?></td>

                            <td>
                                <span class="status-pill status-<?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($r['status']) ?></span>
                            </td>

                            <td data-label="Payment">
                                <span style="font-weight: 600; cursor:pointer;"
                                    title="<?= htmlspecialchars($paymentReferenceTitle, ENT_QUOTES, 'UTF-8') ?>">
									<?= htmlspecialchars(payment_method_label($r['preferred_payment_method'], $r['payment_status'] ?? null)) ?>                                
                                </span>
                                <?php if (strtolower(trim((string)($r['payment_status'] ?? ''))) !== 'free'): ?>
                                <span style="font-size: 12px;">
                                    (<?= htmlspecialchars(ucfirst($r['payment_status'])) ?>)
                                </span>
                                <?php endif; ?>
                                <?php
                                    $refundRequested = !empty($r['refund_requested_at']);
                                    $refundStatus = strtolower(trim((string)($r['refund_status'] ?? 'none')));
                                    $requestStatus = strtolower(trim((string)($r['status'] ?? '')));

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
                                
                                <?php if (!empty($r['pwd_senior_requested'])): ?>
                                    <?php if (!empty($r['pwd_senior_verified'])): ?>
                                        <br><span class="badge bg-success" style="font-size:10px;">🏷️ PWD/Senior -20% Applied</span>
                                    <?php elseif (!empty($r['pwd_senior_rejected'])): ?>
                                        <br><span class="badge bg-danger" style="font-size:10px;">🏷️ PWD/Senior Discount Rejected</span>
                                    <?php else: ?>
                                        <br><span class="badge bg-warning text-dark" style="font-size:10px;">🏷️ PWD/Senior Requested</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= ($status === 'rejected') ? htmlspecialchars($r['rejection_reason'] ?? '') : '—' ?>
                            </td>

                            <td><?= date("M d, Y", strtotime($r['created_at'])) ?></td>

                            <td class="actions-cell">
                                <!-- VIEW -->
                                <button class="icon-action-btn icon-primary view-btn" title="View Details" data-id="<?= htmlspecialchars($r['request_id']) ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>

                                <?php
                                    $pwdSeniorUnresolved = !empty($r['pwd_senior_requested'])
                                        && empty($r['pwd_senior_verified'])
                                        && empty($r['pwd_senior_rejected'])
                                        && strtolower(trim($r['preferred_payment_method'] ?? '')) === 'walk-in'
                                        && !in_array(strtolower(trim($r['payment_status'] ?? '')), ['paid', 'free'], true);

                                    $showApproveButton = ($r['status'] === 'Pending') && !$pwdSeniorUnresolved && !(
                                        strtolower(trim($r['preferred_payment_method'] ?? '')) === 'online' &&
                                        strtolower(trim($r['payment_status'] ?? '')) === 'pending'
                                    );

                                    $showMarkPaidButton = (
                                        in_array(strtolower(trim($r['status'] ?? '')), ['to be claim', 'completed'], true) &&
                                        strtolower(trim($r['preferred_payment_method'] ?? '')) === 'walk-in' &&
                                        !in_array(strtolower(trim($r['payment_status'] ?? '')), ['paid', 'free'], true) &&
                                        (float) ($r['payment_amount'] ?? 0) > 0
                                    );
                                    $combinePaymentAndClaim = $showMarkPaidButton
                                        && strtolower(trim($r['status'] ?? '')) === 'to be claim';
                                ?>

                                <?php if ($showMarkPaidButton): ?>
                                    <button class="icon-action-btn icon-success action-btn" title="<?= $combinePaymentAndClaim ? 'Mark Paid & Complete' : 'Mark Paid' ?>" data-action="mark-paid" data-complete-claim="<?= $combinePaymentAndClaim ? '1' : '0' ?>" data-id="<?= $r['request_id'] ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <?php if ($combinePaymentAndClaim): ?>
                                                <path d="M20 6 9 17l-5-5"/>
                                            <?php else: ?>
                                                <path d="M5 12h14"/><path d="M12 5l7 7-7 7"/>
                                            <?php endif; ?>
                                        </svg>
                                    </button>
                                <?php endif; ?>

                                <?php if (strtolower(trim($r['status'] ?? '')) === 'to be claim' && !$combinePaymentAndClaim): ?>
                                    <button class="icon-action-btn icon-success action-btn" title="Mark Claimed" data-action="claim" data-id="<?= $r['request_id'] ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 6 9 17l-5-5"/>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                                    
                                <?php 
                                    $showVerifyPwdBtn = !empty($r['pwd_senior_requested']) && empty($r['pwd_senior_verified'])
                                    && empty($r['pwd_senior_rejected'])
                                    && strtolower(trim($r['preferred_payment_method'] ?? '')) === 'walk-in'
                                    && !in_array(strtolower(trim($r['payment_status'] ?? '')), ['paid', 'free'], true);
                                ?>
                                <?php if ($showVerifyPwdBtn): ?>
                                    <form method="POST" action="../action/verify_pwd_senior" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Verify PWD/Senior Discount"
                                            onclick="return confirm('Verify PWD/Senior ID and apply 20% discount?')">
                                            🏷️
                                        </button>
                                    </form>
                                    <form method="POST" action="../action/reject_pwd_senior" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject PWD/Senior Discount"
                                            onclick="return confirm('Reject this discount? The resident will be charged the full price.')">
                                            ✖️🏷️
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- ACTIONS -->
                                <?php if($r['is_viewed'] == 1): ?>

                                    <?php if($showApproveButton): ?>
                                        <!-- Approve -->
                                        <button class="icon-action-btn icon-success action-btn" title="Approve" data-action="approve" data-id="<?= $r['request_id'] ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        </button>

                                        <!-- Reject -->
                                        <button class="icon-action-btn icon-danger action-btn" title="Reject" data-action="reject" data-id="<?= $r['request_id'] ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                            </svg>
                                        </button>

									<?php elseif($r['status'] === 'Approved' && !$pwdSeniorUnresolved): ?>
                                        <!-- Process -->
                                        <button class="icon-action-btn icon-warning action-btn" title="Process" data-action="process" data-id="<?= $r['request_id'] ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                                            </svg>
                                        </button>
                                    <?php endif; ?>

                                <?php endif; ?>

                                <?php
                                $refundRequested = !empty($r['refund_requested_at']);
                                $refundStatus = strtolower(trim((string)($r['refund_status'] ?? '')));
                                $requestStatus = strtolower(trim((string)($r['status'] ?? '')));

                                $showRefundButtons =
                                    $refundRequested &&
                                    $requestStatus === 'rejected' &&
                                    !in_array($refundStatus, ['approved', 'denied', 'completed', 'failed'], true);
                                ?>

                                <?php if ($showRefundButtons): ?>
                                    <button class="btn btn-success btn-sm refund-action-btn" title="Approve Refund"
                                        data-refund-action="approve"
                                        data-id="<?= $r['request_id'] ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 1v22"/>
                                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                        </svg>
                                    </button>

                                    <button class="btn btn-outline-danger btn-sm refund-action-btn" title="Deny Refund"
                                        data-refund-action="deny"
                                        data-id="<?= $r['request_id'] ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"/>
                                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                        </svg>
                                    </button>
                                <?php endif; ?>

                                <!-- DELETE -->
                                <!-- <button class="btn btn-danger btn-sm action-btn"
                                    data-action="delete"
                                    data-id="<?= $r['request_id'] ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6l-2 14H7L5 6"/>
                                        <path d="M10 11v6"/>
                                        <path d="M14 11v6"/>
                                    </svg>
                                </button> -->
                            </td>


                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No requests found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>

    </div>

    <!-- RESIDENTS PANEL -->
<div id="panel-residents" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="add-employee" data-group="accounts" onclick="showGroupTab('add-employee',this,'accounts')">Add Employee</button>
        <button class="ov-tab" data-tab="my-submissions" data-group="accounts" onclick="showGroupTab('my-submissions',this,'accounts')">Employees</button>
        <button class="ov-tab" data-tab="pending-requests" data-group="accounts" onclick="showGroupTab('pending-requests',this,'accounts')">
            Pending Requests
            <?php if ($pending_requests_count > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?= $pending_requests_count ?></span>
            <?php endif; ?>
        </button>
        <button class="ov-tab active" data-tab="residents" data-group="accounts" onclick="showGroupTab('residents',this,'accounts')">Residents</button>
        <button class="ov-tab" data-tab="profile" data-group="accounts" onclick="showGroupTab('profile',this,'accounts')">My Profile</button>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3 request-table-header">
        <h4 class="mb-0">Registered Residents</h4>
        <div class="d-flex gap-2 align-items-center request-table-controls">
            <input type="text" id="resSearch" class="form-control form-control-sm res-search" placeholder="Search name, email, phone..." oninput="filterTable('resTable', this.value)">
            <form method="GET" class="mb-0 d-flex gap-2">
                <input type="hidden" name="panel" value="residents">
                <select name="res_filter" class="form-select form-select-sm w-auto request-filter" onchange="this.form.submit()">
                    <option value="all" <?= ($res_filter ?? '')=='all'?'selected':'' ?>>All</option>
                    <option value="today" <?= ($res_filter ?? '')=='today'?'selected':'' ?>>Today</option>
                    <option value="week" <?= ($res_filter ?? '')=='week'?'selected':'' ?>>Last 7 Days</option>
                    <option value="month" <?= ($res_filter ?? '')=='month'?'selected':'' ?>>This Month</option>
                </select>
                <select name="resident_status" class="form-select form-select-sm w-auto request-filter" onchange="this.form.submit()" aria-label="Filter residents by status">
                    <option value="all" <?= ($_GET['resident_status'] ?? 'all') === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="online" <?= ($_GET['resident_status'] ?? 'all') === 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="offline" <?= ($_GET['resident_status'] ?? 'all') === 'offline' ? 'selected' : '' ?>>Offline</option>
                    <option value="deactivated" <?= ($_GET['resident_status'] ?? 'all') === 'deactivated' ? 'selected' : '' ?>>Deactivated</option>
                </select>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <small id="resPageInfo" class="text-muted"></small>
        <div id="resPagination" class="d-flex gap-2"></div>
    </div>

        <!-- TABLE -->
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle" id="resTable">
                <thead class="table-dark">
                    <tr>
                        <th>Resident ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Registered</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if(!empty($residents)): ?>
                    <?php foreach($residents as $r): ?>

                    <tr>
                        <td>
                            <span style="font-size:13px; font-weight:700; letter-spacing:0.3px; color:#8E1B1B;">
                                <?= 'RES-' . date('Ymd', strtotime($r['created_at'])) . str_pad($r['user_id'], 3, '0', STR_PAD_LEFT) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                        <td><?= htmlspecialchars($r['email']) ?></td>
                        <td><?= htmlspecialchars(format_phone_display($r['phone'] ?? '')) ?></td>
                        <td><?= date("M d, Y", strtotime($r['created_at'])) ?></td>
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
                                        <circle cx="12" cy="12" r="8"
                                                fill="currentColor"/>
                                    </svg>
                                    Online
                                </span>

                            <?php else: ?>

                                <span class="text-muted">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                        style="vertical-align:middle;margin-right:4px;">
                                        <circle cx="12" cy="12" r="8"
                                                fill="currentColor"/>
                                    </svg>
                                    Offline
                                </span>

                            <?php endif; ?>
                        </td>

                    </tr>

                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No residents found in this barangay.</td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>

<!-- MY PROFILE PANEL -->
<div id="panel-profile" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="add-employee" data-group="accounts" onclick="showGroupTab('add-employee',this,'accounts')">Add Employee</button>
        <button class="ov-tab" data-tab="my-submissions" data-group="accounts" onclick="showGroupTab('my-submissions',this,'accounts')">Employees</button>
        <button class="ov-tab" data-tab="pending-requests" data-group="accounts" onclick="showGroupTab('pending-requests',this,'accounts')">
            Pending Requests
            <?php if ($pending_requests_count > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?= $pending_requests_count ?></span>
            <?php endif; ?>
        </button>
        <button class="ov-tab" data-tab="residents" data-group="accounts" onclick="showGroupTab('residents',this,'accounts')">Residents</button>
        <button class="ov-tab active" data-tab="profile" data-group="accounts" onclick="showGroupTab('profile',this,'accounts')">My Profile</button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h5 class="mb-3">Personal Information</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <strong>Barangay:</strong><br>
                    <?= htmlspecialchars($barangay) ?>
                </div>
                <div class="col-md-6">
                    <strong>Your ID:</strong><br>
                    EMP-<?= str_pad($user_id, 3, '0', STR_PAD_LEFT) ?>
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
            <button class="btn btn-warning btn-sm" id="adminResetBtn"
                    data-email="<?= htmlspecialchars($user_account['email'] ?? ($_SESSION['email'] ?? '')) ?>"
                    data-user-id="<?= (int)$user_id ?>">
                Forgot Password
            </button>
        </div>
    </div>
</div>

        <!-- ADD EMPLOYEE PANEL -->
<div id="panel-add-employee" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab active" data-tab="add-employee" data-group="accounts" onclick="showGroupTab('add-employee',this,'accounts')">Add Employee</button>
        <button class="ov-tab" data-tab="my-submissions" data-group="accounts" onclick="showGroupTab('my-submissions',this,'accounts')">Employees</button>
        <button class="ov-tab" data-tab="pending-requests" data-group="accounts" onclick="showGroupTab('pending-requests',this,'accounts')">
            Pending Requests
            <?php if ($pending_requests_count > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?= $pending_requests_count ?></span>
            <?php endif; ?>
        </button>
        <button class="ov-tab" data-tab="residents" data-group="accounts" onclick="showGroupTab('residents',this,'accounts')">Residents</button>
        <button class="ov-tab" data-tab="profile" data-group="accounts" onclick="showGroupTab('profile',this,'accounts')">My Profile</button>
    </div>

            <h4>Add Employee Account</h4>
            <p class="text-muted">Submit a new employee account for your barangay. The City Administrator will review and approve it before the account becomes active.</p>
            <hr>

            <?php if(isset($_SESSION['form_success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['form_success']) ?></div>
                <?php unset($_SESSION['form_success']); ?>
            <?php endif; ?>
            <?php if(isset($_SESSION['form_error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['form_error']) ?></div>
                <?php unset($_SESSION['form_error']); ?>
            <?php endif; ?>

            <?php if (!empty($rejected_employees)): ?>
                <?php foreach ($rejected_employees as $rej): ?>
                <div class="alert alert-danger d-flex align-items-start gap-3">
                    <span style="font-size:22px;line-height:1;">❌</span>
                    <div class="flex-grow-1">
                        <strong>Account Request Rejected</strong><br>
                        <span class="text-muted" style="font-size:13px;">
                            <b><?= htmlspecialchars($rej['full_name']) ?></b> (<?= htmlspecialchars($rej['email']) ?>)
                        </span><br>
                        <b>Reason:</b> <?= htmlspecialchars($rej['rejection_reason'] ?? 'No reason provided.') ?><br>
                        <small class="text-muted">Submitted <?= date('M d, Y', strtotime($rej['created_at'])) ?></small>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <button type="button" class="btn btn-sm btn-warning fw-semibold"
                            onclick="editRejected(
                                '<?= $rej['user_id'] ?>',
                                '<?= htmlspecialchars(addslashes($rej['firstname'])) ?>',
                                '<?= htmlspecialchars(addslashes($rej['middlename'])) ?>',
                                '<?= htmlspecialchars(addslashes($rej['lastname'])) ?>',
                                '<?= htmlspecialchars(addslashes($rej['email'])) ?>',
                                '<?= htmlspecialchars(addslashes($rej['phone'] ?? '')) ?>'
                            )">
                            ✏️ Edit &amp; Resubmit
                        </button>
                        <a href="../action/dismiss_rejection?id=<?= $rej['user_id'] ?>"
                           class="btn btn-sm btn-outline-dark"
                           onclick="return confirm('Dismiss this rejection notice?')">
                            Dismiss
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-md-7">
                    <div class="card shadow-sm">
                        <div class="card-body p-4">

                            <form method="POST" action="../action/save_employee" id="createEmployeeForm">

                                <!-- Hidden fields -->
                                <input type="hidden" name="barangay" value="<?= htmlspecialchars($barangay) ?>">
                                <input type="hidden" name="user_id" id="emp_user_id" value="">

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Barangay</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($barangay) ?>" readonly disabled>
                                    <small class="text-muted">Automatically assigned to your barangay.</small>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                                        <input type="text" name="firstname" class="form-control" required placeholder="JUAN" style="text-transform:uppercase">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Middle Name</label>
                                        <input type="text" name="middlename" class="form-control" placeholder="Optional" style="text-transform:uppercase">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" name="lastname" class="form-control" required placeholder="DELA CRUZ" style="text-transform:uppercase">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required placeholder="employee@example.com">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" name="phone" id="emp_phone" class="form-control" required
                                           placeholder="09XXXXXXXXX" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11"
                                           title="Format: 09XXXXXXXXX">
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                        <input type="password" name="password" id="emp_password" class="form-control" required placeholder="Min. 6 characters">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                        <input type="password" name="confirm_password" id="emp_confirm_password" class="form-control" required placeholder="Repeat password">
                                    </div>
                                </div>

                                <div class="alert alert-info py-2 mb-3" style="font-size:13px;">
                                    ℹ The new account will be <strong>pending</strong> until the City Administrator approves it.
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-outline-primary">Submit for Approval</button>
                                    <button type="reset" class="btn btn-outline-danger">Reset</button>
                                </div>

                            </form>

                        </div>
                    </div>
                </div>
            </div>

        </div>

<!-- EMPLOYEES PANEL (active accounts) -->
<div id="panel-my-submissions" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="add-employee" data-group="accounts" onclick="showGroupTab('add-employee',this,'accounts')">Add Employee</button>
        <button class="ov-tab active" data-tab="my-submissions" data-group="accounts" onclick="showGroupTab('my-submissions',this,'accounts')">Employees</button>
        <button class="ov-tab" data-tab="pending-requests" data-group="accounts" onclick="showGroupTab('pending-requests',this,'accounts')">
            Pending Requests
            <?php if ($pending_requests_count > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?= $pending_requests_count ?></span>
            <?php endif; ?>
        </button>
        <button class="ov-tab" data-tab="residents" data-group="accounts" onclick="showGroupTab('residents',this,'accounts')">Residents</button>
        <button class="ov-tab" data-tab="profile" data-group="accounts" onclick="showGroupTab('profile',this,'accounts')">My Profile</button>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Employees</h4>
            <p class="text-muted mb-0" style="font-size:0.95rem;">Approved employee accounts for Barangay <?= htmlspecialchars($barangay) ?>.</p>
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="showGroupTab('add-employee',null,'accounts')">+ Add New</button>
    </div>

    <?php if (empty($active_employees)): ?>
        <div class="alert alert-info">No approved employee accounts yet.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle" id="empTable">
            <thead class="table-dark">
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Online</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($active_employees as $emp): ?>
            <tr>
                <td>EMP-<?= str_pad($emp['user_id'], 3, '0', STR_PAD_LEFT) ?></td>
                <td><?= htmlspecialchars($emp['full_name']) ?></td>
                <td><?= htmlspecialchars($emp['email']) ?></td>
                <td><?= htmlspecialchars(format_phone_display($emp['phone'] ?? '')) ?></td>
                <td>
                    <?php if ((string)($emp['session_id'] ?? '') === 'DEACTIVATED'): ?>
                        <span class="badge bg-danger">Deactivated</span>
                    <?php else: ?>
                        <span class="badge bg-success">Active</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((string)($emp['session_id'] ?? '') === 'DEACTIVATED'): ?>
                        <span class="text-danger">Offline</span>
                    <?php elseif (!empty($emp['session_id'])): ?>
                        <span class="text-success">Online</span>
                    <?php else: ?>
                        <span class="text-muted">Offline</span>
                    <?php endif; ?>
                </td>
                <td><small><?= date('M d, Y', strtotime($emp['created_at'])) ?></small></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- PENDING REQUESTS PANEL (pending + rejected) -->
<div id="panel-pending-requests" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="add-employee" data-group="accounts" onclick="showGroupTab('add-employee',this,'accounts')">Add Employee</button>
        <button class="ov-tab" data-tab="my-submissions" data-group="accounts" onclick="showGroupTab('my-submissions',this,'accounts')">Employees</button>
        <button class="ov-tab active" data-tab="pending-requests" data-group="accounts" onclick="showGroupTab('pending-requests',this,'accounts')">
            Pending Requests
            <?php if ($pending_requests_count > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?= $pending_requests_count ?></span>
            <?php endif; ?>
        </button>
        <button class="ov-tab" data-tab="residents" data-group="accounts" onclick="showGroupTab('residents',this,'accounts')">Residents</button>
        <button class="ov-tab" data-tab="profile" data-group="accounts" onclick="showGroupTab('profile',this,'accounts')">My Profile</button>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Pending Requests</h4>
            <p class="text-muted mb-0" style="font-size:0.95rem;">Accounts awaiting City Administrator approval, or rejected and needing correction.</p>
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="showGroupTab('add-employee',null,'accounts')">+ Add New</button>
    </div>

    <?php if (empty($pending_or_rejected_employees)): ?>
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
            </svg>No pending or rejected requests.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle" id="pendingTable">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Note</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pending_or_rejected_employees as $i => $sub): ?>
            <?php
                $statusLabel = match((int)$sub['is_verified']) {
                    0 => ['Pending', 'warning'],
                    2 => ['Rejected', 'danger'],
                    default => ['Unknown', 'secondary']
                };
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($sub['full_name']) ?></strong></td>
                <td><?= htmlspecialchars($sub['email']) ?></td>
				<td><?= htmlspecialchars(format_phone_display($sub['phone'] ?? '')) ?></td>                
                <td><span class="badge bg-<?= $statusLabel[1] ?>"><?= $statusLabel[0] ?></span></td>
                <td>
                    <?php if ((int)$sub['is_verified'] === 2 && $sub['rejection_reason']): ?>
                        <small class="text-danger"><?= htmlspecialchars($sub['rejection_reason']) ?></small>
                    <?php else: ?>
                        <small class="text-muted">Waiting for admin approval</small>
                    <?php endif; ?>
                </td>
                <td><small><?= date('M d, Y', strtotime($sub['created_at'])) ?></small></td>
                <td>
                    <?php if ((int)$sub['is_verified'] === 2): ?>
                        <button class="btn btn-warning btn-sm"
                            onclick="editRejected(
                                '<?= $sub['user_id'] ?>',
                                '', '', '',
                                '<?= htmlspecialchars(addslashes($sub['email'])) ?>',
                                '<?= htmlspecialchars(addslashes($sub['phone'] ?? '')) ?>'
                            ); showGroupTab('add-employee', null, 'accounts');">
                            Edit
                        </button>
                        <a href="../action/dismiss_rejection?id=<?= $sub['user_id'] ?>"
                           class="btn btn-outline-secondary btn-sm"
                           onclick="return confirm('Remove this rejected record?')">
                           Delete
                        </a>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:12px;">Awaiting review</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div id="panel-payments" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab active" data-tab="requests" data-group="manage" onclick="showGroupTab('requests',this,'manage')">Requests</button>
        <button class="ov-tab" data-tab="payments" data-group="manage" onclick="showGroupTab('payments',this,'manage')">Payments</button>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Payments</h4>
            <p class="text-muted mb-0">Centralized Santa Rosa collection. Barangay expected total represents 100% of the transaction amount.</p>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="panel" value="payments">
            <input type="month" name="pay_month" class="form-control form-control-sm" value="<?= htmlspecialchars($pay_month) ?>">
            <button class="btn btn-outline-dark btn-sm">View</button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4 col-12">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted">Transactions</small>
                    <div class="fs-3 fw-bold"><?= $payment_summary['transaction_count'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card shadow-sm h-100 border-primary">
                <div class="card-body">
                    <small class="text-muted">Gross Collected</small>
                    <div class="fs-5 fw-bold text-primary"><?= money_format_php($payment_summary['gross_amount']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card shadow-sm h-100 border-success">
                <div class="card-body">
                    <small class="text-muted">Barangay Net (100%)</small>
                    <div class="fs-5 fw-bold text-success"><?= money_format_php($payment_summary['net_amount']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payout Request -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold">Request Payout for <?= htmlspecialchars($pay_month) ?></span>
            <form id="payoutForm" method="POST" action="../action/request_payout" class="mb-0">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="month_year" value="<?= htmlspecialchars($pay_month) ?>">
                <?php
                $payout_disabled = ($payment_summary['net_amount'] <= 0 || $has_pending_payout);
                $payout_btn_label = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><path d="M12 2v4"/><path d="M12 18v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M16.24 16.24l2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="M4.93 19.07l2.83-2.83"/><path d="M16.24 7.76l2.83-2.83"/></svg> Request Payout';
                if ($has_pending_payout && $payout_status_for_month) {
                    $payout_btn_labels = [
                        'Pending'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Payout Pending',
                        'Approved' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Payout Approved',
                        'Released' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> Payout Released',
                    ];
                    $payout_btn_label = $payout_btn_labels[$payout_status_for_month] ?? 'Payout Requested';
                }
                ?>
                <button class="btn btn-<?= $payout_disabled ? 'secondary' : 'success' ?> btn-sm"
                    <?= $payout_disabled ? 'disabled' : '' ?>><?= $payout_btn_label ?></button>
            </form>
        </div>
    </div>

    <!-- Cash Transactions -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Cash Transactions</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Resident</th>
                            <th>Document</th>
                            <th>Gross Amount</th>
                            <th>Barangay Net</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($payment_rows_walkin)): ?>
                        <?php foreach ($payment_rows_walkin as $pay): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($pay['recorded_at'])) ?></td>
                            <td><?= htmlspecialchars($pay['full_name']) ?></td>
                            <td><?= htmlspecialchars($pay['document_type']) ?></td>
                            <td><?= money_format_php((float)$pay['gross_amount']) ?></td>
                            <td class="text-success fw-semibold"><?= money_format_php((float)$pay['barangay_net_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No cash transactions for this month.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Online Transactions -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>Online Transactions</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Resident</th>
                            <th>Document</th>
                            <th>Gross Amount</th>
                            <th>Barangay Net</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($payment_rows_online)): ?>
                        <?php foreach ($payment_rows_online as $pay): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($pay['recorded_at'])) ?></td>
                            <td><?= htmlspecialchars($pay['full_name']) ?></td>
                            <td><?= htmlspecialchars($pay['document_type']) ?></td>
                            <td><?= money_format_php((float)$pay['gross_amount']) ?></td>
                            <td class="text-success fw-semibold"><?= money_format_php((float)$pay['barangay_net_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No online transactions for this month.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payout Requests History -->
    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Payout Requests</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                        <tr>
                            <th>Month</th>
                            <th>Gross Amount</th>
                            <th>Net Amount</th>
                            <th>Status</th>
                            <th>Admin Note</th>
                            <th>Requested</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($payout_requests)): ?>
                        <?php foreach ($payout_requests as $po): ?>
                        <tr>
                            <td><?= htmlspecialchars($po['month_year']) ?></td>
                            <td><?= money_format_php((float)$po['gross_amount']) ?></td>
                            <td class="fw-semibold"><?= money_format_php((float)$po['net_amount']) ?></td>
                            <td><span class="badge bg-<?= $po['status'] === 'Approved' || $po['status'] === 'Released' ? 'success' : ($po['status'] === 'Rejected' ? 'danger' : 'warning') ?>"><?= htmlspecialchars($po['status']) ?></span></td>
                            <td><?= htmlspecialchars($po['admin_note'] ?? '') ?></td>
                            <td><?= date('M d, Y', strtotime($po['requested_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No payout requests yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

    <?php
    $doc_revenue_labels = json_encode(array_column($doc_revenue_summary, 'document_type'));
    $doc_revenue_values = json_encode(array_map('floatval', array_column($doc_revenue_summary, 'total_revenue')));
    ?>
    
<div id="panel-top-documents" class="panel">
    <div class="ov-tabs mb-4">
        <button class="ov-tab" data-tab="dashboard" data-group="overview" onclick="showEmployeeOverviewTab('dashboard',this)">Dashboard</button>
        <button class="ov-tab active" data-tab="top-documents" data-group="overview" onclick="showEmployeeOverviewTab('top-documents',this)">Top Documents</button>
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
     PANEL: DOCUMENT SETTINGS (EMPLOYEE)
     City admin defines the master list of document types
     and prerequisites; here the employee just picks which
     of those their barangay actually offers.
══════════════════════════════════════════════ -->
<div id="panel-doc-settings" class="panel">
    <h4 class="mb-1">Document Settings</h4>
    <p class="text-muted mb-4">
        Choose which documents Barangay <?= htmlspecialchars($_SESSION['barangay'] ?? '') ?> offers to residents.
        Prerequisites and pricing are managed by the City Administrator — this only controls
        whether a document shows up when a resident from your barangay files a new request.
    </p>

    <?php
        // Master list of document types (defined by City Admin via
        // document_prerequisites). Self-contained query here so this panel
        // doesn't depend on unseen data-layer variables.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS barangay_document_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                barangay VARCHAR(100) NOT NULL,
                document_type VARCHAR(150) NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_brgy_doctype (barangay, document_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $myBarangay = $_SESSION['barangay'] ?? '';

        $docTypesStmt = $pdo->query("SELECT DISTINCT document_type FROM document_prerequisites ORDER BY document_type ASC");
        $allDocTypesForPanel = $docTypesStmt->fetchAll(PDO::FETCH_COLUMN);
        $allPrereqsByDoc = get_all_prerequisites($pdo);

        $enabledStmt = $pdo->prepare("
            SELECT document_type FROM barangay_document_settings
            WHERE barangay = ? AND is_enabled = 1
        ");
        $enabledStmt->execute([$myBarangay]);
        $enabledForThisBarangay = $enabledStmt->fetchAll(PDO::FETCH_COLUMN);

        // A barangay that has never touched this panel before has no rows
        // yet in barangay_document_settings — default to "everything enabled"
        // so nothing silently disappears from residents' request form.
        $hasAnySettings = $pdo->prepare("SELECT COUNT(*) FROM barangay_document_settings WHERE barangay = ?");
        $hasAnySettings->execute([$myBarangay]);
        $barangayHasSettings = (bool) $hasAnySettings->fetchColumn();
    ?>

    <form action="../action/save_barangay_documents.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="prereq-config-card">
            <?php if (empty($allDocTypesForPanel)): ?>
                <p class="text-muted mb-0">No document types have been configured yet by the City Administrator.</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($allDocTypesForPanel as $docType): ?>
                        <?php
                            $isChecked = $barangayHasSettings
                                ? in_array($docType, $enabledForThisBarangay, true)
                                : true; // default ON until this barangay saves its own settings
                            $docPrereqs = $allPrereqsByDoc[$docType] ?? [];
                        ?>
                        <?php
                            $docFeeInfo = get_document_setting($pdo, $docType);
                            $docFeePrice = (float) ($docFeeInfo['price'] ?? $docFeeInfo['fee_amount'] ?? 0.0);
                            $docIsFree = $docFeeInfo['is_free'] ?? ($docFeePrice <= 0);
                            $docValidityMonths = (int) ($docFeeInfo['validity_months'] ?? 0);
                        ?>
                        <div class="doc-toggle-card">
                            <label class="doc-toggle-row" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                <span style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="enabled_documents[]" value="<?= htmlspecialchars($docType) ?>" <?= $isChecked ? 'checked' : '' ?>>
                                    <span class="doc-toggle-label"><?= htmlspecialchars($docType) ?></span>
                                </span>
                                <span style="display:flex;gap:6px;align-items:center;">
                                    <?php if ($docValidityMonths > 0): ?>
                                        <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:#e0e7ff;color:#3730a3;">
                                            Valid <?= $docValidityMonths ?> mo.
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:#f1f5f9;color:#475569;">
                                            No expiry
                                        </span>
                                    <?php endif; ?>
                                    <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;<?= $docIsFree ? 'background:#dcfce7;color:#166534;' : 'background:#fef3c7;color:#92400e;' ?>">
                                        <?= $docIsFree ? 'FREE' : 'PHP ' . number_format($docFeePrice, 2) ?>
                                    </span>
                                </span>
                            </label>

                            <?php if (!empty($docPrereqs)): ?>
                                <div class="doc-prereq-list">
                                    <?php foreach ($docPrereqs as $prereq): ?>
                                        <span class="prereq-tag">
                                            <?= htmlspecialchars($prereq['prereq_name']) ?>
                                            <?php if (!empty($prereq['is_required'])): ?>
                                                <span class="required-dot">*</span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="doc-prereq-empty">No prerequisite requirement listed.</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-outline-primary">Save Document Settings</button>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ══════════════════════════════════════════════
     PANEL: BARANGAY OFFICIALS (EMPLOYEE)
══════════════════════════════════════════════ -->
<div id="panel-officials" class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
        <div>
            <h4 class="mb-0">Barangay Officials</h4>
            <p class="text-muted mb-0" style="font-size:0.95rem;">Official positions for Barangay <?= htmlspecialchars($barangay) ?>.</p>
        </div>
    </div>

    <?php
    try {
        $stmt_officials = $pdo->prepare("SELECT * FROM barangay_officials WHERE barangay = ? ORDER BY FIELD(position, 'Punong Barangay', 'Sangguniang Barangay Member', 'SK Chairperson', 'Barangay Secretary', 'Barangay Treasurer') ASC");
        $stmt_officials->execute([$barangay]);
        $brgy_officials = $stmt_officials->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $brgy_officials = [];
    }
    ?>

    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Position</th>
                    <th>Full Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($brgy_officials)): ?>
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
                        <td>
							<button class="icon-action-btn icon-warning" title="Edit Official"
    							onclick="openRequestEditModal(<?= $official['id'] ?>, '<?= htmlspecialchars(addslashes($official['position'])) ?>', '<?= htmlspecialchars(addslashes($official['full_name'])) ?>')">                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 20h9"/>
                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                    </svg>
                            </button>
                        </td>


                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- MODAL START -->

<!-- Request Detail Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5>Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="reportDetails">
      </div>

    </div>
  </div>
</div>


<!-- MANUAL REQUEST MODAL -->
<div class="modal fade" id="manualRequestModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Create Manual Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form method="POST" action="../action/manual_request" id="manualRequestForm">

          <div class="row g-3">

            <!-- Full Name -->
            <div class="col-md-6">
              <label>Full Name</label>
              <input type="text" name="full_name" id="m_full_name" class="form-control" required>
            </div>

            <!-- Age -->
            <div class="col-md-3">
              <label>Age</label>
              <input type="number" name="age" id="m_age" class="form-control" required readonly>
            </div>

            <!-- Gender -->
            <div class="col-md-3">
              <label>Gender</label>
              <select name="gender" id="m_gender" class="form-control" required>
                <option value="">-- Select --</option>
                <option>Male</option>
                <option>Female</option>
              </select>
            </div>

            <div class="col-12">
              <label>Address</label>
              <div class="input-group">
                <input type="text" name="address" id="m_address" class="form-control"
                       placeholder="House No. / Street / Purok" required>
                <span class="input-group-text">BRGY. <?= htmlspecialchars($barangay) ?></span>
              </div>
            </div>

            <!-- DOB -->
            <div class="col-md-6">
              <label>Date of Birth</label>
              <input type="date" name="dob" id="m_dob" class="form-control" required>
            </div>

            <!-- Civil Status -->
            <div class="col-md-6">
              <label>Civil Status</label>
              <select name="civil_status" id="m_civil_status" class="form-control" required>
                <option value="">-- Select Civil Status --</option>
                <option>Single</option>
                <option>Married</option>
                <option>Widowed</option>
                <option>Separated</option>
              </select>
            </div>

            <!-- Residency -->
            <div class="col-md-6">
              <label>Years Residency</label>
              <input type="number" name="years_residency" id="m_residency" class="form-control" required>
            </div>


            <!-- Document Type -->
            <div class="col-md-6">
              <label>Document Type</label>
              <select name="document_type" id="m_doc_type" class="form-control" required>
                <option value="">-- Select --</option>
                <?php foreach ($available_document_types as $docType): ?>
                  <option value="<?= htmlspecialchars($docType) ?>"><?= htmlspecialchars($docType) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Custom Document -->
            <div class="col-12" id="m_custom_doc_div" style="display:none;">
              <label>Specify Document</label>
              <input type="text" name="custom_document" id="m_custom_doc" class="form-control">
            </div>

            <!-- Purpose -->
            <div class="col-12">
              <label>Purpose</label>
              <input type="text" name="purpose" id="m_purpose" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label>Payment Amount</label>
              <div class="input-group">
                <input type="number" name="payment_amount" id="m_payment_amount" class="form-control" min="0" step="0.01" required>
                <button type="button" class="btn btn-outline-success" id="m_discount_toggle" title="Apply 20% PWD/Senior Discount">
                  🏷️ PWD/Senior
                </button>
              </div>
              <small class="text-success" id="m_discount_note" style="display:none;">20% PWD/Senior discount applied.</small>
            </div>

            <!-- CONFIRMATION -->
            <div class="col-12 mt-2">
              <label>
                <input type="checkbox" name="confirm" required>
                I confirm that all the information is true and correct.
              </label>
            </div>

          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="submit" form="manualRequestForm" class="btn btn-outline-primary">
          Save Manual Request
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Request Edit Modal -->
<div class="modal fade" id="requestEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="../action/request_official_edit">
            <div class="modal-header">
                <h5 class="modal-title">Request Official Edit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="official_id" id="req_off_id">
                <p>Propose changes for: <strong id="req_off_pos"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Proposed Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="proposed_name" id="req_off_name" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-outline-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

</div><!-- /main-content -->
<?php include __DIR__ . '/../partials/quick_add_modal.php'; ?>
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
$welcomeRole = htmlspecialchars($roleLabels[$_SESSION['user_type'] ?? 'employee'] ?? 'User');
$userType = $_SESSION['user_type'] ?? 'employee';
$barangayLabel = !empty($_SESSION['barangay']) ? htmlspecialchars($_SESSION['barangay']) : null;
if ($userType === 'city_administrator') {
    $barangayLabel = 'Santa Rosa Ibaba 2';
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
                    <p class="text-muted mb-3">Barangay <?= $barangayLabel ?></p>
                <?php endif; ?>
                <button type="button" class="btn btn-primary btn-block px-4" data-bs-dismiss="modal">Continue to dashboard</button>
            </div>
        </div>
    </div>
</div>

<style>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" defer></script>
<script src="../js/session_guard.js"></script>
<script src="../js/heartbeat.js"></script>
<script src="../js/alert_autodismiss.js"></script>
<script>
window.employeeDashboardConfig = {
    chartDocLabels: <?= $rpt_doc_labels ?>,
    chartDocData: <?= $rpt_doc_data ?>,
    chartDailyLabels: <?= $rpt_daily_labels ?>,
    chartDailyData: <?= $rpt_daily_data ?>,
    initialPanel: <?= json_encode($_GET['panel'] ?? '') ?>,
    showWelcome: <?= !empty($showWelcome) ? 'true' : 'false' ?>,
    docRevenueLabels: <?= $doc_revenue_labels ?>,
    docRevenueValues: <?= $doc_revenue_values ?>
};

const documentFees = <?= json_encode($document_settings ?? []) ?>;
    
function applyDashboardFilter() {
    const year = document.getElementById('filterYear').value;
    const month = document.getElementById('filterMonth').value;
    const monthSelect = document.getElementById('filterMonth');

    // Disable month if no year selected
    monthSelect.disabled = !year;
    if (!year) monthSelect.value = '';

    // Build URL — always send filter_year explicitly, even when empty,
    // so "All Years" is recognized as a deliberate choice by the backend
    // instead of being treated as "no filter set" (which now defaults to
    // the current month). Same for filter_month.
    let url = '?panel=dashboard';
    url += '&filter_year=' + encodeURIComponent(year);
    url += '&filter_month=' + encodeURIComponent(year ? month : '');

    // Submit form
    window.location.href = url;
}
</script>
    
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mDocType = document.getElementById('m_doc_type');
    const mPaymentAmount = document.getElementById('m_payment_amount');
    const mDiscountBtn = document.getElementById('m_discount_toggle');
    const mDiscountNote = document.getElementById('m_discount_note');

    let discountActive = false;
    let priceBeforeDiscount = null;

    function resetDiscountState() {
        discountActive = false;
        priceBeforeDiscount = null;
        if (mDiscountBtn) {
            mDiscountBtn.classList.remove('btn-success');
            mDiscountBtn.classList.add('btn-outline-secondary');
        }
        if (mDiscountNote) mDiscountNote.style.display = 'none';
    }

    function fillPriceFromDocType() {
        if (!mDocType || !mPaymentAmount) return;
        const selected = mDocType.value;
        const setting = typeof documentFees !== 'undefined' ? documentFees[selected] : null;

        // Reset discount whenever the document changes — 20% should
        // apply fresh to whatever price this newly selected document has.
        resetDiscountState();

        if (!setting) {
            // Custom/"Other" document — no known fee, leave blank for manual entry.
            mPaymentAmount.value = '';
            return;
        }

        const price = parseFloat(setting.price ?? setting.fee_amount ?? 0);
        mPaymentAmount.value = price.toFixed(2);
    }

    function toggleDiscount() {
        if (!mPaymentAmount) return;
        const currentValue = parseFloat(mPaymentAmount.value || '0');

        if (!discountActive) {
            priceBeforeDiscount = currentValue;
            mPaymentAmount.value = (currentValue * 0.8).toFixed(2);
            discountActive = true;
            if (mDiscountBtn) {
                mDiscountBtn.classList.remove('btn-outline-secondary');
                mDiscountBtn.classList.add('btn-success');
            }
            if (mDiscountNote) mDiscountNote.style.display = 'block';
        } else {
            mPaymentAmount.value = (priceBeforeDiscount !== null ? priceBeforeDiscount : currentValue / 0.8).toFixed(2);
            resetDiscountState();
        }
    }

    if (mDocType) mDocType.addEventListener('change', fillPriceFromDocType);
    if (mDiscountBtn) mDiscountBtn.addEventListener('click', toggleDiscount);
});
</script>
    
<script src="../js/employee_dashboard.js?v=<?= time() ?>"></script>
<script src="../js/quick_add.js?v=<?= time() ?>"></script>
<script src="../js/notifications.js"></script>
</body>
</html>