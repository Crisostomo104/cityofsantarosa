<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once '../config/db.php';
require_once '../config/session.php';

require_once '../config/functions.php'; 

require_once __DIR__ . '/../core/request_workflow.php';
check_login_redirect(['employee']);
ensure_request_workflow_schema($pdo);

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Fetch the archived request
    $stmt = $pdo->prepare("SELECT * FROM archive_requests WHERE request_id=? LIMIT 1");
    $stmt->execute([$id]);
    $arch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($arch) {
        // Restore to requests table
        $stmt = $pdo->prepare("
            INSERT INTO requests (


                user_id, full_name, document_name_parts, age, gender, address, contact_number,

                dob, civil_status, years_residency, valid_id, document_type, purpose,

                status, rejection_reason, processed_by, created_at

            ) VALUES (


                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");
        $stmt->execute([
            $arch['user_id'],

            $arch['full_name'],

            $arch['document_name_parts'] ?? null,
            $arch['age'],
            $arch['gender'],
            $arch['address'],
            $arch['contact_number'],
            $arch['dob'],
            $arch['civil_status'],
            $arch['years_residency'],
            $arch['valid_id'],
            $arch['document_type'],
            $arch['purpose'],
            $arch['status'],
            $arch['rejection_reason'],
            $arch['processed_by'],
            $arch['created_at']
        ]);

        // Delete from archive_requests
        $stmt = $pdo->prepare("DELETE FROM archive_requests WHERE request_id=?");
        $stmt->execute([$id]);

        // Log the action
        log_action(
            $pdo,
            $_SESSION['user_id'],
            'Request Restored',
            'REQ-00'.$id,
            'Restored request ID ARC - '.$id
        );
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
exit;
?>