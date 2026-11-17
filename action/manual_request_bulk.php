<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  BULK MANUAL REQUEST ("Quick Add")
 *  Mirrors action/manual_request.php, but for many rows at once.
 *
 *  Per row it does exactly what the single form does:
 *    1. INSERT into requests  (status Completed, walk-in, processed_by = employee)
 *    2. generate_tracking_code()
 *    3. record_payment_transaction()   (only if amount > 0)
 *    4. log_action()
 *
 *  Step 1 for ALL rows is one transaction: all rows save, or none do.
 *  Steps 2-4 run right after; if one of them fails for a row, the row is
 *  kept and the failure is reported (see the end of this file).
 * ─────────────────────────────────────────────────────────────
 */

// Set to true TEMPORARILY to see the real cause of an error on screen. Set back to false after.
const QUICK_ADD_DEBUG = false;

if (QUICK_ADD_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// A fatal error (undefined function, missing file, ...) skips try/catch and gives a blank 500.
// This always writes the real reason to the PHP error log.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('manual_request_bulk FATAL: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        if (QUICK_ADD_DEBUG) {
            echo '<pre style="padding:12px;background:#fee;color:#900;">FATAL: '
               . htmlspecialchars($e['message']) . "\n" . htmlspecialchars($e['file']) . ':' . (int)$e['line'] . '</pre>';
        }
    }
});

// Same includes, same order, as action/manual_request.php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/payments.php';
require_once __DIR__ . '/../core/tracking.php';
require_once __DIR__ . '/../core/prerequisites.php';

// Readable message instead of a blank 500 if something is not loaded.
foreach (['check_login_redirect', 'csrf_token', 'generate_tracking_code', 'record_payment_transaction', 'log_action'] as $fn) {
    if (!function_exists($fn)) {
        error_log("manual_request_bulk: $fn() is not defined.");
        http_response_code(500);
        exit("Setup error: $fn() is not defined in this action.");
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log('manual_request_bulk: $pdo is not available.');
    http_response_code(500);
    exit('Setup error: database connection ($pdo) is not available in this action.');
}

// ONLY EMPLOYEES CAN ACCESS
check_login_redirect(['employee']);

const QUICK_ADD_MAX_ROWS = 100;

/** Redirect back to the page the modal was opened from. */
function quick_add_redirect(): void
{
    $back = (string)($_POST['return_to'] ?? '');
    // Only allow same-site absolute paths ("/x/y"), never "//evil.com" or full URLs.
    if ($back === '' || $back[0] !== '/' || str_starts_with($back, '//') || str_contains($back, "\n") || str_contains($back, "\r")) {
        $back = '../dashboards/employee';
    }
    header('Location: ' . $back . '?panel=requests');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    quick_add_redirect();
}

// ── CSRF ─────────────────────────────────────────────────────
$postedToken = (string)($_POST['csrf_token'] ?? '');
$expectedToken = (string)csrf_token();
if ($postedToken === '' || !hash_equals($expectedToken, $postedToken)) {
    // Diagnostics: lengths and key NAMES only, never the token itself.
    $csrfKeys = array_filter(array_keys($_SESSION ?? []), fn($k) => stripos((string)$k, 'csrf') !== false);
    $detail = ' [posted_len=' . strlen($postedToken)
            . ', expected_len=' . strlen($expectedToken)
            . ', session_csrf_keys=' . (implode(',', $csrfKeys) ?: 'none') . ']';
    error_log('manual_request_bulk CSRF mismatch' . $detail);
    $_SESSION['sms_error'] = 'Invalid or expired form. Please reload the page and try again.' . $detail;
    quick_add_redirect();
}

$employee_id = (int)($_SESSION['user_id'] ?? 0);
$barangay    = (string)($_SESSION['barangay'] ?? '');
if ($employee_id <= 0 || $barangay === '') {
    $_SESSION['sms_error'] = 'Session problem: your account has no barangay assigned.';
    quick_add_redirect();
}

// ── Read the parallel arrays ─────────────────────────────────
$names    = $_POST['q_full_name']       ?? [];
$docTypes = $_POST['q_document_type']   ?? [];
$purposes = $_POST['q_purpose']         ?? [];
$amounts  = $_POST['q_payment_amount']  ?? [];
$pwdFlags = $_POST['q_pwd_flag']        ?? [];   // hidden input, ALWAYS one per row (0 or 1)
$dates    = $_POST['q_date_requested']  ?? [];

foreach ([$names, $docTypes, $purposes, $amounts, $pwdFlags, $dates] as $arr) {
    if (!is_array($arr)) {
        $_SESSION['sms_error'] = 'Malformed submission.';
        quick_add_redirect();
    }
}

$rowCount = count($names);
if ($rowCount === 0) {
    $_SESSION['sms_error'] = 'No requests to save.';
    quick_add_redirect();
}
if ($rowCount > QUICK_ADD_MAX_ROWS) {
    $_SESSION['sms_error'] = 'Too many rows (max ' . QUICK_ADD_MAX_ROWS . ' per batch).';
    quick_add_redirect();
}
foreach ([$docTypes, $purposes, $amounts, $pwdFlags, $dates] as $arr) {
    if (count($arr) !== $rowCount) {
        $_SESSION['sms_error'] = 'Malformed submission: row data does not line up.';
        quick_add_redirect();
    }
}

// ── Allowed document types ───────────────────────────────────
$allowedDocs = function_exists('get_available_document_types')
    ? get_available_document_types($pdo)
    : $pdo->query("SELECT DISTINCT document_type FROM document_prerequisites")->fetchAll(PDO::FETCH_COLUMN);
$allowedDocs = array_flip(array_map('strval', $allowedDocs));

// ── Validate every row, collect ALL errors ───────────────────
$errors = [];
$clean  = [];
$today  = date('Y-m-d');

for ($i = 0; $i < $rowCount; $i++) {
    $n = $i + 1;

    $name    = trim((string)$names[$i]);
    $doc     = trim((string)$docTypes[$i]);
    $purpose = trim((string)$purposes[$i]);
    $amtRaw  = trim((string)$amounts[$i]);
    $date    = trim((string)$dates[$i]);
    $pwd     = ((string)$pwdFlags[$i] === '1') ? 1 : 0;

    if ($name === '' || mb_strlen($name) > 150)          $errors[] = "Row $n: Full Name is required (max 150 chars).";
    if ($doc === '' || !isset($allowedDocs[$doc]))        $errors[] = "Row $n: Document Type is invalid.";
    if ($purpose === '' || mb_strlen($purpose) > 255)     $errors[] = "Row $n: Purpose is required (max 255 chars).";
    if ($amtRaw === '' || !is_numeric($amtRaw) || (float)$amtRaw < 0 || (float)$amtRaw > 100000) {
        $errors[] = "Row $n: Amount must be a number from 0 to 100,000.";
    }

    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date)            $errors[] = "Row $n: Date Requested is invalid.";
    elseif ($date > $today)                              $errors[] = "Row $n: Date Requested cannot be in the future.";

    $amount = round((float)$amtRaw, 2);

    $clean[] = [
        'full_name'      => mb_strtoupper($name),
        'doc'            => $doc,
        'purpose'        => mb_strtoupper($purpose),
        'amount'         => $amount,
        // same rule as manual_request.php
        'payment_status' => ($amount > 0) ? 'paid' : 'unpaid',
        'pwd'            => $pwd,
        'date'           => $date,
    ];
}

if ($errors) {
    $_SESSION['sms_error'] = 'Nothing was saved. ' . implode(' ', array_slice($errors, 0, 5))
        . (count($errors) > 5 ? ' (+' . (count($errors) - 5) . ' more)' : '');
    quick_add_redirect();
}

// ── STEP 1: insert every row in ONE transaction ──────────────
// Columns not collected by Quick Add (age, gender, dob, civil_status,
// years_residency) are left out. If your table has them as NOT NULL,
// run the ALTERs in sql/01_quick_add_migration.sql.
// contact_number / valid_id use the same defaults the single form ends up with.
$sql = "
    INSERT INTO requests (
        user_id, full_name, address, contact_number, valid_id,
        document_type, purpose,
        status, processed_by,
        preferred_payment_method, payment_status, payment_amount, payment_reference,
        pwd_senior_requested, pwd_senior_verified,
        is_viewed, source, date_originally_requested,
        created_at
    ) VALUES (
        :user_id, :full_name, :address, '', 'N/A',
        :document_type, :purpose,
        'Completed', :processed_by,
        'walk-in', :payment_status, :payment_amount, 'walk-in',
        :pwd_req, :pwd_ver,
        1, 'manual_bulk', :date_requested,
        NOW()
    )
";

$savedIds = [];   // request_id => clean row

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare($sql);

    foreach ($clean as $row) {
        $stmt->execute([
            ':user_id'        => $employee_id,
            ':full_name'      => $row['full_name'],
            ':address'        => $barangay,
            ':document_type'  => $row['doc'],
            ':purpose'        => $row['purpose'],
            ':processed_by'   => $employee_id,
            ':payment_status' => $row['payment_status'],
            ':payment_amount' => $row['amount'],
            ':pwd_req'        => $row['pwd'],
            ':pwd_ver'        => $row['pwd'],
            ':date_requested' => $row['date'],
        ]);
        $savedIds[(int)$pdo->lastInsertId()] = $row;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('manual_request_bulk insert failed: ' . $e->getMessage());
    $_SESSION['sms_error'] = 'Batch encoding failed. No requests were saved.'
        . (QUICK_ADD_DEBUG ? ' [' . $e->getMessage() . ']' : ' Please check the data and try again.');
    quick_add_redirect();
}

// ── STEPS 2-4: tracking code, payment record, log (same as single) ──
// Kept outside the insert transaction on purpose: those helpers may manage
// their own transactions, and nesting would throw.
$postFailed = [];
foreach ($savedIds as $request_id => $row) {
    try {
        generate_tracking_code($pdo, $request_id, $barangay);

        if ($row['amount'] > 0) {
            record_payment_transaction($pdo, $request_id, $barangay, $row['amount'], $employee_id, 'walk-in');
        }

        log_action(
            $pdo,
            $employee_id,
            'Manual Request',
            'Requests',
            "Created MANUAL REQUEST (Quick Add) for {$row['full_name']}"
        );
    } catch (Throwable $e) {
        error_log("manual_request_bulk post-step failed for request #$request_id: " . $e->getMessage());
        $postFailed[] = '#' . $request_id;
    }
}

$saved = count($savedIds);
$_SESSION['sms_success'] = $saved . ' manual request(s) successfully encoded.';
if ($postFailed) {
    $_SESSION['sms_error'] = 'Saved, but the tracking code or payment record failed for request '
        . implode(', ', $postFailed) . '. Please review these before releasing documents.';
}

quick_add_redirect();