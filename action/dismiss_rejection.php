<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

check_login_redirect(['employee']);

// CSRF verification for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

if (isset($_POST['id']) || isset($_GET['id'])) {
    $id       = intval($_POST['id'] ?? $_GET['id']);
    $barangay = $_SESSION['barangay'];

    // Only delete if it belongs to this employee's barangay and is rejected
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND barangay = ? AND is_verified = 2 AND user_type = 'employee'");
    $stmt->execute([$id, $barangay]);

    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$id]);
    }
}

header("Location: ../dashboards/employee");
exit;
?>
