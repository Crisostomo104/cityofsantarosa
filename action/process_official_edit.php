<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in as city administrator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'city_administrator') {
    header("Location: ../");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'] ?? '';
    $action = $_POST['action'] ?? '';

    if (empty($request_id) || empty($action)) {
        header("Location: ../dashboards/city_administrator?error=" . urlencode("Invalid data provided."));
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM official_update_requests WHERE id = ? FOR UPDATE");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$req || $req['status'] !== 'Pending') {
            $pdo->rollBack();
            header("Location: ../dashboards/city_administrator?panel=officials-requests&error=" . urlencode("Request not found or already processed."));
            exit();
        }

        if ($action === 'approve') {
            // Update the actual official record
            $update_stmt = $pdo->prepare("UPDATE barangay_officials SET full_name = ? WHERE id = ?");
            $update_stmt->execute([$req['proposed_name'], $req['official_id']]);
            
            // Mark request as Approved
            $status_stmt = $pdo->prepare("UPDATE official_update_requests SET status = 'Approved' WHERE id = ?");
            $status_stmt->execute([$request_id]);

            $msg = "Edit request approved successfully.";
        } elseif ($action === 'reject') {
            // Mark request as Rejected
            $status_stmt = $pdo->prepare("UPDATE official_update_requests SET status = 'Rejected' WHERE id = ?");
            $status_stmt->execute([$request_id]);

            $msg = "Edit request rejected.";
        } else {
            $pdo->rollBack();
            header("Location: ../dashboards/city_administrator?panel=officials-requests&error=" . urlencode("Invalid action."));
            exit();
        }

        $pdo->commit();
        header("Location: ../dashboards/city_administrator?panel=officials-requests&success=" . urlencode($msg));
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: ../dashboards/city_administrator?panel=officials-requests&error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
} else {
    header("Location: ../dashboards/city_administrator");
    exit();
}
?>
