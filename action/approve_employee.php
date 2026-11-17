<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

check_login_redirect(['city_administrator']);

// CSRF verification for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id > 0) {

    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? AND user_type = 'employee' LIMIT 1");
    $stmt->execute([$id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($emp) {
        $pdo->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ?")
            ->execute([$id]);

        log_action($pdo, $_SESSION['user_id'], 'Employee Approved', $emp['email'], 'Approved employee account: ' . $emp['full_name']);

        // Bust the dashboard caches so the approval reflects immediately
        // instead of waiting out the 300s cache TTL.
        clear_cached_data('city_admin_account_data');
        clear_cached_data('city_admin_dashboard_stats');

        $_SESSION['admin_success'] = "Employee account approved: " . $emp['full_name'];
    } else {
        $_SESSION['admin_error'] = "Employee not found.";
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/city_administrator'));
exit;
?>