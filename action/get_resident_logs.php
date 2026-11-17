<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  GET RESIDENT LOGIN LOGS — AJAX endpoint
 *  Returns last 5 login activity logs for the current resident.
 * ─────────────────────────────────────────────────────────────
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM system_logs
    WHERE user_id = ?
    AND action_type = 'Login Success'
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getDevice($agent) {
    $agent = strtolower($agent);
    if (strpos($agent, 'windows') !== false) return 'Windows PC 💻';
    if (strpos($agent, 'android') !== false) return 'Android Phone 📱';
    if (strpos($agent, 'iphone')  !== false) return 'iPhone 📱';
    if (strpos($agent, 'ipad')    !== false) return 'iPad 📱';
    if (strpos($agent, 'mac')     !== false) return 'Mac 💻';
    if (strpos($agent, 'linux')   !== false) return 'Linux 💻';
    return 'Unknown Device ❓';
}

function getBrowser($agent) {
    $agent = strtolower($agent);
    if (strpos($agent, 'edg/') !== false) return 'Edge';
    if (strpos($agent, 'chrome') !== false) return 'Chrome';
    if (strpos($agent, 'firefox') !== false) return 'Firefox';
    if (strpos($agent, 'safari') !== false) return 'Safari';
    if (strpos($agent, 'opera') !== false || strpos($agent, 'opr/') !== false) return 'Opera';
    return 'Unknown Browser';
}

function formatTimestamp($value) {
    if (empty($value)) return 'Unknown';
    try {
        $dt = new DateTime($value, new DateTimeZone('Asia/Manila'));
        return $dt->format('M d, Y h:i A');
    } catch (Exception $e) {
        return $value;
    }
}

foreach ($logs as &$log) {
    $log['device'] = getDevice($log['user_agent'] ?? '');
    $log['browser'] = getBrowser($log['user_agent'] ?? '');
    $log['status'] = stripos($log['action_type'] ?? '', 'success') !== false ? 'success' : 'failed';
    $log['label'] = $log['status'] === 'success' ? 'Successful Login' : 'Failed Login';
    $log['created_at_formatted'] = formatTimestamp($log['created_at'] ?? null);
}

echo json_encode($logs);
