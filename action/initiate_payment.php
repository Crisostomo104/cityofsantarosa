<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/paymongo_config.php';
require_once __DIR__ . '/../core/prerequisites.php';

check_login_redirect(['resident']);

// Clear stale flash messages from the request submission form
// so they don't persist through the payment flow and re-appear on the dashboard
unset($_SESSION['success_message']);

$request_id = (int)($_GET['request_id'] ?? 0);
$rawProvider = $_GET['provider'] ?? $_POST['provider'] ?? '';
$provider = normalize_payment_provider($rawProvider ?: '');
if ($request_id <= 0) {
    header('Location: ../dashboards/resident');
    exit;
}

// Load the request + resident email
$stmt = $pdo->prepare(
    "SELECT r.*, u.email
    FROM requests r
    JOIN users u ON u.user_id = r.user_id
    WHERE r.request_id = ? AND r.user_id = ?"
);
$stmt->execute([$request_id, $_SESSION['user_id']]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    $_SESSION['flash_error'] = 'Request not found.';
    header('Location: ../dashboards/resident');
    exit;
}

if ($request['preferred_payment_method'] !== 'online') {
    $_SESSION['flash_error'] = 'This request is not configured for online payment.';
    header('Location: ../dashboards/resident');
    exit;
}

if (empty($rawProvider) && isset($request['payment_reference']) && str_starts_with($request['payment_reference'], 'pi_')) {
    header('Location: ../payment/qrph?request_id=' . $request_id);
    exit;
}

if (empty($rawProvider) && in_array($request['payment_reference'], ['gcash', 'qrph', 'paypal', 'card'], true)) {
    $provider = normalize_payment_provider($request['payment_reference']);
}

if (!in_array($provider, ['gcash', 'paymaya', 'paypal', 'card'], true)) {
    $_SESSION['flash_error'] = 'Unsupported payment provider for this request.';
    header('Location: ../dashboards/resident');
    exit;
}

// Get fee for this document type
$docType = $request['document_type'] ?? 'Other';
$amount  = get_document_fee($pdo, $docType);
$amountCentavos = (int)round($amount * 100);

if ($amount <= 0) {
    $pdo->prepare("
        UPDATE requests
        SET payment_status = 'free', payment_reference = 'free', payment_amount = 0.00
        WHERE request_id = ? AND user_id = ?
    ")->execute([$request_id, $_SESSION['user_id']]);

    $_SESSION['flash_success'] = 'This document is free. Your request has been submitted successfully.';
    header('Location: ../dashboards/resident');
    exit;
}

// Robust phone → +639XXXXXXXXX
$rawPhone = preg_replace('/[^0-9]/', '', $request['contact_number'] ?? '');
if (substr($rawPhone, 0, 2) === '09') {
    $rawPhone = '63' . substr($rawPhone, 1);
} elseif (substr($rawPhone, 0, 1) === '9' && strlen($rawPhone) === 10) {
    $rawPhone = '63' . $rawPhone;
}
$phone = '+' . $rawPhone;
$email = $request['email'] ?? 'noreply@einquire.gov.ph';

if ($provider === 'card') {
    // Create a PayMongo Payment Intent for card payments
    $cardIntentPayload = json_encode([
        'data' => [
            'attributes' => [
                'amount' => $amountCentavos,
                'currency' => 'PHP',
                'payment_method_allowed' => ['card'],
                'description' => 'E-Inquire Request #' . $request_id,
            ],
        ],
    ]);

    $ch = curl_init(PAYMONGO_API_URL . '/payment_intents');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $cardIntentPayload,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
    ]);

    $cardIntentResponse = curl_exec($ch);
    $cardIntentHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cardCurlError = curl_error($ch);
    curl_close($ch);

    if ($cardCurlError) {
        $_SESSION['flash_error'] = 'Connection error while creating card payment intent: ' . $cardCurlError;
        header('Location: ../dashboards/resident');
        exit;
    }

    $cardIntentData = json_decode($cardIntentResponse, true);
    $cardPaymentIntentId = $cardIntentData['data']['id'] ?? null;
    $cardClientKey = $cardIntentData['data']['attributes']['client_key'] ?? null;

    if ($cardIntentHttpCode !== 200 || !$cardPaymentIntentId || !$cardClientKey) {
        $errorDetail = $cardIntentData['errors'][0]['detail']
            ?? $cardIntentData['message']
            ?? ('HTTP ' . $cardIntentHttpCode);
        $_SESSION['flash_error'] = 'Unable to create card payment: ' . $errorDetail;
        header('Location: ../dashboards/resident');
        exit;
    }

    // Save the real Payment Intent ID as the payment reference
    $pdo->prepare("
        UPDATE requests
        SET payment_status = 'pending', payment_reference = ?, payment_amount = ?
        WHERE request_id = ? AND user_id = ?
    ")->execute([$cardPaymentIntentId, $amount, $request_id, $_SESSION['user_id']]);

    // Store client_key in session for card.php to use when attaching
    $_SESSION['card_client_key_' . $request_id] = $cardClientKey;

    $_SESSION['flash_success'] = 'Your card payment session is ready. Please enter your card details.';
    header('Location: ../payment/card?request_id=' . $request_id);
    exit;
}

if ($provider === 'paypal') {
    $paypalRef = 'paypal_pending_' . $request_id . '_' . time();
    $pdo->prepare("
        UPDATE requests
        SET payment_status = 'pending', payment_reference = ?, payment_amount = ?
        WHERE request_id = ? AND user_id = ?
    ")->execute([$paypalRef, $amount, $request_id, $_SESSION['user_id']]);

    $_SESSION['flash_success'] = 'Your request is waiting for payment via PayPal.';
    header('Location: ../payment/paypal?request_id=' . $request_id);
    exit;
}

if (!in_array($provider, ['gcash', 'paymaya', 'paypal'], true)) {
    $pdo->prepare("
        UPDATE requests
        SET payment_status = 'pending', payment_reference = ?, payment_amount = ?
        WHERE request_id = ? AND user_id = ?
    ")->execute([ucfirst($provider), $amount, $request_id, $_SESSION['user_id']]);

    $_SESSION['flash_success'] = 'Your request is waiting for payment via ' . get_payment_provider_label($provider) . '.';
    header('Location: ../dashboards/resident');
    exit;
}

if (empty(PAYMONGO_SECRET_KEY) && !in_array($provider, ['paymaya', 'paypal'], true)) {
    $_SESSION['flash_error'] = 'PayMongo secret key is not configured correctly. Please set PAYMONGO_SECRET_KEY in your environment or config.';
    header('Location: ../dashboards/resident');
    exit;
}

// Build redirect URLs
$baseUrl    = get_site_base_url();
$successUrl = $baseUrl . '/payment/success?request_id=' . $request_id;
$failedUrl  = $baseUrl . '/payment/failed?request_id=' . $request_id;

$paymentMethodTypeMap = [
    'gcash'   => 'gcash',
    'paymaya' => 'paymaya',
    'paypal'  => 'paypal',
    'card'    => 'card',
];
$paymentMethodType = $paymentMethodTypeMap[$provider] ?? 'gcash';
$paymentPage = in_array($provider, ['gcash', 'paymaya', 'paypal'], true) ? $provider : 'card';

// Create a PayMongo payment intent for the selected provider
$intentPayload = json_encode([
    'data' => [
        'attributes' => [
            'amount' => $amountCentavos,
            'currency' => 'PHP',
            'payment_method_allowed' => [$paymentMethodType],
            'description' => 'E-Inquire Request #' . $request_id,
        ],
    ],
]);

$useSimulatedCheckout = false;
$simulatedReference = '';

$ch = curl_init(PAYMONGO_API_URL . '/payment_intents');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $intentPayload,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
]);

$intentResponse = curl_exec($ch);
$intentHttpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError      = curl_error($ch);
curl_close($ch);

if ($curlError) {
    if (in_array($provider, ['paymaya', 'paypal'], true)) {
        $useSimulatedCheckout = true;
    } else {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $logEntry = [
            'time' => date('c'),
            'request_id' => $request_id,
            'provider' => $provider,
            'type' => 'curl_error',
            'error' => $curlError,
        ];
        @file_put_contents($logDir . '/paymongo_errors.log', json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);

        $_SESSION['flash_error'] = 'Connection error while creating ' . get_payment_provider_label($provider) . ' payment intent: ' . $curlError;
        header('Location: ../dashboards/resident');
        exit;
    }
}

if (!$useSimulatedCheckout) {
    $intentData = json_decode($intentResponse, true);
    $paymentIntentId = $intentData['data']['id'] ?? null;
    $clientKey = $intentData['data']['attributes']['client_key'] ?? null;

    if ($intentHttpCode !== 200 || !$paymentIntentId || !$clientKey) {
        if (in_array($provider, ['paymaya', 'paypal'], true)) {
            $useSimulatedCheckout = true;
        } else {
            $errorDetail = '';
            if (is_array($intentData) && !empty($intentData['errors'][0]['detail'])) {
                $errorDetail = $intentData['errors'][0]['detail'];
            } elseif (is_array($intentData) && !empty($intentData['message'])) {
                $errorDetail = $intentData['message'];
            } else {
                $errorDetail = 'HTTP ' . $intentHttpCode . ': ' . $intentResponse;
            }

            @file_put_contents(__DIR__ . '/../logs/paymongo_errors.log', json_encode([
                'time' => date('c'),
                'request_id' => $request_id,
                'provider' => $provider,
                'error' => $errorDetail,
                'raw_response' => substr((string)$intentResponse, 0, 10000),
            ]) . PHP_EOL, FILE_APPEND | LOCK_EX);

            $_SESSION['flash_error'] = 'Unable to create PayMongo ' . get_payment_provider_label($provider) . ' payment: ' . $errorDetail;
            header('Location: ../dashboards/resident');
            exit;
        }
    }
}

if ($useSimulatedCheckout) {
    $simulatedReference = build_simulated_payment_reference($provider, $request_id);
    $pdo->prepare("UPDATE requests SET payment_status = 'pending', payment_reference = ?, payment_amount = ? WHERE request_id = ?")
        ->execute([$simulatedReference, $amount, $request_id]);

    $_SESSION['flash_success'] = 'Your ' . get_payment_provider_label($provider) . ' payment session is ready in simulation mode. Please use the demo checkout screen to complete the payment.';
    header('Location: ../payment/' . $paymentPage . '?request_id=' . $request_id . '&simulated=1');
    exit;
}

$paymentMethodPayload = json_encode([
    'data' => [
        'attributes' => [
            'type' => $paymentMethodType
        ]
    ]
]);

$ch = curl_init(PAYMONGO_API_URL . '/payment_methods');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $paymentMethodPayload,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
]);

$methodResponse = curl_exec($ch);
$methodHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$methodError    = curl_error($ch);
curl_close($ch);

if ($methodError) {
    @file_put_contents(__DIR__ . '/../logs/paymongo_errors.log', json_encode([
        'time' => date('c'),
        'request_id' => $request_id,
        'provider' => $provider,
        'type' => 'curl_error',
        'error' => $methodError,
    ]) . PHP_EOL, FILE_APPEND | LOCK_EX);

    $_SESSION['flash_error'] = 'Connection error while creating PayMongo ' . get_payment_provider_label($provider) . ' payment method: ' . $methodError;
    header('Location: ../dashboards/resident');
    exit;
}

$methodData = json_decode($methodResponse, true);
$paymentMethodId = $methodData['data']['id'] ?? null;

if ($methodHttpCode !== 200 || !$paymentMethodId) {
    $errorDetail = '';
    if (is_array($methodData) && !empty($methodData['errors'][0]['detail'])) {
        $errorDetail = $methodData['errors'][0]['detail'];
    } elseif (is_array($methodData) && !empty($methodData['message'])) {
        $errorDetail = $methodData['message'];
    } else {
        $errorDetail = 'HTTP ' . $methodHttpCode . ': ' . $methodResponse;
    }

    @file_put_contents(__DIR__ . '/../logs/paymongo_errors.log', json_encode([
        'time' => date('c'),
        'request_id' => $request_id,
        'provider' => $provider,
        'error' => $errorDetail,
        'raw_response' => substr((string)$methodResponse, 0, 10000),
    ]) . PHP_EOL, FILE_APPEND | LOCK_EX);

    $_SESSION['flash_error'] = 'Unable to create PayMongo ' . get_payment_provider_label($provider) . ' payment method: ' . $errorDetail;
    header('Location: ../dashboards/resident');
    exit;
}

$attachPayload = json_encode([
    'data' => [
        'attributes' => [
            'payment_method' => $paymentMethodId,
            'client_key' => $clientKey,
            'return_url' => $baseUrl . '/payment/success?request_id=' . $request_id,
        ],
    ],
]);

$ch = curl_init(PAYMONGO_API_URL . '/payment_intents/' . $paymentIntentId . '/attach');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $attachPayload,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
]);

$attachResponse = curl_exec($ch);
$attachHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$attachError    = curl_error($ch);
curl_close($ch);

if ($attachError) {
    @file_put_contents(__DIR__ . '/../logs/paymongo_errors.log', json_encode([
        'time' => date('c'),
        'request_id' => $request_id,
        'provider' => $provider,
        'type' => 'curl_error',
        'error' => $attachError,
    ]) . PHP_EOL, FILE_APPEND | LOCK_EX);

    $_SESSION['flash_error'] = 'Connection error while attaching PayMongo ' . get_payment_provider_label($provider) . ' payment method: ' . $attachError;
    header('Location: ../dashboards/resident');
    exit;
}

$attachData = json_decode($attachResponse, true);
$attachStatus = $attachData['data']['attributes']['status'] ?? 'unknown';

if ($attachHttpCode !== 200 || $attachStatus === 'failed') {
    $errorDetail = '';
    if (is_array($attachData) && !empty($attachData['errors'][0]['detail'])) {
        $errorDetail = $attachData['errors'][0]['detail'];
    } elseif (is_array($attachData) && !empty($attachData['message'])) {
        $errorDetail = $attachData['message'];
    } else {
        $errorDetail = 'HTTP ' . $attachHttpCode . ': ' . $attachResponse;
    }

    @file_put_contents(__DIR__ . '/../logs/paymongo_errors.log', json_encode([
        'time' => date('c'),
        'request_id' => $request_id,
        'provider' => $provider,
        'status' => $attachStatus,
        'error' => $errorDetail,
        'raw_response' => substr((string)$attachResponse, 0, 10000),
    ]) . PHP_EOL, FILE_APPEND | LOCK_EX);

    $_SESSION['flash_error'] = 'Unable to initialize PayMongo ' . get_payment_provider_label($provider) . ' payment: ' . $errorDetail;
    header('Location: ../dashboards/resident');
    exit;
}

$pdo->prepare("
    UPDATE requests
    SET payment_status = 'pending', payment_reference = ?, payment_amount = ?
    WHERE request_id = ?
")->execute([$paymentIntentId, $amount, $request_id]);

$_SESSION['flash_success'] = 'Your ' . get_payment_provider_label($provider) . ' payment session is ready. Please follow the payment instructions.';
header('Location: ../payment/' . $paymentPage . '?request_id=' . $request_id);
exit;

