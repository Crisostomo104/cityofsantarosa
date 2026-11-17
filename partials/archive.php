<?php
require_once __DIR__ . '/pagination.php';

$archive_filter = $_GET['archive_filter'] ?? 'all';

$whereArchive = match($archive_filter) {
    'today'     => "WHERE DATE(a.created_at) = CURDATE()",
    'yesterday' => "WHERE DATE(a.created_at) = CURDATE() - INTERVAL 1 DAY",
    'week'      => "WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month'     => "WHERE MONTH(a.created_at) = MONTH(CURDATE()) 
                    AND YEAR(a.created_at) = YEAR(CURDATE())",
    'all'       => "",
    default     => "",
};

$archive_limit = 10;
$archive_page = max(1, (int)($_GET['archive_page'] ?? 1));

$totalArchive = $pdo->query("
    SELECT COUNT(*) 
    FROM archive_requests a
    $whereArchive
")->fetchColumn();

$archive_totalPages = max(1, ceil($totalArchive / $archive_limit));

$archive_page = min($archive_page, $archive_totalPages);
$archive_offset = ($archive_page - 1) * $archive_limit;

$stmt = $pdo->prepare("
    SELECT a.*, u.full_name AS resident_name
    FROM archive_requests a
    LEFT JOIN users u ON a.user_id = u.user_id
    $whereArchive
    ORDER BY a.created_at DESC
    LIMIT $archive_limit OFFSET $archive_offset
");

$stmt->execute();
$archived_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>