<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth_guard.php';

// Only allow employees and city administrators to view logs
check_login_redirect(['employee', 'city_administrator']);

$logFile = __DIR__ . '/../logs/paymongo_errors.log';
$entries = [];
if (file_exists($logFile)) {
    $lines = array_filter(array_map('trim', file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)));
    // show newest first
    $lines = array_reverse($lines);
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if ($decoded) $entries[] = $decoded; else $entries[] = ['raw' => $line];
        if (count($entries) >= 200) break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PayMongo Logs — E-Inquire</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h3>PayMongo Error Logs</h3>
    <p class="text-muted">Showing latest entries (most recent first). Only visible to authorized personnel.</p>
    <?php if (empty($entries)): ?>
        <div class="alert alert-info">No log entries found.</div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($entries as $e): ?>
                <div class="list-group-item">
                    <div><strong><?= htmlspecialchars($e['time'] ?? date('c')) ?></strong> — Request: <?= htmlspecialchars($e['request_id'] ?? '—') ?> — Provider: <?= htmlspecialchars($e['provider'] ?? '—') ?></div>
                    <pre style="white-space:pre-wrap;margin-top:8px;"><?= htmlspecialchars(json_encode($e, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <a href="../dashboards/city_administrator" class="btn btn-sm btn-secondary mt-3">Back</a>
</div>
</body>
</html>
