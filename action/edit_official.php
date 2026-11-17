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
    $official_id = $_POST['official_id'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');

    if (empty($official_id) || empty($full_name)) {
        header("Location: ../dashboards/city_administrator?error=" . urlencode("Official ID and Full Name are required."));
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE barangay_officials SET full_name = ? WHERE id = ?");
        $stmt->execute([$full_name, $official_id]);

        $panel = 'officials-' . str_replace(' ', '-', $barangay);
        header("Location: ../dashboards/city_administrator?panel={$panel}&success=" . urlencode("Barangay official updated successfully."));
        exit();
    } catch (PDOException $e) {
        header("Location: ../dashboards/city_administrator?error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
} else {
    header("Location: ../dashboards/city_administrator");
    exit();
}
?>
