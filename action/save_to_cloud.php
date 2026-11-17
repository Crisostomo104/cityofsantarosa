<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  CLOUD BACKUP — Google Drive (ZIP-compressed)
 *  Backs up all requests as a ZIP-compressed CSV to the
 *  city administrator's connected Google Drive account.
 *  ZIP reduces file size by ~70–90% vs raw CSV.
 * ─────────────────────────────────────────────────────────────
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/google_drive.php';

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

if (($_SESSION['user_type'] ?? '') !== 'city_administrator') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Access denied. City Administrator only.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

$driveResult = get_google_drive_service($pdo, $userId);

if (!$driveResult['success']) {
    ob_clean();
    echo json_encode($driveResult);
    exit;
}

// ── Fetch all request data ────────────────────────────────────
$stmt = $pdo->query("
    SELECT r.request_id, u.barangay, r.full_name, r.age, r.gender,
           r.address, r.contact_number, r.dob, r.civil_status,
           r.years_residency, r.valid_id, r.document_type, r.purpose,
           r.status, r.payment_status, r.rejection_reason,
           r.created_at, r.processed_by
    FROM requests r
    INNER JOIN users u ON r.user_id = u.user_id
    ORDER BY r.created_at DESC
");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$data) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'No request data to back up.']);
    exit;
}

$rowCount = count($data);
// Base name without extension — function adds .zip (or .csv fallback)
$baseName = 'einquire_requests_' . date('Y-m-d_H-i-s');

try {
    // ── Upload as ZIP-compressed CSV ────────────────────────
    $upload = upload_zip_csv_to_drive($driveResult['drive'], $baseName, $data);

    // Determine actual file name uploaded (zip or csv fallback)
    $uploadedName = $baseName . (class_exists('ZipArchive') ? '.zip' : '.csv');

    log_action(
        $pdo,
        $userId,
        'Cloud Backup',
        'Google Drive',
        "Backed up $rowCount rows as '$uploadedName' (ID: {$upload['file_id']})"
    );

    $fileType = class_exists('ZipArchive') ? 'ZIP' : 'CSV';
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => "Backed up $rowCount rows as \"$uploadedName\" ($fileType)",
        'file_id' => $upload['file_id'],
        'link'    => $upload['link'],
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Unexpected error: ' . $e->getMessage()]);
}
