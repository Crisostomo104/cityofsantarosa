<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

$target_id = $_GET['id'] ?? null;
$actor_id = $_SESSION['user_id'] ?? null; // 👈 ADMIN DOING THE ACTION

if (!$target_id || !$actor_id) {
    die("Invalid request");
}

/*
-------------------------------------------------
1. Get target user info
-------------------------------------------------
*/
$stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ?");
$stmt->execute([$target_id]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);

/*
-------------------------------------------------
2. Get admin info (who did it)
-------------------------------------------------
*/
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
$stmt->execute([$actor_id]);
$actor = $stmt->fetch(PDO::FETCH_ASSOC);

/*
-------------------------------------------------
3. Force logout (clear session)
-------------------------------------------------
*/
$stmt = $pdo->prepare("UPDATE users SET session_id = NULL WHERE user_id = ?");
$stmt->execute([$target_id]);

/*
-------------------------------------------------
4. LOG ACTION (NOW WITH WHO DID IT)
-------------------------------------------------
*/
log_action(
    $pdo,
    $actor_id,
    'Forced Logout',
    $target['email'] ?? null,
    'User was forced logged out by ' . ($actor['full_name'] ?? 'Admin')
);

/*
-------------------------------------------------
5. Redirect back to admin page
-------------------------------------------------
*/
header("Location: ../dashboards/employee?kicked=1");
exit;
?>