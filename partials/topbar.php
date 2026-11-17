<?php
/**
 * ─────────────────────────────────────────────────────────────
 *  PARTIAL: TOP BAR — Shared header bar for all dashboards.
 *  Usage: <?php include __DIR__ . '/../partials/topbar.php'; ?>
 *
 *  Expected variables:
 *   $topbar_title  — Title text (e.g. "Resident Dashboard")
 *   $topbar_user   — Username to display
 *   $logout_url    — Logout URL (defaults to ../auth/logout.php)
 * ─────────────────────────────────────────────────────────────
 */
$topbar_title = $topbar_title ?? 'Dashboard';
$topbar_user  = $topbar_user  ?? ($_SESSION['full_name'] ?? 'User');
$logout_url   = $logout_url   ?? '../auth/logout.php';
?>
<div class="top-bar">
    <div class="d-flex align-items-center gap-2">
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">&#9776;</button>
        <h5><?= htmlspecialchars($topbar_title) ?></h5>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span style="font-size:15px;color:#bcc8daff;">👤 <?= htmlspecialchars($topbar_user) ?></span>

        <!-- NOTIFICATION BELL -->
        <div class="notif-wrapper">
            <button class="notif-bell" id="notifBell" aria-label="Notifications">🔔
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

        <a class="btn btn-light btn-sm" href="#"
            onclick="Swal.fire({text:'Logout now?',showCancelButton:true})
            .then(r=>{if(r.isConfirmed)location='<?= $logout_url ?>'})">
            Logout
        </a>
    </div>
</div>
