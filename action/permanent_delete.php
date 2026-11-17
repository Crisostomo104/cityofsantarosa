<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../config/functions.php'; 
check_login_redirect(['employee']); // Only employees can delete

// CSRF verification for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

if (isset($_POST['id']) || isset($_GET['id'])) {
    $id = intval($_POST['id'] ?? $_GET['id']);

    // Fetch the archived request
    $stmt = $pdo->prepare("SELECT * FROM archive_requests WHERE request_id=? LIMIT 1");
    $stmt->execute([$id]);
    $archived = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($archived) {
        // Permanently delete
        $stmt = $pdo->prepare("DELETE FROM archive_requests WHERE request_id=?");
        $stmt->execute([$id]);

        // Log the action
        log_action(
            $pdo,
            $_SESSION['user_id'],
            'Request Permanently Deleted',
            'ARC-00'.$id,
            'Permanently deleted archived request for '.$archived['full_name']
        );

        $_SESSION['form_success'] = "Archived request deleted successfully!";
    } else {
        $_SESSION['form_error'] = "Archived request not found.";
    }
} else {
    $_SESSION['form_error'] = "No request ID provided.";
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
exit;
?>