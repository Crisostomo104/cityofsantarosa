<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  PREREQUISITE HELPERS
 *  Admin-configurable prerequisite rules per document type.
 * ─────────────────────────────────────────────────────────────
 */

/**
 * Ensure the prerequisite tables exist.
 */
function ensure_prerequisite_tables(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;

    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $isSqlite = $driver === 'sqlite';

    if ($isSqlite) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS document_prerequisites (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                document_type   TEXT NOT NULL,
                prereq_name     TEXT NOT NULL,
                is_required     INTEGER DEFAULT 1,
                price           NUMERIC NOT NULL DEFAULT 0.00,
                created_at      TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(document_type, prereq_name)
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS document_settings (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                document_type   TEXT NOT NULL UNIQUE,
                price           NUMERIC NOT NULL DEFAULT 0.00,
                fee_amount      NUMERIC NOT NULL DEFAULT 0.00,
                is_free         INTEGER NOT NULL DEFAULT 0,
                created_at      TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at      TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS document_prerequisites (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                document_type   VARCHAR(100) NOT NULL,
                prereq_name     VARCHAR(150) NOT NULL,
                is_required     TINYINT(1) DEFAULT 1,
                price           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_doc_prereq (document_type, prereq_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS document_settings (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                document_type   VARCHAR(100) NOT NULL UNIQUE,
                price           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                fee_amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                is_free         TINYINT(1) NOT NULL DEFAULT 0,
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    try {
        $pdo->exec("ALTER TABLE document_prerequisites ADD COLUMN price NUMERIC NOT NULL DEFAULT 0.00");
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("ALTER TABLE document_prerequisites ADD COLUMN validity_months INT NOT NULL DEFAULT 0");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("ALTER TABLE document_settings ADD COLUMN price NUMERIC NOT NULL DEFAULT 0.00");
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("ALTER TABLE document_prerequisites ADD COLUMN pwd_senior_enabled TINYINT(1) NOT NULL DEFAULT 0");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN pwd_senior_requested TINYINT(1) NOT NULL DEFAULT 0");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN pwd_senior_verified TINYINT(1) NOT NULL DEFAULT 0");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN pwd_senior_rejected TINYINT(1) NOT NULL DEFAULT 0");
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN pwd_senior_original_amount DECIMAL(10,2) NULL");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("ALTER TABLE requests ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL");
    } catch (PDOException $e) {}
    
    $pdo->exec("UPDATE document_settings SET price = fee_amount WHERE (price IS NULL OR price = 0) AND COALESCE(fee_amount, 0) > 0");
    $pdo->exec("UPDATE document_settings SET fee_amount = price, is_free = CASE WHEN price <= 0 THEN 1 ELSE 0 END WHERE price IS NOT NULL");

    if ($isSqlite) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS request_attachments (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                request_id      INTEGER NOT NULL,
                prereq_name     TEXT NOT NULL,
                file_path       TEXT NOT NULL,
                file_size       INTEGER NOT NULL,
                original_name   TEXT NOT NULL,
                file_hash       TEXT NOT NULL DEFAULT '',
                uploaded_at     TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS request_attachments (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                request_id      INT NOT NULL,
                prereq_name     VARCHAR(150) NOT NULL,
                file_path       VARCHAR(255) NOT NULL,
                file_size       INT NOT NULL,
                original_name   VARCHAR(255) NOT NULL,
                file_hash       VARCHAR(64) NOT NULL DEFAULT '',
                uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_request (request_id),
                INDEX idx_file_hash (file_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    try {
        $pdo->exec("ALTER TABLE request_attachments ADD COLUMN file_hash TEXT NOT NULL DEFAULT ''");
    } catch (PDOException $e) {
    }

    try {
        $pdo->exec("ALTER TABLE request_attachments ADD COLUMN file_hash VARCHAR(64) NOT NULL DEFAULT ''");
    } catch (PDOException $e) {
    }

    $checked = true;
}

/**
 * Seed default prerequisites if the table is empty.
 */
function seed_default_prerequisites(PDO $pdo): void
{
    ensure_prerequisite_tables($pdo);

    $count = (int) $pdo->query("SELECT COUNT(*) FROM document_prerequisites")->fetchColumn();
    if ($count > 0) return;

    $defaults = [
        ['Business Permit', 'DTI/SEC Registration', 1],
        ['Business Permit', 'Valid ID with Photo', 1],
        ['Other', 'Valid ID with Address', 1],
    ];

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO document_prerequisites (document_type, prereq_name, is_required)
        VALUES (?, ?, ?)
    ");

    foreach ($defaults as $row) {
        $stmt->execute($row);
    }

    $defaultsFees = [];
    if (defined('DOCUMENT_FEES')) {
        foreach (DOCUMENT_FEES as $docType => $fee) {
            $defaultsFees[$docType] = (float) $fee;
        }
    }

    foreach (['Barangay Clearance','Certificate of Indigency','Business Permit','Residency Certificate','Other'] as $docType) {
        $fee = $defaultsFees[$docType] ?? 0.0;
        upsert_document_setting($pdo, $docType, $fee, $fee <= 0);
    }
}

/**
 * Get all document types that should be available for requests.
 * Includes built-in defaults and any custom types already stored in the DB.
 */
function get_available_document_types(PDO $pdo): array
{
    ensure_prerequisite_tables($pdo);

    $defaults = [
        'Barangay Clearance',
        'Certificate of Indigency',
        'Business Permit',
        'Residency Certificate',
        'Other',
    ];

    $stmt = $pdo->query("
        SELECT DISTINCT document_type
        FROM (
            SELECT document_type FROM document_prerequisites
            UNION
            SELECT document_type FROM document_settings
        ) AS combined
        WHERE document_type IS NOT NULL
          AND TRIM(document_type) <> ''
        ORDER BY document_type
    ");

    $types = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $type) {
        $type = trim((string) $type);
        if ($type !== '' && !in_array($type, $types, true)) {
            $types[] = $type;
        }
    }

    $ordered = [];
    foreach ($defaults as $default) {
        if (in_array($default, $types, true) && !in_array($default, $ordered, true)) {
            $ordered[] = $default;
        }
    }

    foreach ($types as $type) {
        if (!in_array($type, $ordered, true)) {
            $ordered[] = $type;
        }
    }

    return $ordered;
}

/**
 * Get all prerequisites grouped by document type.
 */
function get_all_prerequisites(PDO $pdo): array
{
    ensure_prerequisite_tables($pdo);

    $stmt = $pdo->query("
        SELECT id, document_type, prereq_name, is_required
        FROM document_prerequisites
        ORDER BY document_type, prereq_name
    ");

    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $grouped[$row['document_type']][] = $row;
    }
    return $grouped;
}

/**
 * Get prerequisites for a specific document type.
 */
function get_prerequisites_for(PDO $pdo, string $document_type): array
{
    ensure_prerequisite_tables($pdo);

    $stmt = $pdo->prepare("
        SELECT id, prereq_name, is_required
        FROM document_prerequisites
        WHERE document_type = ?
        ORDER BY prereq_name
    ");
    $stmt->execute([$document_type]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Add a prerequisite rule.
 */
function add_prerequisite(PDO $pdo, string $document_type, string $prereq_name, bool $is_required = true): bool
{
    ensure_prerequisite_tables($pdo);

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO document_prerequisites (document_type, prereq_name, is_required)
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$document_type, $prereq_name, $is_required ? 1 : 0]);
}

/**
 * Save or update a document fee setting.
 */
function upsert_document_setting(PDO $pdo, string $document_type, float $price, bool $is_free = false): bool
{
    ensure_prerequisite_tables($pdo);

    $document_type = trim((string) $document_type);
    $price = max(0.0, round((float) $price, 2));
    $is_free = $is_free || $price <= 0;

    $stmt = $pdo->prepare("UPDATE document_prerequisites SET price = ? WHERE document_type = ?");
    $stmt->execute([$price, $document_type]);

    $compatStmt = $pdo->prepare("
        INSERT INTO document_settings (document_type, price, fee_amount, is_free)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE price = VALUES(price), fee_amount = VALUES(fee_amount), is_free = VALUES(is_free)
    ");
    $compatStmt->execute([$document_type, $price, $price, $is_free ? 1 : 0]);

    return true;
}

function rename_document_type(PDO $pdo, string $old_name, string $new_name): bool
{
    ensure_prerequisite_tables($pdo);

    $oldName = trim((string) $old_name);
    $newName = trim((string) $new_name);

    if ($oldName === '' || $newName === '' || $oldName === $newName) {
        return false;
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare("UPDATE document_prerequisites SET document_type = ? WHERE document_type = ?")
            ->execute([$newName, $oldName]);
        $pdo->prepare("UPDATE document_settings SET document_type = ? WHERE document_type = ?")
            ->execute([$newName, $oldName]);
        $pdo->prepare("UPDATE requests SET document_type = ? WHERE document_type = ?")
            ->execute([$newName, $oldName]);
        $pdo->prepare("UPDATE archive_requests SET document_type = ? WHERE document_type = ?")
            ->execute([$newName, $oldName]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Failed to rename document type: ' . $e->getMessage());
        return false;
    }
}

function remove_document_type(PDO $pdo, string $document_type): bool
{
    ensure_prerequisite_tables($pdo);

    $documentType = trim((string) $document_type);
    if ($documentType === '') {
        return false;
    }

    $protectedDefaults = ['Barangay Clearance', 'Certificate of Indigency', 'Business Permit', 'Residency Certificate', 'Other'];
    if (in_array($documentType, $protectedDefaults, true)) {
        return false;
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare("DELETE FROM document_prerequisites WHERE document_type = ?")
            ->execute([$documentType]);
        $pdo->prepare("DELETE FROM document_settings WHERE document_type = ?")
            ->execute([$documentType]);
        $pdo->prepare("UPDATE requests SET document_type = 'Other' WHERE document_type = ?")
            ->execute([$documentType]);
        $pdo->prepare("UPDATE archive_requests SET document_type = 'Other' WHERE document_type = ?")
            ->execute([$documentType]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Failed to remove document type: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get the fee setting for a document type.
 */
function get_document_setting(PDO $pdo, string $document_type): array
{
    ensure_prerequisite_tables($pdo);

    $document_type = trim((string) $document_type);
    static $settingsCache = [];
    $cacheKey = $document_type;

    if (array_key_exists($cacheKey, $settingsCache)) {
        return $settingsCache[$cacheKey];
    }

    $stmt = $pdo->prepare("SELECT MAX(price) AS price, MAX(pwd_senior_enabled) AS pwd_senior_enabled, MAX(validity_months) AS validity_months FROM document_prerequisites WHERE document_type = ?");
    $stmt->execute([$document_type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && isset($row['price'])) {
        $price = (float)($row['price'] ?? 0.0);
        $is_free = $price <= 0;

        $settingsCache[$cacheKey] = [
            'price' => $price,
            'fee_amount' => $price,
            'is_free' => $is_free,
            'pwd_senior_enabled' => (bool)($row['pwd_senior_enabled'] ?? 0),
            'validity_months' => (int)($row['validity_months'] ?? 0),
        ];
        return $settingsCache[$cacheKey];
    }

    $fallbackFee = 0.0;
    if (defined('DOCUMENT_FEES') && isset(DOCUMENT_FEES[$document_type])) {
        $fallbackFee = (float) DOCUMENT_FEES[$document_type];
    }

    upsert_document_setting($pdo, $document_type, $fallbackFee, $fallbackFee <= 0);

    $settingsCache[$cacheKey] = [
        'price' => $fallbackFee,
        'fee_amount' => $fallbackFee,
        'is_free' => $fallbackFee <= 0,
        'pwd_senior_enabled' => false,
        'validity_months' => 0,
    ];
    return $settingsCache[$cacheKey];
}

/**
 * Get the effective fee for a document type.
 */
function get_document_fee(PDO $pdo, string $document_type): float
{
    $setting = get_document_setting($pdo, $document_type);
    return $setting['is_free'] ? 0.0 : (float) ($setting['price'] ?? $setting['fee_amount'] ?? 0.0);
}

/**
 * Get all document fee settings.
 */
function get_all_document_settings(PDO $pdo): array
{
    ensure_prerequisite_tables($pdo);

        $stmt = $pdo->query("
            SELECT document_type, MAX(price) AS price, MAX(pwd_senior_enabled) AS pwd_senior_enabled, MAX(validity_months) AS validity_months
            FROM document_prerequisites
            GROUP BY document_type
            ORDER BY document_type
        ");
        $settings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $documentType = $row['document_type'];
            $price = (float)($row['price'] ?? 0.0);
            $settings[$documentType] = [
                'price' => $price,
                'fee_amount' => $price,
                'is_free' => $price <= 0,
                'pwd_senior_enabled' => (bool)($row['pwd_senior_enabled'] ?? 0),
                'validity_months' => (int)($row['validity_months'] ?? 0),
            ];
        }

    return $settings;
}

/**
 * Delete a prerequisite rule by ID.
 */
function delete_prerequisite(PDO $pdo, int $id): bool
{
    ensure_prerequisite_tables($pdo);
    $stmt = $pdo->prepare("DELETE FROM document_prerequisites WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Get attachments for a request.
 */
function get_request_attachments(PDO $pdo, int $request_id): array
{
    ensure_prerequisite_tables($pdo);

    $stmt = $pdo->prepare("
        SELECT id, prereq_name, file_path, file_size, original_name, file_hash, uploaded_at
        FROM request_attachments
        WHERE request_id = ?
        ORDER BY prereq_name
    ");
    $stmt->execute([$request_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Auto-heal records affected by the PNG→JPG compression bug:
    // If file_size is 0 or the stored .png path doesn't exist but the .jpg does, fix it.
    $updateStmt = null;
    $projectRoot = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;

    foreach ($rows as &$row) {
        $absPath = $projectRoot . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $row['file_path']);
        $needsUpdate = false;

        // Check if stored path doesn't exist but .jpg variant does (PNG was compressed to JPG)
        if (!file_exists($absPath) && preg_match('/\.png$/i', $absPath)) {
            $jpgPath = preg_replace('/\.png$/i', '.jpg', $absPath);
            if (file_exists($jpgPath)) {
                $row['file_path'] = preg_replace('/\.png$/i', '.jpg', $row['file_path']);
                $absPath = $jpgPath;
                $needsUpdate = true;
            }
        }

        // Fix file_size if it's 0 but the file exists on disk
        if ((int)$row['file_size'] <= 0 && file_exists($absPath)) {
            $row['file_size'] = filesize($absPath);
            $needsUpdate = true;
        }

        // Persist the correction so we don't re-check every time
        if ($needsUpdate) {
            if (!$updateStmt) {
                $updateStmt = $pdo->prepare("UPDATE request_attachments SET file_path = ?, file_size = ? WHERE id = ?");
            }
            try {
                $updateStmt->execute([$row['file_path'], $row['file_size'], $row['id']]);
            } catch (PDOException $e) {
                error_log('Failed to auto-heal attachment #' . $row['id'] . ': ' . $e->getMessage());
            }
        }
    }
    unset($row);

    return $rows;
}

/**
 * Save an attachment record.
 */
function save_attachment(PDO $pdo, int $request_id, string $prereq_name, string $file_path, int $file_size, string $original_name, string $file_hash): void
{
    ensure_prerequisite_tables($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO request_attachments (request_id, prereq_name, file_path, file_size, original_name, file_hash)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$request_id, $prereq_name, $file_path, $file_size, $original_name, $file_hash]);
}

function request_attachment_duplicate_exists(PDO $pdo, int $request_id, string $prereq_name, string $file_hash): bool
{
    ensure_prerequisite_tables($pdo);

    if ($file_hash === '') {
        return false;
    }

    $stmt = $pdo->prepare("SELECT 1 FROM request_attachments WHERE request_id = ? AND prereq_name = ? AND file_hash = ? LIMIT 1");
    $stmt->execute([$request_id, $prereq_name, $file_hash]);
    return (bool) $stmt->fetchColumn();
}

function get_duplicate_attachment_history(PDO $pdo, string $file_hash, int $exclude_request_id = 0): array
{
    ensure_prerequisite_tables($pdo);

    if ($file_hash === '') {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT r.request_id, r.document_type, COALESCE(u.barangay, '') AS barangay, a.prereq_name, a.uploaded_at
        FROM request_attachments a
        JOIN requests r ON r.request_id = a.request_id
        LEFT JOIN users u ON u.user_id = r.user_id
        WHERE a.file_hash = ? AND a.request_id <> ?
        ORDER BY a.uploaded_at DESC
        LIMIT 20
    ");
    $stmt->execute([$file_hash, $exclude_request_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_doc_settings_cache_version(): int
{
    $file = __DIR__ . '/../cache/doc_settings_version.txt';
    if (!file_exists($file)) {
        @file_put_contents($file, '1');
        return 1;
    }
    $val = (int) trim((string) @file_get_contents($file));
    return $val > 0 ? $val : 1;
}

function bump_doc_settings_cache_version(): void
{
    $file = __DIR__ . '/../cache/doc_settings_version.txt';
    $current = get_doc_settings_cache_version();
    @file_put_contents($file, (string) ($current + 1));
}

/**
 * Enable/disable PWD-Senior 20% discount availability for a document type.
 * This is separate from prerequisite uploads — it's a per-document toggle,
 * not a text/file requirement.
 */
function set_pwd_senior_enabled(PDO $pdo, string $document_type, bool $enabled): bool
{
    ensure_prerequisite_tables($pdo);
    $documentType = trim($document_type);
    $pdo->prepare("UPDATE document_prerequisites SET pwd_senior_enabled = ? WHERE document_type = ?")
        ->execute([$enabled ? 1 : 0, $documentType]);
    return true;
}

function get_pwd_senior_enabled(PDO $pdo, string $document_type): bool
{
    ensure_prerequisite_tables($pdo);
    $stmt = $pdo->prepare("SELECT MAX(pwd_senior_enabled) FROM document_prerequisites WHERE document_type = ?");
    $stmt->execute([trim($document_type)]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Set how many months a document remains valid after being issued
 * (Completed). 0 means it never expires.
 */
function set_document_validity(PDO $pdo, string $document_type, int $months): bool
{
    ensure_prerequisite_tables($pdo);
    $documentType = trim($document_type);
    $months = max(0, $months);
    $pdo->prepare("UPDATE document_prerequisites SET validity_months = ? WHERE document_type = ?")
        ->execute([$months, $documentType]);
    return true;
}

function get_document_validity(PDO $pdo, string $document_type): int
{
    ensure_prerequisite_tables($pdo);
    $stmt = $pdo->prepare("SELECT MAX(validity_months) FROM document_prerequisites WHERE document_type = ?");
    $stmt->execute([trim($document_type)]);
    return (int) $stmt->fetchColumn();
}