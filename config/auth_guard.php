<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: ../auth/login");
    exit;
}

/*
|--------------------------------------------------------------------------
| SESSION VALIDATION
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT session_id, user_type, barangay
    FROM users
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['session_id'] !== session_id()) {
    session_destroy();
    // Clear session cookie with secure attributes
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?? '/', 
            'domain'   => $params['domain'] ?? '',
            'secure'   => $params['secure'] ?? true,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => 'Lax'
        ]);
    }
    header("Location: ../auth/login?logged_out=1");
    exit;
}

/*
|--------------------------------------------------------------------------
| SYNC SESSION DATA (FIXED)
|--------------------------------------------------------------------------
*/
$_SESSION['user_type'] = $user['user_type'];
$_SESSION['barangay']   = $user['barangay'];

/*
|--------------------------------------------------------------------------
| ROLE GUARD
|--------------------------------------------------------------------------
*/
function require_role($roles = []) {
    if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], $roles)) {
        die("Unauthorized access.");
    }
}

/*
|--------------------------------------------------------------------------
| BARANGAY SCOPE (EMPLOYEE LOCK)
|--------------------------------------------------------------------------
*/
function get_barangay_scope() {
    // employees are locked to their barangay
    if (($_SESSION['user_type'] ?? null) === 'employee') {
        return $_SESSION['barangay'];
    }

    // admin sees all barangays
    return null;
}