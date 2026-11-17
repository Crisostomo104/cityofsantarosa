<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  SMS HELPERS — Centralized Semaphore SMS functions
 *  Eliminates duplication across reject_request.php, process_request.php, etc.
 *
 *  Usage:  require_once __DIR__ . '/../core/sms_helpers.php';
 *          $result = sms_send($phone, $message);  // returns ['success' => bool, 'error' => string]
 * ─────────────────────────────────────────────────────────────
 */

/**
 * Normalize PH phone numbers to international format.
 * Converts:
 *  - 09XXXXXXXXX   → 639XXXXXXXXX
 *  - +639XXXXXXXXX → 639XXXXXXXXX
 *  - 639XXXXXXXXX  → 639XXXXXXXXX (already correct)
 *
 * @return string|false  The normalized number or false if invalid.
 */
function sms_normalize_phone(string $phone)
{
    $phone = preg_replace('/\D/', '', $phone);

    if (preg_match('/^09\d{9}$/', $phone)) {
        return '63' . substr($phone, 1);
    }

    if (preg_match('/^639\d{9}$/', $phone)) {
        return $phone;
    }

    return false;
}

/**
 * Send an SMS via Semaphore API.
 *
 * @param string $phone   Raw phone number (will be normalized).
 * @param string $message The message body.
 * @return array          ['success' => bool, 'error' => string|null]
 */
function sms_send(string $phone, string $message): array
{
    // Normalize
    $phone = sms_normalize_phone($phone);
    if (!$phone) {
        return ['success' => false, 'error' => 'Invalid phone number'];
    }

    // Load API key from environment
    $apikey = $_ENV['SEMAPHORE_API_KEY'] ?? getenv('SEMAPHORE_API_KEY') ?: '';
    if (empty($apikey)) {
        return ['success' => false, 'error' => 'Missing SEMAPHORE_API_KEY'];
    }

    $data = [
        'apikey'     => $apikey,
        'number'     => $phone,
        'message'    => $message,
        'sendername' => 'SREREQ',
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.semaphore.co/api/v4/messages',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => $error];
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Debug log
    $logDir = __DIR__ . '/../logs';
    if (is_dir($logDir)) {
        file_put_contents($logDir . '/sms_debug.log', date('[Y-m-d H:i:s] ') . $response . PHP_EOL, FILE_APPEND);
    }

    $decoded = json_decode($response, true);
    $status  = $decoded[0]['status'] ?? '';

    if ($http_code === 200 && in_array($status, ['Queued', 'Pending'], true)) {
        return ['success' => true, 'error' => null];
    }

    return ['success' => false, 'error' => $status ?: 'HTTP Error ' . $http_code];
}
