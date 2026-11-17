<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  GET SYSTEM LOGS — AJAX endpoint
 *  Returns paginated and filtered system logs for City Admin.
 * ─────────────────────────────────────────────────────────────
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'city_administrator') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$log_filter = $_GET['log_filter'] ?? 'today';
if (!in_array($log_filter, ['today','yesterday','week','month','all'])) {
    $log_filter = 'today';
}

$whereLog = match($log_filter) {
    'today'     => "DATE(l.created_at) = CURDATE()",
    'yesterday' => "DATE(l.created_at) = CURDATE() - INTERVAL 1 DAY",
    'week'      => "l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month'     => "MONTH(l.created_at) = MONTH(CURDATE()) AND YEAR(l.created_at) = YEAR(CURDATE())",
    'all'       => "1=1",
};

$log_page   = max(1, (int)($_GET['log_page'] ?? 1));
$log_limit  = 10;
$log_total  = (int)$pdo->query("SELECT COUNT(*) FROM system_logs l WHERE $whereLog")->fetchColumn();
$log_pages  = max(1, ceil($log_total / $log_limit));
$log_page   = min($log_page, $log_pages);
$log_offset = ($log_page - 1) * $log_limit;

$logsStmt = $pdo->prepare("
    SELECT l.*, u.full_name AS user_name, u.user_type
    FROM system_logs l
    LEFT JOIN users u ON l.user_id = u.user_id
    WHERE $whereLog
    ORDER BY l.created_at DESC
    LIMIT :lim OFFSET :off
");
$logsStmt->bindValue(':lim',  $log_limit,  PDO::PARAM_INT);
$logsStmt->bindValue(':off',  $log_offset, PDO::PARAM_INT);
$logsStmt->execute();
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// Format times for easier display on frontend
foreach ($logs as &$lg) {
    if (!empty($lg['created_at'])) {
        $lg['created_at_formatted'] = date('M d, Y h:i A', strtotime($lg['created_at']));
    } else {
        $lg['created_at_formatted'] = '—';
    }
}

echo json_encode([
    'success'    => true,
    'logs'       => $logs,
    'log_page'   => $log_page,
    'log_pages'  => $log_pages,
    'log_filter' => $log_filter,
    'log_total'  => $log_total
]);
