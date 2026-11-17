<?php

// ─────────────────────────────────────────────
//  PayMongo Configuration
//  NEVER commit this file to public repositories
// ─────────────────────────────────────────────

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

$paymongoSecretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
$paymongoPublicKey = $_ENV['PAYMONGO_PUBLIC_KEY'] ?? getenv('PAYMONGO_PUBLIC_KEY');
$siteBaseUrl = $_ENV['SITE_BASE_URL'] ?? getenv('SITE_BASE_URL');

// Paste your keys from: dashboard.paymongo.com/developers
define('PAYMONGO_SECRET_KEY', $paymongoSecretKey ?: '');
define('PAYMONGO_PUBLIC_KEY', $paymongoPublicKey ?: '');

// Your site's public base URL (no trailing slash)
// For ngrok: 'https://abc123.ngrok-free.app/e-inquire'
// For live:  'https://yourdomain.com/e-inquire'
define('SITE_BASE_URL', $siteBaseUrl ?: 'http://localhost/cityofsantarosa');

// PayPal Configuration
$paypalClientId = $_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID');
$paypalClientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? getenv('PAYPAL_CLIENT_SECRET');

define('PAYPAL_CLIENT_ID', $paypalClientId ?: '');
define('PAYPAL_CLIENT_SECRET', $paypalClientSecret ?: '');

// Determine PayPal API endpoint based on APP_ENV
$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
define('PAYPAL_API_URL', $appEnv === 'local' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com');

// Exchange rate for conversion since PayPal SDK doesn't natively support PHP for all sandbox integrations
define('PHP_USD_EXCHANGE_RATE', 56.0); // 1 USD = 56 PHP

// Document fees in PHP Pesos (minimum 100.00 per PayMongo rules)
define('DOCUMENT_FEES', [
    'Barangay Clearance'     => 100.00,
    'Certificate of Indigency' => 100.00,
    'Business Permit'        => 200.00,
    'Residency Certificate'  => 100.00,
    'Other'                  => 100.00,
]);

// PayMongo API base URL
define('PAYMONGO_API_URL', 'https://api.paymongo.com/v1');

function normalize_payment_provider(?string $provider): string
{
    $provider = strtolower(trim((string)($provider ?? 'gcash')));
    $valid = ['gcash', 'qrph', 'paymaya', 'paypal', 'card'];
    return in_array($provider, $valid, true) ? $provider : 'gcash';
}

function get_payment_provider_label(string $provider): string
{
    $labels = [
        'gcash'   => 'GCash',
        'qrph'    => 'PayMongo QRPh',
        'paymaya' => 'Maya (PayMaya)',
        'paypal'  => 'PayPal',
        'card'    => 'Card',
    ];
    return $labels[strtolower(trim($provider))] ?? ucfirst($provider);
}

function build_simulated_payment_reference(string $provider, int $requestId): string
{
    $providerSlug = strtolower(trim((string)($provider ?: 'paymaya')));
    $providerSlug = preg_replace('/[^a-z0-9]+/', '', $providerSlug) ?: 'payment';
    $seed = $requestId . ':' . $providerSlug . ':' . time();
    return 'sim_' . $providerSlug . '_' . substr(hash('sha256', $seed), 0, 12);
}

function is_simulated_payment_reference(string $reference): bool
{
    return preg_match('/^sim_[a-z0-9]{2,}_[a-z0-9]{6,}$/i', trim($reference)) === 1;
}

function extract_paymongo_qr_details(array $paymongoResponse): array
{
    $intent = $paymongoResponse['data'] ?? [];
    $attributes = $intent['attributes'] ?? [];
    $nextAction = $attributes['next_action'] ?? [];

    $displayQr = $nextAction['display_qr_code'] ?? [];
    $legacyQr = $nextAction['code'] ?? [];

    return [
        'status' => $attributes['status'] ?? 'unknown',
        'image_url' => $displayQr['image_url'] ?? $legacyQr['image_url'] ?? '',
        'code_data' => $displayQr['data'] ?? $legacyQr['data'] ?? '',
        'expires_at' => $displayQr['expires_at'] ?? $nextAction['expires_at'] ?? $legacyQr['expires_at'] ?? null,
        'redirect_url' => $nextAction['redirect']['url'] ?? '',
    ];
}

function get_supported_online_payment_providers(): array
{
    return ['gcash', 'paymaya', 'paypal', 'card'];
}

function get_site_base_url(): string
{
    if (defined('SITE_BASE_URL') && SITE_BASE_URL) {
        return rtrim((string) SITE_BASE_URL, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 0) == 443)
        ? 'https'
        : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return rtrim($scheme . '://' . $host . '/cityofsantarosa', '/');
}
