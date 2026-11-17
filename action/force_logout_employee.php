<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

check_login_redirect(['city_administrator']);

// CSRF verification for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$employee_id = $_POST['id'] ?? $_GET['id'] ?? null;
$admin_id    = $_SESSION['user_id'] ?? null;

if (!$employee_id || !$admin_id) {
    die("Invalid request");
}

// Get admin name
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
$stmt->execute([$admin_id]);
$actor = $stmt->fetch(PDO::FETCH_ASSOC);

// Get employee info
$stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ? AND user_type = 'employee'");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    $_SESSION['admin_error'] = "Employee not found.";
    header("Location: ../dashboards/city_administrator");
    exit;
}

// Force logout: clear active session without deactivating
$stmt = $pdo->prepare("UPDATE users SET session_id = NULL WHERE user_id = ?");
$stmt->execute([$employee_id]);

// Log
log_action(
    $pdo,
    $admin_id,
    'Force Logout Employee',
    $employee['email'] ?? null,
    'Employee was force-logged out by ' . ($actor['full_name'] ?? 'Admin')
);

$_SESSION['admin_success'] = "Employee \"" . $employee['full_name'] . "\" has been force-logged out.";
header("Location: ../dashboards/city_administrator?panel=employees");
exit;
