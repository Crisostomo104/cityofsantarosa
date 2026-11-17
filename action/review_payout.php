<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/payments.php';

check_login_redirect(['city_administrator']);

ensure_payment_schema($pdo);

$payoutId = (int)($_POST['payout_id'] ?? 0);
$status = $_POST['status'] ?? '';
$note = trim($_POST['admin_note'] ?? '');

if ($payoutId <= 0 || !in_array($status, ['Approved', 'Rejected', 'Released'], true)) {
    $_SESSION['admin_error'] = 'Invalid payout review request.';
    header('Location: ../dashboards/city_administrator?panel=payments');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM payout_requests WHERE payout_id = ?");
$stmt->execute([$payoutId]);
$payout = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payout) {
    $_SESSION['admin_error'] = 'Payout request not found.';
    header('Location: ../dashboards/city_administrator?panel=payments');
    exit;
}

if ($status === 'Released' && $payout['status'] !== 'Approved') {
    $_SESSION['admin_error'] = 'Only approved payout requests can be marked as released.';
    header('Location: ../dashboards/city_administrator?panel=payments');
    exit;
}

if (in_array($status, ['Approved', 'Rejected'], true) && $payout['status'] !== 'Pending') {
    $_SESSION['admin_error'] = 'Only pending payout requests can be approved or rejected.';
    header('Location: ../dashboards/city_administrator?panel=payments');
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE payout_requests
        SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE payout_id = ?
    ");
    $stmt->execute([$status, $note, $_SESSION['user_id'], $payoutId]);

    // Invalidate affected caches
	clear_cached_data('employee_payouts:' . $payout['barangay']);
	clear_cached_data('city_admin_payout_requests');
    
    log_action(
        $pdo,
        $_SESSION['user_id'],
        'Payout ' . $status,
        $payout['barangay'] . ' ' . $payout['month_year'],
        $status . ' payout request worth ' . money_format_php((float)$payout['net_amount'])
    );

    $_SESSION['admin_success'] = 'Payout request ' . strtolower($status) . ' successfully.';
} catch (\Throwable $e) {
    error_log('[review_payout] Error: ' . $e->getMessage());
    $_SESSION['admin_error'] = 'Failed to update payout request. Please try again.';
}

header('Location: ../dashboards/city_administrator?panel=payments');
exit;
