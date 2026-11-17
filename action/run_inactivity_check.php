<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/mailer.php';

// ── Protect this endpoint with a secret key ──
// Set this same value sa cron-job.org URL mo, e.g. ?key=abc123xyz
// Palitan mo ito ng sarili mong random long string, at wag i-commit sa public repo.
define('INACTIVITY_CHECK_SECRET', 'PALITAN_MO_ITO_NG_RANDOM_STRING');

$provided_key = $_GET['key'] ?? $_POST['key'] ?? '';

if (!hash_equals(INACTIVITY_CHECK_SECRET, $provided_key)) {
    http_response_code(403);
    die('Forbidden');
}

$now = new DateTime();

// ── STEP 1: Auto-deactivate users inactive for 30+ days ──
$stmt = $pdo->prepare("
    SELECT user_id, email, full_name, user_type
    FROM users
    WHERE user_type IN ('resident', 'employee')
      AND (session_id IS NULL OR session_id != 'DEACTIVATED')
      AND last_active IS NOT NULL
      AND last_active <= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$to_deactivate = $stmt->fetchAll(PDO::FETCH_ASSOC);

$deactivated_count = 0;

foreach ($to_deactivate as $user) {
    $pdo->prepare("
        UPDATE users
        SET session_id = 'DEACTIVATED'
        WHERE user_id = ?
    ")->execute([$user['user_id']]);

    log_action(
        $pdo,
        null,
        'Auto-Deactivate (Inactivity)',
        $user['email'],
        'Account auto-deactivated after 30 days of inactivity'
    );

    send_app_email(
        $user['email'],
        'Your Account Has Been Deactivated',
        "
            <p>Hi " . htmlspecialchars($user['full_name']) . ",</p>
            <p>Your eRequest Mo account has been deactivated because it was inactive for 30 days.</p>
            <p>If you'd like to reactivate your account, please contact the City Administrator support for assistance.</p>
            <p>Thanks,<br>City of Santa Rosa Team</p>
        "
    );

    $deactivated_count++;
}

// ── STEP 2: Send warning to users inactive for 28-29 days (not yet warned) ──
$stmt = $pdo->prepare("
    SELECT user_id, email, full_name, last_active
    FROM users
    WHERE user_type IN ('resident', 'employee')
      AND (session_id IS NULL OR session_id != 'DEACTIVATED')
      AND last_active IS NOT NULL
      AND last_active <= DATE_SUB(NOW(), INTERVAL 28 DAY)
      AND last_active > DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND inactivity_warning_sent_at IS NULL
");
$stmt->execute();
$to_warn = $stmt->fetchAll(PDO::FETCH_ASSOC);

$warned_count = 0;

foreach ($to_warn as $user) {
    $days_inactive = (int) floor(
        (time() - strtotime($user['last_active'])) / 86400
    );

    $sent = send_app_email(
        $user['email'],
        'Your Account Will Be Deactivated Soon',
        "
            <p>Hi " . htmlspecialchars($user['full_name']) . ",</p>
            <p>You have been inactive for almost {$days_inactive} days. Please log in immediately to prevent the deactivation of your account.</p>
            <p>Accounts inactive for 30 days are automatically deactivated for security purposes.</p>
            <p>Thanks,<br>City of Santa Rosa Team</p>
        "
    );

    if ($sent) {
        $pdo->prepare("
            UPDATE users
            SET inactivity_warning_sent_at = NOW()
            WHERE user_id = ?
        ")->execute([$user['user_id']]);

        log_action(
            $pdo,
            null,
            'Inactivity Warning Sent',
            $user['email'],
            "Warning email sent after {$days_inactive} days of inactivity"
        );

        $warned_count++;
    }
}

echo json_encode([
    'success'     => true,
    'deactivated' => $deactivated_count,
    'warned'      => $warned_count,
    'ran_at'      => $now->format('Y-m-d H:i:s'),
]);