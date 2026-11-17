<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  CLOUD BACKUP — AWS S3 (ZIP-compressed, multi-section)
 *  Backs up the city admin's selected data sections as a single
 *  ZIP file (one CSV per table) to the configured AWS S3 bucket.
 * ─────────────────────────────────────────────────────────────
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/aws_s3.php';

ob_start();
ini_set('display_errors', '1'); // TEMPORARY: para makita ang totoong error. Ibalik sa '0' pagkatapos i-debug.
error_reporting(E_ALL);

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

// ── Read selected sections from the request body ───────────────
$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$sections = $input['sections'] ?? [];

if (!is_array($sections) || empty($sections)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'No section selected for backup.']);
    exit;
}

// TODO: hook this up to your existing CSRF check (functions.php),
// e.g. if (!verify_csrf_token($input['csrf_token'] ?? '')) { http_response_code(403); ...exit; }

// ── Whitelist: section key => tables. Must match the checkboxes in
//    the Cloud Backup modal (city_administrator.php) exactly, so this
//    action can never be made to touch a table that wasn't offered
//    to the admin in the UI. ─────────────────────────────────────
$sectionTableMap = [
    'requests'    => ['requests', 'payment_transactions', 'request_attachments'],
    'payouts'     => ['payout_requests'],
    'accounts'    => ['users', 'barangay_officials', 'official_update_requests'],
    'chatbot'     => ['chatbot_settings', 'chatbot_responses'],
    'docsettings' => ['document_settings', 'barangay_document_settings', 'document_prerequisites'],
    'logs'        => ['system_logs', 'notifications', 'announcements', 'otp_requests'],
];

$tablesToExport = [];
foreach ($sections as $sectionKey) {
    if (isset($sectionTableMap[$sectionKey])) {
        foreach ($sectionTableMap[$sectionKey] as $table) {
            $tablesToExport[$table] = true; // de-dupe in case a table is shared between sections
        }
    }
}
$tablesToExport = array_keys($tablesToExport);

if (empty($tablesToExport)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid section(s) selected.']);
    exit;
}

// ── Fetch every selected table in full (SELECT * per table) ────
// A backup is for disaster recovery, so we grab every column as-is
// rather than a curated report-style join (unlike export_requests).
$datasets  = [];
$totalRows = 0;

foreach ($tablesToExport as $table) {
    try {
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Skip a table that fails to query instead of failing the whole
        // backup — the frontend whitelist above should already prevent
        // this in normal use.
        continue;
    }
    $datasets[$table] = $rows;
    $totalRows += count($rows);
}

if ($totalRows === 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'No data found in the selected section(s).']);
    exit;
}

$baseName = 'einquire_backup_' . date('Y-m-d_H-i-s');

try {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive class not available — the zip PHP extension is not enabled on this server.');
    }

    // Reuse the same writable-temp-dir resolution logic as
    // upload_zip_csv_to_s3() (core/aws_s3.php) — tries ../tmp first,
    // falls back to sys_get_temp_dir() if that's not writable.
    $localTmpDir = get_backup_tmp_dir();
    $zipPath = $localTmpDir . '/' . $baseName . '.zip';

    $zip = new ZipArchive();
    $openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($openResult !== true) {
        $zipErrorMessages = [
            ZipArchive::ER_EXISTS => 'File already exists.',
            ZipArchive::ER_INCONS => 'Zip archive inconsistent.',
            ZipArchive::ER_INVAL  => 'Invalid argument.',
            ZipArchive::ER_MEMORY => 'Malloc failure.',
            ZipArchive::ER_NOENT  => 'No such file.',
            ZipArchive::ER_NOZIP  => 'Not a zip archive.',
            ZipArchive::ER_OPEN   => 'Can\'t open file — check folder permissions.',
            ZipArchive::ER_READ   => 'Read error.',
            ZipArchive::ER_SEEK   => 'Seek error.',
        ];
        $reason = $zipErrorMessages[$openResult] ?? ('Unknown error code: ' . $openResult);
        throw new RuntimeException("Could not create ZIP archive at $zipPath. Reason: $reason");
    }
    foreach ($datasets as $table => $rows) {
        if (empty($rows)) continue;

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, array_keys($rows[0])); // header row
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);

        $zip->addFromString($table . '.csv', $csvContent);
    }
    $zip->close();

    // ── ASSUMPTION (please confirm / adjust against core/aws_s3.php) ──
    // The original code called upload_zip_csv_to_s3($data, $baseName),
    // which only ever built a ZIP from ONE flat dataset (the `requests`
    // rows) internally. Since this action now builds a multi-CSV ZIP
    // itself above, it needs a helper that just uploads an
    // *already-built* ZIP file and returns ['name'=>, 'key'=>, 'link'=>],
    // e.g.:
    //
    //   function upload_zip_file_to_s3(string $localZipPath, string $baseName): array
    //
    // If core/aws_s3.php doesn't have that yet, either add it there
    // (reusing whatever S3Client/bucket/credential setup
    // upload_zip_csv_to_s3() already uses), or share that file so this
    // call can be pointed at the exact right function name.
    $upload = upload_zip_file_to_s3($zipPath, $baseName);

    @unlink($zipPath);

    log_action(
        $pdo,
        $userId,
        'Cloud Backup',
        'AWS S3',
        "Backed up $totalRows rows across " . count($datasets) . " table(s) [" . implode(', ', array_keys($datasets)) . "] as '{$upload['name']}' (Key: {$upload['key']})"
    );

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => "Backed up $totalRows rows across " . count($datasets) . " table(s) as \"{$upload['name']}\" (ZIP)",
        'key'     => $upload['key'],
        'link'    => $upload['link'],
    ]);

} catch (Throwable $e) {
    // Throwable catches Errors (fatal, class-not-found, etc.) too.
    // Kept as DEBUG ERROR to match the original file's debugging style.
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'DEBUG ERROR: ' . $e->getMessage() . ' (sa file: ' . $e->getFile() . ' line: ' . $e->getLine() . ')',
    ]);
}