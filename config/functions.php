<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  SHARED UTILITY FUNCTIONS
 *  - log_action()  : audit trail writer
 *  - csrf_token()  : generate & store CSRF token
 *  - csrf_verify() : validate submitted CSRF token (timing-safe)
 *  - rate_limit()  : session-based rate limiter
 *  - e()           : safe output escaping shorthand
 * ─────────────────────────────────────────────────────────────
 */

// ── Audit Logger ──────────────────────────────────────────────
function log_action(PDO $pdo, $user_id, string $action_type, ?string $target = null, ?string $details = null): void
{
    $ip = 'unknown';
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $candidate = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
                break;
            }
        }
    }

    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);

    $stmt = $pdo->prepare("
        INSERT INTO system_logs (user_id, action_type, target, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $action_type, $target, $details, $ip, $user_agent]);
}

// ── CSRF Token Generation ─────────────────────────────────────
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

// ── CSRF Verification (timing-safe) ──────────────────────────
function csrf_verify(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $stored    = $_SESSION['_csrf_token'] ?? '';

    if (!$stored || !hash_equals($stored, $submitted)) {
        unset($_SESSION['_csrf_token']);
        error_log('[CSRF] Invalid token from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            http_response_code(403);
            exit(json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']));
        }

        $_SESSION['admin_error'] = 'Security token expired or invalid. Please refresh the page and try again.';
        $referer = $_SERVER['HTTP_REFERER'] ?? '../dashboards/city_administrator?panel=chatbot';
        header('Location: ' . $referer);
        exit;
    }
}

// ── Session-based Rate Limiter ────────────────────────────────
function rate_limit(string $action, int $max = 5, int $window_seconds = 60): bool
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $key = '_rl_' . $action;
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'window_start' => $now];
    }

    $data = &$_SESSION[$key];
    if ($now - $data['window_start'] > $window_seconds) {
        $data = ['count' => 0, 'window_start' => $now];
    }

    $data['count']++;

    if ($data['count'] > $max) {
        http_response_code(429);
        return false;
    }

    return true;
}

// ── Safe Output Escaping ──────────────────────────────────────
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Verify a Google reCAPTCHA v2 token against Google's siteverify endpoint.
 * Returns true if the token is valid, false otherwise (including missing
 * token, missing secret key, or any network/API failure — fail closed so
 * bots can't slip through if reCAPTCHA is misconfigured).
 */
function verify_recaptcha(string $token, ?string $remoteIp = null): bool {
    $secret = $_ENV['RECAPTCHA_SECRET_KEY'] ?? getenv('RECAPTCHA_SECRET_KEY');

    if (!$secret) {
        error_log('reCAPTCHA verification skipped: missing RECAPTCHA_SECRET_KEY');
        return false;
    }

    if (!$token) {
        return false;
    }

    $postFields = [
        'secret' => $secret,
        'response' => $token,
    ];
    if ($remoteIp) {
        $postFields['remoteip'] = $remoteIp;
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postFields),
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $response === false) {
        error_log('reCAPTCHA verification cURL error: ' . $curlError);
        return false;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        error_log('reCAPTCHA verification: invalid JSON response: ' . $response);
        return false;
    }

    if (empty($data['success'])) {
        error_log('reCAPTCHA verification failed: ' . json_encode($data['error-codes'] ?? []));
        return false;
    }

    return true;
}

/**
 * Format a stored Philippine phone number (63XXXXXXXXX or +63XXXXXXXXX)
 * back into local display format 09XXXXXXXXX. Returns the original
 * value unchanged if it doesn't match the expected pattern.
 */
function format_phone_display(?string $phone): string {
    $phone = trim((string) $phone);
    if ($phone === '') {
        return '—';
    }

    $digits = preg_replace('/[^0-9]/', '', $phone);

    if (preg_match('/^63(\d{10})$/', $digits, $m)) {
        return '0' . $m[1];
    }

    if (preg_match('/^09\d{9}$/', $digits)) {
        return $digits;
    }

    return $phone;
}

function payment_method_label(?string $method, ?string $status = null): string
{
    $method = strtolower(trim((string) $method));
    if (strtolower(trim((string) $status)) === 'free' || $method === 'free') {
        return 'Free';
    }
    if ($method === '') {
        return '';
    }
    if ($method === 'walk-in') {
        return 'Cash';
    }
    return ucfirst($method);
}

// ── Lightweight File Cache ────────────────────────────────────
// Cache helpers moved to config/cache.php
require_once __DIR__ . '/cache.php';