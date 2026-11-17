<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  SESSION BOOTSTRAP
 *  - Secure cookie flags (httponly, samesite=Lax)
 *  - Optional Redis-backed session storage when configured
 *  - Role-based redirect guard
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/db.php';

$redisHost = $_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST');
$redisPort = $_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6379;
$redisPass = $_ENV['REDIS_PASSWORD'] ?? getenv('REDIS_PASSWORD');
$redisDb   = $_ENV['REDIS_DB'] ?? getenv('REDIS_DB') ?: 0;

if ($redisHost && extension_loaded('redis')) {
    $savePath = sprintf('tcp://%s:%s?database=%s', $redisHost, $redisPort, $redisDb);
    if (!empty($redisPass)) {
        $savePath .= '&auth=' . rawurlencode($redisPass);
    }
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', $savePath);
}

if (session_status() === PHP_SESSION_NONE) {
    $sessionTimeoutSeconds = (int)($_ENV['SESSION_TIMEOUT_SECONDS'] ?? getenv('SESSION_TIMEOUT_SECONDS') ?: 1800);
    if ($sessionTimeoutSeconds > 0) {
        ini_set('session.gc_maxlifetime', (string)$sessionTimeoutSeconds);
    }

    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $cookieParams['domain'],
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Update last_active for active session (throttled to once every 5 minutes per user)
if (isset($_SESSION['user_id'])) {
    $now = time();
    $last_update = $_SESSION['_last_active_update'] ?? 0;
    if (($now - $last_update) > 60) {
        try {
            $stmt_active = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE user_id = ?");
            $stmt_active->execute([$_SESSION['user_id']]);
            $_SESSION['_last_active_update'] = $now;
        } catch (PDOException $e) {
            // ignore update errors
        }
    }
}

if (!isset($_SESSION['_last_regen'])) {
    $_SESSION['_last_regen'] = time();
}

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../core/chatbot.php';
    $chatbot_settings = get_chatbot_settings($pdo);
    $maintenance_mode = ($chatbot_settings['maintenance_mode'] ?? '0') === '1';

    if ($maintenance_mode) {
        $stmt_user = $pdo->prepare('SELECT user_type, session_id FROM users WHERE user_id = ? LIMIT 1');
        $stmt_user->execute([$_SESSION['user_id']]);
        $current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($current_user && $current_user['user_type'] === 'resident') {
            try {
            $stmt_logout = $pdo->prepare('UPDATE users SET session_id = NULL WHERE user_id = ? AND session_id = ?');
            $stmt_logout->execute([$_SESSION['user_id'], session_id()]);
            } catch (PDOException $e) {
                // ignore logout failure
            }

            session_unset();
            session_destroy();
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?? '/', 
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => $params['secure'] ?? true,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => 'Lax'
                ]);
            }

            header('Location: /cityofsantarosa/auth/login?maintenance=1');
            exit();
        }
    }
}

function check_login_redirect($allowed_roles = []) {
    global $pdo;

    if (!isset($_SESSION['user_id'])) {
        header('Location: /cityofsantarosa/erequest-mo');
        exit();
    }

    $stmt = $pdo->prepare('SELECT user_type, session_id FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$user ||
        $user['session_id'] === 'DEACTIVATED' ||
        $user['session_id'] === NULL ||
        $user['session_id'] !== session_id()
    ) {
        session_unset();
        session_destroy();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'] ?? '/', 
                'domain'   => $params['domain'] ?? '',
                'secure'   => $params['secure'] ?? true,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => 'Lax'
            ]);
        }
        header('Location: /cityofsantarosa/auth/login?expired=1');
        exit();
    }

    if (!in_array($user['user_type'], $allowed_roles)) {
        if ($user['user_type'] === 'employee') {
            header('Location: /cityofsantarosa/dashboards/employee');
        } elseif ($user['user_type'] === 'resident') {
            header('Location: /cityofsantarosa/dashboards/resident');
        } elseif ($user['user_type'] === 'city_administrator') {
            header('Location: /cityofsantarosa/dashboards/city_administrator');
        } else {
            header('Location: /cityofsantarosa/auth/login');
        }
        exit();
    }
}
