<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

check_login_redirect(['employee']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$barangay = $_SESSION['barangay'] ?? '';
if (empty($barangay)) {
    $_SESSION['sms_error'] = "Unable to determine your barangay.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
    exit;
}

// Make sure the table exists (safe to call every request, no-op if already created)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS barangay_document_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        barangay VARCHAR(100) NOT NULL,
        document_type VARCHAR(150) NOT NULL,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_brgy_doctype (barangay, document_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// $_POST['enabled_documents'] is an array of document_type values whose
// checkboxes were checked. Every OTHER known document_type (city-wide list)
// gets explicitly disabled for this barangay — checkboxes only submit when
// checked, so absence from the array means "unchecked".
$enabledDocs = $_POST['enabled_documents'] ?? [];
if (!is_array($enabledDocs)) {
    $enabledDocs = [];
}
$enabledDocs = array_map('strval', $enabledDocs);

$allDocTypes = $pdo->query("SELECT DISTINCT document_type FROM document_prerequisites")
    ->fetchAll(PDO::FETCH_COLUMN);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO barangay_document_settings (barangay, document_type, is_enabled)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)
    ");

    foreach ($allDocTypes as $docType) {
        $isEnabled = in_array($docType, $enabledDocs, true) ? 1 : 0;
        $stmt->execute([$barangay, $docType, $isEnabled]);
    }

    $pdo->commit();

    log_action(
        $pdo,
        (int) $_SESSION['user_id'],
        'Document Settings Updated',
        $barangay,
        'Enabled document types: ' . (empty($enabledDocs) ? '(none)' : implode(', ', $enabledDocs))
    );

    $_SESSION['sms_success'] = "Document settings updated for " . htmlspecialchars($barangay) . ".";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['sms_error'] = "Failed to update document settings.";
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboards/employee'));
exit;
