<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  PRODUCTION BOOTSTRAP
 *  Include this at the very top of any entry-point PHP file
 *  to enforce safe PHP settings at runtime.
 *
 *  Usage:  require_once __DIR__ . '/../config/bootstrap.php';
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/cache.php';

// ── Error display: OFF in production, ON in local ─────────────
$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';

if ($appEnv === 'local') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    // Production: never leak errors to browser
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);            // still log everything
    ini_set('log_errors', '1');
}

// ── Hide PHP version from HTTP headers ────────────────────────
header_remove('X-Powered-By');

// ── Prevent response caching for authenticated pages ──────────
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// ── Clickjacking protection (fallback if mod_headers is off) ──
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
