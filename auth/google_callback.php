<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';  // sets SameSite=Lax + starts session
require_once __DIR__ . '/../core/google_drive.php';

// ── User must be logged in (session survives because SameSite=Lax) ──
if (!isset($_SESSION['user_id'])) {
    // Session lost — send back to login with a helpful message
    header('Location: /cityofsantarosa/auth/login?error=google_session_lost');
    exit;
}

// Validate the OAuth state before accepting Google's authorization code.
$expectedState = $_SESSION['google_oauth_state'] ?? '';
$receivedState = $_GET['state'] ?? '';
unset($_SESSION['google_oauth_state']);

if (!is_string($expectedState) || $expectedState === '' || !is_string($receivedState) || !hash_equals($expectedState, $receivedState)) {
    header('Location: /cityofsantarosa/dashboards/city_administrator?panel=data-management&google=failed&msg=Invalid+OAuth+state');
    exit;
}

if (isset($_GET['error']) || !isset($_GET['code']) || !is_string($_GET['code'])) {
    header('Location: /cityofsantarosa/dashboards/city_administrator?panel=data-management&google=failed&msg=Google+authorization+cancelled');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// ── Exchange auth code for access token ──────────────────────
try {
    $token = google_exchange_auth_code($_GET['code']);
} catch (RuntimeException $e) {
    error_log('[Google OAuth] Authorization code exchange failed: ' . $e->getMessage());
    $msg = urlencode('Google authorization failed. Please try again.');
    header("Location: /cityofsantarosa/dashboards/city_administrator?panel=data-management&google=failed&msg=$msg");
    exit;
}

// ── Preserve existing refresh_token if Google didn't send a new one ──
$stmt = $pdo->prepare("SELECT google_token FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$existingToken = json_decode($stmt->fetchColumn() ?: 'null', true);

if (!isset($token['refresh_token']) && !empty($existingToken['refresh_token'])) {
    $token['refresh_token'] = $existingToken['refresh_token'];
}

// ── Save token to DB ──────────────────────────────────────────
$pdo->prepare("UPDATE users SET google_token = ?, google_connected = 1 WHERE user_id = ?")
    ->execute([json_encode($token), $user_id]);

$_SESSION['google_connected'] = true;

// ── Redirect back to dashboard ────────────────────────────────
$userType = $_SESSION['user_type'] ?? '';
if ($userType === 'city_administrator') {
    header("Location: /cityofsantarosa/dashboards/city_administrator?panel=data-management&google=connected");
} else {
    header("Location: /cityofsantarosa/dashboards/employee?google=connected");
}
exit;
