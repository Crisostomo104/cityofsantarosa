<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';


check_login_redirect(['employee', 'city_administrator']); // Both can import

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {

    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, 'r')) !== false) {

        $header = array_map('trim', fgetcsv($handle)); // CSV header
        $inserted = 0;
        $skipped = 0;

        while(($row = fgetcsv($handle)) !== false){

            if(count($row) !== count($header)){
                $skipped++;
                continue;
            }

            $data = array_combine($header, $row);
            $data = array_map('trim', $data);

            // Required fields
            $required = [
                'user_id','full_name','age','gender','address','contact_number',
                'dob','civil_status','years_residency','valid_id','document_type','purpose'
            ];

            $missing = false;
            foreach($required as $field){
                if(empty($data[$field])){
                    $missing = true;
                    break;
                }
            }
            if($missing){
                $skipped++;
                continue;
            }

            $user_id = $data['user_id'];

            // Check user exists
            $stmtCheckUser = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id=?");
            $stmtCheckUser->execute([$user_id]);

            if($stmtCheckUser->fetchColumn() == 0){
                $skipped++;
                continue;
            }

            // Format DOB
            $dob = date('Y-m-d', strtotime($data['dob']));

            // Check duplicate: user_id + document_type + purpose + dob
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*) FROM requests 
                WHERE user_id=? AND document_type=? AND purpose=? AND dob=?
            ");
            $stmtCheck->execute([
                $user_id,
                $data['document_type'],
                $data['purpose'],
                $dob
            ]);

            if($stmtCheck->fetchColumn() > 0){
                $skipped++;
                continue;
            }

            // Insert request
            $stmtInsert = $pdo->prepare("
                INSERT INTO requests (
                    user_id, full_name, age, gender, address, contact_number,
                    dob, civil_status, years_residency, valid_id,
                    document_type, purpose, status, created_at
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'Pending', NOW())
            ");

            try{
                
                $stmtInsert->execute([
                    $user_id,
                    $data['full_name'],
                    $data['age'],
                    $data['gender'],
                    $data['address'],
                    $data['contact_number'],
                    $dob,
                    $data['civil_status'],
                    $data['years_residency'],
                    $data['valid_id'],
                    $data['document_type'],
                    $data['purpose']
                ]);
                $inserted++;
            }catch(PDOException $e){
                $skipped++;
                continue;
            }
        }

        fclose($handle);

                log_action(
                        $pdo,
                        $_SESSION['user_id'],
                        'Import CSV',
                        'Requests',
                        'Imported REQUESTS from CSV file'
                    );

        $_SESSION['form_success'] = "Import completed! Inserted: $inserted, Skipped: $skipped";
    } else {
        $_SESSION['form_error'] = "Failed to read CSV file.";
    }

} else {
    $_SESSION['form_error'] = "No CSV file uploaded.";
}

// Redirect back to dashboard or import page
header("Location:../dashboards/city_administrator#data_management");
exit;
?>