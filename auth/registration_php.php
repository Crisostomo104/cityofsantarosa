<?php 
require_once __DIR__ . '/../config/bootstrap.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/otp_helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Configure error handling. Show errors only in local/dev, always log errors.
$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
if (php_sapi_name() !== 'cli') {
    if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', '0');
    }
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/registration_error.log');
}

$apikey = $_ENV['SEMAPHORE_API_KEY'] ?? '';


header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$error = '';
$success = '';
$visionDebug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';
    $storedToken = $_SESSION['_csrf_token'] ?? '';

    if (!$storedToken || !hash_equals($storedToken, $submittedToken)) {
        $error = "Invalid security token. Please refresh and try again.";
    } else {
    $firstname = strtoupper(trim($_POST['firstname'] ?? ''));
    $middlename_raw = trim($_POST['middlename'] ?? '');
    $lastname = strtoupper(trim($_POST['lastname'] ?? ''));
	$suffix_raw = trim($_POST['suffix'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $barangay = strtoupper(trim($_POST['barangay'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $idImage = $_FILES['id_image'] ?? null;
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';

    // middle name normalize
    if ($middlename_raw === '' || strtoupper($middlename_raw) === 'N/A' || strtoupper($middlename_raw) === 'NONE' || $middlename_raw === '-') {
        $middlename = null;
    } else {
        $middlename = strtoupper($middlename_raw);
    }

    // suffix normalize
    $allowedSuffixes = ['JR.', 'SR.', 'II', 'III', 'IV', 'V'];
    $suffix = strtoupper($suffix_raw);
    if ($suffix === '' || !in_array($suffix, $allowedSuffixes, true)) {
        $suffix = null;
    }
        
	$fullname = $firstname . ' ' . ($middlename ? $middlename . ' ' : '') . $lastname . ($suffix ? ' ' . $suffix : '');
        
    // normalize phone to Philippine E.164 (no leading +). Accept 09..., 9..., +63..., 63...
    $phone = preg_replace('/\D/', '', $phone);
    if (preg_match('/^09\d{9}$/', $phone)) {
        $phone = '63' . substr($phone, 1);
    } elseif (preg_match('/^9\d{9}$/', $phone)) {
        $phone = '63' . $phone;
    } elseif (preg_match('/^\+?63\d{10}$/', $_POST['phone'] ?? '')) {
        $phone = preg_replace('/^\+/', '', $phone);
    }

    // VALIDATION
    if (!isset($_POST['terms'])) {
        $error = "You must agree to the Terms and Conditions.";

    } elseif (!verify_recaptcha($recaptchaToken, $_SERVER['REMOTE_ADDR'] ?? null)) {
        $error = "Please verify that you are not a robot.";

    } elseif (!$firstname || !$lastname || !$email || !$phone || !$password || !$confirm_password) {
        $error = "All fields are required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";

    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";

    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";

    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least 1 uppercase letter, 1 lowercase letter, and 1 number.";

    } elseif (empty($barangay)) {
        $error = "Please select your barangay.";

    } elseif (!$idImage || !isset($idImage['tmp_name']) || $idImage['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload a clear government ID image.";

    } elseif (!in_array($idImage['type'] ?? '', ['image/jpeg', 'image/jpg', 'image/png'], true)) {
        $error = "ID image must be a JPG or PNG file.";

    } elseif ($idImage['size'] > 5 * 1024 * 1024) {
        $error = "ID image must be smaller than 5MB.";

    } elseif ((($_ENV['DISABLE_RATE_LIMIT'] ?? getenv('DISABLE_RATE_LIMIT')) === '1') ? false : !rate_limit('registration', 5, 3600)) {
        $error = "Too many registration attempts. Please try again later.";

    } else {

        // check duplicate
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->execute([$email, $phone]);

        if ($stmt->fetchColumn()) {
            $error = "Email or phone already exists.";

        } else {
            $idTempDir = __DIR__ . '/../uploads/id_verification/';
            if (!is_dir($idTempDir)) mkdir($idTempDir, 0755, true);

            $extension = strtolower(pathinfo($idImage['name'], PATHINFO_EXTENSION));
            $idFilename = bin2hex(random_bytes(16)) . '.' . $extension;
            $idPath = $idTempDir . $idFilename;

            if (!move_uploaded_file($idImage['tmp_name'], $idPath)) {
                $error = "Unable to save ID image. Please try again.";
            } else {
                $ocrText = extract_text_from_image($idPath);

                // If cloud OCR failed, try local Tesseract fallback
                if ($ocrText === false) {
                    $tessText = extract_text_with_tesseract($idPath);
                    if ($tessText !== false) {
                        $ocrText = $tessText;
                        $visionDebug .= '; fallback=tesseract';
                    } else {
                        // As a web-friendly fallback when shell_exec is disabled,
                        // use OCR.Space HTTP API (free-tier available). This avoids
                        // needing `shell_exec` on the web process.
                        $ocrSpaceText = extract_text_with_ocrspace($idPath);
                        if ($ocrSpaceText !== false && trim($ocrSpaceText) !== '') {
                            $ocrText = $ocrSpaceText;
                            $visionDebug .= '; fallback=ocrspace';
                        }
                    }
                }

                if ($ocrText === false) {
                    $error = "Unable to extract text from the ID image. Please try a clearer photo.";

                    if (!empty($visionDebug)) {
                        error_log('OCR fallback debug: ' . $visionDebug);
                    }
                    $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
                    if ($appEnv === 'local' && !empty($visionDebug)) {
                        $error .= " (Vision debug: " . htmlspecialchars($visionDebug) . ")";
                    }
                    @unlink($idPath);
                } else {
                    $ocrNormalized = normalize_text($ocrText);
                    $fullNameNormalized = normalize_text($fullname);
                    // collapsed normalized OCR for label checks
                    $ocrNormalizedCollapsed = str_replace(' ', '', $ocrNormalized);

                    // ── Name matching ──────────────────────────────────────
                    // IMPORTANT: build the match patterns from the ORIGINAL
                    // $firstname / $middlename / $lastname form fields, not
                    // by re-splitting the combined $fullname string by
                    // whitespace count. Many Filipino surnames are two words
                    // ("DELA CRUZ", "DE LEON", "DEL ROSARIO", "DE GUZMAN"),
                    // and splitting-by-word-count previously mis-assigned the
                    // first word of a compound last name as a "middle name",
                    // which then never matched the ID text — causing valid
                    // IDs to be rejected as a "name mismatch".
                    $firstNameNorm = normalize_text($firstname);
                    $lastNameNorm = normalize_text($lastname);
                    $middleNameNorm = $middlename ? normalize_text($middlename) : '';
                    $suffixNorm = $suffix ? normalize_text($suffix) : '';

                    $firstPattern = preg_quote($firstNameNorm, '/');
                    $lastPattern = preg_quote($lastNameNorm, '/');
                    // Wrapped in a non-capturing group so the "?" makes the WHOLE suffix
                    // optional, not just its last character. Many IDs never print the
                    // suffix even when the resident legally has one, so this must never
                    // block an otherwise-correct name match.
                    $suffixPattern = $suffixNorm !== '' ? '(?:\\s*' . preg_quote($suffixNorm, '/') . ')?' : '';

                    $nameMatch = false;

                    if ($middleNameNorm === '') {
                        $nameMatch = preg_match("/\\b{$firstPattern}\\s+{$lastPattern}{$suffixPattern}\\b/i", $ocrNormalized)
                                     || preg_match("/\\b{$lastPattern}{$suffixPattern}\\s*,?\\s*{$firstPattern}\\b/i", $ocrNormalized);
                    } else {
                        $middlePattern = preg_quote($middleNameNorm, '/');
                        // IDs often print only a middle INITIAL rather than the
                        // full middle name — accept either form.
                        $middleInitial = preg_quote(mb_substr($middleNameNorm, 0, 1), '/');
                        $middleAlt = "(?:{$middlePattern}|{$middleInitial}\\.?)";

                        $nameMatch = preg_match("/\\b{$firstPattern}\\s+{$middleAlt}\\s+{$lastPattern}{$suffixPattern}\\b/i", $ocrNormalized)
                                     || preg_match("/\\b{$lastPattern}{$suffixPattern}\\s*,?\\s*{$firstPattern}\\s+{$middleAlt}\\b/i", $ocrNormalized)
                                     // some IDs don't print the middle name/initial at all
                                     || preg_match("/\\b{$firstPattern}\\s+{$lastPattern}{$suffixPattern}\\b/i", $ocrNormalized)
                                     || preg_match("/\\b{$lastPattern}{$suffixPattern}\\s*,?\\s*{$firstPattern}\\b/i", $ocrNormalized);
                    }

                    // Last-resort fallback: some ID layouts print "Last Name"
                    // and "First Name" as separate stacked fields far apart
                    // (not adjacent), so require only that both the first
                    // and last name appear SOMEWHERE in the OCR'd text.
                    if (!$nameMatch) {
                        $nameMatch = (stripos($ocrNormalized, $firstNameNorm) !== false)
                                     && (stripos($ocrNormalized, $lastNameNorm) !== false);
                    }

                    // More tolerant city check using normalized OCR text (handles spacing/ocr quirks)
                    $cityMatch = (stripos($ocrNormalizedCollapsed, 'SANTAROSA') !== false)
                                 || (stripos($ocrNormalizedCollapsed, 'CITYOFSANTAROSA') !== false);
                    $birthDate = extract_birthdate_from_id_text($ocrText);
                    $birthDateMissing = $birthDate === false;
                    $underage = false;

                    if (!$birthDateMissing) {
                        $age = (int)$birthDate->diff(new DateTime('now'))->y;
                        if ($age < 18) {
                            $underage = true;
                        }
                    }

                    // Consider the ID valid if it contains standard ID markers
                    // OR as a pragmatic fallback if both the name and birthdate
                    // were confidently extracted (covers non-English layouts).
                    $validId = is_valid_id_text($ocrText) || ($nameMatch && !$birthDateMissing);

                    if (!$nameMatch) {
                        $error = "The name on your ID does not match the name you entered. Please upload a valid ID.";
                        @unlink($idPath);
                    } elseif (!$cityMatch) {
                        $error = "Unable to verify Santa Rosa City on your ID. Please upload a valid Santa Rosa ID.";
                        @unlink($idPath);
                    } elseif ($birthDateMissing) {
                        $error = "Unable to verify your date of birth from the uploaded ID. Please upload a clearer government ID showing your DOB.";
                        @unlink($idPath);
                    } elseif ($underage) {
                        $error = "You must be at least 18 years old to register. Please upload a valid government ID showing your date of birth.";
                        @unlink($idPath);
                    } elseif (!$validId) {
                        $error = "Unable to verify this ID as a valid government-issued ID. Please upload a genuine ID document.";

                        // Log OCR and vision debug info to help diagnose why valid IDs are rejected
                        $debugSnippet = substr(trim($ocrText ?? ''), 0, 2000);
                        $visionSnippet = substr(trim($visionDebug ?? ''), 0, 1000);
                        $logMsg = 'ID verification failed: nameMatch=' . ($nameMatch ? '1' : '0')
                                . ' cityMatch=' . ($cityMatch ? '1' : '0')
                                . ' birthDateMissing=' . ($birthDateMissing ? '1' : '0')
                                . ' underage=' . ($underage ? '1' : '0')
                                . ' validId=' . ($validId ? '1' : '0')
                                . ' visionDebug=' . $visionSnippet
                                . ' ocrText=' . preg_replace('/\s+/', ' ', $debugSnippet);
                        error_log($logMsg);

                        // In local/dev environment surface a short debug hint to the user
                        if (in_array($appEnv, ['local', 'development', 'dev'], true) && (!empty($visionSnippet) || !empty($debugSnippet))) {
                            $error .= ' (Debug: ' . htmlspecialchars(substr($visionSnippet . ' | ' . $debugSnippet, 0, 300)) . ')';
                        }

                        // keep the uploaded file for inspection in non-production environments
                        if (!in_array($appEnv, ['local', 'development', 'dev'], true)) {
                            @unlink($idPath);
                        }
                    } else {
                        $success = "Sending OTP...";
                        $_SESSION['registration_id_verified_flash'] = true;

                        $_SESSION['pending_user'] = [
                            'firstname' => $firstname,
                            'middlename' => $middlename,
                            'lastname' => $lastname,
                            'suffix' => $suffix,
                            'fullname' => $fullname,
                            'email' => $email,
                            'phone' => $phone,
                            'password' => password_hash($password, PASSWORD_DEFAULT),
                            'barangay' => $barangay,
                            'id_file_path' => $idPath,
                            'id_ocr_text' => $ocrNormalized,
                            'id_verification' => [
                                'name_match' => true,
                                'city_match' => true,
                            ],
                        ];

                        // CHECK EXISTING OTP
                        $stmt = $pdo->prepare(
                            "SELECT * FROM otp_requests
                            WHERE phone = ?
                            LIMIT 1
                        "
                        );

                        $stmt->execute([$phone]);

                        $existingOtp = $stmt->fetch();

                        if ($existingOtp && !empty($existingOtp['locked_until']) && (int)$existingOtp['locked_until'] > time()) {
                            $_SESSION['otp_locked_until'] = (int)$existingOtp['locked_until'];
                            $_SESSION['otp_expire'] = (int)$existingOtp['expires_at'];
                            $success = "OTP already sent.";
                        } elseif ($existingOtp && (int)$existingOtp['expires_at'] > time()) {

                            $_SESSION['otp_expire'] = (int)$existingOtp['expires_at'];
                            $_SESSION['otp_locked_until'] = 0;
                            $_SESSION['otp_last_sent'] = $_SESSION['otp_last_sent'] ?? time();

                            $success = "OTP already sent.";

                        } else {

                            // OTP GENERATION
                            $otp = random_int(100000, 999999);
                            $expires_at = time() + 120;

                            $_SESSION['otp_code'] = $otp;
                            $_SESSION['otp_expire'] = $expires_at;
                            $_SESSION['otp_locked_until'] = 0;
                            $_SESSION['otp_last_sent'] = time();

                            // STORE OTP IN DB
                            $stmt = $pdo->prepare(
                                "INSERT INTO otp_requests (phone, otp_code, expires_at)
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                    otp_code = VALUES(otp_code),
                                    expires_at = VALUES(expires_at),
                                    attempts = 0,
                                    locked_until = NULL
                            "
                            );

                            $stmt->execute([$phone, $otp, $expires_at]);

                            // SEND SMS
                            $result = send_sms($phone, $otp, $apikey);

                            if ($result['success']) {
                                $success = "OTP sent to your phone.";
                            } else {
                                $error = "Failed to send OTP.";
                            }
                        }
                    }
                }
            }
        }
    }
}
}

function old($field) {
    return htmlspecialchars($_POST[$field] ?? '');
}

function normalize_text(string $text): string {
    $text = strtoupper($text);
    $text = preg_replace('/[^A-Z0-9\s]/', ' ', $text);
    return preg_replace('/\s+/', ' ', trim($text));
}

function is_plausible_birthdate(DateTime $date): bool {
    $now = new DateTime('now');
    if ($date > $now) {
        return false; // can't have been born in the future
    }
    $earliest = (clone $now)->modify('-120 years');
    if ($date < $earliest) {
        return false; // implausibly old — almost certainly a misread date
    }
    return true;
}

function extract_birthdate_from_id_text(string $text): DateTime|false {
    $months = [
        'JAN' => '01', 'JANUARY' => '01',
        'FEB' => '02', 'FEBRUARY' => '02',
        'MAR' => '03', 'MARCH' => '03',
        'APR' => '04', 'APRIL' => '04',
        'MAY' => '05',
        'JUN' => '06', 'JUNE' => '06',
        'JUL' => '07', 'JULY' => '07',
        'AUG' => '08', 'AUGUST' => '08',
        'SEP' => '09', 'SEPT' => '09', 'SEPTEMBER' => '09',
        'OCT' => '10', 'OCTOBER' => '10',
        'NOV' => '11', 'NOVEMBER' => '11',
        'DEC' => '12', 'DECEMBER' => '12',
    ];

    // Labeled patterns FIRST — anchored to an explicit birthdate label, so
    // they can't accidentally grab a "Valid Until" or "Date Issued" date
    // instead. Generic date-shaped patterns are only used as a last resort
    // if no labeled birthdate field was found anywhere on the ID.
    $labeledPatterns = [
        '/DATE\s*OF\s*BIRTH\s*[:\-\s]*([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{4})/i',
        '/BIRTHDATE\s*[:\-\s]*([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{4})/i',
        '/DOB\s*[:\-\s]*([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{4})/i',
    ];

    $genericPatterns = [
        '/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4})/',
        '/(\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})/',
    ];

    foreach ([$labeledPatterns, $genericPatterns] as $patternGroup) {
        foreach ($patternGroup as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $value = $matches[1];
                $normalized = str_replace(['/', '.'], '-', trim($value));
                $formats = ['Y-m-d', 'd-m-Y', 'm-d-Y'];
                foreach ($formats as $format) {
                    $birthDate = DateTime::createFromFormat($format, $normalized);
                    if ($birthDate !== false) {
                        $errors = DateTime::getLastErrors();
                        $clean = !$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
                        if ($clean && is_plausible_birthdate($birthDate)) {
                            return $birthDate;
                        }
                    }
                }
            }
        }
    }

    // Spelled-out month formats, e.g. "JAN 15, 1990" / "15 JANUARY 1990"
    $monthNames = implode('|', array_keys($months));
    if (preg_match('/\b(' . $monthNames . ')\s+(\d{1,2}),?\s+(\d{4})\b/i', $text, $m)) {
        $mm = $months[strtoupper($m[1])];
        $dd = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $yyyy = $m[3];
        $birthDate = DateTime::createFromFormat('Y-m-d', "$yyyy-$mm-$dd");
        if ($birthDate !== false && is_plausible_birthdate($birthDate)) return $birthDate;
    }
    if (preg_match('/\b(\d{1,2})\s+(' . $monthNames . ')\s+(\d{4})\b/i', $text, $m)) {
        $dd = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mm = $months[strtoupper($m[2])];
        $yyyy = $m[3];
        $birthDate = DateTime::createFromFormat('Y-m-d', "$yyyy-$mm-$dd");
        if ($birthDate !== false && is_plausible_birthdate($birthDate)) return $birthDate;
    }

    return false;
}

function is_valid_id_text(string $text): bool {
    $idText = strtoupper($text);
    // Collapse all whitespace so OCR spacing quirks ("DATE OFBIRTH",
    // "DATEOF BIRTH") don't cause a real label to be missed.
    $idTextCollapsed = preg_replace('/\s+/', '', $idText);

    $labelMarkers = [
        'ID CARD',
        'DRIVER LICENSE',
        'UMID',
        'PASSPORT',
        'REPUBLIC OF THE PHILIPPINES',
        'PHILIPPINES',
        'SSS',
        'GSIS',
        'PRC',
        'TIN',
        'VOTER',
        'NBI',
        'OSCA',
        'PHILSYS',
        'NATIONAL ID',
        'BARANGAY',
        'POSTAL ID',
        'SCHOOL ID',
        'STUDENT',
        'SENIOR CITIZEN',
        'PWD',
    ];

    $fieldMarkers = [
        'DATE OF BIRTH',
        'BIRTHDATE',
        'PLACE OF BIRTH',
        'SEX',
        'GENDER',
        'CIVIL STATUS',
        'VALID UNTIL',
        'ISSUED ON',
        'DATE ISSUED',
        'ID NUMBER',
        'ADDRESS',
        'SIGNATURE',
        'FINGERPRINT',
        'FATHER',
        'MOTHER',
        'HEIGHT',
        'WEIGHT',
        'BLOOD TYPE',
        'NATIONALITY',
    ];

    $hasLabel = false;
    foreach ($labelMarkers as $marker) {
        $markerCollapsed = str_replace(' ', '', $marker);
        if (stripos($idText, $marker) !== false || stripos($idTextCollapsed, $markerCollapsed) !== false) {
            $hasLabel = true;
            break;
        }
    }

    $fieldCount = 0;
    foreach ($fieldMarkers as $marker) {
        $markerCollapsed = str_replace(' ', '', $marker);
        if (stripos($idText, $marker) !== false || stripos($idTextCollapsed, $markerCollapsed) !== false) {
            $fieldCount++;
        }
    }

    return $hasLabel && $fieldCount >= 2;
}

function extract_text_from_image(string $imagePath): string|false {
    global $visionDebug;

    $apiKey = $_ENV['GOOGLE_API_KEY'] ?? getenv('GOOGLE_API_KEY');
    if (!$apiKey) {
        $visionDebug = 'Missing GOOGLE_API_KEY';
        return false;
    }

    $content = file_get_contents($imagePath);
    if ($content === false) {
        $visionDebug = 'Unable to read image file';
        return false;
    }

    $content = base64_encode($content);
    $body = json_encode([
        'requests' => [[
            'image' => ['content' => $content],
            'features' => [
                ['type' => 'DOCUMENT_TEXT_DETECTION', 'maxResults' => 1],
                ['type' => 'TEXT_DETECTION', 'maxResults' => 1],
            ],
        ]],
    ]);

    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . urlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        $visionDebug = 'cURL error: ' . $curlError;
        return false;
    }

    if ($code !== 200) {
        $visionDebug = 'HTTP code ' . $code . ' response: ' . $response;

        // If the Google Vision API is rejected due to billing/permission (403),
        // automatically try local Tesseract and OCR.Space as immediate fallbacks
        // so users without an active billing account can still have OCR attempted.
        if ($code === 403) {
            // try local Tesseract first
            $tess = extract_text_with_tesseract($imagePath);
            if ($tess !== false) {
                $visionDebug .= '; fallback=tesseract';
                return $tess;
            }

            // then try OCR.Space HTTP API
            $ocrspace = extract_text_with_ocrspace($imagePath);
            if ($ocrspace !== false) {
                $visionDebug .= '; fallback=ocrspace';
                return $ocrspace;
            }
        }

        return false;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['responses'][0])) {
        $visionDebug = 'Invalid JSON response: ' . $response;
        return false;
    }

    $responseData = $data['responses'][0];
    if (!empty($responseData['error'])) {
        $visionDebug = 'Vision API error: ' . ($responseData['error']['message'] ?? json_encode($responseData['error']));
        return false;
    }

    if (!empty($responseData['fullTextAnnotation']['text'])) {
        return $responseData['fullTextAnnotation']['text'];
    }

    if (!empty($responseData['textAnnotations'][0]['description'])) {
        return $responseData['textAnnotations'][0]['description'];
    }

    if (!empty($responseData['textAnnotations'])) {
        $lines = array_filter(array_column($responseData['textAnnotations'], 'description'));
        return implode("\n", $lines);
    }

    $visionDebug = 'No text detected in response';
    // fall through to try local tesseract (handled by separate function)
    // but return false here; the tesseract fallback will be invoked by caller if needed
    return false;
}

/**
 * Attempt to extract text using local Tesseract OCR binary.
 * Returns extracted text or false on failure.
 */
function extract_text_with_tesseract(string $imagePath): string|false {
    // Do not attempt Tesseract if process execution is disabled in PHP.
    if (!function_exists('shell_exec') || !function_exists('exec')) {
        return false;
    }

    // Candidate locations for Windows installers
    $candidates = [];
    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $candidates[] = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
        $candidates[] = 'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe';
    }

    // Try system lookup
    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $where = @shell_exec('where tesseract 2>&1');
        if ($where) {
            foreach (preg_split('/\r?\n/', $where) as $line) {
                $line = trim($line);
                if ($line !== '') $candidates[] = $line;
            }
        }
    } else {
        $which = @shell_exec('which tesseract 2>&1');
        if ($which) $candidates[] = trim($which);
    }

    $bin = null;
    foreach ($candidates as $candidate) {
        if (!$candidate) continue;
        if (file_exists($candidate) && is_file($candidate)) {
            $bin = $candidate;
            break;
        }
    }

    if (!$bin) return false;

    $escImage = escapeshellarg($imagePath);
    $escBin = escapeshellarg($bin);
    $cmd = "$escBin $escImage stdout 2>&1";
    $output = [];
    $ret = 0;
    exec($cmd, $output, $ret);
    if ($ret !== 0) return false;
    $text = trim(implode("\n", $output));
    return $text === '' ? false : $text;
}

/**
 * Use OCR.Space public API as an HTTP fallback when local Tesseract cannot be executed.
 * Returns extracted text string or false on failure.
 */
function extract_text_with_ocrspace(string $imagePath): string|false {
    global $visionDebug;
    $apiKey = $_ENV['OCR_SPACE_API_KEY'] ?? getenv('OCR_SPACE_API_KEY') ?: 'helloworld';

    if (!file_exists($imagePath)) {
        $visionDebug .= '; ocrspace=file_missing';
        return false;
    }

    $imageData = file_get_contents($imagePath);
    if ($imageData === false) {
        $visionDebug .= '; ocrspace=image_read_error';
        return false;
    }

    $mimeType = mime_content_type($imagePath) ?: 'image/png';
    $base64Image = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    $post = [
        'apikey' => $apiKey,
        'language' => 'eng',
        'isOverlayRequired' => 'false',
        'detectOrientation' => 'true',
        'scale' => 'true',
        'OCREngine' => '2',
        'isTable' => 'false',
        'base64Image' => $base64Image,
    ];

    $res = false;
    $code = 0;
    $err = '';

    if (function_exists('curl_init') && function_exists('curl_setopt')) {
        $visionDebug .= '; ocrspace=using_curl_base64';
        $ch = curl_init('https://api.ocr.space/parse/image');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Expect:']);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res === false || $err || $code !== 200) {
            $visionDebug .= '; ocrspace_error=' . ($err ?: 'unknown') . '; http=' . $code;
            $res = false;
        }
    }

    if ($res === false && ini_get('allow_url_fopen') && function_exists('stream_context_create')) {
        $visionDebug .= '; ocrspace=using_stream_base64';
        $content = http_build_query($post);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $content,
                'timeout' => 60,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $res = @file_get_contents('https://api.ocr.space/parse/image', false, $context);
        if ($res === false) {
            $visionDebug .= '; ocrspace_stream_error';
        }
    }

    if ($res === false) {
        if (strpos($visionDebug, 'ocrspace_error') === false && strpos($visionDebug, 'ocrspace_stream_error') === false) {
            $visionDebug .= '; ocrspace_no_response';
        }
        return false;
    }

    $json = json_decode($res, true);
    if (!is_array($json)) {
        $visionDebug .= '; ocrspace_invalid_json';
        $visionDebug .= '; raw_response=' . preg_replace('/\s+/', ' ', substr($res, 0, 320));
        return false;
    }

    if (!empty($json['IsErroredOnProcessing'])) {
        $visionDebug .= '; ocrspace_processing_error';
        if (!empty($json['ErrorMessage'])) {
            $visionDebug .= '; message=' . implode(' | ', (array) $json['ErrorMessage']);
        }
        if (!empty($json['ErrorDetails'])) {
            $visionDebug .= '; details=' . substr($json['ErrorDetails'], 0, 240);
        }
        error_log('OCR.Space processing failed: ' . $visionDebug . ' response=' . substr($res, 0, 800));
        return false;
    }

    if (!empty($json['ParsedResults'][0]['ParsedText'])) {
        $visionDebug .= '; ocrspace_success';
        return $json['ParsedResults'][0]['ParsedText'];
    }

    if (!empty($json['ParsedResults'][0]['ErrorMessage'])) {
        $visionDebug .= '; ocrspace_parsed_error=' . implode(' | ', (array) $json['ParsedResults'][0]['ErrorMessage']);
    }

    $visionDebug .= '; ocrspace_no_text';
    error_log('OCR.Space returned no text: ' . $visionDebug . ' response=' . substr($res, 0, 800));
    return false;
}

?>