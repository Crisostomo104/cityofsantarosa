<?php
/**
 * Save (create) a new announcement with optional image upload.
 * POST only: title, body, target_role, barangay, expires_at, image (file)
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

$title      = trim($_POST['title'] ?? '');
$body       = trim($_POST['body'] ?? '');
$targetRole = in_array($_POST['target_role'] ?? '', ['all', 'employee', 'resident', 'dashboard']) ? $_POST['target_role'] : 'all';
$barangay   = strtoupper(trim($_POST['barangay'] ?? ''));
$expiresAt  = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

if (!$title || !$body) {
    $_SESSION['admin_error'] = "Title and body are required.";
    header("Location: ../dashboards/city_administrator?panel=announcements");
    exit;
}

// ── Handle Image Upload ──
$imagePath = null;
if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowed   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize   = 5 * 1024 * 1024; // 5MB
    $finfo     = finfo_open(FILEINFO_MIME_TYPE);
    $mime      = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed)) {
        $_SESSION['admin_error'] = "Invalid image format. Allowed: JPG, PNG, GIF, WEBP.";
        header("Location: ../dashboards/city_administrator?panel=announcements");
        exit;
    }

    if ($_FILES['image']['size'] > $maxSize) {
        $_SESSION['admin_error'] = "Image too large. Maximum size is 5MB.";
        header("Location: ../dashboards/city_administrator?panel=announcements");
        exit;
    }

    // Create upload directory if needed
    $uploadDir = __DIR__ . '/../uploads/announcements/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $ext       = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default      => 'jpg'
    };
    $filename  = 'ann_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath  = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
        $imagePath = 'uploads/announcements/' . $filename;
    } else {
        $_SESSION['admin_error'] = "Failed to save uploaded image.";
        header("Location: ../dashboards/city_administrator?panel=announcements");
        exit;
    }
}

// Ensure table exists
ensure_announcements_table($pdo);

$isActive   = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

$stmt = $pdo->prepare("
    INSERT INTO announcements (title, body, image, target_role, barangay, is_active, created_by, expires_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$title, $body, $imagePath, $targetRole, $barangay ?: null, $isActive, $_SESSION['user_id'], $expiresAt]);

log_action($pdo, $_SESSION['user_id'], 'Create Announcement', $title, "Announcement posted for: $targetRole" . ($barangay ? " | Barangay: $barangay" : " | All barangays"));

clear_announcements_cache($pdo);

$_SESSION['admin_success'] = "Announcement posted successfully.";
header("Location: ../dashboards/city_administrator?panel=announcements");
exit;
