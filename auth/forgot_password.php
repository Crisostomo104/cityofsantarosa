<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php'; // your DB connection
require_once __DIR__ . '/../config/functions.php'; // add system logging
require_once __DIR__ . '/../config/mailer.php'; // shared send_app_email() using .env credentials
require_once __DIR__ . '/../core/chatbot.php';

header('Content-Type: application/json');

$chatbot_settings = get_chatbot_settings($pdo);
$maintenance_mode = ($chatbot_settings['maintenance_mode'] ?? '0') === '1';
$maintenance_reply = $chatbot_settings['maintenance_message'] ?? 'The system is currently under maintenance. Please try again later.';

if ($maintenance_mode) {
    echo json_encode(['success' => false, 'message' => $maintenance_reply]);
    exit;
}

// Get JSON data from modal
$data = json_decode(file_get_contents('php://input'), true);
$email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Rate limit: max 3 password reset requests per 10 minutes
if (!rate_limit('forgot_password', 3, 600)) {
    echo json_encode(['success' => false, 'message' => 'Too many reset attempts. Please try again later.']);
    exit;
}

// Check if email exists in DB
$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Always return success to avoid email enumeration
$responseMessage = 'If an account exists for this email, you will receive reset instructions.';

if (!$user) {
    // Log failed password reset attempt (non-existent email)
    log_action($pdo, null, 'Password Reset Request Failed', $email, 'Password reset requested for non-existent email');

    echo json_encode([
        'success' => true,
        'message' => $responseMessage
    ]);
    exit;
}

// Generate token and expiry (1 hour)
$token = bin2hex(random_bytes(16));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Save token and expiry in DB
try {
    $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
    $update->execute([$token, $expires, $user['user_id']]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Prepare reset link (change domain to your localhost or real domain)
$resetLink = "https://bis.xo.je/cityofsantarosa/auth/reset_password.php?token=" . rawurlencode($token);

// Log the successful password reset request
log_action($pdo, $user['user_id'], 'Password Reset Request', $email, 'User requested password reset');

$sent = send_app_email(
    $email,
    'Password Reset Request',
    "
        <p>Hi,</p>
        <p>You requested a password reset. Click the link below to reset it:</p>
        <p><a href='" . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . "'>Reset your password</a></p>
        <p>If the button does not work, copy and paste this link into your browser:</p>
        <p>" . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . "</p>
        <p>This link will expire in 1 hour.</p>
        <p>If you did not request this, ignore this email.</p>
        <p>Thanks,<br>City of Santa Rosa Team</p>
    "
);

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Reset instructions sent. Please check your email.']);
} else {
    log_action($pdo, $user['user_id'], 'Password Reset Email Failed', $email, 'send_app_email() failed — check error_log');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email. Please try again in a moment.'
    ]);
}