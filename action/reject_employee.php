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

$id     = intval($_POST['id'] ?? $_GET['id'] ?? 0);
$reason = trim($_POST['reason'] ?? $_GET['reason'] ?? 'No reason provided.');

if ($id > 0) {

    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? AND user_type = 'employee' LIMIT 1");
    $stmt->execute([$id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($emp) {
        // Soft-reject: is_verified = 2, store reason in reset_token
        $pdo->prepare("UPDATE users SET is_verified = 2, reset_token = ? WHERE user_id = ?")
            ->execute([substr($reason, 0, 255), $id]);

        log_action($pdo, $_SESSION['user_id'], 'Employee Rejected', $emp['email'],
            'Rejected employee account: ' . $emp['full_name'] . ' | Reason: ' . $reason);

        // Bust the dashboard caches so the rejection reflects immediately
        // instead of waiting out the 300s cache TTL.
        clear_cached_data('city_admin_account_data');
        clear_cached_data('city_admin_dashboard_stats');

        $_SESSION['admin_success'] = "Employee account rejected: " . $emp['full_name'];
    } else {
        $_SESSION['admin_error'] = "Employee not found.";
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/city_administrator'));
exit;
?>