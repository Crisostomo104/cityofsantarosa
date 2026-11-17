<?php
// logs.php (CLEAN VERSION)
// assumes: $pdo already exists from employee_dashboard.php

$log_filter = $_GET['log_filter'] ?? 'today';

$allowedFilters = ['today','yesterday','week','month','all'];
if (!in_array($log_filter, $allowedFilters)) {
    $log_filter = 'today';
}

$whereLog = match($log_filter) {
    'today'     => "DATE(l.created_at) = CURDATE()",
    'yesterday' => "DATE(l.created_at) = CURDATE() - INTERVAL 1 DAY",
    'week'      => "l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month'     => "MONTH(l.created_at) = MONTH(CURDATE()) AND YEAR(l.created_at) = YEAR(CURDATE())",
    'all'       => "1=1",
};

$log_limit = 10;
$log_page = max(1, (int)($_GET['log_page'] ?? 1));

// TOTAL COUNT
$totalStmt = $pdo->query("
    SELECT COUNT(*)
    FROM system_logs l
    WHERE $whereLog
");
$totalLogs = $totalStmt->fetchColumn();

$log_totalPages = max(1, ceil($totalLogs / $log_limit));
$log_page = min($log_page, $log_totalPages);

$log_offset = ($log_page - 1) * $log_limit;

// FETCH LOGS
$logsStmt = $pdo->prepare("
    SELECT l.*, u.full_name AS user_name
    FROM system_logs l
    LEFT JOIN users u ON l.user_id = u.user_id
    WHERE $whereLog
    ORDER BY l.created_at DESC
    LIMIT :limit OFFSET :offset
");

$logsStmt->bindValue(':limit', $log_limit, PDO::PARAM_INT);
$logsStmt->bindValue(':offset', $log_offset, PDO::PARAM_INT);

$logsStmt->execute();
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
?>