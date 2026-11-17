<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

check_login_redirect(['city_administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed');
}
csrf_verify();

$employee_id = $_POST['id'] ?? $_GET['id'] ?? null;
$admin_id = $_SESSION['user_id'] ?? null; // Who is performing this action

if (!$employee_id || !$admin_id) {
    die("Invalid request");
}

$stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
$stmt->execute([$admin_id]);
$actor = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Get employee info ---
$stmt = $pdo->prepare("SELECT email, full_name, session_id FROM users WHERE user_id = ? AND user_type = 'employee'");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    $_SESSION['admin_error'] = "Employee not found.";
    header("Location: ../dashboards/city_administrator?panel=employees");
    exit;
}

// --- Deactivate by setting session_id to 'DEACTIVATED' ---
$stmt = $pdo->prepare("UPDATE users SET session_id = 'DEACTIVATED' WHERE user_id = ? AND user_type = 'employee'");
$stmt->execute([$employee_id]);

// --- Log the action ---
log_action(
    $pdo,
    $admin_id,
    'Deactivate Employee',
    $employee['email'] ?? null,
    'User account deactivated by ' . ($actor['full_name'] ?? 'Unknown Employee')
);

clear_cached_data('city_admin_account_data');

// --- Redirect back ---
$_SESSION['admin_success'] = "Employee \"" . $employee['full_name'] . "\" has been deactivated.";
header("Location: ../dashboards/city_administrator?panel=employees");
exit;