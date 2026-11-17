<?php

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

function google_oauth_config(): array
{
    $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID');
    $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET');
    $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost/cityofsantarosa';

    if (!$clientId || !$clientSecret) {
        throw new RuntimeException('Google OAuth client credentials are not configured.');
    }

    return [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => rtrim($appUrl, '/') . '/auth/google_callback.php',
    ];
}

function google_create_authorization_url(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $config = google_oauth_config();
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $state;

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/drive.file',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $state,
    ]);
}

function google_request(string $url, array $headers, ?string $body = null): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false || $curlError !== '') {
        throw new RuntimeException('Google API connection failed: ' . $curlError);
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Google API returned an invalid response.');
    }

    if ($status < 200 || $status >= 300) {
        $message = $data['error_description'] ?? $data['error']['message'] ?? $data['error'] ?? 'Unknown Google API error';
        throw new RuntimeException('Google API request failed: ' . (is_string($message) ? $message : 'Unknown error'));
    }

    return $data;
}

function google_exchange_auth_code(string $code): array
{
    $config = google_oauth_config();
    $token = google_request('https://oauth2.googleapis.com/token', [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ], http_build_query([
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]));

    $token['created'] = time();
    return $token;
}

function get_google_drive_service(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT google_token FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $rawToken = $stmt->fetchColumn();

    if (!$rawToken) {
        return [
            'success' => false,
            'message' => 'Google Drive not connected. Please connect your Google account first.',
            'needs_connect' => true,
        ];
    }

    $token = json_decode($rawToken, true);

    if (!$token || empty($token['access_token'])) {
        return [
            'success' => false,
            'message' => 'Invalid Google token. Please reconnect your Google account.',
            'needs_connect' => true,
        ];
    }

    $expiresAt = (int)($token['created'] ?? 0) + (int)($token['expires_in'] ?? 0);
    if ($expiresAt <= time() + 60) {
        $refreshToken = $token['refresh_token'] ?? null;

        if (!$refreshToken) {
            clear_google_connection($pdo, $userId);

            return [
                'success' => false,
                'message' => 'Google session expired. Please reconnect your Google account.',
                'needs_connect' => true,
            ];
        }

        try {
            $config = google_oauth_config();
            $newToken = google_request('https://oauth2.googleapis.com/token', [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ], http_build_query([
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]));
        } catch (RuntimeException $e) {
            clear_google_connection($pdo, $userId);

            return [
                'success' => false,
                'message' => 'Failed to refresh Google session. Please reconnect your Google account.',
                'needs_connect' => true,
            ];
        }

        $newToken['created'] = time();
        if (!isset($newToken['refresh_token'])) {
            $newToken['refresh_token'] = $refreshToken;
        }

        $pdo->prepare("UPDATE users SET google_token = ?, google_connected = 1 WHERE user_id = ?")
            ->execute([json_encode($newToken), $userId]);

        $token = $newToken;
    }

    return [
        'success' => true,
        'drive' => $token['access_token'],
    ];
}

function clear_google_connection(PDO $pdo, int $userId): void
{
    $pdo->prepare("UPDATE users SET google_token = NULL, google_connected = 0 WHERE user_id = ?")
        ->execute([$userId]);
}

/**
 * Generic file uploader — accepts raw binary content and MIME type.
 */
function upload_file_to_drive(string $accessToken, string $fileName, string $content, string $mimeType): array
{
    $boundary = 'einquire_' . bin2hex(random_bytes(16));
    $metadata = json_encode(['name' => $fileName], JSON_THROW_ON_ERROR);
    $body = "--$boundary\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . $metadata . "\r\n"
        . "--$boundary\r\n"
        . "Content-Type: $mimeType\r\n\r\n"
        . $content . "\r\n"
        . "--$boundary--\r\n";

    $query = http_build_query(['uploadType' => 'multipart', 'fields' => 'id,name,webViewLink']);
    $file = google_request(
        'https://www.googleapis.com/upload/drive/v3/files?' . $query,
        [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Accept: application/json',
        ],
        $body
    );

    if (empty($file['id'])) {
        throw new RuntimeException('Google Drive did not return an uploaded file ID.');
    }

    return [
        'success' => true,
        'file_id' => $file['id'],
        'link'    => $file['webViewLink'] ?? '',
    ];
}

/**
 * Build a CSV string from an array of rows.
 */
function build_csv(array $rows): string
{
    if (!$rows) return '';

    $handle = fopen('php://temp', 'w+');
    fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM — Excel compatibility
    fputcsv($handle, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    return $content;
}

/**
 * Upload rows as a ZIP-compressed CSV to Google Drive.
 * ZIP gives 70–90% size reduction over raw CSV.
 *
 * @param string $baseName  e.g. 'einquire_requests_2024-06-06'
 *                          (.csv and .zip extensions added automatically)
 */
function upload_zip_csv_to_drive(string $accessToken, string $baseName, array $rows): array
{
    if (!$rows) {
        return ['success' => false, 'message' => 'No data to back up.'];
    }

    if (!class_exists('ZipArchive')) {
        // Fallback: upload plain CSV if ZipArchive not available
        $csv     = build_csv($rows);
        $csvName = $baseName . '.csv';
        return upload_file_to_drive($accessToken, $csvName, $csv, 'text/csv');
    }

    $csvContent = build_csv($rows);
    $csvName    = $baseName . '.csv';
    $zipName    = $baseName . '.zip';

    // Write ZIP to a temp file (ZipArchive needs a real path)
    $tmpZip = tempnam(sys_get_temp_dir(), 'einquire_') . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        // Fallback to plain CSV
        return upload_file_to_drive($accessToken, $csvName, $csvContent, 'text/csv');
    }

    $zip->addFromString($csvName, $csvContent);
    $zip->close();

    $zipContent = file_get_contents($tmpZip);
    @unlink($tmpZip);

    // Log size info for debugging
    $csvKB = round(strlen($csvContent) / 1024, 1);
    $zipKB = round(strlen($zipContent) / 1024, 1);
    error_log("[E-Inquire] Cloud backup: CSV={$csvKB}KB → ZIP={$zipKB}KB");

    return upload_file_to_drive($accessToken, $zipName, $zipContent, 'application/zip');
}

/**
 * Legacy helper — kept for backward compatibility.
 * @deprecated Use upload_zip_csv_to_drive() instead.
 */
function upload_csv_to_drive(string $accessToken, string $fileName, array $rows): array
{
    $csv = build_csv($rows);
    if (!$csv) {
        return ['success' => false, 'message' => 'No data to back up.'];
    }
    return upload_file_to_drive($accessToken, $fileName, $csv, 'text/csv');
}
