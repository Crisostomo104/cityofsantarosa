<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

/**
 * Send an HTML email through Brevo's HTTPS API, with an optional PDF attachment.
 *
 * Requires BREVO_API_KEY, MAIL_USERNAME (a verified Brevo sender), and
 * optionally MAIL_FROM_NAME in .env.
 */
function send_app_email(
    string $toEmail,
    string $subject,
    string $htmlBody,
    ?string $attachmentContent = null,
    ?string $attachmentName = null
): bool {
    $logFailure = static function (string $message): void {
        $logEntry = date('Y-m-d H:i:s') . ' - send_app_email failed: ' . $message . PHP_EOL;
        error_log($logEntry);
        if (file_put_contents(__DIR__ . '/../logs/mail_debug.log', $logEntry, FILE_APPEND | LOCK_EX) === false) {
            error_log('send_app_email failed: could not write logs/mail_debug.log.');
        }
    };

    if (($attachmentContent === null) !== ($attachmentName === null)) {
        throw new InvalidArgumentException('Attachment content and filename must be provided together.');
    }
    if ($attachmentContent !== null && ($attachmentContent === '' || $attachmentName === '')) {
        throw new InvalidArgumentException('Attachment content and filename must not be empty.');
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $logFailure('recipient email address is invalid.');
        return false;
    }

    $apiKey = $_ENV['BREVO_API_KEY'] ?? (getenv('BREVO_API_KEY') ?: ($_SERVER['BREVO_API_KEY'] ?? null));
    $fromEmail = $_ENV['MAIL_USERNAME'] ?? (getenv('MAIL_USERNAME') ?: ($_SERVER['MAIL_USERNAME'] ?? null));
    $fromName = $_ENV['MAIL_FROM_NAME'] ?? (getenv('MAIL_FROM_NAME') ?: ($_SERVER['MAIL_FROM_NAME'] ?? 'eRequest Mo'));

    if (!$apiKey || !$fromEmail) {
        $logFailure('BREVO_API_KEY / MAIL_USERNAME not set.');
        return false;
    }
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $logFailure('sender email address is invalid.');
        return false;
    }
    if (!extension_loaded('curl')) {
        $logFailure('PHP cURL extension is not enabled.');
        return false;
    }

    $payload = [
        'sender' => ['name' => $fromName, 'email' => $fromEmail],
        'to' => [['email' => $toEmail]],
        'subject' => $subject,
        'htmlContent' => $htmlBody,
    ];
    if ($attachmentContent !== null && $attachmentName !== null) {
        $payload['attachment'] = [[
            'name' => $attachmentName,
            'content' => base64_encode($attachmentContent),
        ]];
    }

    try {
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        $logFailure('email payload could not be encoded.');
        return false;
    }

    $curl = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($curl === false) {
        $logFailure('could not initialize cURL.');
        return false;
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response !== false && $httpCode === 201) {
        return true;
    }

    $responseData = is_string($response) ? json_decode($response, true) : null;
    $errorMessage = is_array($responseData) && isset($responseData['message'])
        ? preg_replace('/[\r\n]+/', ' ', (string) $responseData['message'])
        : 'No error details returned.';
    $errorMessage = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $errorMessage);
    $logFailure('Brevo HTTP ' . $httpCode . ' - ' . substr($errorMessage, 0, 300)
        . ($curlError !== '' ? ' - cURL error: ' . $curlError : ''));

    return false;
}
