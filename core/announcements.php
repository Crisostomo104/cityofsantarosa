<?php
/**
 * ─────────────────────────────────────────────────────────────
 *  ANNOUNCEMENT HELPERS
 *  Target roles:
 *    all       → shows on dashboards (employee + resident) AND index2.php
 *    employee  → employee dashboard only
 *    resident  → resident dashboard only
 *    dashboard → index2.php public page ONLY
 * ─────────────────────────────────────────────────────────────
 */

function ensure_announcements_table(PDO $pdo): void
{
    if (!empty($_SESSION['schema_checked_announcements'])) return;
    $_SESSION['schema_checked_announcements'] = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS announcements (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            title       VARCHAR(200) NOT NULL,
            body        TEXT NOT NULL,
            image       VARCHAR(255) DEFAULT NULL COMMENT 'Path to uploaded image',
            target_role ENUM('all','employee','resident','dashboard') NOT NULL DEFAULT 'all',
            barangay    VARCHAR(100) DEFAULT NULL COMMENT 'NULL = all barangays',
            is_active   TINYINT(1) NOT NULL DEFAULT 1,
            created_by  INT DEFAULT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at  DATETIME DEFAULT NULL,
            INDEX idx_active_role (is_active, target_role),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Auto-add image column if missing
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM announcements LIKE 'image'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE announcements ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER body");
        }
    } catch (PDOException $e) {
        error_log('[Announcements] ' . $e->getMessage());
    }

    // Auto-expand ENUM to include 'dashboard' if missing
    try {
        $col = $pdo->query("SHOW COLUMNS FROM announcements LIKE 'target_role'")->fetch(PDO::FETCH_ASSOC);
        if ($col && strpos($col['Type'], 'dashboard') === false) {
            $pdo->exec("ALTER TABLE announcements MODIFY COLUMN target_role ENUM('all','employee','resident','dashboard') NOT NULL DEFAULT 'all'");
        }
    } catch (PDOException $e) {
        error_log('[Announcements] ENUM migration: ' . $e->getMessage());
    }
}

/**
 * For employee/resident dashboards.
 * Shows: 'all' + matching role. Excludes 'dashboard'-only announcements.
 */
function get_announcements(PDO $pdo, string $role, string $barangay = ''): array
{
    require_once __DIR__ . '/../config/cache.php';
    ensure_announcements_table($pdo);
    
    // Cache announcements for 5 minutes (announcements rarely change)
    $cache_key = 'announcements:' . md5($role . ':' . $barangay);
    return get_cached_data($cache_key, 300, function() use ($pdo, $role, $barangay) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, title, body, image, target_role, barangay, created_at, expires_at
                FROM announcements
                WHERE is_active = 1
                  AND (expires_at IS NULL OR expires_at > NOW())
                  AND (target_role = 'all' OR target_role = ?)
                  AND (barangay IS NULL OR barangay = '' OR barangay = ?)
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$role, $barangay]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[Announcements] ' . $e->getMessage());
            return [];
        }
    });
}

/**
 * For index2.php public page.
 * Shows: 'all' + 'dashboard'. Excludes employee/resident-only.
 */
function get_public_announcements(PDO $pdo, int $limit = 6): array
{
    ensure_announcements_table($pdo);
    try {
        $stmt = $pdo->prepare("
            SELECT id, title, body, image, target_role, created_at, expires_at
            FROM announcements
            WHERE is_active = 1
              AND (expires_at IS NULL OR expires_at > NOW())
              AND target_role = 'dashboard'
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[Announcements] ' . $e->getMessage());
        return [];
    }
}

/**
 * For city admin — includes author name, all announcements.
 */
function get_all_announcements(PDO $pdo): array
{
    ensure_announcements_table($pdo);
    try {
        $stmt = $pdo->query("
            SELECT a.*, u.full_name AS author
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.user_id
            ORDER BY a.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[Announcements] ' . $e->getMessage());
        return [];
    }
}
function clear_announcements_cache(PDO $pdo): void
{
    require_once __DIR__ . '/../config/cache.php';
    $roles = ['employee', 'resident'];
    $barangays = [''];

    try {
        $stmt = $pdo->query("SELECT DISTINCT barangay FROM announcements WHERE barangay IS NOT NULL AND barangay <> ''");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $brgy) {
            $barangays[] = strtoupper(trim($brgy));
        }
    } catch (PDOException $e) {
        error_log('[Announcements] Failed to read barangays for cache clear: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay <> ''");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $brgy) {
            $barangays[] = strtoupper(trim($brgy));
        }
    } catch (PDOException $e) {
        error_log('[Announcements] Failed to read user barangays for cache clear: ' . $e->getMessage());
    }

    $barangays = array_unique($barangays);
    foreach ($roles as $role) {
        foreach ($barangays as $barangay) {
            clear_cached_data('announcements:' . md5($role . ':' . $barangay));
        }
    }

    // Invalidate the public e-inquire page cache too, since it caches announcements separately.
    clear_cached_data('e_inquire_page:' . date('Y-m-d-H'));
}
