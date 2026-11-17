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

$resident_id = $_POST['id'] ?? $_GET['id'] ?? null;
$admin_id    = $_SESSION['user_id'] ?? null;

if (!$resident_id || !$admin_id) {
    die("Invalid request");
}

// Get admin name
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
$stmt->execute([$admin_id]);
$actor = $stmt->fetch(PDO::FETCH_ASSOC);

// Get resident info
$stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ? AND user_type = 'resident'");
$stmt->execute([$resident_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    $_SESSION['admin_error'] = "Resident not found.";
    header("Location: ../dashboards/city_administrator");
    exit;
}

// Force logout: clear session_id (kicks them out without deactivating account)
$stmt = $pdo->prepare("UPDATE users SET session_id = NULL WHERE user_id = ?");
$stmt->execute([$resident_id]);

// Log
log_action(
    $pdo,
    $admin_id,
    'Force Logout Resident',
    $resident['email'] ?? null,
    'Resident was force-logged out by ' . ($actor['full_name'] ?? 'Admin')
);

$_SESSION['admin_success'] = "Resident \"" . $resident['full_name'] . "\" has been force-logged out.";
header("Location: ../dashboards/city_administrator?panel=residents");
exit;
