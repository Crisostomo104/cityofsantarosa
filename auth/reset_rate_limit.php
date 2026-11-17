<?php
// One-time utility to clear the session-based registration rate limiter for testing.
// Remove this file after use in production.
require_once __DIR__ . '/../config/bootstrap.php';
session_start();

$keys = ['_rl_registration', 'otp_code', 'otp_expire', 'pending_user'];
foreach ($keys as $k) {
    if (isset($_SESSION[$k])) unset($_SESSION[$k]);
}

// Redirect back to registration form
header('Location: registration');
exit;
