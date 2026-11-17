<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/functions.php';
require_once __DIR__ . '/../core/notifications.php';
check_login_redirect(['employee']);


$current_user = $_SESSION['user_id'] ?? null;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id = $_POST['user_id'] ?? null;

    $firstname  = strtoupper(trim($_POST['firstname'] ?? ''));
    $middlename = strtoupper(trim($_POST['middlename'] ?? ''));
    $lastname   = strtoupper(trim($_POST['lastname'] ?? ''));
    $email      = trim($_POST['email'] ?? '');
    $phone      = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (preg_match('/^09\d{9}$/', $phone)) {
        $phone = '63' . substr($phone, 1);
    }

    $user_type  = 'employee';
    $barangay   = strtoupper(trim($_POST['barangay'] ?? ''));

    $full_name = trim("$firstname $middlename $lastname");

    if (!$firstname || !$lastname || !$email) {
        $_SESSION['form_error'] = "Please fill in all required fields.";
        header("Location: ../dashboards/employee#?section=create-employee");
        exit;
    }

    if (!preg_match('/^63\d{10}$/', $phone)) {
        $_SESSION['form_error'] = "Enter a valid 11-digit mobile number starting with 09.";
        header("Location: ../dashboards/employee#?section=create-employee");
        exit;
    }

    if ($password && $password !== $confirm_password) {
        $_SESSION['form_error'] = "Passwords do not match.";
        header("Location: ../dashboards/employee#?section=create-employee");
        exit;
    }

    try {

    $check = $pdo->prepare("
        SELECT user_id FROM users 
        WHERE (email = ? OR phone = ?) AND user_id != ?
        LIMIT 1
    ");
    $check->execute([$email, $phone, $user_id ?? 0]);

    if ($check->fetch()) {
        $_SESSION['form_error'] = "Email or phone number already exists. Please use another one.";
        header("Location: /cityofsantarosa/dashboards/employee#?section=create-employee");
        exit;
    }

        if ($user_id) {

            // =====================
            // UPDATE EMPLOYEE
            // =====================
            $sql = "UPDATE users
                    SET firstname=?, middlename=?, lastname=?, full_name=?, email=?, phone=?,
                        is_verified=0, reset_token=NULL";

            $params = [
                $firstname,
                $middlename,
                $lastname,
                $full_name,
                $email,
                $phone
            ];

            if ($password) {
                $sql .= ", password_hash=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE user_id=? AND user_type='employee'";
            $params[] = $user_id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            notify_city_admins_employee_account_request($pdo, (int)$user_id, $full_name, $barangay);

            $logStmt = $pdo->prepare("
                INSERT INTO system_logs 
                (user_id, action_type, target, details, ip_address, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $current_user,
                'Update Employee',
                'EMP - ' . $user_id,
                "Updated employee: $full_name",
                $ip_address
            ]);

            // Bust the dashboard caches so the resubmitted (pending again)
            // account shows up for the City Administrator immediately.
            clear_cached_data('city_admin_account_data');
            clear_cached_data('city_admin_dashboard_stats');

            $_SESSION['form_success'] = "Account resubmitted for City Administrator approval.";

        } else {

            // =====================
            // CREATE EMPLOYEE
            // =====================
            $stmt = $pdo->prepare("
                INSERT INTO users
                (firstname, middlename, lastname, full_name, email, phone, password_hash, user_type, barangay, is_verified, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $firstname,
                $middlename,
                $lastname,
                $full_name,
                $email,
                $phone,
                password_hash($password, PASSWORD_DEFAULT),
                $user_type,
                $barangay,
                0  // is_verified=0 → pending admin approval
            ]);

            $new_user_id = $pdo->lastInsertId();
            notify_city_admins_employee_account_request($pdo, (int)$new_user_id, $full_name, $barangay);

            $logStmt = $pdo->prepare("
                INSERT INTO system_logs 
                (user_id, action_type, target, details, ip_address, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $current_user,
                'Create Employee',
                'EMP-' . $new_user_id,
                "Created employee: $full_name",
                $ip_address
            ]);

            // Bust the dashboard caches so this new pending request shows up
            // for the City Administrator immediately instead of after 300s.
            clear_cached_data('city_admin_account_data');
            clear_cached_data('city_admin_dashboard_stats');

            $_SESSION['form_success'] = "Employee account submitted. Awaiting City Administrator approval.";
        }

    } catch (Exception $e) {
        $_SESSION['form_error'] = "Error: " . $e->getMessage();
    }

    header("Location: /cityofsantarosa/dashboards/employee#?section=create-employee");
    exit;
}
?>