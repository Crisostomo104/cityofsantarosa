<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

// Only employees and city admin can export
check_login_redirect(['employee', 'city_administrator']);

$role     = $_SESSION['user_type'] ?? '';
$barangay = $_SESSION['barangay']  ?? '';

// ── Build query based on role ─────────────────────────────────
if ($role === 'city_administrator') {
    // Export ALL requests across every barangay
    $stmt = $pdo->query("
        SELECT r.request_id, u.barangay, r.full_name, r.age, r.gender,
               r.address, r.contact_number, r.dob, r.civil_status,
               r.years_residency, r.valid_id, r.document_type, r.purpose,
               r.status, r.rejection_reason, r.created_at
        FROM requests r
        JOIN users u ON r.user_id = u.user_id
        ORDER BY r.created_at DESC
    ");
    $filename = 'all_requests_' . date('Y-m-d') . '.csv';
} else {
    // Employee: export only their barangay's requests
    $stmt = $pdo->prepare("
        SELECT r.request_id, u.barangay, r.full_name, r.age, r.gender,
               r.address, r.contact_number, r.dob, r.civil_status,
               r.years_residency, r.valid_id, r.document_type, r.purpose,
               r.status, r.rejection_reason, r.created_at
        FROM requests r
        JOIN users u ON r.user_id = u.user_id
        WHERE u.barangay = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$barangay]);
    $brgy_slug = strtolower(str_replace(' ', '_', $barangay));
    $filename  = 'requests_' . $brgy_slug . '_' . date('Y-m-d') . '.csv';
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    $_SESSION['form_error'] = 'No data to export.';
    header('Location: ../dashboards/' . ($role === 'city_administrator' ? 'city_administrator' : 'employee'));
    exit;
}

// ── Log the action ────────────────────────────────────────────
log_action(
    $pdo,
    $_SESSION['user_id'],
    'Export CSV',
    'Requests',
    ($role === 'city_administrator' ? 'Exported ALL requests' : "Exported requests for Barangay $barangay") . ' to CSV'
);

// ── Stream CSV to browser ─────────────────────────────────────
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
// BOM for Excel UTF-8 compatibility
fputs($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, array_keys($rows[0]));

foreach ($rows as $row) {
    fputcsv($out, $row);
}

fclose($out);
exit;
