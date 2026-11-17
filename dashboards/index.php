<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

$cache_key = 'dashboard_redirect:' . ($_SESSION['user_type'] ?? 'guest');
$redirect_target = get_cached_data($cache_key, 300, function() {
    $role = $_SESSION['user_type'] ?? '';

    if ($role === 'resident') {
        return '/cityofsantarosa/dashboards/resident';
    }

    if ($role === 'employee') {
        return '/cityofsantarosa/dashboards/employee';
    }

    if ($role === 'city_administrator') {
        return '/cityofsantarosa/dashboards/city_administrator';
    }

    return null;
});

if ($redirect_target) {
    header('Location: ' . $redirect_target);
    exit;
}

http_response_code(403);
include __DIR__ . '/../errors/403.php';
exit;
