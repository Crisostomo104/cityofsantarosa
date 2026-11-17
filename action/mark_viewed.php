<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

check_login_redirect(['employee']);

if (isset($_GET['id'])) {

    $id = intval($_GET['id']);

    // Check if request exists
    $stmt = $pdo->prepare("
        SELECT request_id, full_name
        FROM requests
        WHERE request_id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {

        // Update viewed status
        $stmt = $pdo->prepare("
            UPDATE requests
            SET is_viewed = 1
            WHERE request_id = ?
        ");

        $stmt->execute([$id]);

        // Optional log
        log_action(
            $pdo,
            $_SESSION['user_id'],
            'Viewed Request',
            'Request #' . $id,
            'Viewed request for ' . $request['full_name']
        );

        echo "success";

    } else {

        echo "request not found";
    }
}
?>