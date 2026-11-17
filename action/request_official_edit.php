<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
// Only employees can request edits
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $official_id = $_POST['official_id'] ?? '';
    $proposed_name = trim($_POST['proposed_name'] ?? '');
    $proposed_contact = trim($_POST['proposed_contact'] ?? '');
    $user_id = $_SESSION['user_id'];
    if (empty($official_id) || empty($proposed_name)) {
        $_SESSION['sms_error'] = "Proposed name is required.";
        header("Location: ../dashboards/employee?panel=officials");
        exit();
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO official_update_requests (official_id, requested_by, proposed_name, proposed_contact) VALUES (?, ?, ?, ?)");
        $stmt->execute([$official_id, $user_id, $proposed_name, $proposed_contact]);

        // Bust the pending-edits count cache so the city admin dashboard
        // shows this new request immediately instead of after 300s.
        clear_cached_data('city_admin_pending_edits');

        $_SESSION['sms_success'] = "Edit request submitted successfully. Awaiting administrator approval.";
        header("Location: ../dashboards/employee?panel=officials");
        exit();
    } catch (PDOException $e) {
        $_SESSION['sms_error'] = "Database error: " . $e->getMessage();
        header("Location: ../dashboards/employee?panel=officials");
        exit();
    }
} else {
    header("Location: ../dashboards/employee");
    exit();
}
?>