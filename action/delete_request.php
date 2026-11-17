<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../config/functions.php'; 
require_once __DIR__ . '/../core/request_workflow.php';
check_login_redirect(['employee']);

// CSRF verification for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}
ensure_request_workflow_schema($pdo);

$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id > 0) {

    // Fetch request first
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE request_id=? LIMIT 1");
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        // Insert into archive_requests
        $stmt = $pdo->prepare("
            INSERT INTO archive_requests (
                request_id, user_id, full_name, relationship, age, gender, address, contact_number, document_name_parts,
                dob, civil_status, years_residency, valid_id, document_type, purpose,
                status, rejection_reason, processed_by, created_at, deleted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $request['request_id'],
            $request['user_id'],
            $request['full_name'],
            $request['relationship'] ?? null,
            $request['age'],
            $request['gender'],

            $request['address'],

            $request['contact_number'],

            $request['document_name_parts'] ?? null,
            $request['dob'],
            $request['civil_status'],
            $request['years_residency'],
            $request['valid_id'],
            $request['document_type'],
            $request['purpose'],
            $request['status'],
            $request['rejection_reason'],
            $request['processed_by'],
            $request['created_at'],
            date('Y-m-d H:i:s')
        ]);

        // Delete from requests
        $stmt = $pdo->prepare("DELETE FROM requests WHERE request_id=?");
        $stmt->execute([$id]);

        log_action(
            $pdo,
            $_SESSION['user_id'],
            'Request Archived',
            'REQ-00'.$id,
            'Archived request for '.$request['full_name']
        );
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>