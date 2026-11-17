<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  AWS S3 CONFIG
 *  Kinukuha ang credentials mula sa .env (via vlucas/phpdotenv,
 *  parehong pattern gaya ng ginagamit sa mail.php / send_app_email()).
 *
 *  Idagdag ito sa .env file mo (root ng project):
 *      AWS_ACCESS_KEY=AKIA...
 *      AWS_SECRET_KEY=wJalr...
 *
 *  May masked debug log dito (logs/aws_debug.log) para makita mo kung
 *  na-detect ba talaga ang .env values -- hindi buong key/secret ang
 *  nilalagay dito, unang/huling ilang characters lang.
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Safe to call ulit kahit na-load na ito somewhere else (hal. sa bootstrap.php) —
// hindi ito magco-conflict, at gagana pa rin kahit standalone tinawag ito.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$accessKey = $_ENV['AWS_ACCESS_KEY'] ?? getenv('AWS_ACCESS_KEY') ?? $_SERVER['AWS_ACCESS_KEY'] ?? null;
$secretKey = $_ENV['AWS_SECRET_KEY'] ?? getenv('AWS_SECRET_KEY') ?? $_SERVER['AWS_SECRET_KEY'] ?? null;

// ── MASKED DEBUG LOG ──────────────────────────────────────────
// Huwag kailanman i-log ang buong key/secret. Unang 4 at huling 2
// characters lang, at yung length, sapat na para malaman natin kung
// "may laman ba talaga" o placeholder/blangko pa rin.
function mask_secret_for_log(?string $value): string
{
    if ($value === null || $value === '') {
        return '(EMPTY / NOT SET)';
    }
    $len = strlen($value);
    if ($len <= 8) {
        return str_repeat('*', $len) . " (len:$len)";
    }
    return substr($value, 0, 4) . str_repeat('*', $len - 6) . substr($value, -2) . " (len:$len)";
}

$logDir = __DIR__ . '/../logs';
if (is_dir($logDir) && is_writable($logDir)) {
    $debugLine = sprintf(
        "[%s] AWS_ACCESS_KEY=%s | AWS_SECRET_KEY=%s | is_placeholder_key=%s | is_placeholder_secret=%s\n",
        date('Y-m-d H:i:s'),
        mask_secret_for_log($accessKey),
        mask_secret_for_log($secretKey),
        ($accessKey === 'PALITAN_NG_ACCESS_KEY_MO') ? 'YES (.env not loaded!)' : 'no',
        ($secretKey === 'PALITAN_NG_SECRET_KEY_MO') ? 'YES (.env not loaded!)' : 'no'
    );
    @file_put_contents($logDir . '/aws_debug.log', $debugLine, FILE_APPEND);
}

if (!$accessKey || !$secretKey) {
    error_log('aws_config.php: AWS_ACCESS_KEY / AWS_SECRET_KEY not found in .env — S3 uploads will fail.');
}

return [
    'region' => 'ap-southeast-2',

    // I-verify mo ito sa AWS Console mo — dapat LOWERCASE lang lahat, walang
    // uppercase letters (bawal sa S3 bucket naming rules).
    'bucket' => 'erequest-mo-backup-101608676151-ap-southeast-2-an',

    'key'    => $accessKey,
    'secret' => $secretKey,
];