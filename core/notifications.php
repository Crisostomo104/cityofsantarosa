<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  NOTIFICATION HELPERS
 *  In-app notification system for residents & employees.
 *
 *  Table: notifications
 *    id          INT AUTO_INCREMENT PRIMARY KEY
 *    user_id     INT NOT NULL
 *    message     VARCHAR(500) NOT NULL
 *    link        VARCHAR(255) DEFAULT NULL
 *    is_read     TINYINT(1) DEFAULT 0
 *    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * ─────────────────────────────────────────────────────────────
 */

/**
 * Ensure the notifications table exists (safe to call on every request).
 */
function ensure_notifications_table(PDO $pdo): void
{
    if (!empty($_SESSION['schema_checked_notifications'])) return;
    $_SESSION['schema_checked_notifications'] = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            message     VARCHAR(500) NOT NULL,
            link        VARCHAR(255) DEFAULT NULL,
            is_read     TINYINT(1) DEFAULT 0,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_read (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/**
 * Create a notification for a specific user.
 */
function create_notification(PDO $pdo, int $user_id, string $message, ?string $link = null): void
{
    ensure_notifications_table($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, message, link)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user_id, $message, $link]);
    
    // Periodic maintenance: delete expired notifications approximately once per hour.
    // Use a lightweight file lock to avoid running DELETE on every request.
    $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'e_inquire_notif_cleanup.lock';
    $maxAge = 3600; // seconds
    if (!file_exists($lockFile) || (time() - @filemtime($lockFile) > $maxAge)) {
        // touch lock file first to avoid concurrent runs
        @touch($lockFile);
        try {
            delete_expired_notifications($pdo);
            @touch($lockFile);
        } catch (Exception $e) {
            // ignore cleanup errors
        }
    }
}

/**
 * Notify city administrators when a barangay employee account is submitted.
 */
function notify_city_admins_employee_account_request(
    PDO $pdo,
    int $employee_id,
    string $full_name,
    string $barangay
): void {
    $stmt = $pdo->query("
        SELECT user_id
        FROM users
        WHERE user_type = 'city_administrator'
    ");
    $admin_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $barangayLabel = trim($barangay) !== '' ? ' from ' . trim($barangay) : '';
    $message = 'New barangay employee account request' . $barangayLabel . ': ' . $full_name;

    foreach ($admin_ids as $admin_id) {
        create_notification(
            $pdo,
            (int)$admin_id,
            $message,
            '../dashboards/city_administrator?panel=approvals'
        );
    }
}

/**
 * Get unread notification count for a user.
 */
function get_unread_count(PDO $pdo, int $user_id): int
{
    // Do not run cleanup here — avoid expensive DELETE on every poll.
    ensure_notifications_table($pdo);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get latest notifications for a user (read + unread).
 */
function get_notifications(PDO $pdo, int $user_id, int $limit = 15): array
{
    // Avoid running cleanup during high-frequency reads.
    ensure_notifications_table($pdo);

    $stmt = $pdo->prepare("
        SELECT id, message, link, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mark a single notification as read.
 */
function mark_notification_read(PDO $pdo, int $notification_id, int $user_id): void
{
    ensure_notifications_table($pdo);

    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
}

/**
 * Delete a notification for a user.
 */
function delete_notification(PDO $pdo, int $notification_id, int $user_id): void
{
    ensure_notifications_table($pdo);

    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
}

/**
 * Delete all notifications for a user.
 */
function delete_all_notifications(PDO $pdo, int $user_id): void
{
    ensure_notifications_table($pdo);

    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

/**
 * Delete notifications older than 7 days.
 */
function delete_expired_notifications(PDO $pdo): void
{
    ensure_notifications_table($pdo);

    $stmt = $pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
}

/**
 * Mark all notifications as read for a user.
 */
function mark_all_notifications_read(PDO $pdo, int $user_id): void
{
    ensure_notifications_table($pdo);

    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
}
