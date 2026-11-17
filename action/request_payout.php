<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/payments.php';

check_login_redirect(['employee']);

ensure_payment_schema($pdo);

$barangay = $_SESSION['barangay'] ?? '';
$monthYear = $_POST['month_year'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
    $_SESSION['sms_error'] = 'Invalid payout month.';
    header('Location: ../dashboards/employee?panel=payments');
    exit;
}

$summary = get_barangay_monthly_payment_summary_by_method($pdo, $barangay, $monthYear, 'online');

if ($summary['gross_amount'] <= 0) {
    $_SESSION['sms_error'] = 'No online transactions recorded for the selected month.';
    header('Location: ../dashboards/employee?panel=payments&pay_month=' . urlencode($monthYear));
    exit;
}

$stmt = $pdo->prepare("
    SELECT status
    FROM payout_requests
    WHERE barangay = ?
      AND month_year = ?
      AND status IN ('Pending', 'Approved', 'Released')
    LIMIT 1
");
$stmt->execute([$barangay, $monthYear]);
$existingStatus = $stmt->fetchColumn();

if ($existingStatus) {
    $statusMessages = [
        'Pending'  => 'A payout request is already pending for this month.',
        'Approved' => 'The payout for this month has already been approved. Please wait for release.',
        'Released' => 'The payout for this month has already been released.',
    ];
    $_SESSION['sms_error'] = $statusMessages[$existingStatus] ?? 'A payout request already exists for this month.';
    header('Location: ../dashboards/employee?panel=payments&pay_month=' . urlencode($monthYear));
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO payout_requests
        (barangay, requested_by, month_year, gross_amount, city_fee_amount, net_amount)
    VALUES (?, ?, ?, ?, 0, ?)
");
$stmt->execute([
    $barangay,
    $_SESSION['user_id'],
    $monthYear,
    $summary['gross_amount'],
    $summary['gross_amount'],  // net = gross (no fee deducted)
]);

log_action(
    $pdo,
    $_SESSION['user_id'],
    'Payout Requested',
    $barangay . ' ' . $monthYear,
    'Requested payout of ' . money_format_php($summary['gross_amount']) . ' gross'
);

// Invalidate affected caches
clear_cached_data('employee_payouts:' . $barangay);
clear_cached_data('city_admin_payout_requests');

$_SESSION['sms_success'] = 'Payout request sent to City Administrator.';
header('Location: ../dashboards/employee?panel=payments&pay_month=' . urlencode($monthYear));
exit;
