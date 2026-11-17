<?php

const CITY_SHARE_RATE = 0.00;

function ensure_payment_schema(PDO $pdo): void
{
    if (!empty($_SESSION['schema_checked_payments'])) return;
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_transactions (
        payment_id int(11) NOT NULL AUTO_INCREMENT,
        request_id int(11) NOT NULL,
        barangay varchar(100) NOT NULL,
        gross_amount decimal(12,2) NOT NULL,
        city_fee_rate decimal(5,4) NOT NULL DEFAULT 0.0200,
        city_fee_amount decimal(12,2) NOT NULL,
        barangay_net_amount decimal(12,2) NOT NULL,
        recorded_by int(11) DEFAULT NULL,
        recorded_at datetime NOT NULL DEFAULT current_timestamp(),
        payment_method enum('online','walk-in') NOT NULL DEFAULT 'walk-in',
        PRIMARY KEY (payment_id),
        UNIQUE KEY request_id (request_id),
        KEY idx_payment_barangay_month (barangay, recorded_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS payout_requests (
        payout_id int(11) NOT NULL AUTO_INCREMENT,
        barangay varchar(100) NOT NULL,
        requested_by int(11) NOT NULL,
        month_year char(7) NOT NULL,
        gross_amount decimal(12,2) NOT NULL,
        city_fee_amount decimal(12,2) NOT NULL,
        net_amount decimal(12,2) NOT NULL,
        status enum('Pending','Approved','Rejected','Released') NOT NULL DEFAULT 'Pending',
        admin_note text DEFAULT NULL,
        requested_at datetime NOT NULL DEFAULT current_timestamp(),
        reviewed_by int(11) DEFAULT NULL,
        reviewed_at datetime DEFAULT NULL,
        PRIMARY KEY (payout_id),
        KEY idx_payout_barangay_month (barangay, month_year),
        KEY idx_payout_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Add refund columns to requests if they don't exist
    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN refund_status ENUM('none','pending','completed','failed') DEFAULT 'none'");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1060') === false) throw $e; // Ignore "duplicate column" errors
    }
    
    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN refund_reference VARCHAR(255)");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1060') === false) throw $e;
    }

    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN refund_amount DECIMAL(12,2)");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1060') === false) throw $e;
    }

    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN refund_requested_at DATETIME");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1060') === false) throw $e;
    }

    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN refund_initiated_at DATETIME");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1060') === false) throw $e;
    }

    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN refund_processed_at DATETIME");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1060') === false) throw $e;
    }

    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN refund_processed_by INT");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1060') === false) throw $e;
    }

    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN paymongo_payment_id VARCHAR(255)");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1060') === false) throw $e;
    }

    // Add 'refunded' to payment_status enum if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE requests MODIFY COLUMN payment_status ENUM('unpaid','pending','paid','refunded','free') DEFAULT 'unpaid'");
    } catch (PDOException $e) {
        // Silently ignore if enum modification fails (may already have the value)
    }

    $_SESSION['schema_checked_payments'] = true;
}

function record_payment_transaction(PDO $pdo, int $requestId, string $barangay, float $grossAmount, int $recordedBy, string $paymentMethod = 'walk-in'): void
{
    $grossAmount = round($grossAmount, 2);
    $cityFee = 0.00;
    $netAmount = $grossAmount;

    $stmt = $pdo->prepare("
        INSERT INTO payment_transactions
            (request_id, barangay, gross_amount, city_fee_rate, city_fee_amount, barangay_net_amount, payment_method, recorded_by)
        VALUES
            (?, ?, ?, 0.0000, 0.00, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            barangay = VALUES(barangay),
            gross_amount = VALUES(gross_amount),
            city_fee_rate = 0.0000,
            city_fee_amount = 0.00,
            barangay_net_amount = VALUES(barangay_net_amount),
            payment_method = VALUES(payment_method),
            recorded_by = VALUES(recorded_by),
            recorded_at = CURRENT_TIMESTAMP
    ");

    $stmt->execute([$requestId, $barangay, $grossAmount, $netAmount, $paymentMethod, $recordedBy]);
}

function mark_request_as_paid(PDO $pdo, int $requestId, float $amount, string $paymentReference, string $paymentMethod = 'online'): void
{
    $amount = round($amount, 2);
    $stmt = $pdo->prepare("SELECT user_id, barangay FROM requests WHERE request_id = ?");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        return;
    }

    $pdo->prepare("UPDATE requests SET payment_status = 'paid', payment_amount = ?, payment_reference = ? WHERE request_id = ?")
        ->execute([$amount, $paymentReference, $requestId]);

    record_payment_transaction($pdo, $requestId, $request['barangay'] ?? '', $amount, (int)($request['user_id'] ?? 0), $paymentMethod);
}

function get_barangay_monthly_payment_summary(PDO $pdo, string $barangay, string $monthYear): array
{
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS transaction_count,
            COALESCE(SUM(gross_amount), 0) AS gross_amount,
            COALESCE(SUM(city_fee_amount), 0) AS city_fee_amount,
            COALESCE(SUM(barangay_net_amount), 0) AS net_amount
        FROM payment_transactions
        WHERE barangay = ?
          AND DATE_FORMAT(recorded_at, '%Y-%m') = ?
    ");
    $stmt->execute([$barangay, $monthYear]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'transaction_count' => (int)($summary['transaction_count'] ?? 0),
        'gross_amount' => (float)($summary['gross_amount'] ?? 0),
        'city_fee_amount' => (float)($summary['city_fee_amount'] ?? 0),
        'net_amount' => (float)($summary['net_amount'] ?? 0),
    ];
}

function get_barangay_monthly_payment_summary_by_method(PDO $pdo, string $barangay, string $monthYear, string $method): array
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS transaction_count, COALESCE(SUM(gross_amount), 0) AS gross_amount
        FROM payment_transactions
        WHERE barangay = ? AND DATE_FORMAT(recorded_at, '%Y-%m') = ? AND payment_method = ?
    ");
    $stmt->execute([$barangay, $monthYear, $method]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'transaction_count' => (int)($row['transaction_count'] ?? 0),
        'gross_amount'      => (float)($row['gross_amount'] ?? 0),
    ];
}

function get_city_monthly_payment_summary(PDO $pdo, string $monthYear): array
{
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS transaction_count,
            COALESCE(SUM(gross_amount), 0) AS gross_amount,
            COALESCE(SUM(city_fee_amount), 0) AS city_fee_amount,
            COALESCE(SUM(barangay_net_amount), 0) AS net_amount
        FROM payment_transactions
        WHERE DATE_FORMAT(recorded_at, '%Y-%m') = ?
    ");
    $stmt->execute([$monthYear]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'transaction_count' => (int)($summary['transaction_count'] ?? 0),
        'gross_amount' => (float)($summary['gross_amount'] ?? 0),
        'city_fee_amount' => (float)($summary['city_fee_amount'] ?? 0),
        'net_amount' => (float)($summary['net_amount'] ?? 0),
    ];
}

function money_format_php($amount): string
{
    // Accept null or non-numeric input safely
    if (!isset($amount) || !is_numeric($amount)) {
        $amount = 0.0;
    }
    return 'PHP ' . number_format((float)$amount, 2);
}

/**
 * Process refund via PayMongo
 * @param string $paymongoPaymentId The PayMongo payment ID (from payment_reference if it's a real PayMongo ID)
 * @param float $amount Amount to refund in PHP
 * @return array ['success' => bool, 'refund_id' => string|null, 'error' => string|null]
 */
function process_paymongo_refund(string $paymongoPaymentId, float $amount): array
{
    // Validate PayMongo payment ID format
    if (!preg_match('/^pi_[a-zA-Z0-9]+$/i', $paymongoPaymentId) && !preg_match('/^pay_[a-zA-Z0-9]+$/i', $paymongoPaymentId)) {
        $errorMsg = 'Invalid PayMongo payment ID format: ' . $paymongoPaymentId;
        error_log('[PayMongo Refund] ' . $errorMsg);
        return [
            'success' => false,
            'refund_id' => null,
            'error' => $errorMsg
        ];
    }

    // Get API key from environment
    $apiKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
    if (!$apiKey) {
        return [
            'success' => false,
            'refund_id' => null,
            'error' => 'PayMongo API key not configured'
        ];
    }

    $amountCentavos = (int)round($amount * 100);
    $refundPayload = json_encode([
        'data' => [
            'attributes' => [
                'amount' => $amountCentavos,
                'payment_id' => $paymongoPaymentId,
                'reason' => 'requested_by_customer',
                'notes' => 'Automatic refund due to request rejection'
            ]
        ]
    ]);

    // PayMongo refund endpoint (unified for all payment types)
    $refundUrl = 'https://api.paymongo.com/v1/refunds';
    error_log('[PayMongo Refund] Using endpoint: ' . $refundUrl . ' for payment: ' . $paymongoPaymentId);

    $ch = curl_init($refundUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $refundPayload,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($apiKey . ':'),
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Log the refund attempt
    error_log('[PayMongo Refund] Payment ID: ' . $paymongoPaymentId . ', Amount: ' . $amount . ' PHP, HTTP: ' . $httpCode);

    if ($curlError) {
        error_log('[PayMongo Refund Error] CURL: ' . $curlError);
        return [
            'success' => false,
            'refund_id' => null,
            'error' => 'Connection error: ' . $curlError
        ];
    }

    $data = json_decode($response, true);

    // PayMongo returns HTTP 200 for refund creation (not 201)
    if (($httpCode === 200 || $httpCode === 201) && isset($data['data']['id'])) {
        error_log('[PayMongo Refund Success] Refund ID: ' . $data['data']['id'] . ', Status: ' . ($data['data']['attributes']['status'] ?? 'unknown'));
        return [
            'success' => true,
            'refund_id' => $data['data']['id'],
            'error' => null
        ];
    }

    $errorMsg = $data['errors'][0]['detail'] ?? $data['message'] ?? 'HTTP ' . $httpCode;
    error_log('[PayMongo Refund Error] Response: ' . $errorMsg . ' | Raw response: ' . substr($response, 0, 500));

    return [
        'success' => false,
        'refund_id' => null,
        'error' => $errorMsg
    ];
}

/**
 * Initiate refund for a rejected request
 * Automatically processes PayMongo refund if applicable
 * @param PDO $pdo Database connection
 * @param int $requestId Request ID to refund
 * @param int $processedBy User ID of the employee processing the refund
 * @return array ['success' => bool, 'message' => string, 'refund_id' => string|null]
 */
function initiate_refund(PDO $pdo, int $requestId, int $processedBy): array
{
    error_log('[Refund] Initiating refund for request: ' . $requestId);
    
    $stmt = $pdo->prepare("
        SELECT request_id, payment_status, payment_amount, payment_reference, paymongo_payment_id, refund_status, status
        FROM requests
        WHERE request_id = ?
        LIMIT 1
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        error_log('[Refund] Request not found: ' . $requestId);
        return [
            'success' => false,
            'message' => 'Request not found',
            'refund_id' => null
        ];
    }

    error_log('[Refund] Request status: ' . $request['status'] . ', Payment status: ' . $request['payment_status'] . ', Refund status: ' . $request['refund_status']);

    // Check if already refunded
    if ($request['refund_status'] === 'completed') {
        error_log('[Refund] Already refunded: ' . $requestId);
        return [
            'success' => false,
            'message' => 'Refund already processed for this request',
            'refund_id' => null
        ];
    }

    // Only refund paid online requests
    if ($request['payment_status'] !== 'paid') {
        error_log('[Refund] Request not paid or already refunded: ' . $requestId . ', status: ' . $request['payment_status']);
        return [
            'success' => false,
            'message' => 'Request has no paid online payment to refund',
            'refund_id' => null
        ];
    }

    $refundAmount = (float)($request['payment_amount'] ?? 0);
    if ($refundAmount <= 0) {
        error_log('[Refund] No refundable amount: ' . $requestId);
        return [
            'success' => false,
            'message' => 'No refundable amount for this request',
            'refund_id' => null
        ];
    }

    error_log('[Refund] Processing refund for request ' . $requestId . ', amount: ' . $refundAmount);

    // Mark refund as pending
    $updateStmt = $pdo->prepare("
        UPDATE requests
        SET refund_status = 'pending', refund_amount = ?, refund_initiated_at = NOW()
        WHERE request_id = ?
    ");
    $updateStmt->execute([$refundAmount, $requestId]);

    // Try to process PayMongo refund if payment ID exists
    $paymongoPaymentId = $request['paymongo_payment_id'] ?: $request['payment_reference'];
    
    error_log('[Refund] PayMongo ID (from paymongo_payment_id): ' . ($request['paymongo_payment_id'] ?? 'NULL'));
    error_log('[Refund] Payment reference: ' . ($request['payment_reference'] ?? 'NULL'));
    error_log('[Refund] Using PayMongo ID: ' . $paymongoPaymentId);

    // Check if it's a real PayMongo payment ID (starts with pi_ or pay_) or a simulated payment
    if (preg_match('/^(pi_|pay_)[a-zA-Z0-9]+$/i', $paymongoPaymentId)) {
        error_log('[Refund] Attempting PayMongo API call for payment: ' . $paymongoPaymentId);
        $refundResult = process_paymongo_refund($paymongoPaymentId, $refundAmount);

        if ($refundResult['success']) {
            error_log('[Refund] PayMongo refund successful, ID: ' . $refundResult['refund_id']);
            $updateStmt = $pdo->prepare("
                UPDATE requests
                SET refund_status = 'completed', payment_status = 'refunded', refund_reference = ?, refund_processed_at = NOW(), refund_processed_by = ?
                WHERE request_id = ?
            ");
            $updateStmt->execute([$refundResult['refund_id'], $processedBy, $requestId]);

            return [
                'success' => true,
                'message' => 'Refund processed successfully. Reference: ' . $refundResult['refund_id'],
                'refund_id' => $refundResult['refund_id']
            ];
        } else {
            // PayMongo refund failed - mark as failed but log for admin follow-up
            error_log('[Refund] PayMongo refund failed: ' . $refundResult['error']);
            $updateStmt = $pdo->prepare("
                UPDATE requests
                SET refund_status = 'failed', refund_reference = ?, refund_processed_at = NOW(), refund_processed_by = ?
                WHERE request_id = ?
            ");
            $updateStmt->execute(['FAILED: ' . $refundResult['error'], $processedBy, $requestId]);

            return [
                'success' => false,
                'message' => 'Refund initiation failed: ' . $refundResult['error'] . '. Admin will manually process.',
                'refund_id' => null
            ];
        }
    } else {
        // Simulated or walk-in payment - mark as completed without calling PayMongo
        error_log('[Refund] Simulated/non-PayMongo payment, marking refund as completed locally');
        $updateStmt = $pdo->prepare("
            UPDATE requests
            SET refund_status = 'completed', payment_status = 'refunded', refund_reference = ?, refund_processed_at = NOW(), refund_processed_by = ?
            WHERE request_id = ?
        ");
        $updateStmt->execute(['Auto: ' . $paymongoPaymentId, $processedBy, $requestId]);

        return [
            'success' => true,
            'message' => 'Refund marked as completed (simulated/walk-in payment)',
            'refund_id' => $paymongoPaymentId
        ];
    }
}

function get_document_revenue_summary(PDO $pdo, ?string $barangay, ?string $rangeStart, ?string $rangeEnd): array
{
    $sql = "
        SELECT r.document_type,
               COUNT(*) AS transaction_count,
               COALESCE(SUM(pt.gross_amount), 0) AS total_revenue
        FROM payment_transactions pt
        INNER JOIN requests r ON pt.request_id = r.request_id
        WHERE 1=1
    ";
    $params = [];

    if ($rangeStart !== null) {
        $sql .= " AND pt.recorded_at >= ? AND pt.recorded_at < ?";
        $params[] = $rangeStart;
        $params[] = $rangeEnd;
    }

    if ($barangay !== null) {
        $sql .= " AND pt.barangay = ?";
        $params[] = $barangay;
    }

    $sql .= " GROUP BY r.document_type ORDER BY total_revenue DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
