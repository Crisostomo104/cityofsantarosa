<?php 
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../data/resident_data.php';
require_once __DIR__ . '/../core/archiving.php';
run_expiry_check($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Resident - Santa Rosa</title>
<link rel="icon" href="../images/ibaba2.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../css/dashboard.css?v=3">
<!-- <link rel="stylesheet" href="../css/resident.css">
<link rel="stylesheet" href="../css/shared_dashboard.css"> -->
<link rel="stylesheet" href="../css/prerequisites.css">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" defer></script>
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
        <img src="../images/logos/erequest-logo.png" alt="eRequest Mo">
    </div>
    <div class="d-flex align-items-center gap-2">
        
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

<!-- Sidebar backdrop overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

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
            Resident of
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
    
    <a href="#" class="active" onclick="showPanel('requests',this)">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="8" y="2" width="8" height="4" rx="1"/>
            <path d="M9 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-3"/>
            <path d="M9 12h6M9 16h6"/>
        </svg>
        <span>Requests Here</span>
    </a>

    <br>

    <a href="#" onclick="showPanel('announcements',this)">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 11v2l14-2V7L3 9v2z"/>
            <path d="M17 7l4-2v12l-4-2"/>
            <path d="M7 13l1 5"/>
        </svg>
        <span>Announcements</span>

        <?php if (!empty($res_announcements)): ?>
            <span class="pending-badge"><?= count($res_announcements) ?></span>
        <?php endif; ?>
    </a>

    <br>

    <a href="#" onclick="showPanel('officials',this)">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 10l9-6 9 6"/>
            <path d="M5 10v9M10 10v9M14 10v9M19 10v9"/>
            <path d="M2 19h20"/>
        </svg>
        <span>Barangay Officials</span>
    </a>

    <br>

    <a href="#" onclick="showPanel('settings',this)">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9A1.7 1.7 0 0 0 10 3.2V3a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.4 1z"/>
        </svg>
        <span>Settings</span>
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

<!-- MAIN CONTENT -->
<div class="main-content">

<!-- ══ PANEL: MY REQUESTS ══ -->
<div id="panel-requests" class="panel active">

    <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">
                Your Requests</h4>
            <p class="text-muted mb-0" style="font-size:13px;">
                Barangay <?= htmlspecialchars(ucwords(strtolower($_SESSION['barangay'] ?? ''))) ?>
            </p>
        </div>
        <button class="btn btn-sm new-request-btn" style="border:1px solid #8E1B1B; color:#8E1B1B; background:transparent;" id="openModalBtn">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14"/>
                <path d="M5 12h14"/>
            </svg>
            <span id="newRequestLabel">New Request</span>
            <span id="requestCooldownStatus" class="badge rounded-pill bg-warning text-dark ms-1" style="display:none; font-size:11px; font-weight:700;"></span>
        </button>
    </div>
    <div id="requestCooldownAlert" class="alert alert-warning mt-3 mb-0 request-cooldown-alert" role="status" aria-live="polite" style="display:none;">
        New Request, Edit Request, and Request Again will be available in <strong id="requestCooldownCountdown"></strong>.
    </div>

    <?php if($message): ?>
    <div class="alert alert-success alert-dismissible fade show mt-3">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($res_announcements)): ?>
    <div class="alert alert-info alert-dismissible fade show mt-2" style="font-size:13px;">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <strong>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    Announcement:
                </strong>
                <?= htmlspecialchars($res_announcements[0]['title']) ?> &mdash;
                <?= htmlspecialchars(mb_strimwidth($res_announcements[0]['body'], 0, 150, '...')) ?>
                <?php if (count($res_announcements) > 1): ?>
                    <br><a href="#" onclick="showPanel('announcements');return false;" style="font-size:12px;">View all <?= count($res_announcements) ?> announcements →</a>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;"></button>
        </div>
    </div>
    <?php endif; ?>


    <?php if(!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show mt-3">
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if(!empty($_SESSION['flash_info'])): ?>
    <div class="alert alert-info alert-dismissible fade show mt-3">
        <?= htmlspecialchars($_SESSION['flash_info']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_info']); ?>
    <?php endif; ?>

    <!-- FILTERS -->
    <div class="d-flex flex-wrap gap-2 align-items-center mt-3 mb-3">
        <select id="statusSelect" class="form-select form-select-sm w-auto">
            <option value="">All Status</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="To be Claim">To be Claim</option>
            <option value="Completed">Completed</option>
            <option value="Rejected">Rejected</option>
            <option value="Expired">Expired</option>
        </select>
        <select id="dateSelect" class="form-select form-select-sm w-auto">
            <option value="">All Dates</option>
            <option value="Today">Today</option>
            <option value="Yesterday">Yesterday</option>
            <option value="Last 7 Days">Last 7 Days</option>
            <option value="This Month">This Month</option>
        </select>
        <select id="documentSelect" class="form-select form-select-sm w-auto">
            <option value="">All Documents</option>
            <?php foreach ($available_document_types as $docType): ?>
                <option value="<?= htmlspecialchars($docType) ?>"><?= htmlspecialchars($docType) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-dark" id="openNoticeBtn">Requirements</button>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle" id="requestsTable">
                <thead>
                    <tr>
                        <th>Request No.</th>
                        <th>Full Name</th>
                        <th>Document</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Reason</th>
                        <th>Date Requested</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($requests): ?>
                        <?php foreach($requests as $req):
                            $status = strtolower($req['status']);
                            $statusClass = strtolower(str_replace(' ', '-', (string) $req['status']));
                            $paymentReferenceTitle = strtolower(trim((string) ($req['preferred_payment_method'] ?? ''))) === 'walk-in'
                                ? (!empty($req['receipt_number']) ? 'Receipt No: ' . $req['receipt_number'] : 'Receipt not issued')
                                : (!empty($req['payment_reference']) ? 'Reference No: ' . $req['payment_reference'] : 'No Reference Number'); ?>
                        <tr class="request-row"
                            data-status="<?= strtolower($req['status'] ?? '') ?>"
                            data-doc="<?= htmlspecialchars($req['document_type'] ?? '') ?>"
                            data-date="<?= $req['created_at'] ?? '' ?>"
                            data-is-deleted="<?= $req['is_deleted'] ?? 0 ?>"
                            style="<?= !empty($req['is_deleted']) ? 'display:none;' : '' ?>">

                            <td data-label="Request ID">
                                <span style="font-size:13px; font-weight:700; letter-spacing:0.3px; color:#8E1B1B;">
                                    <?php
                                    if(!empty($req['request_id'])) {
                                        $requestDate = date('Ymd', strtotime($req['created_at']));
                                        $userId = $req['user_id'] ?? '0';
                                        $reqId = str_pad($req['request_id'], 3, '0', STR_PAD_LEFT);
                                        echo 'REQ'.htmlspecialchars($requestDate.$userId.' - '.$reqId);
                                    } else { echo '-'; }
                                    ?>
                                </span>
                            </td>
                            <td data-label="Name" class="request-name-cell"><?= htmlspecialchars(request_display_name($req['full_name'] ?? '', $req['relationship'] ?? '')) ?></td>
                            <td data-label="Document"><?= htmlspecialchars($req['document_type']) ?></td>

                            <td data-label="Status">
                                <?php
                                    // Pending -> Approved -> To be Claim -> Completed | Pending -> Rejected
                                    $isRej  = ($status === 'rejected');
                                    $labels = $isRej ? ['Pending', 'Rejected'] : ['Pending', 'Approved', 'To be Claim', 'Completed'];
                                    $cur = match ($status) {
                                        'approved' => 1,
                                        'to be claim' => 2,
                                        'completed', 'expired' => 3,
                                        'rejected' => 1,
                                        default => 0,
                                    };
                                ?>
                                <div class="status-cell">
                                    <span class="status-pill status-<?= htmlspecialchars($statusClass) ?>">
                                        <?= htmlspecialchars($req['status']) ?>
                                    </span>
                                    <?php if (!empty($req['duplicate_of_request_id'])): ?>
                                        <span class="badge bg-warning text-dark">Duplicate</span>
                                    <?php endif; ?>
                                    <span class="req-steps s-<?= htmlspecialchars($statusClass) ?>" role="img"
                                          aria-label="Status: <?= htmlspecialchars($req['status']) ?>">
                                        <?php foreach ($labels as $i => $label):
                                            $cls = $i < $cur ? 'done' : ($i === $cur ? 'current' : 'todo'); ?>
                                            <i class="<?= $cls ?>" title="<?= $label ?>"></i>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                            </td>

                            <td data-label="Payment">
                                <span style="font-weight: 600;" 
                                    title="<?= htmlspecialchars($paymentReferenceTitle, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(payment_method_label($req['preferred_payment_method'], $req['payment_status'] ?? null)) ?>
                                </span>
                                <?php if (strtolower(trim((string)($req['payment_status'] ?? ''))) !== 'free'): ?>
                                <span style="font-size: 12px;">
                                    (<?= htmlspecialchars(ucfirst($req['payment_status'])) ?>)
                                </span>
                                <?php endif; ?>
                                <?php
                                    $refundRequested = !empty($req['refund_requested_at']);
                                    $refundStatus = strtolower(trim((string)($req['refund_status'] ?? 'none')));
                                    $requestStatus = strtolower(trim((string)($req['status'] ?? '')));

                                    $refundRejectedByFinalStatus = $refundRequested && in_array($requestStatus, ['approved', 'completed'], true);

                                    if ($refundRejectedByFinalStatus):
                                ?>
                                    <br><span class="badge bg-danger" style="font-size:10px;">Refund Rejected</span>
                                <?php elseif ($refundRequested && $requestStatus === 'rejected' && !in_array($refundStatus, ['approved', 'denied', 'completed', 'failed'], true)): ?>
                                    <br><span class="badge bg-warning text-dark" style="font-size:10px;">Refund Requested</span>
                                <?php elseif ($refundStatus === 'completed'): ?>
                                    <br><span class="badge bg-success" style="font-size:10px;">Refunded</span>
                                <?php elseif ($refundStatus === 'failed' || $refundStatus === 'denied'): ?>
                                    <br><span class="badge bg-danger" style="font-size:10px;">Refund Denied</span>
                                <?php endif; ?>
                                
                                <?php if (!empty($req['pwd_senior_requested'])): ?>
                                    <?php if (!empty($req['pwd_senior_verified'])): ?>
                                        <br><span class="badge bg-success" style="font-size:10px;">🏷️ PWD/Senior -20% Applied</span>
                                    <?php elseif (!empty($req['pwd_senior_rejected'])): ?>
                                        <br><span class="badge bg-danger" style="font-size:10px;">🏷️ PWD/Senior Discount Rejected</span>
                                    <?php else: ?>
                                        <br><span class="badge bg-warning text-dark" style="font-size:10px;">🏷️ PWD/Senior Requested</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td data-label="Reason">
                            <?php if($status === 'rejected' && !empty($req['rejection_reason'])): ?>
                                <span class="text-danger" style="font-size:12px;"><?= htmlspecialchars($req['rejection_reason']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                            </td>

                            <td data-label="Date"><small><?= date("M d, Y", strtotime($req['created_at'])) ?></small></td>

                            <td class="actions-cell">
                                <button class="icon-action-btn icon-primary view-btn" title="View Details"
                                    data-id="<?= htmlspecialchars($req['request_id']) ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <span class="btn-label">View Details</span>
                                </button>

                                <?php if(!empty($req['is_deleted'])): ?>
                                    <span class="text-danger" style="font-size:11px;">Archived</span>
                                <?php elseif(in_array($status, ['pending', 'rejected'])): ?>
                                    <button class="icon-action-btn icon-warning edit-btn" title="Edit Request"
                                        data-id="<?= htmlspecialchars($req['request_id']) ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 20h9"/>
                                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                        </svg>
                                        <span class="btn-label">Edit Request</span>
                                    </button>
                                <?php endif; ?>

                                <?php if ($status === 'completed'): ?>
                                    <button class="icon-action-btn icon-info request-again-btn" title="Request Again"
                                        data-id="<?= htmlspecialchars($req['request_id']) ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 2v6h6"/>
                                            <path d="M3 13a9 9 0 1 0 2.7-7.7L3 8"/>
                                        </svg>
                                        <span class="btn-label">Request Again</span>
                                    </button>
                                <?php endif; ?>

                                <?php if( $status === 'rejected' && ($req['payment_status'] ?? '') === 'paid' && ($req['refund_status'] ?? 'none') === 'none' && empty($req['refund_requested_at'])): ?>
                                    <button class="icon-action-btn icon-success request-refund-btn" title="Request Refund"
                                        data-id="<?= htmlspecialchars($req['request_id']) ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 5v14"/><path d="M5 12h14"/>
                                        </svg>
                                        <span class="btn-label">Request Refund</span>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="table-card-footer d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-3">
            <div class="text-muted small">
                <?php if ($total_requests > 15): ?>
                    Showing the last 15 requests
                <?php else: ?>
                    Showing <?= $total_requests ?> of <?= $total_requests ?> requests
                <?php endif; ?>
            </div>

            <div class="mobile-see-more-wrapper">
                <button id="loadMoreRequestsBtn" class="btn btn-sm btn-outline-dark">Show more requests</button>
            </div>
            </div>
        </div>
    </div>

</div><!-- /panel-requests -->

<!-- ══ PANEL: ANNOUNCEMENTS ══ -->
<div id="panel-announcements" class="panel">
    <h4 class="mb-1">Announcements</h4>
    <p class="text-muted mb-3">Latest updates from the City of Santa Rosa.</p>

    <?php if (empty($res_announcements)): ?>
        <div class="text-center text-muted py-5">
            <div style="font-size:48px;margin-bottom:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            </div>
            <p class="mb-0">No announcements at this time.</p>
            <small>Check back later for updates from your barangay.</small>
        </div>
    <?php else: ?>
        <div class="row g-3">
        <?php foreach ($res_announcements as $ann): ?>
            <div class="col-12">
                <div class="card shadow-sm">
                    <?php if (!empty($ann['image'])): ?>
                        <img src="../<?= htmlspecialchars($ann['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($ann['title']) ?>" style="max-height:280px;object-fit:cover;" loading="lazy" width="800">
                    <?php endif; ?>
                    <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($ann['title']) ?></h6>
                        <div style="white-space:pre-wrap;font-size:14px;color:#374151;line-height:1.6;">
                            <?= nl2br(htmlspecialchars($ann['body'])) ?>
                        </div>
                        <hr style="margin:10px 0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">City of Santa Rosa</small>
                            <small class="text-muted">
                                <?= date('M j, Y', strtotime($ann['created_at'])) ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div><!-- /panel-announcements -->

<!-- ══════════════════════════════════════════════
     PANEL: BARANGAY OFFICIALS (RESIDENT)
══════════════════════════════════════════════ -->
<div id="panel-officials" class="panel">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Barangay Officials</h4>
            <p class="text-muted mb-0" style="font-size:13px;">Official positions for Barangay <?= htmlspecialchars($barangay) ?>.</p>
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
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Position</th>
                    <th>Full Name</th>
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
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══ PANEL: SETTINGS ══ -->
<div id="panel-settings" class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Settings</h4>
            <p class="text-muted mb-0" style="font-size:13px;">Manage your account details and recent login activity.</p>
        </div>
    </div>

    <div class="ov-tabs settings-nav mb-4">
        <button class="ov-tab active" type="button" data-setting-tab="profile">
             My Profile
        </button>
        <button class="ov-tab" type="button" data-setting-tab="logs"> Login Logs
        </button>
    </div>

    <div id="settings-profile" class="settings-tab-panel">
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
                        <?= 'RES-' . date("Ymd", strtotime($registered_created_at)).str_pad(htmlspecialchars($user_id), 3, '0', STR_PAD_LEFT) ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Full Name:</strong><br>
                        <?= htmlspecialchars($registered_fullname) ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Email:</strong><br>
                        <?= htmlspecialchars($registered_email) ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Contact Number:</strong><br>
                        +<?= htmlspecialchars($registered_phone) ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Date Registered:</strong><br>
                        <?= date("M d, Y", strtotime($registered_created_at)) ?>
                    </div>
                </div>
                <hr>
                <button class="btn btn-warning btn-sm" id="resetBtn">Forgot Password</button>
            </div>
        </div>
    </div>

    <div id="settings-logs" class="settings-tab-panel" style="display:none;">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Login Activity</h5>
                    <small class="text-muted">Latest 5 sessions</small>
                </div>
                <div id="logsContent" class="small text-muted">
                    <p class="mb-0">Loading logs...</p>
                </div>
            </div>
        </div>
    </div>
</div><!-- /panel-settings -->

</div><!-- /main-content -->

<!-- NOTICE MODAL -->
<div id="noticeModal">
    <div class="modal-content">
        <button class="close-btn" id="closeNoticeBtn">&times;</button>
        <h3 style="text-align:center;margin-bottom:15px;">Requirements</h3>
        <p style="font-size:13px;color:#555;margin-bottom:15px;">Requirements needed for each document type:</p>
        <?php if (!empty($document_requirements) && is_array($document_requirements)): ?>
            <?php foreach ($document_requirements as $docType => $items): ?>
                <?php
                    $docFee = $document_settings[$docType] ?? null;
                    $docPrice = (float) ($docFee['price'] ?? $docFee['fee_amount'] ?? 0.0);
                    $isFreeDoc = empty($docFee) || $docPrice <= 0;
                ?>
                <?php $docValidityMonths = (int) ($docFee['validity_months'] ?? 0); ?>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:8px;">
                    <b><?= htmlspecialchars($docType) ?></b>
                    <span style="display:flex;gap:6px;">
                        <?php if ($docValidityMonths > 0): ?>
                            <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:#e0e7ff;color:#3730a3;">
                                Valid <?= $docValidityMonths ?> mo.
                            </span>
                        <?php endif; ?>
                        <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;<?= $isFreeDoc ? 'background:#dcfce7;color:#166534;' : 'background:#fef3c7;color:#92400e;' ?>">
                            <?= $isFreeDoc ? 'FREE' : 'PHP ' . number_format($docPrice, 2) ?>
                        </span>
                    </span>
                </div>
                <ul>
                    <?php foreach ($items as $item): ?>
                        <li><?= htmlspecialchars($item['prereq_name']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No requirements are configured yet.</p>
        <?php endif; ?>
    </div>
</div>


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

<!-- CREATE A REQUEST -->
<div class="custom-modal" id="requestModal">
    <div class="modal-content">
        <span class="close-btn" id="closeModalBtn">&times;</span>
        <form method="POST" id="requestForm" enctype="multipart/form-data">
            <input type="hidden" name="request_id" id="request_id" value="">

            <!-- Full Name -->
            <div class="form-group">
                <label>Name on Document</label>
                <div class="form-group" style="display: inline-flex; align-items: center; gap: 5px; margin-bottom: 8px;">
                    <input type="checkbox" id="useRegisteredName">
                    <label for="useRegisteredName" style="margin:0; cursor:pointer; color: gray; white-space: nowrap;">
                        <small>Use my registered name</small>
                    </label>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label for="requestFirstName">First Name</label>
                        <input type="text" name="request_firstname" id="requestFirstName" placeholder="JUAN" autocomplete="given-name" required>
                    </div>
                    <div class="col-sm-6">
                        <label for="requestMiddleName">Middle Name / Initial <small class="text-muted">(optional)</small></label>
                        <input type="text" name="request_middlename" id="requestMiddleName" placeholder="Middle name or initial" autocomplete="additional-name">
                    </div>
                    <div class="col-sm-8">
                        <label for="requestLastName">Last Name</label>
                        <input type="text" name="request_lastname" id="requestLastName" placeholder="DELA CRUZ" autocomplete="family-name" required>
                    </div>
                    <div class="col-sm-4">
                        <label for="requestSuffix">Suffix <small class="text-muted">(optional)</small></label>
                        <select name="request_suffix" id="requestSuffix">
                            <option value="">None</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="II">II</option>
                            <option value="III">III</option>
                            <option value="IV">IV</option>
                            <option value="V">V</option>
                        </select>
                    </div>
                </div>
                <label for="fullNamePreview" class="mt-2">Full Name Preview</label>
                <input type="text" id="fullNamePreview" readonly aria-live="polite" placeholder="Your document name will appear here">
                <input type="hidden" name="full_name" id="full_name">
            </div>

            <div class="form-group relationship-field relationship-field--hidden" id="relationshipGroup" aria-hidden="true">
                <div class="relationship-field__content">
                    <label for="relationship">Relationship to registered resident</label>
                    <select name="relationship" id="relationship" disabled>
                        <option value="">-- Select Relationship --</option>
                        <?php foreach (request_relationship_options() as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group animated-field animated-field--hidden" id="authorizationLetterGroup" aria-hidden="true">
                <div class="animated-field__content">
                    <label for="authorizationLetter">Authorization Letter</label>
                    <input type="file" name="authorization_letter" id="authorizationLetter" accept="image/jpeg,image/png,image/webp,application/pdf" disabled>
                    <small class="text-muted">Required when requesting for another person. JPG, PNG, WebP, or PDF • Max 5MB.</small>
                    <div id="authorizationLetterExisting" class="mt-2"></div>
                    <small id="authorizationLetterSelectedFile" class="text-muted" aria-live="polite"></small>
                </div>
            </div>

            <!-- Gender -->
            <div class="form-group">
                <label>Gender</label>
                <select name="gender" id="gender" required>
                    <option value="">-- Select Gender --</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>

            <!-- Date of Birth -->
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob" id="dob" required>
            </div>

            <!-- Age (auto-calculated) -->
            <div class="form-group">
                <label>Age</label>
                <input type="number" name="age" id="age" readonly>
            </div>

            <!-- Civil Status -->
            <div class="form-group">
                <label>Civil Status</label>
                <select name="civil_status" id="civil_status" required>
                    <option value="">-- Select Civil Status --</option>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Widowed">Widowed</option>
                    <option value="Separated">Separated</option>
                </select>
            </div>

            <!-- Address -->
            <div class="form-group">
                <label>Address</label>

                <div style="display:flex; gap:5px;">
                    <input type="text" id="addressBarangayPrefix" value="Brgy.<?= ucwords(strtolower($_SESSION['barangay'] ?? '')) ?>" readonly>

                    <input type="text" name="address" id="address"
                           placeholder="Enter house / street"
                           required>

                </div>
            </div>

            <!-- Contact Number -->
            <div class="form-group">
                <label>Contact Number</label>
                <div class="form-group" style="display: inline-flex; align-items: center; gap: 5px; margin-bottom: 8px;">
                    <input type="checkbox" id="useRegisteredNumber" />
                    <label for="useRegisteredNumber" style="margin: 0; font-weight: 600; cursor: pointer; white-space: nowrap;color: gray;"><small>Use my registered number</small>
                    </label>
                </div>
                <input type="tel" name="contact_number" id="contact_number" placeholder="09XXXXXXXXX" pattern="^(09|\+639)\d{9}$" required>
                <small>Format: 09XXXXXXXXX or +639XXXXXXXXX</small>
            </div>

            <!-- Years of Residency -->
            <div class="form-group">
                <label>Years of Residency</label>
                <input type="number" name="years_residency" id="years_residency" min="0" required>
            </div>

            <!-- Document Type -->
            <div class="form-group">
                <label>Document Type</label>
                <select name="document_type" id="document_type" required>
                    <option value="">-- Select Document --</option>
                    <?php foreach ($available_document_types as $docType): ?>
                        <option value="<?= htmlspecialchars($docType) ?>"><?= htmlspecialchars($docType) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="docPriceDisplay" style="display:none;margin-top:6px;font-size:13px;font-weight:600;"></div>
            </div>

            <div class="form-group" id="customDocumentDiv" style="display:none;">
                <label>Specify Document</label>
                <input type="text" name="custom_document" id="custom_document" placeholder="Enter document name">
            </div>

            <!-- Purpose -->
            <div class="form-group">
                <label for="purpose">Purpose</label>
                <select name="purpose" id="purpose" required>
                    <option value="">-- Select Purpose --</option>
                    <?php foreach (request_purpose_options() as $purposeValue => $purposeLabel): ?>
                        <option value="<?= htmlspecialchars($purposeValue, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($purposeLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group animated-field animated-field--hidden" id="purposeOtherGroup" aria-hidden="true">
                <div class="animated-field__content">
                    <label for="purposeOther">Specific Purpose (for Others)</label>
                    <input type="text" name="purpose_other" id="purposeOther" maxlength="220" placeholder="Enter specific purpose">
                    <small class="text-muted">Required when “Others (specify)” is selected.</small>
                </div>
            </div>
            <div class="form-group animated-field animated-field--hidden" id="purposeRenewalGroup" aria-hidden="true">
                <div class="animated-field__content">
                    <label for="purposeRenewalReference">Old number / last registered</label>
                    <input type="text" name="purpose_renewal_reference" id="purposeRenewalReference" maxlength="220" placeholder="Enter old number or last registered details">
                </div>
            </div>

            <!-- Payment Method -->
            <div class="form-group">
                <label>Payment Method</label>
                <select name="preferred_payment_method" id="preferred_payment_method" required>
                    <option value="">-- Select Payment Method --</option>
                    <option value="walk-in">Walk-in Payment</option>
                    <!-- <option value="online">Pay Online</option> -->
                </select>
                <small class="text-muted">Choose walk-in if you will pay at the barangay office. Choose pay online to select a provider below.</small>
                <input type="hidden" name="online_payment_method" id="online_payment_method" value="">
            </div>
            
            <!-- PWD/Senior Discount (only shown if enabled for the selected document type) -->
            <div class="form-group" id="pwdSeniorGroup" style="display:none;border:1px solid #fde68a;background:#fffbeb;padding:10px 12px;border-radius:8px;">
                <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="pwd_senior_declared" id="pwd_senior_declared" value="1" style="margin-top:3px;">
                    <span>I am a Senior Citizen / PWD and would like to avail the 20% discount</span>
                </label>
                <div id="pwdSeniorUploadWrap" style="display:none;margin-top:8px;">
                    <label>Upload PWD/Senior Citizen ID</label>
                    <input type="file" name="pwd_senior_id_file" id="pwd_senior_id_file" accept="image/*,.pdf">
                    <small class="text-muted d-block mt-1">Your discount will be reviewed and applied by barangay staff before you settle payment.</small>
                </div>
            </div>

            <!-- Online Payment Provider Picker (shown when "Pay Online" is selected) -->
            <div class="form-group" id="providerPickerGroup" style="display:none;">
                <label>Select Payment Provider</label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:6px;">

                    <label class="provider-card" data-provider="gcash" style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all .2s;background:#fff;">
                        <input type="radio" name="_provider_radio" value="gcash" style="display:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#007bff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        <div>
                            <div style="font-weight:700;font-size:14px;color:#007bff;">GCash</div>
                            <div style="font-size:11px;color:#64748b;">Mobile wallet</div>
                        </div>
                    </label>

                    <label class="provider-card" data-provider="paymaya" style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all .2s;background:#fff;">
                        <input type="radio" name="_provider_radio" value="paymaya" style="display:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#00b4d8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        <div>
                            <div style="font-weight:700;font-size:14px;color:#00b4d8;">Maya</div>
                            <div style="font-size:11px;color:#64748b;">PayMaya wallet</div>
                        </div>
                    </label>

                    <label class="provider-card" data-provider="paypal" style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all .2s;background:#fff;">
                        <input type="radio" name="_provider_radio" value="paypal" style="display:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#003087" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11l5-5 5 5"/><path d="M12 6v12"/><circle cx="12" cy="12" r="10"/></svg>
                        <div>
                            <div style="font-weight:700;font-size:14px;color:#003087;">PayPal</div>
                            <div style="font-size:11px;color:#64748b;">International</div>
                        </div>
                    </label>

                    <label class="provider-card" data-provider="card" style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all .2s;background:#fff;">
                        <input type="radio" name="_provider_radio" value="card" style="display:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/><line x1="7" y1="15" x2="13" y2="15"/></svg>
                        <div>
                            <div style="font-weight:700;font-size:14px;color:#6366f1;">Card</div>
                            <div style="font-size:11px;color:#64748b;">Visa / Mastercard</div>
                        </div>
                    </label>

                </div>
            </div>

            <input type="hidden" name="submission_key" id="submission_key" value="">
            <input type="hidden" name="confirm_duplicate_request" id="confirm_duplicate_request" value="0">
            <input type="hidden" name="replace_duplicate_request" id="replace_duplicate_request" value="0">

            <!-- CHECKBOX -->
            <div class="form-group">
                <label class="confirmation-label">
                <input type="checkbox" name="confirm_correct" required>
                I confirm that all the information I provided is true and correct.
                </label>
            </div>

            <div id="cooldownHint" class="alert alert-warning d-none" role="alert" style="font-size:13px; margin-bottom:12px;"></div>
            <button type="submit" class="btn btn-primary" id="submitBtn">Submit Request</button>

        </form>
    </div>
</div>

<div class="custom-modal" id="duplicateRequestModal" style="display:none;">
    <div class="modal-content" style="max-width:460px;">
        <span class="close-btn" data-close-duplicate-modal>&times;</span>
        <h5 class="mb-3" style="font-weight:700; color:#8E1B1B;">Existing Request Detected</h5>
        <p class="mb-3" id="duplicateRequestMessage" style="line-height:1.5;">You already have an active request for this document. Replace it with these details, or create a separate request?</p>
        <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="duplicateRequestCancelBtn">Cancel</button>
            <button type="button" class="btn btn-outline-danger btn-sm" id="duplicateRequestReplaceBtn">Replace Existing</button>
            <button type="button" class="btn btn-primary btn-sm" id="duplicateRequestSeparateBtn">Create Separate</button>
        </div>
    </div>
</div>

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
$welcomeRole = htmlspecialchars($roleLabels[$_SESSION['user_type'] ?? 'resident'] ?? 'User');
$userType = $_SESSION['user_type'] ?? 'resident';
$paidRequestIds = [];
foreach ($requests as $req) {
    if (($req['payment_status'] ?? '') === 'paid') {
        $paidRequestIds[] = (int)($req['request_id'] ?? 0);
    }
}
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

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
    <p>Submitting request...</p>
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

<!-- JAVASCRIPT -->
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
<script src="../js/session_guard.js"></script>
<script src="../js/heartbeat.js"></script>
<script src="../js/alert_autodismiss.js"></script>
<script>
    const userEmail = "<?= $registered_email ?>";
    const userId = "<?= $user_id ?>";
    const registeredNameParts = <?= json_encode([
        'firstname' => $user_data['firstname'] ?? '',
        'middlename' => $user_data['middlename'] ?? '',
        'lastname' => $user_data['lastname'] ?? '',
        'suffix' => $registered_suffix,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const registeredPhone = "<?= htmlspecialchars($registered_phone) ?>";
    const documentFees = <?= json_encode($document_settings ?? []) ?>;
    const paidRequestIds = <?= json_encode($paidRequestIds ?? []) ?>;
    const residentRequestHistory = <?= json_encode($request_history ?? []) ?>;
    const residentActiveRequestHistory = <?= json_encode($active_request_history ?? []) ?>;
    const requestCooldownRemaining = <?= (int) $request_cooldown_remaining ?>;
    window.residentRequestHistory = residentRequestHistory;
    window.residentActiveRequestHistory = residentActiveRequestHistory;
    window.requestCooldownRemaining = requestCooldownRemaining;
</script>
<script src="../js/resident.js?v=<?= filemtime(__DIR__ . '/../js/resident.js') ?>"></script>
<script src="../js/prerequisite_upload.js?v=<?= filemtime(__DIR__ . '/../js/prerequisite_upload.js') ?>"></script>
<script src="../js/notifications.js"></script>
<script>
    document.addEventListener('click', function (e) {
        const target = e.target;
        const btn = (target instanceof Element ? target.closest('.request-refund-btn') : null)
            || (target && target.parentElement ? target.parentElement.closest('.request-refund-btn') : null);
        if (!btn) return;
        e.preventDefault();

        const requestId = btn.dataset.id;
        if (!requestId) {
            alert('Unable to identify the refund request.');
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const form = new FormData();
        form.append('id', requestId);
        if (csrfToken) {
            form.append('csrf_token', csrfToken);
        }

        const sendRequest = () => {
            return fetch('../action/request_refund.php', {
                method: 'POST',
                body: form,
                credentials: 'same-origin'
            }).then(async response => {
                const text = await response.text();
                let data = {};
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (err) {
                    throw new Error('Invalid server response.');
                }
                if (!data.success) {
                    throw new Error(data.error || 'Refund request failed.');
                }
                return data;
            });
        };

        const handleSuccess = () => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Refund Requested',
                    text: 'The barangay will review your request soon.',
                    confirmButtonText: 'OK'
                }).then(() => window.location.reload());
            } else {
                alert('Refund requested. The barangay will review your request soon.');
                window.location.reload();
            }
        };

        const handleError = (message) => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Refund Request Failed',
                    text: message || 'Please try again later.'
                });
            } else {
                alert(message || 'Refund request failed.');
            }
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Request a refund?',
        html: `
            The barangay will review your request before the refund is processed.<br>
            <span style="color:red; font-weight:bold;">Refunds can only be requested once for each transaction.</span>
        `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Request Refund',
                showLoaderOnConfirm: true,
                preConfirm: () => sendRequest().catch(err => Swal.showValidationMessage(err.message || 'Request failed'))
            }).then(result => {
                if (result.isConfirmed) {
                    handleSuccess();
                }
            }).catch(err => handleError(err.message));
        } else {
            if (confirm('Request a refund? The barangay will review your request before the refund is processed.')) {
                sendRequest().then(handleSuccess).catch(err => handleError(err.message));
            }
        }
    });
</script>
</body>
</html>