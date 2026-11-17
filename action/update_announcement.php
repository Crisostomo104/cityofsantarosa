<?php
/**
 * Update an existing announcement.
 * POST only: id, title, body, target_role, barangay, expires_at, is_active
 * Optional: image (file upload for replacing the picture)
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

$id         = intval($_POST['id'] ?? 0);
$title      = trim($_POST['title'] ?? '');
$body       = trim($_POST['body'] ?? '');
$targetRole = in_array($_POST['target_role'] ?? '', ['all', 'employee', 'resident', 'dashboard']) ? $_POST['target_role'] : 'all';
$barangay   = strtoupper(trim($_POST['barangay'] ?? ''));
$expiresAt  = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
$isActive   = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

if ($id <= 0 || !$title) {
    $_SESSION['admin_error'] = "ID and title are required.";
    header("Location: ../dashboards/city_administrator?panel=announcements");
    exit;
}

// Default body if empty
if (!$body) $body = '(Image Announcement)';

ensure_announcements_table($pdo);

// ── Handle optional image upload (cropped image) ──
$imagePath = null;
if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024;
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);

    if (in_array($mime, $allowed) && $_FILES['image']['size'] <= $maxSize) {
        $uploadDir = __DIR__ . '/../uploads/announcements/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg'
        };
        $filename = 'ann_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
            // Delete old image
            $old = $pdo->prepare("SELECT image FROM announcements WHERE id = ?");
            $old->execute([$id]);
            $oldRow = $old->fetch(PDO::FETCH_ASSOC);
            if ($oldRow && !empty($oldRow['image'])) {
                $oldPath = __DIR__ . '/../' . $oldRow['image'];
                if (file_exists($oldPath)) unlink($oldPath);
            }
            $imagePath = 'uploads/announcements/' . $filename;
        }
    }
}

// Build UPDATE query
if ($imagePath) {
    $stmt = $pdo->prepare("
        UPDATE announcements
        SET title = ?, body = ?, image = ?, target_role = ?, barangay = ?, expires_at = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->execute([$title, $body, $imagePath, $targetRole, $barangay ?: null, $expiresAt, $isActive, $id]);
} else {
    $stmt = $pdo->prepare("
        UPDATE announcements
        SET title = ?, body = ?, target_role = ?, barangay = ?, expires_at = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->execute([$title, $body, $targetRole, $barangay ?: null, $expiresAt, $isActive, $id]);
}

log_action($pdo, $_SESSION['user_id'], 'Edit Announcement', $title, "Announcement #$id updated for: $targetRole");

clear_announcements_cache($pdo);

$_SESSION['admin_success'] = "Announcement updated successfully.";
header("Location: ../dashboards/city_administrator?panel=announcements");
exit;
