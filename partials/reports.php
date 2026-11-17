<?php
// reports.php (FIXED VERSION - NO PDO ERRORS)

$rep_search = trim($_GET['search'] ?? '');
$rep_filter = $_GET['rep_filter'] ?? 'today';
$barangay = trim($_SESSION['barangay'] ?? '');

$allowedFilters = ['today','yesterday','week','month','all'];
if (!in_array($rep_filter, $allowedFilters)) {
    $rep_filter = 'today';
}

$whereRep = match($rep_filter) {
    'today'     => "DATE(r.created_at) = CURDATE()",
    'yesterday' => "DATE(r.created_at) = CURDATE() - INTERVAL 1 DAY",
    'week'      => "r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month'     => "MONTH(r.created_at) = MONTH(CURDATE()) AND YEAR(r.created_at) = YEAR(CURDATE())",
    'all'       => "1=1",
};

$whereSearch = "";
$params = [':barangay' => $barangay];

if (!empty($rep_search)) {
    $rep_search = "%" . $rep_search . "%";
    $whereSearch = " AND (
        e.full_name LIKE :search1 OR
        u.full_name LIKE :search2 OR
        r.document_type LIKE :search3 OR
        r.status LIKE :search4
    )";
    $params[':search1'] = $rep_search;
    $params[':search2'] = $rep_search;
    $params[':search3'] = $rep_search;
    $params[':search4'] = $rep_search;
}

$rep_limit = 10;
$rep_page = max(1, (int)($_GET['rep_page'] ?? 1));

$rep_cache_key = 'employee_reports:' . $barangay . ':' . $rep_filter . ':' . $rep_page . ':' . md5($rep_search);
$rep_data = get_cached_data($rep_cache_key, 5, function() use ($pdo, $whereRep, $whereSearch, $params, $rep_limit, $rep_page, $barangay) {
    $totalStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM requests r
        LEFT JOIN users u ON r.user_id = u.user_id
        LEFT JOIN users e ON r.processed_by = e.user_id
        WHERE u.barangay = :barangay
          AND $whereRep $whereSearch
    ");

    foreach ($params as $key => $value) {
        $totalStmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $totalStmt->execute();
    $totalRows = (int)$totalStmt->fetchColumn();

    $rep_totalPages = max(1, ceil($totalRows / $rep_limit));
    $rep_page = min($rep_page, $rep_totalPages);
    $rep_offset = ($rep_page - 1) * $rep_limit;

    $stmt = $pdo->prepare("
        SELECT r.*, 
               r.full_name AS resident_name,
               e.user_id AS employee_id,
               e.full_name AS employee_name
        FROM requests r
        LEFT JOIN users u ON r.user_id = u.user_id
        LEFT JOIN users e ON r.processed_by = e.user_id
        WHERE u.barangay = :barangay
          AND $whereRep $whereSearch
        ORDER BY r.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':barangay', $barangay, PDO::PARAM_STR);
    foreach ($params as $key => $value) {
        if ($key !== ':barangay') {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $stmt->bindValue(':limit', $rep_limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $rep_offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'totalRows' => $totalRows,
        'rep_totalPages' => $rep_totalPages,
        'rep_page' => $rep_page,
        'rep_offset' => $rep_offset,
        'reports' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ];
});

$totalRows = (int)($rep_data['totalRows'] ?? 0);
$rep_totalPages = (int)($rep_data['rep_totalPages'] ?? 1);
$rep_page = (int)($rep_data['rep_page'] ?? $rep_page);
$rep_offset = (int)($rep_data['rep_offset'] ?? 0);
$reports = $rep_data['reports'] ?? [];
?>