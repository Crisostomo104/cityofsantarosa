<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// A stale tab hitting logout while an OTP login is pending in this browser
// must not destroy the pending session.
if (!isset($_SESSION['user_id']) && isset($_SESSION['pending_user_id'])) {
    header('Location: verify-otp');
    exit;
}

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Clear the claim only if it still belongs to THIS session.
    // (Also means a 'DEACTIVATED' account can never be re-enabled by logging out.)
    $stmt = $pdo->prepare("UPDATE users SET session_id = NULL WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$user_id, session_id()]);

    log_action($pdo, $user_id, 'Logout', $_SESSION['email'] ?? null, 'User logged out');
}

// Destroy session
$_SESSION = [];
session_destroy();

// Delete the session cookie (use options array to include SameSite)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'] ?? '/', 
        'domain'   => $params['domain'] ?? '',
        'secure'   => $params['secure'] ?? true,
        'httponly' => $params['httponly'] ?? true,
        'samesite' => 'Lax'
    ]);
}

// Redirect target
$redirect = '/cityofsantarosa/erequest-mo';
$message = "Thank you for visiting! Logging out";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logging Out</title>
<link rel="icon" href="../images/ibaba2.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="../css/login.css">
<style>
    body {
        background: #fff4ed;
        cursor: not-allowed !important;
        color: black;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        font-family: 'Poppins', sans-serif;
        text-align: center;
        margin: 0;
        animation: none;
    }

    .logout-box {
        animation: fadeIn 1s ease-in-out;
    }

    #redirectText {
        letter-spacing: 1px;
        font-size: 16px;
        opacity: 0.8;
    }

    h1 {
        font-weight: 600;
        margin-bottom: 10px;
    }

    p {
        opacity: 0.8;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
</head>
<body>
<div class="logout-box">
    <h1><?= htmlspecialchars($message) ?></h1>
    <p id="redirectText">Redirecting to login</p>
</div>

<script>
    const redirect = "<?= $redirect ?>";
    const text = document.getElementById("redirectText");
    let dots = 0;

    // Redirect after 3 seconds
    setTimeout(() => {
        window.location.replace(redirect);
    }, 3000);

    // Animate dots for "Redirecting..."
    setInterval(() => {
        dots = (dots + 1) % 4;
        text.textContent = "Redirecting" + ".".repeat(dots);
    }, 500);
</script>
</body>
</html>