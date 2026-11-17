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
    $barangay = $_POST['barangay'] ?? '';
    $official_ids = $_POST['official_ids'] ?? [];
    $full_names = $_POST['full_names'] ?? [];

    if (empty($official_ids) || empty($barangay)) {
        header("Location: ../dashboards/city_administrator?error=" . urlencode("Invalid data provided."));
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE barangay_officials SET full_name = ? WHERE id = ?");
        
        for ($i = 0; $i < count($official_ids); $i++) {
            $id = $official_ids[$i];
            $name = trim($full_names[$i] ?? '');
            
            if (!empty($name)) {
                $stmt->execute([$name, $id]);
            }
        }

        $pdo->commit();

        $panel = 'officials-' . str_replace(' ', '-', $barangay);
        header("Location: ../dashboards/city_administrator?panel={$panel}&success=" . urlencode("All officials for {$barangay} updated successfully."));
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: ../dashboards/city_administrator?error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
} else {
    header("Location: ../dashboards/city_administrator");
    exit();
}
?>
