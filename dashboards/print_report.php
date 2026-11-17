<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

check_login_redirect(['employee', 'city_administrator']);

$role = $_SESSION['user_type'] ?? 'employee';
$barangay = $_SESSION['barangay'] ?? '';
$fullname  = $_SESSION['fullname'] ?? ($role === 'city_administrator' ? 'City Administrator' : 'Barangay Employee');
$scopeCondition = $role === 'city_administrator' ? '1=1' : 'u.barangay = ?';
$scopeLabel = $role === 'city_administrator' ? 'All Barangays' : 'Barangay ' . $barangay;

// =======================
// FILTER
// =======================
$rep_filter = $_GET['rep_filter'] ?? 'month';
$rep_search = trim($_GET['search'] ?? '');

$allowedFilters = ['today','yesterday','week','month','all'];
if (!in_array($rep_filter, $allowedFilters, true)) $rep_filter = 'month';

// ── Year / Month filter (mirrors the Barangay Report tab's Year/Month
//    picker in city_administrator.php). When rep_year is present, it
//    takes priority over the Period preset above.
//    rep_year = ''    -> not set, fall back to the Period preset
//    rep_year = 'all' -> explicit "All Years" (same as Period=all)
//    rep_year = 'YYYY' -> that year, optionally narrowed by rep_month
$rep_year  = $_GET['rep_year']  ?? '';
$rep_month = $_GET['rep_month'] ?? '';
$hasYearFilter = ($rep_year !== '');

if ($hasYearFilter) {
    if ($rep_year === 'all') {
        $whereDate   = "1=1";
        $filterLabel = 'All Time';
    } elseif ($rep_month !== '') {
        $whereDate   = "YEAR(r.created_at) = " . (int)$rep_year . " AND MONTH(r.created_at) = " . (int)$rep_month;
        $filterLabel = date('F', mktime(0, 0, 0, (int)$rep_month, 1)) . ' ' . (int)$rep_year;
    } else {
        $whereDate   = "YEAR(r.created_at) = " . (int)$rep_year;
        $filterLabel = (string)(int)$rep_year;
    }
} else {
    $whereDate = match($rep_filter) {
        'today'     => "DATE(r.created_at) = CURDATE()",
        'yesterday' => "DATE(r.created_at) = CURDATE() - INTERVAL 1 DAY",
        'week'      => "r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        'month'     => "MONTH(r.created_at) = MONTH(CURDATE()) AND YEAR(r.created_at) = YEAR(CURDATE())",
        'all'       => "1=1",
    };

    $filterLabel = match($rep_filter) {
        'today'     => 'Today — ' . date('F d, Y'),
        'yesterday' => 'Yesterday — ' . date('F d, Y', strtotime('-1 day')),
        'week'      => 'Last 7 Days',
        'month'     => 'This Month — ' . date('F Y'),
        'all'       => 'All Time (Latest 100 Requests)',
    };
}

// ── PRINT ELIGIBILITY ──
// Printing is only reliable at "one month or less" granularity. Anything
// wider (All Time, a whole Year with no Month picked, or explicit "All
// Years") is blocked from printing — the user is guided to narrow down
// to a specific Month instead. Presets that are already naturally small
// (today / yesterday / last 7 days / this month) stay printable.
$printBlocked = ($hasYearFilter && ($rep_year === 'all' || $rep_month === ''))
             || (!$hasYearFilter && $rep_filter === 'all');

$recordLimitClause = (!$hasYearFilter && $rep_filter === 'all') ? ' LIMIT 100' : '';

// =======================
// STATUS SUMMARY
// =======================
$summary = [];
$summaryParams = $role === 'city_administrator' ? [] : [$barangay];
foreach (['Pending','Approved','Rejected','Completed'] as $s) {
    $st = $pdo->prepare("
        SELECT COUNT(*) FROM requests r
        INNER JOIN users u ON r.user_id = u.user_id
        WHERE r.status = ? AND $scopeCondition AND $whereDate
    ");
    $params = $role === 'city_administrator' ? [$s] : [$s, $barangay];
    $st->execute($params);
    $summary[$s] = (int)$st->fetchColumn();
}
$total = array_sum($summary);

// =======================
// ALL RECORDS (NO PAGINATION)
// =======================
$whereSearch = '';
$params = $role === 'city_administrator' ? [] : [$barangay];

if (!empty($rep_search)) {
    $likeSearch = '%' . $rep_search . '%';
    $whereSearch = " AND (r.full_name LIKE ? OR r.document_type LIKE ? OR r.status LIKE ?)";
    $params = array_merge($params, [$likeSearch, $likeSearch, $likeSearch]);
}

$st = $pdo->prepare("
    SELECT r.*,
           r.full_name AS resident_name,
           e.full_name AS employee_name,
           u.barangay
    FROM requests r
    INNER JOIN users u ON r.user_id = u.user_id
    LEFT JOIN users e ON r.processed_by = e.user_id
    WHERE $scopeCondition AND $whereDate $whereSearch
    ORDER BY r.created_at DESC
    $recordLimitClause
");
$st->execute($params);
$records = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $role === 'city_administrator' ? 'City Report — City of Santa Rosa' : 'Barangay Report — ' . htmlspecialchars($barangay) ?></title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Arial', sans-serif;
        font-size: 12px;
        color: #111;
        padding: 24px 32px;
        background: #fff;
    }

    /* ── HEADER ── */
    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid #111;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }
    .report-header .logo-block h1 { font-size: 16px; font-weight: bold; }
    .report-header .logo-block p  { font-size: 11px; color: #444; }
    .report-header .meta          { text-align: right; font-size: 11px; color: #444; }

    /* ── SUMMARY ── */
    .summary-row {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .summary-box {
        flex: 1;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 10px;
        text-align: center;
    }
    .summary-box .num  { font-size: 22px; font-weight: bold; }
    .summary-box .lbl  { font-size: 10px; color: #555; }
    .summary-box.total { border-color: #111; }
    .summary-box.pend  { border-color: #f59e0b; }
    .summary-box.appr  { border-color: #3b82f6; }
    .summary-box.rej   { border-color: #ef4444; }
    .summary-box.comp  { border-color: #22c55e; }

    /* ── TABLE ── */
    .section-title {
        font-size: 13px;
        font-weight: bold;
        margin-bottom: 8px;
        border-left: 4px solid #111;
        padding-left: 8px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }
    thead tr { background: #111; color: #fff; }
    thead th { padding: 7px 8px; text-align: left; }
    tbody tr:nth-child(even) { background: #f5f5f5; }
    tbody td { padding: 6px 8px; border-bottom: 1px solid #e0e0e0; }

    .badge {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 3px;
        font-size: 10px;
        font-weight: bold;
        color: #fff;
    }
    .badge-Pending   { background: #f59e0b; }
    .badge-Approved  { background: #3b82f6; }
    .badge-Rejected  { background: #ef4444; }
    .badge-Completed { background: #22c55e; }

    /* ── FOOTER ── */
    .report-footer {
        margin-top: 32px;
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        color: #555;
    }
    .sig-line {
        text-align: center;
        min-width: 200px;
    }
    .sig-line hr { margin-bottom: 4px; border-color: #111; }

    /* ── REPORT META (Period line made more noticeable) ── */
    .meta .period-line {
        font-size: 16px;
        font-weight: bold;
        color: #111;
    }

    /* ── PRINT-ONLY FALLBACK MESSAGE (hidden on screen) ── */
    .print-only-message { display: none; }

    /* ── NO PRINT CONTROLS ── */
    @media print {
        .no-print { display: none !important; }
        body { padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

        table { page-break-inside: auto; }
        tr    { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }   /* para umuulit ang header sa bawat page */
        .summary-row, .report-header { page-break-inside: avoid; }

        /* When the current filter is too wide to print reliably, replace
           the whole report with a plain instruction instead — this kicks
           in for ANY print trigger (button, Ctrl+P, browser menu),
           because it's a pure CSS rule evaluated at print time. */
        body[data-print-blocked="true"] .report-header,
        body[data-print-blocked="true"] .summary-row,
        body[data-print-blocked="true"] .section-title,
        body[data-print-blocked="true"] table,
        body[data-print-blocked="true"] .report-footer {
            display: none !important;
        }
        body[data-print-blocked="true"] .print-only-message {
            display: block !important;
            text-align: center;
            margin-top: 120px;
            font-size: 16px;
            font-weight: bold;
        }
    }
</style>
</head>
<body<?= $printBlocked ? ' data-print-blocked="true"' : '' ?>>

<!-- PRINT CONTROLS (hidden when printing) -->
<div class="no-print" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:8px 0;border-bottom:1px solid #ddd;margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <label style="font-weight:bold;">Period:</label>
        <select name="rep_filter" onchange="this.form.submit()" style="padding:4px 8px;border:1px solid #ccc;border-radius:4px;" <?= $hasYearFilter ? 'disabled' : '' ?>>
            <option value="today"     <?= $rep_filter==='today'?'selected':'' ?>>Today</option>
            <option value="yesterday" <?= $rep_filter==='yesterday'?'selected':'' ?>>Yesterday</option>
            <option value="week"  <?= $rep_filter==='week' ?'selected':'' ?>>Last 7 Days</option>
            <option value="month" <?= $rep_filter==='month'?'selected':'' ?>>This Month</option>
            <option value="all"   <?= $rep_filter==='all'  ?'selected':'' ?>>All Time</option>
        </select>

        <label style="font-weight:bold;">Year:</label>
        <select name="rep_year" onchange="this.form.submit()" style="padding:4px 8px;border:1px solid #ccc;border-radius:4px;">
            <option value="">(use Period)</option>
            <option value="all" <?= $rep_year === 'all' ? 'selected' : '' ?>>All Years</option>
            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 2; $y--): ?>
                <option value="<?= $y ?>" <?= $rep_year === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <select name="rep_month" onchange="this.form.submit()" style="padding:4px 8px;border:1px solid #ccc;border-radius:4px;" <?= (!$hasYearFilter || $rep_year === 'all') ? 'disabled' : '' ?>>
            <option value="">All Months</option>
            <?php for ($m = 1; $m <= 12; $m++): $mv = sprintf('%02d', $m); ?>
                <option value="<?= $mv ?>" <?= $rep_month === $mv ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
            <?php endfor; ?>
        </select>

        <input type="text" name="search" value="<?= htmlspecialchars($rep_search) ?>" placeholder="Search..." style="padding:4px 8px;border:1px solid #ccc;border-radius:4px;">
        <button type="submit" style="padding:4px 12px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;">Filter</button>
    </form>
    <button id="printBtn" type="button"
        style="padding:5px 16px;background:<?= $printBlocked ? '#9ca3af' : '#1d4ed8' ?>;color:#fff;border:none;border-radius:4px;cursor:<?= $printBlocked ? 'not-allowed' : 'pointer' ?>;font-weight:bold;display:flex;align-items:center;"
        <?= $printBlocked ? 'aria-disabled="true" title="Select a specific Year and Month to enable printing"' : '' ?>>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;">
            <polyline points="6 9 6 2 18 2 18 9"/>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
            <rect x="6" y="14" width="12" height="8"/>
        </svg>
        Print<?= $printBlocked ? ' (select a Month)' : '' ?>
    </button>
    <a href="<?= $role === 'city_administrator' ? 'city_administrator' : 'employee' ?>" style="padding:5px 12px;background:#eee;border:1px solid #ccc;border-radius:4px;text-decoration:none;color:#333;display:flex;align-items:center;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;">
            <line x1="19" y1="12" x2="5" y2="12"/>
            <polyline points="12 19 5 12 12 5"/>
        </svg>
        Back
    </a>
</div>
<?php if ($printBlocked): ?>
<div class="no-print" style="margin:-8px 0 16px;padding:8px 12px;background:#fff7ed;border:1px solid #f59e0b;border-radius:4px;font-size:11px;color:#92400e;">
    Printing is disabled for this view — too many rows to print cleanly. Pick a specific <strong>Year</strong> and <strong>Month</strong> above to enable printing.
</div>
<?php endif; ?>

<!-- Shown only inside the printed page when printing is blocked -->
<div class="print-only-message">
    This report cannot be printed for the current filter.<br>
    Please go back, select a specific Year and Month, then print again.
</div>

<!-- REPORT DOCUMENT -->
<div class="report-header">
    <div class="logo-block">
        <h1><?= $role === 'city_administrator' ? 'CITY OF SANTA ROSA' : 'BARANGAY ' . htmlspecialchars(strtoupper($barangay)) ?></h1>
        <p>City of Santa Rosa, Laguna</p>
        <p><strong><?= $role === 'city_administrator' ? 'City Request Transaction Report' : 'Request Transaction Report' ?></strong></p>
    </div>
    <div class="meta">
        <p class="period-line"><strong>Period:</strong> <?= $filterLabel ?></p>
        <p><strong>Generated:</strong> <?= date('F d, Y  h:i A') ?></p>
        <p><strong>Prepared by:</strong> <?= htmlspecialchars($fullname) ?></p>
    </div>
</div>

<!-- SUMMARY -->
<div class="summary-row">
    <div class="summary-box total">
        <div class="num"><?= $total ?></div>
        <div class="lbl">TOTAL</div>
    </div>
    <div class="summary-box pend">
        <div class="num"><?= $summary['Pending'] ?></div>
        <div class="lbl">PENDING</div>
    </div>
    <div class="summary-box appr">
        <div class="num"><?= $summary['Approved'] ?></div>
        <div class="lbl">APPROVED</div>
    </div>
    <div class="summary-box rej">
        <div class="num"><?= $summary['Rejected'] ?></div>
        <div class="lbl">REJECTED</div>
    </div>
    <div class="summary-box comp">
        <div class="num"><?= $summary['Completed'] ?></div>
        <div class="lbl">COMPLETED</div>
    </div>
</div>

<!-- TABLE -->
<div class="section-title">Transaction Records (<?= count($records) ?> entries)</div>
<?php if (!$hasYearFilter && $rep_filter === 'all'): ?>
<div style="margin-bottom:10px;font-size:11px;color:#666;">Showing the latest 100 requests for this report.</div>
<?php endif; ?>
<table>
    <thead>
        <tr>
            <th>Request No.</th>
            <th>Resident Name</th>
                    <?php if ($role === 'city_administrator'): ?><th>Barangay</th><?php endif; ?>
                    <th>Document Type</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Processed By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
    <?php if (!empty($records)): ?>
        <?php foreach ($records as $r): ?>
        <tr>
            <td><?= htmlspecialchars((string)($r['request_id'] ?? $r['id'] ?? '—')) ?></td>
            <td><?= htmlspecialchars($r['resident_name']) ?></td>
            <?php if ($role === 'city_administrator'): ?><td><?= htmlspecialchars($r['barangay'] ?? '—') ?></td><?php endif; ?>
            <td><?= htmlspecialchars($r['document_type']) ?></td>
            <td><?= htmlspecialchars($r['purpose']) ?></td>
            <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
            <td><?= htmlspecialchars($r['employee_name'] ?? '—') ?></td>
            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="<?= $role === 'city_administrator' ? 8 : 7 ?>" style="text-align:center;padding:16px;color:#888;">No records found for this period.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- SIGNATURE FOOTER -->
<div class="report-footer">
    <div class="sig-line">
        <hr>
        <strong><?= htmlspecialchars($fullname) ?></strong><br>
        Prepared by
    </div>
    <div class="sig-line">
        <hr>
        <?= $role === 'city_administrator' ? 'City Administrator / Authorized Official' : 'Barangay Captain / Authorized Official' ?><br>
        <?= $role === 'city_administrator' ? 'City of Santa Rosa' : 'Barangay ' . htmlspecialchars($barangay) ?>
    </div>
</div>

<script>
const printBlocked = <?= $printBlocked ? 'true' : 'false' ?>;

// Button click — real block, no "print anyway" bypass.
document.getElementById('printBtn').addEventListener('click', () => {
    if (printBlocked) {
        alert('Printing is disabled for this view. Please select a specific Year and Month first.');
        return;
    }
    window.print();
});

// Ctrl+P / Cmd+P — intercepted before the browser opens its print dialog.
document.addEventListener('keydown', (e) => {
    const isPrintShortcut = (e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P');
    if (isPrintShortcut && printBlocked) {
        e.preventDefault();
        alert('Printing is disabled for this view. Please select a specific Year and Month first.');
    }
});

// Fallback net: even if print is triggered some other way (browser menu,
// right-click, etc.), the @media print CSS rules above swap the whole
// report out for a plain "select a Year and Month" message.

// Auto-print only if ?autoprint=1 is in the URL, and only when allowed.
if (new URLSearchParams(window.location.search).get('autoprint') === '1' && !printBlocked) {
    window.addEventListener('load', () => {
        setTimeout(() => window.print(), 400);
    });
}
</script>

</body>
</html>