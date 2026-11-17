<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  ARCHIVING / EXPIRY HELPERS
 *  Handles what happens to a "Completed" request once its
 *  document validity period has lapsed.
 *
 *  Applies the SAME rule everywhere (resident, employee, city
 *  admin): the request stays in `requests`, its status just
 *  flips from 'Completed' to 'Expired'. Nothing is moved or
 *  deleted, so payment/discount history and the resident's own
 *  history are all preserved.
 * ─────────────────────────────────────────────────────────────
 */

/**
 * Flip status to 'Expired' for any Completed request whose validity
 * period (completed_at + document's validity_months) has lapsed.
 * Safe to call repeatedly — only touches rows still marked Completed.
 */
function expire_completed_requests(PDO $pdo): int
{
    $stmt = $pdo->query("
        SELECT r.request_id
        FROM requests r
        WHERE r.status = 'Completed'
          AND r.completed_at IS NOT NULL
          AND (
              SELECT MAX(dp.validity_months)
              FROM document_prerequisites dp
              WHERE dp.document_type = r.document_type
          ) > 0
          AND DATE_ADD(r.completed_at, INTERVAL (
              SELECT MAX(dp.validity_months)
              FROM document_prerequisites dp
              WHERE dp.document_type = r.document_type
          ) MONTH) < NOW()
    ");
    $expiredIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($expiredIds)) {
        return 0;
    }

    $updateStmt = $pdo->prepare("UPDATE requests SET status = 'Expired' WHERE request_id = ?");
    $count = 0;
    foreach ($expiredIds as $id) {
        try {
            $updateStmt->execute([$id]);
            $count++;
        } catch (Throwable $e) {
            error_log('Failed to flag request #' . $id . ' as expired: ' . $e->getMessage());
        }
    }
    return $count;
}

/**
 * Runs expire_completed_requests() at most once per hour, regardless
 * of which dashboard triggers it first (resident, employee, or city
 * admin all call this the same way).
 */
function run_expiry_check(PDO $pdo): void
{
    get_cached_data('global_expiry_check', 3600, function() use ($pdo) {
        expire_completed_requests($pdo);
        return true;
    });
}