<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/payments.php';
require_once __DIR__ . '/../core/tracking.php';

// ONLY EMPLOYEES CAN ACCESS
check_login_redirect(['employee']);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ======================
    // EMPLOYEE = USER OWNER
    // ======================
    $employee_id = $_SESSION['user_id'] ?? null;

    if (!$employee_id) {
        die("Unauthorized access.");
    }

    // ======================
    // FORM DATA
    // ======================
    $full_name = trim($_POST['full_name'] ?? '');
    $age = $_POST['age'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = ($_POST['address'] ?? '') . ', ' . ($_SESSION['barangay'] ?? 'Barangay');
    $dob = $_POST['dob'] ?? '';
    $civil_status = $_POST['civil_status'] ?? '';
    $years_residency = $_POST['years_residency'] ?? '';
    $document_type = $_POST['document_type'] ?? '';
    $purpose = strtoupper(trim($_POST['purpose'] ?? ''));
    $contact_number = $_POST['contact_number'] ?? '';
    $valid_id = $_POST['valid_id'] ?? 'N/A';
    $payment_amount = (float)($_POST['payment_amount'] ?? 0);

    // CUSTOM DOCUMENT
    if ($document_type === "Other" && !empty($_POST['custom_document'])) {
        $document_type = trim($_POST['custom_document']);
    }

    $status = "Completed";
    $payment_status = ($payment_amount > 0) ? 'paid' : 'unpaid';

    // ======================
    // INSERT REQUEST
    // ======================
    $stmt = $pdo->prepare("
        INSERT INTO requests (
            user_id,
            full_name,
            age,
            gender,
            address,
            contact_number,
            dob,
            civil_status,
            years_residency,
            valid_id,
            document_type,
            purpose,
            status,
            processed_by,
            preferred_payment_method,
            payment_status,
            payment_amount,
            payment_reference,
            created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'walk-in', ?, ?, 'walk-in', NOW()
        )
    ");

    try {

        $stmt->execute([
            $employee_id,      
            $full_name,
            $age,
            $gender,
            $address,
            $contact_number,
            $dob,
            $civil_status,
            $years_residency,
            $valid_id,
            $document_type,
            $purpose,
            $status,
            $employee_id,
            $payment_status,
            $payment_amount
        ]);

        $request_id = (int)$pdo->lastInsertId();
        $barangay = $_SESSION['barangay'] ?? '';

        // Generate tracking code
        generate_tracking_code($pdo, $request_id, $barangay);

        // Record payment transaction (walk-in)
        if ($payment_amount > 0) {
            record_payment_transaction($pdo, $request_id, $barangay, $payment_amount, (int)$employee_id, 'walk-in');
        }

        log_action(
            $pdo,
            $employee_id,
            'Manual Request',
            'Requests',
            "Created MANUAL REQUEST for $full_name"
        );

        $_SESSION['sms_success'] = "Manual request created successfully.";

    } catch (PDOException $e) {
        $_SESSION['form_error'] = "Error: " . $e->getMessage();
    }

    header("Location: ../dashboards/employee#");
    exit;
}
?>