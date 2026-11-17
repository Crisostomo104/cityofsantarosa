<?php
require_once __DIR__ . '/pagination.php';

$res_filter = $_GET['res_filter'] ?? 'all';
$res_search = trim($_GET['res_search'] ?? '');

$res_limit = 15;
$res_page = max(1, (int)($_GET['res_page'] ?? 1));

$barangay = trim($_SESSION['barangay'] ?? '');

/*
|--------------------------------------------------------------------------
| BASE WHERE
|--------------------------------------------------------------------------
*/

$whereSQL = "WHERE user_type = 'resident' AND TRIM(barangay) = :barangay";
$searchSQL = "";
$params = [':barangay' => $barangay];

if ($res_search !== '') {
    $searchTerm = '%' . $res_search . '%';
    $searchSQL = " AND (
        full_name LIKE :search
        OR email LIKE :search
        OR phone LIKE :search
        OR CAST(user_id AS CHAR) LIKE :search
    )";
    $params[':search'] = $searchTerm;
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

if ($res_filter == 'today') {
    $whereSQL .= " AND DATE(created_at) = CURDATE()";
} elseif ($res_filter == 'yesterday') {
    $whereSQL .= " AND DATE(created_at) = CURDATE() - INTERVAL 1 DAY";
} elseif ($res_filter == 'week') {
    $whereSQL .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($res_filter == 'month') {
    $whereSQL .= " AND MONTH(created_at) = MONTH(CURDATE()) 
                   AND YEAR(created_at) = YEAR(CURDATE())";
}

/*
|--------------------------------------------------------------------------
| COUNT + DATA QUERY (CACHED)
|--------------------------------------------------------------------------
*/

$res_cache_key = 'employee_residents:' . $barangay . ':' . $res_filter . ':' . $res_page . ':' . md5($res_search);
$res_data = get_cached_data($res_cache_key, 5, function() use ($pdo, $whereSQL, $searchSQL, $params, $barangay, $res_limit, $res_page) {
    $totalStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        $whereSQL
        $searchSQL
    ");

    $totalStmt->bindValue(':barangay', $barangay);
    foreach ($params as $key => $val) {
        if ($key !== ':barangay') {
            $totalStmt->bindValue($key, $val);
        }
    }
    $totalStmt->execute();
    $totalRes = (int)$totalStmt->fetchColumn();

    $res_totalPages = max(1, ceil($totalRes / $res_limit));
    $res_page = min($res_page, $res_totalPages);
    $res_offset = ($res_page - 1) * $res_limit;

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        $whereSQL
        $searchSQL
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':barangay', $barangay);
    foreach ($params as $key => $val) {
        if ($key !== ':barangay') {
            $stmt->bindValue($key, $val);
        }
    }
    $stmt->bindValue(':limit', $res_limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $res_offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'totalRes' => $totalRes,
        'res_totalPages' => $res_totalPages,
        'res_page' => $res_page,
        'res_offset' => $res_offset,
        'residents' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ];
});

$totalRes = (int)($res_data['totalRes'] ?? 0);
$res_totalPages = (int)($res_data['res_totalPages'] ?? 1);
$res_page = (int)($res_data['res_page'] ?? $res_page);
$res_offset = (int)($res_data['res_offset'] ?? 0);
$residents = $res_data['residents'] ?? [];
?>