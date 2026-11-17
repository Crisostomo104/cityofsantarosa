<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

check_login_redirect(['employee', 'city_administrator']);

$role = $_SESSION['user_type'] ?? 'employee';
$barangay = $_SESSION['barangay'] ?? '';
$fullname = $_SESSION['fullname'] ?? ($role === 'city_administrator' ? 'City Administrator' : 'Barangay Employee');
$scopeLabel = $role === 'city_administrator' ? 'City of Santa Rosa — All Barangays' : 'Barangay ' . $barangay;
$scopeCondition = $role === 'city_administrator' ? '1=1' : 'u.barangay = ?';

// =======================
// FILTER
// =======================
$rep_filter = $_GET['rep_filter'] ?? 'month';
$rep_search = trim($_GET['search'] ?? '');

$allowedFilters = [
    'today',
    'yesterday',
    'week',
    'month',
    'all'
];

if (!in_array($rep_filter, $allowedFilters, true)) {
    $rep_filter = 'month';
}

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
        'today' => "DATE(r.created_at) = CURDATE()",
        'yesterday' => "DATE(r.created_at) = CURDATE() - INTERVAL 1 DAY",
        'week' => "r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        'month' => "MONTH(r.created_at) = MONTH(CURDATE()) AND YEAR(r.created_at) = YEAR(CURDATE())",
        'all' => "1=1"
    };

    $filterLabel = match($rep_filter) {
        'today' => 'Today — ' . date('F d, Y'),
        'yesterday' => 'Yesterday — ' . date('F d, Y', strtotime('-1 day')),
        'week' => 'Last 7 Days',
        'month' => 'This Month — ' . date('F Y'),
        'all' => 'All Time'
    };
}

// =======================
// STATUS SUMMARY
// =======================
$summary = [];
$statusList = [
    'Pending',
    'Approved',
    'Rejected',
    'Completed'
];

foreach ($statusList as $status) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM requests r
        INNER JOIN users u ON r.user_id = u.user_id
        WHERE r.status = ?
          AND $scopeCondition
          AND $whereDate
    ");
    $params = $role === 'city_administrator' ? [$status] : [$status, $barangay];
    $stmt->execute($params);
    $summary[$status] = (int)$stmt->fetchColumn();
}

$total = array_sum($summary);

// =======================
// TOTAL REQUEST COUNT
// =======================
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM requests r
    INNER JOIN users u ON r.user_id = u.user_id
    WHERE $scopeCondition
      AND $whereDate
");
$params = $role === 'city_administrator' ? [] : [$barangay];
$stmt->execute($params);
$totalRequests = (int)$stmt->fetchColumn();

// =======================
// DOCUMENT STATISTICS
// =======================
$queryParams = $role === 'city_administrator' ? [] : [$barangay];

// Most requested document
$stmt = $pdo->prepare("
    SELECT r.document_type, COUNT(*) AS total
    FROM requests r
    INNER JOIN users u ON r.user_id = u.user_id
    WHERE $scopeCondition
      AND $whereDate
    GROUP BY r.document_type
    ORDER BY total DESC
    LIMIT 1
");
$stmt->execute($queryParams);
$topDocument = $stmt->fetch(PDO::FETCH_ASSOC);

// =======================
// PURPOSE STATISTICS
// =======================
$stmt = $pdo->prepare("
    SELECT r.purpose, COUNT(*) AS total
    FROM requests r
    INNER JOIN users u ON r.user_id = u.user_id
    WHERE $scopeCondition
      AND $whereDate
    GROUP BY r.purpose
    ORDER BY total DESC
    LIMIT 1
");
$stmt->execute($queryParams);
$topPurpose = $stmt->fetch(PDO::FETCH_ASSOC);

// =======================
// EMPLOYEE PROCESSING STAT
// =======================
$stmt = $pdo->prepare("
    SELECT e.full_name, COUNT(*) AS total
    FROM requests r
    LEFT JOIN users e ON r.processed_by = e.user_id
    INNER JOIN users u ON r.user_id = u.user_id
    WHERE $scopeCondition
      AND $whereDate
      AND e.full_name IS NOT NULL
    GROUP BY e.user_id
    ORDER BY total DESC
    LIMIT 1
");
$stmt->execute($queryParams);
$topEmployee = $stmt->fetch(PDO::FETCH_ASSOC);

// =======================
// NARRATIVE VARIABLES
// =======================
$approved = $summary['Approved'];
$pending = $summary['Pending'];
$rejected = $summary['Rejected'];
$completed = $summary['Completed'];

$documentName = $topDocument['document_type'] ?? 'various barangay documents';
$purposeName = $topPurpose['purpose'] ?? 'various personal and official purposes';
$processorName = $topEmployee['full_name'] ?? 'barangay personnel';

// =======================
// GENERATION INFO
// =======================
$generatedDate = date('F d, Y h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $role === 'city_administrator' ? 'City Narrative Report — City of Santa Rosa' : 'Barangay Narrative Report — ' . htmlspecialchars($barangay) ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: "Times New Roman", serif;
            font-size: 17px;
            color: #111;
            padding: 50px 75px;
            background: #fff;
        }
        /* HEADER */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 18px;
            margin-bottom: 45px;
        }
        .report-header h1 {
            font-size: 24px;
            font-weight: bold;
        }
        .report-header p {
            font-size: 16px;
            color: #333;
            margin-top: 5px;
        }
        .meta {
            text-align: right;
            font-size: 15px;
            color: #333;
            line-height: 1.6;
        }
        /* TITLE */
        .section-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 25px;
            border-left: 6px solid #111;
            padding-left: 14px;
        }
        /* NARRATIVE */
        .narrative {
            font-size: 18px;
            line-height: 2.1;
            text-align: justify;
        }
        .narrative p {
            margin-bottom: 25px;
            text-indent: 60px;
            text-align: justify;
        }
        .highlight {
            font-weight: bold;
        }
        /* FOOTER */
        .report-footer {
            margin-top: 120px;
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            color: #333;
        }
        .sig-line {
            text-align: center;
            min-width: 260px;
        }
        .sig-line hr {
            margin-bottom: 8px;
            border-color: #111;
        }
        /* CONTROLS */
        .no-print {
            margin-bottom: 20px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>

    <!-- PRINT CONTROLS -->
    <div class="no-print" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding:10px 0; border-bottom:1px solid #ddd; margin-bottom:30px;">
        <form method="GET" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <label style="font-weight:bold;">Period:</label>
            <select name="rep_filter" onchange="this.form.submit()" style="padding:6px 12px; border:1px solid #ccc; border-radius:4px;" <?= $hasYearFilter ? 'disabled' : '' ?>>
                <option value="today" <?= $rep_filter === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="yesterday" <?= $rep_filter === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                <option value="week" <?= $rep_filter === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                <option value="month" <?= $rep_filter === 'month' ? 'selected' : '' ?>>This Month</option>
                <option value="all" <?= $rep_filter === 'all' ? 'selected' : '' ?>>All Time</option>
            </select>

            <label style="font-weight:bold;">Year:</label>
            <select name="rep_year" onchange="this.form.submit()" style="padding:6px 12px; border:1px solid #ccc; border-radius:4px;">
                <option value="">(use Period)</option>
                <option value="all" <?= $rep_year === 'all' ? 'selected' : '' ?>>All Years</option>
                <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 2; $y--): ?>
                    <option value="<?= $y ?>" <?= $rep_year === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <select name="rep_month" onchange="this.form.submit()" style="padding:6px 12px; border:1px solid #ccc; border-radius:4px;" <?= (!$hasYearFilter || $rep_year === 'all') ? 'disabled' : '' ?>>
                <option value="">All Months</option>
                <?php for ($m = 1; $m <= 12; $m++): $mv = sprintf('%02d', $m); ?>
                    <option value="<?= $mv ?>" <?= $rep_month === $mv ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <button onclick="window.print()" style="padding:7px 20px; background:#1d4ed8; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold; display:flex; align-items:center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Print
        </button>
        <a href="<?= $role === 'city_administrator' ? 'city_administrator' : 'employee' ?>" style="padding:7px 15px; background:#eee; border:1px solid #ccc; border-radius:4px; text-decoration:none; color:#333; display:flex; align-items:center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            Back
        </a>
    </div>

    <!-- REPORT HEADER -->
    <div class="report-header">
        <div>
            <h1><?= $role === 'city_administrator' ? 'CITY OF SANTA ROSA' : 'BARANGAY ' . htmlspecialchars(strtoupper($barangay)) ?></h1>
            <p>City of Santa Rosa, Laguna</p>
            <p><strong><?= $role === 'city_administrator' ? 'City Request Transaction Narrative Report' : 'Request Transaction Narrative Report' ?></strong></p>
        </div>
        <div class="meta">
            <p><strong>Period:</strong> <?= $filterLabel ?></p>
            <p><strong>Generated:</strong> <?= $generatedDate ?></p>
            <p><strong>Prepared by:</strong> <?= htmlspecialchars($fullname) ?></p>
        </div>
    </div>

    <!-- NARRATIVE -->
    <div class="section-title">Narrative Report</div>
    <div class="narrative">
        <p>
            This narrative report is prepared to provide an overview of the document request
            transactions received and processed by
            <strong><?= $role === 'city_administrator' ? 'the City of Santa Rosa' : 'Barangay ' . htmlspecialchars($barangay) ?></strong>
            during the reporting period of
            <strong><?= $filterLabel ?></strong>.
            This report serves as an official record of the services provided by the
            <?= $role === 'city_administrator' ? 'city government' : 'barangay office' ?> and reflects the continuous efforts of the administration in
            responding to the needs and requests of its residents.
        </p>
        <p>
            During the stated reporting period, <?= $role === 'city_administrator' ? 'the city government' : 'the barangay office' ?> recorded a total of
            <strong><?= $totalRequests ?></strong>
            document request transactions.
            Each request was carefully reviewed and processed by authorized <?= $role === 'city_administrator' ? 'city' : 'barangay' ?>
            personnel to ensure compliance with the required documents, proper evaluation,
            and efficient delivery of services.
        </p>
        <p>
            Out of the recorded transactions,
            <strong><?= $approved ?></strong>
            requests were approved,
            <strong><?= $completed ?></strong>
            requests were successfully completed and released to the requesting residents,
            <strong><?= $pending ?></strong>
            requests remain under evaluation or are awaiting completion of requirements,
            while
            <strong><?= $rejected ?></strong>
            requests were not approved after assessment based on existing guidelines and
            documentary requirements.
        </p>
        <p>
            The requests received during this period involved various barangay services
            and official documents required by residents for employment, education,
            business transactions, government-related requirements, and other personal
            matters. The most frequently requested document during this reporting period
            was
            <strong><?= htmlspecialchars($documentName) ?></strong>,
            while the most common purpose indicated by residents was
            <strong><?= htmlspecialchars($purposeName) ?></strong>.
        </p>
        <p>
            The processing of these transactions was made possible through the dedication
            and cooperation of authorized <?= $role === 'city_administrator' ? 'city' : 'barangay' ?> personnel responsible for reviewing,
            verifying, and completing each request. The personnel who handled the highest
            number of processed transactions during this period was
            <strong><?= htmlspecialchars($processorName) ?></strong>,
            contributing to the efficient and organized delivery of <?= $role === 'city_administrator' ? 'city' : 'barangay' ?> services.
        </p>
        <p>
            Based on the recorded transactions, <?= $role === 'city_administrator' ? 'the City of Santa Rosa' : 'Barangay ' . htmlspecialchars($barangay) ?>
            continues to demonstrate its commitment to providing accessible, reliable,
            and responsive public services to its residents. The <?= $role === 'city_administrator' ? 'city government' : 'barangay office' ?> remains
            dedicated to improving its procedures and ensuring that every request receives
            proper attention and timely action.
        </p>
        <p>
            This narrative report is respectfully submitted as an official documentation
            of the <?= $role === 'city_administrator' ? 'city' : 'barangay' ?> request transactions for monitoring, evaluation, administrative
            review, and future reference.
        </p>
    </div>

    <!-- SIGNATURE -->
    <div class="report-footer">
        <div class="sig-line">
            <hr>
            <strong><?= htmlspecialchars($fullname) ?></strong>
            <br>Prepared by
        </div>
        <div class="sig-line">
            <hr>
            <?= $role === 'city_administrator' ? 'City Administrator / Authorized Official' : 'Barangay Captain / Authorized Official' ?>
            <br><?= $role === 'city_administrator' ? 'City of Santa Rosa' : 'Barangay ' . htmlspecialchars($barangay) ?>
        </div>
    </div>

    <script>
        if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
            window.addEventListener('load', () => {
                setTimeout(() => {
                    window.print();
                }, 400);
            });
        }
    </script>
</body>
</html>