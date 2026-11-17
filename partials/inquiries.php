<?php
// Barangay request filtering and pagination

$inq_filter = $_GET['inq_filter'] ?? 'today';
$inq_search = $_GET['inq_search'] ?? '';
$inq_search = is_string($inq_search) ? trim($inq_search) : '';

$allowedFilters = ['today','yesterday','week','month','all','all_requests','dashboard'];

if (!is_string($inq_filter) || !in_array($inq_filter, $allowedFilters, true)) {
    $inq_filter = 'today';
}

$inq_status = $_GET['req_status'] ?? '';
$allowedStatuses = ['Pending','Approved','To be Claim','Rejected','Completed'];
if (!is_string($inq_status) || !in_array($inq_status, $allowedStatuses, true)) {
    $inq_status = '';
}

/*
|--------------------------------------------------------------------------
| EMPLOYEE BARANGAY
|--------------------------------------------------------------------------
*/
$barangay = trim($_SESSION['barangay'] ?? '');

/*
|--------------------------------------------------------------------------
| DATE FILTER
|--------------------------------------------------------------------------
*/
$dashboard_year = $_GET['filter_year'] ?? date('Y');
$dashboard_year = is_string($dashboard_year) && preg_match('/^\d{4}$/', $dashboard_year)
    ? $dashboard_year
    : 'all';
$dashboard_month = $_GET['filter_month'] ?? ($dashboard_year === date('Y') ? date('m') : 'all');
$dashboard_month = is_string($dashboard_month) && preg_match('/^(0[1-9]|1[0-2])$/', $dashboard_month)
    ? $dashboard_month
    : 'all';
if ($dashboard_year === 'all') {
    $dashboard_month = 'all';
}

$dashboard_range_start = null;
$dashboard_range_end = null;
if ($dashboard_year !== 'all') {
    if ($dashboard_month !== 'all') {
        $dashboard_range_start = sprintf('%04d-%02d-01', (int)$dashboard_year, (int)$dashboard_month);
        $dashboard_range_end = date('Y-m-d', strtotime($dashboard_range_start . ' +1 month'));
    } else {
        $dashboard_range_start = sprintf('%04d-01-01', (int)$dashboard_year);
        $dashboard_range_end = sprintf('%04d-01-01', (int)$dashboard_year + 1);
    }
}

$whereInq = match($inq_filter) {
    'today'     => "DATE(r.created_at) = CURDATE()",
    'yesterday' => "DATE(r.created_at) = CURDATE() - INTERVAL 1 DAY",
    'week'      => "r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month'     => "MONTH(r.created_at) = MONTH(CURDATE())
                    AND YEAR(r.created_at) = YEAR(CURDATE())",
    'all'       => "1=1",
    'all_requests' => "1=1",
    'dashboard' => $dashboard_range_start !== null
        ? 'r.created_at >= :dashboard_start AND r.created_at < :dashboard_end'
        : '1=1',
};

/*
|--------------------------------------------------------------------------
| SEARCH (SAFE VERSION)
|--------------------------------------------------------------------------
*/
$searchSQL = "";
$serverPaginatedRequests = $inq_filter === 'all_requests';
$params = [
    ':barangay' => $barangay
];

if ($inq_filter === 'dashboard' && $dashboard_range_start !== null) {
    $params[':dashboard_start'] = $dashboard_range_start;
    $params[':dashboard_end'] = $dashboard_range_end;
}

if ($inq_status !== '') {
    $searchSQL .= ' AND r.status = :req_status';
    $params[':req_status'] = $inq_status;
}

if ($serverPaginatedRequests && $inq_search !== '') {
    $searchTerm = '%' . $inq_search . '%';
    $searchSQL .= " AND (
        CAST(r.request_id AS CHAR) LIKE :search_id
        OR CONCAT('REQ', DATE_FORMAT(r.created_at, '%Y%m%d'), r.user_id, '-', LPAD(r.request_id, 3, '0')) LIKE :search_display_id
        OR r.full_name LIKE :search_name
        OR r.document_type LIKE :search_document
        OR r.rejection_reason LIKE :search_reason
        OR DATE_FORMAT(r.created_at, '%Y-%m-%d') LIKE :search_date
    )";
    foreach (['id', 'display_id', 'name', 'document', 'reason', 'date'] as $searchField) {
        $params[':search_' . $searchField] = $searchTerm;
    }
}

$inq_page_size = 15;
$inq_page = $_GET['inq_page'] ?? 1;
$inq_page = is_string($inq_page) || is_int($inq_page) ? filter_var($inq_page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
$inq_page = $inq_page === false ? 1 : $inq_page;
$inq_total = 0;
$inq_page_count = 1;
$inq_offset = 0;

if ($serverPaginatedRequests) {
    $countCacheKey = 'employee_request_count:' . md5(implode('|', [
        $barangay,
        $inq_filter,
        $dashboard_range_start ?? '',
        $dashboard_range_end ?? '',
        $inq_status,
        $inq_search,
    ]));
    $inq_total = get_cached_data($countCacheKey, 30, function () use ($pdo, $params, $whereInq, $searchSQL) {
        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM requests r
            INNER JOIN users u ON r.user_id = u.user_id
            WHERE u.barangay = :barangay
              AND $whereInq
              $searchSQL
        ");
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        return (int)$countStmt->fetchColumn();
    });
    $inq_page_count = max(1, (int)ceil($inq_total / $inq_page_size));
    $inq_page = min($inq_page, $inq_page_count);
    $inq_offset = ($inq_page - 1) * $inq_page_size;
}

$dataLimitSql = $serverPaginatedRequests
    ? 'LIMIT :page_limit OFFSET :page_offset'
    : 'LIMIT 100';

$dataSql = "
    SELECT r.request_id, r.full_name, r.relationship, r.document_type, r.status, r.rejection_reason, r.created_at, r.user_id, r.payment_status, r.payment_amount, r.preferred_payment_method, r.payment_reference,
           CASE
               WHEN r.preferred_payment_method = 'walk-in' AND r.payment_status IN ('paid', 'refunded') THEN (
                   SELECT CONCAT('RCPT-', LPAD(pt.payment_id, 8, '0'))
                   FROM payment_transactions pt
                   WHERE pt.request_id = r.request_id
                   LIMIT 1
               )
               ELSE NULL
           END AS receipt_number,
           r.is_viewed, r.refund_requested_at, r.refund_status, r.pwd_senior_requested, r.pwd_senior_verified, r.pwd_senior_rejected
    FROM requests r
    INNER JOIN users u ON r.user_id = u.user_id
    WHERE u.barangay = :barangay
    AND $whereInq
    $searchSQL
    ORDER BY r.created_at DESC, r.request_id DESC
    $dataLimitSql
";

$stmt = $pdo->prepare($dataSql);

foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
if ($serverPaginatedRequests) {
    $stmt->bindValue(':page_limit', $inq_page_size, PDO::PARAM_INT);
    $stmt->bindValue(':page_offset', $inq_offset, PDO::PARAM_INT);
}

$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>