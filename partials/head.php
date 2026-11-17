<?php
/**
 * ─────────────────────────────────────────────────────────────
 *  PARTIAL: <head> — Shared head includes for all dashboards.
 *  Usage: <?php include __DIR__ . '/../partials/head.php'; ?>
 *
 *  Expected variables:
 *   $page_title  — The page <title>
 *   $extra_css    — Optional array of additional CSS file paths
 * ─────────────────────────────────────────────────────────────
 */
$page_title = $page_title ?? 'E-Inquire';
$extra_css  = $extra_css  ?? [];
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($page_title) ?></title>
<link rel="icon" href="../images/ibaba2.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../css/shared_dashboard.css">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<?php foreach ($extra_css as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
<?php endforeach; ?>
