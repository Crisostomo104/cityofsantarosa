<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

/**
 * Send an HTML email via the Brevo (formerly Sendinblue) HTTP API.
 *
 * Switched from Gmail SMTP because InfinityFree's shared hosting could not
 * reliably resolve/connect to smtp.gmail.com (DNS + port restrictions).
 * Brevo uses a plain HTTPS POST request instead, which is not blocked.
 *
 * Requires these keys in .env:
 *   BREVO_API_KEY=your-brevo-api-key
 *   MAIL_USERNAME=barangay.ibaba.inquiry@gmail.com   (used as the "From" address)
 *   MAIL_FROM_NAME=eRequest Mo
 *
 * @return bool true on success, false on failure (check logs/mail_debug.log for details)
 */
function send_app_email(string $toEmail, string $subject, string $htmlBody): bool
{
    $apiKey   = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY') ?? $_SERVER['BREVO_API_KEY'] ?? null;
    $fromMail = $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?? $_SERVER['MAIL_USERNAME'] ?? null;
    $fromName = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?? $_SERVER['MAIL_FROM_NAME'] ?? 'eRequest Mo';

    if (!$apiKey || !$fromMail) {
        error_log('send_app_email failed: BREVO_API_KEY / MAIL_USERNAME not set in .env');
        return false;
    }

    $payload = json_encode([
        'sender'      => ['name' => $fromName, 'email' => $fromMail],
        'to'          => [['email' => $toEmail]],
        'subject'     => $subject,
        'htmlContent' => $htmlBody,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ],
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Brevo returns 201 Created on success
    if ($httpCode === 201) {
        return true;
    }

    $debugMsg = date('Y-m-d H:i:s') . ' - send_app_email (Brevo) failed: '
        . 'HTTP ' . $httpCode . ' - ' . $response
        . ($curlError ? ' - cURL error: ' . $curlError : '')
        . PHP_EOL;
    error_log($debugMsg);
    @file_put_contents(__DIR__ . '/../logs/mail_debug.log', $debugMsg, FILE_APPEND);

    return false;
}