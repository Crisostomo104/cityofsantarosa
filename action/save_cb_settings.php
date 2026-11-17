<?php
/**
 * Save chatbot and maintenance settings from the city administrator dashboard.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/chatbot.php';

check_login_redirect(['city_administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboards/city_administrator');
    exit;
}

csrf_verify();

$redirect = trim($_POST['redirect_panel'] ?? 'chatbot');
$redirect = preg_match('/^[a-z0-9\-]+$/i', $redirect) ? $redirect : 'chatbot';

$chatbot_enabled = isset($_POST['chatbot_enabled']) ? true : false;
$chatbot_disabled_message = trim($_POST['chatbot_disabled_message'] ?? '');
$maintenance_mode = isset($_POST['maintenance_mode']) ? true : false;
$maintenance_message = trim($_POST['maintenance_message'] ?? '');

$saveSuccess = false;
$saveNotes = [];

if (isset($_POST['chatbot_enabled']) || array_key_exists('chatbot_disabled_message', $_POST)) {
    if ($maintenance_mode) {
        save_chatbot_settings($pdo, false, $chatbot_disabled_message);
        $saveNotes[] = 'Chatbot remains disabled while maintenance mode is active.';
    } else {
        save_chatbot_settings($pdo, $chatbot_enabled, $chatbot_disabled_message);
    }
    $saveSuccess = true;
}

if (isset($_POST['maintenance_mode']) || array_key_exists('maintenance_message', $_POST)) {
    save_maintenance_settings($pdo, $maintenance_mode, $maintenance_message);
    if ($maintenance_mode) {
        save_chatbot_settings($pdo, false, $chatbot_disabled_message);
        $saveNotes[] = 'Chatbot was automatically disabled because maintenance mode is active.';
    }
    $saveSuccess = true;
}

if ($saveSuccess) {
    $message = 'Settings saved successfully.';
    if (!empty($saveNotes)) {
        $message .= ' ' . implode(' ', $saveNotes);
    }
    $_SESSION['admin_success'] = $message;
} else {
    $_SESSION['admin_error'] = 'No settings were updated.';
}

header('Location: ../dashboards/city_administrator?panel=' . rawurlencode($redirect));
exit;
