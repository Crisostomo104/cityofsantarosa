<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php'; // your DB connection
require_once __DIR__ . '/../config/functions.php'; // for log_action
require_once __DIR__ . '/../config/session.php';

$error = '';
$blocked = false;
$resetSuccess = false;

$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!$token) {
    $error = "Invalid password reset link.";
    $blocked = true;
}

if (!$blocked) {
    $stmt = $pdo->prepare("SELECT user_id, reset_expires, email FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = "Invalid or expired token.";
        $blocked = true;
    } elseif (strtotime($user['reset_expires']) < time()) {
        $error = "This password reset link has expired.";
        $blocked = true;
    }
}

// Handle form submission — only when the token is actually valid.
if (!$blocked && $_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    if (!rate_limit('reset_password', 5, 60)) {
        $error = "Too many attempts. Please wait a moment and try again.";
    } else {
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!$password || !$confirm_password) {
            $error = 'Please fill in all fields.';
            log_action($pdo, $user['user_id'], 'Password Reset Failed', $user['email'], 'Empty password fields submitted');
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
            log_action($pdo, $user['user_id'], 'Password Reset Failed', $user['email'], 'Passwords did not match');
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
            $error = "Password is too weak. Must contain uppercase, lowercase, number, and special character.";
            log_action($pdo, $user['user_id'], 'Password Reset Failed', $user['email'], 'Weak password attempt');
        } else {
            // Hash the new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Update password, clear token, and reset login attempts
            $update = $pdo->prepare("
                UPDATE users
                SET password_hash = ?, reset_token = NULL, reset_expires = NULL, login_attempts = 0
                WHERE user_id = ?
            ");
            $update->execute([$password_hash, $user['user_id']]);

            // Log successful password reset
            log_action($pdo, $user['user_id'], 'Password Reset Success', $user['email'], 'Password successfully reset');

            $resetSuccess = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - Santa Rosa</title>
<!-- ICON -->
<link rel="icon" href="../images/ibaba2.png">

<!-- Nexa Fonts (City of Santa Rosa) -->
<link href="https://santarosacity.gov.ph/assets/fonts/stylesheet.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://santarosacity.gov.ph/assets/lib/animate/animate.min.css" rel="stylesheet">

<!-- BOOTSTRAP CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://santarosacity.gov.ph/assets/css/bootstrap.css" rel="stylesheet">
<link href="https://santarosacity.gov.ph/assets/css/style.css" rel="stylesheet">
<link href="https://santarosacity.gov.ph/css/custom.css" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<!-- CUSTOM CSS -->
<link rel="stylesheet" type="text/css" href="../css/login.css">
<link rel="stylesheet" type="text/css" href="../css/style.css">
<link rel="stylesheet" type="text/css" href="../css/auth-page-navbar.css?v=6">
</head>
<style>
.form-check-input {
    cursor: pointer;
    transform: scale(1.1);
}

.form-check-label {
    cursor: pointer;
    font-size: 16px;
    color: #444;
}
</style>
<body>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Navbar Start (same as homepage, not clickable) -->
<nav class="navbar bg-theme navbar-light sticky-top shadow p-0 auth-page-navbar auth-page-navbar--glass" aria-label="Password reset">
    <div class="navbar-brand d-flex align-items-center auth-page-navbar-brand">
        <img class="h-95" src="/cityofsantarosa/images/logos/erequest-logo.png" alt="City of Santa Rosa">
    </div>
    <span class="auth-page-navbar-title">Reset Password</span>
</nav>
<!-- Navbar End -->

<div class="reset-page-wrap">
<div class="index-container">
    <h1 class="mb-3">Reset Password</h1>

    <?php if ($blocked): ?>
        <script>
            Swal.fire({
                icon: "error",
                title: "Reset Failed",
                text: "<?= htmlspecialchars($error, ENT_QUOTES) ?>"
            }).then(() => {
                window.location.href = "login";
            });
        </script>
    <?php elseif ($resetSuccess): ?>
        <script>
            Swal.fire({
                icon: "success",
                title: "Password Changed!",
                text: "Your password has been successfully updated. You can now log in with your new password.",
                confirmButtonText: "Go to Login",
                timer: 4000,
                timerProgressBar: true,
                allowOutsideClick: false
            }).then(() => {
                window.location.href = "login?reset=success";
            });
        </script>
    <?php else: ?>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="resetForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" name="password" id="password" required class="form-control" autocomplete="new-password">
        </div>

        <!-- Live password strength meter -->
        <div id="strengthMeter" style="height:6px; border-radius:4px; background:#e9ecef; margin-top:8px; overflow:hidden;">
            <div id="strengthMeterFill" style="height:100%; width:0%; background:#dc3545; transition:width .2s ease, background-color .2s ease;"></div>
        </div>
        <p id="strengthLabel" style="font-size:13px; margin:4px 0 0; color:#888;"></p>

        <!-- Live checklist of requirements -->
        <ul id="requirementList" style="list-style:none; padding-left:0; margin:10px 0 0; font-size:13px;">
            <li data-rule="length" style="color:#888;">✗ At least 8 characters</li>
            <li data-rule="upper" style="color:#888;">✗ 1 uppercase letter</li>
            <li data-rule="lower" style="color:#888;">✗ 1 lowercase letter</li>
            <li data-rule="number" style="color:#888;">✗ 1 number</li>
            <li data-rule="special" style="color:#888;">✗ 1 special character</li>
        </ul>

        <div class="form-group mt-3">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required class="form-control" autocomplete="new-password">
        </div>
<div class="form-check mt-2 d-flex align-items-center gap-2">
    <input class="form-check-input" type="checkbox" id="togglePassword">
    <label class="form-check-label" for="togglePassword">
        Show password
    </label>
</div>
        <button type="submit" class="btn btn-primary mt-3" id="resetBtn">
            Reset Password
        </button>
    </form>

    <a href="/cityofsantarosa/erequest-mo" class="bottom-text" style="display:block; margin-top:16px;">← Back to Login</a>

    <?php endif; ?>
</div>

<script>
function isStrongPassword(password) {
    // At least:
    // - 8 characters
    // - 1 uppercase
    // - 1 lowercase
    // - 1 number
    // - 1 special character
    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
    return regex.test(password);
}

// ── Live checklist + strength meter ──
const rules = {
    length:  (p) => p.length >= 8,
    upper:   (p) => /[A-Z]/.test(p),
    lower:   (p) => /[a-z]/.test(p),
    number:  (p) => /\d/.test(p),
    special: (p) => /[\W_]/.test(p),
};

const requirementList = document.getElementById('requirementList');
const strengthMeterFill = document.getElementById('strengthMeterFill');
const strengthLabel = document.getElementById('strengthLabel');

function updatePasswordFeedback(password) {
    let metCount = 0;

    Object.entries(rules).forEach(([rule, test]) => {
        const li = requirementList.querySelector(`[data-rule="${rule}"]`);
        const passed = test(password);
        if (passed) metCount++;

        const label = li.textContent.slice(2); // strip existing ✓/✗
        li.textContent = (passed ? '✓ ' : '✗ ') + label;
        li.style.color = passed ? '#1e7e34' : '#888';
    });

    const percent = (metCount / Object.keys(rules).length) * 100;
    strengthMeterFill.style.width = percent + '%';

    let color = '#dc3545'; // weak — red
    let label = 'Weak';
    if (metCount >= 5) {
        color = '#1e7e34'; // strong — green
        label = 'Strong';
    } else if (metCount >= 3) {
        color = '#e0a800'; // medium — yellow
        label = 'Medium';
    }

    strengthMeterFill.style.backgroundColor = color;
    strengthLabel.textContent = password ? `Strength: ${label}` : '';
    strengthLabel.style.color = color;
}

document.getElementById('password')?.addEventListener('input', (e) => {
    updatePasswordFeedback(e.target.value);
});

document.getElementById("resetForm")?.addEventListener("submit", function(e) {

    const pass = document.getElementById("password").value;
    const confirm = document.getElementById("confirm_password").value;

    if (pass !== confirm) {
        e.preventDefault();
        Swal.fire({
            icon: "warning",
            title: "Mismatch",
            text: "Passwords do not match."
        });
        return;
    }

    if (!isStrongPassword(pass)) {
        e.preventDefault();
        Swal.fire({
            icon: "error",
            title: "Weak Password",
            html: `
                Password must contain:<br>
                • At least 8 characters<br>
                • 1 uppercase letter<br>
                • 1 lowercase letter<br>
                • 1 number<br>
                • 1 special character
            `
        });
        return;
    }

    Swal.fire({
        title: "Updating password...",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
});
</script>

<script>
    document.getElementById("togglePassword")?.addEventListener("change", function () {

    const pass = document.getElementById("password");
    const confirm = document.getElementById("confirm_password");

    if (this.checked) {
        pass.type = "text";
        confirm.type = "text";
    } else {
        pass.type = "password";
        confirm.type = "password";
    }
});
</script>

</div>
</body>
</html>