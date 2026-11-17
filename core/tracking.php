<?php
/**
 * ─────────────────────────────────────────────────────────────
 *  TRACKING CODE HELPERS
 *  Generates and resolves short tracking codes for requests.
 *  Format: SR-{BRGY_INITIALS}-{YYYYMMDD}-{0001}
 * ─────────────────────────────────────────────────────────────
 */

/**
 * Generate a tracking code for a request.
 * @param PDO    $pdo       Database connection
 * @param int    $requestId The request_id after INSERT
 * @param string $barangay  The barangay name
 * @param string $createdAt The request's created_at timestamp
 * @return string           The generated tracking code
 */
function generate_tracking_code(PDO $pdo, int $requestId, string $barangay, string $createdAt = ''): string
{
    // Fetch the user_id that created the request
    $stmt = $pdo->prepare("SELECT user_id FROM requests WHERE request_id = ? LIMIT 1");
    $stmt->execute([$requestId]);
    $userId = $stmt->fetchColumn() ?: '0';

    // Date part (dateonly)
    $date = $createdAt ? date('Ymd', strtotime($createdAt)) : date('Ymd');

    // Format using sprintf (padding request ID to 3 digits)
    $code = sprintf("REQ%s%s-%03d", $date, $userId, $requestId);

    // Save to DB
    try {
        $stmt = $pdo->prepare("UPDATE requests SET tracking_code = ? WHERE request_id = ?");
        $stmt->execute([$code, $requestId]);
    } catch (PDOException $e) {
        error_log("[Tracking] Failed to save code for request #{$requestId}: " . $e->getMessage());
    }

    // Also generate the QR verification token (only if this request doesn't have one yet)
    generate_qr_token($pdo, $requestId);

    return $code;
}

/**
 * Generate (or return existing) random QR verification token for a request.
 * This token is unguessable, unlike tracking_code (which is date/user/id based),
 * so it's the component that actually protects the QR verification page from
 * being forged or brute-forced.
 * @param PDO $pdo       Database connection
 * @param int $requestId The request_id
 * @return string        The qr_token (existing or newly generated)
 */
function generate_qr_token(PDO $pdo, int $requestId): string
{
    $stmt = $pdo->prepare("SELECT qr_token FROM requests WHERE request_id = ? LIMIT 1");
    $stmt->execute([$requestId]);
    $existing = $stmt->fetchColumn();

    if (!empty($existing)) {
        return $existing;
    }

    $token = bin2hex(random_bytes(8)); // 16-character hex string

    try {
        $stmt = $pdo->prepare("UPDATE requests SET qr_token = ? WHERE request_id = ?");
        $stmt->execute([$token, $requestId]);
    } catch (PDOException $e) {
        error_log("[QR Token] Failed to save token for request #{$requestId}: " . $e->getMessage());
    }

    return $token;
}

/**
 * Verify a tracking_code + qr_token pair for QR document verification.
 * Both must match the SAME request row — tracking_code alone is guessable,
 * so requiring the random token prevents forged/guessed verification links.
 * @param PDO    $pdo          Database connection
 * @param string $trackingCode The tracking code from the scanned QR
 * @param string $token        The qr_token from the scanned QR
 * @return array|null          Limited public-safe request info, or null if invalid
 */
function verify_qr_code(PDO $pdo, string $trackingCode, string $token): ?array
{
    $trackingCode = strtoupper(trim($trackingCode));
    $token = trim($token);

    if ($trackingCode === '' || $token === '') {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT r.request_id, r.tracking_code, r.document_type, r.status, r.created_at, r.preferred_payment_method, u.barangay
        FROM requests r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.tracking_code = ? AND r.qr_token = ?
        LIMIT 1
    ");
    $stmt->execute([$trackingCode, $token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ?: null;
}

/**
 * Lookup a request by its tracking code.
 * @param PDO    $pdo  Database connection
 * @param string $code The tracking code to look up
 * @return array|null  The request row, or null if not found
 */
function lookup_tracking_code(PDO $pdo, string $code): ?array
{
    $code = strtoupper(trim($code));

    // 1. Try exact match from DB first
    $stmt = $pdo->prepare("
        SELECT r.*, u.barangay, u.email
        FROM requests r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.tracking_code = ?
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        return $result;
    }

    // 2. Fallback for migrated/changed formats: extract request_id from the code
    $parts = explode('-', $code);
    $reqId = (int)end($parts);

    if ($reqId > 0) {
        $stmt = $pdo->prepare("
            SELECT r.*, u.barangay, u.email
            FROM requests r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.request_id = ?
            LIMIT 1
        ");
        $stmt->execute([$reqId]);
        $fallback = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fallback) {
            // Verify if the input code matches the newly formatted code logic
            $date = date('Ymd', strtotime($fallback['created_at']));
            $userId = $fallback['user_id'];
            
            // Check both %03d and %04d (previous padded version)
            $expected3 = sprintf("REQ%s%s-%03d", $date, $userId, $reqId);
            $expected4 = sprintf("REQ%s%s-%04d", $date, $userId, $reqId);

            if ($code === $expected3 || $code === $expected4) {
                // Optionally update DB to new format so exact match works next time
                $update = $pdo->prepare("UPDATE requests SET tracking_code = ? WHERE request_id = ?");
                $update->execute([$expected3, $reqId]);
                $fallback['tracking_code'] = $expected3;
                return $fallback;
            }
        }
    }

    return null;
}