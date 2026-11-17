<?php
/**
 * Toggle announcement active/inactive status or delete.
 * POST only: id, action (toggle|delete)
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/announcements.php';

check_login_redirect(['city_administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['admin_error'] = "Invalid request method.";
    header("Location: ../dashboards/city_administrator?panel=announcements");
    exit;
}

csrf_verify();

$id     = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? 'toggle';

if ($id <= 0) {
    $_SESSION['admin_error'] = "Invalid announcement.";
    header("Location: ../dashboards/city_administrator?panel=announcements");
    exit;
}

if ($action === 'delete') {
    // Delete associated image file if it exists
    $stmt = $pdo->prepare("SELECT image FROM announcements WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['image'])) {
        $imgPath = __DIR__ . '/../' . $row['image'];
        if (file_exists($imgPath)) {
            unlink($imgPath);
        }
    }

    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
    log_action($pdo, $_SESSION['user_id'], 'Delete Announcement', "ID: $id", "Announcement deleted");
    $_SESSION['admin_success'] = "Announcement deleted.";
} else {
    // Toggle active status
    $pdo->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    log_action($pdo, $_SESSION['user_id'], 'Toggle Announcement', "ID: $id", "Announcement toggled");
    $_SESSION['admin_success'] = "Announcement status updated.";
}

clear_announcements_cache($pdo);

header("Location: ../dashboards/city_administrator?panel=announcements");
exit;
