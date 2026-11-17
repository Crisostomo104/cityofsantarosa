<?php
/*
 * Chatbot settings + menu/intent helpers
 */

require_once __DIR__ . '/prerequisites.php';

   ini_set('display_errors', 1);
   error_reporting(E_ALL);

function ensure_chatbot_tables(PDO $pdo): void
{
    if (!empty($_SESSION['schema_checked_chatbot'])) return;
    $_SESSION['schema_checked_chatbot'] = true;

    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $isSqlite = $driver === 'sqlite';

    if ($isSqlite) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS chatbot_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS chatbot_responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            keywords TEXT NOT NULL DEFAULT '',
            reply TEXT NOT NULL DEFAULT '',
            intent_code TEXT,
            label TEXT,
            parent_intent TEXT,
            linked_document_type TEXT,
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS chatbot_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS chatbot_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            keywords TEXT NOT NULL,
            reply TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // ── Migration: add the menu/intent columns to existing installs ──
    $alters = [
        "ALTER TABLE chatbot_responses ADD COLUMN intent_code VARCHAR(60) NULL",
        "ALTER TABLE chatbot_responses ADD COLUMN label VARCHAR(150) NULL",
        "ALTER TABLE chatbot_responses ADD COLUMN parent_intent VARCHAR(60) NULL",
        "ALTER TABLE chatbot_responses ADD COLUMN linked_document_type VARCHAR(100) NULL",
        "ALTER TABLE chatbot_responses ADD COLUMN sort_order INT NOT NULL DEFAULT 0",
        "ALTER TABLE chatbot_responses ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
    ];
    foreach ($alters as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* column already exists */ }
    }

    // Unique index on intent_code (skip rows where it's NULL — MySQL/SQLite both allow multiple NULLs in a unique index)
    try {
        if ($isSqlite) {
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_chatbot_intent_code ON chatbot_responses(intent_code)");
        } else {
            $pdo->exec("ALTER TABLE chatbot_responses ADD UNIQUE KEY uq_intent_code (intent_code)");
        }
    } catch (PDOException $e) { /* already exists */ }
}

/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/

function get_chatbot_settings(PDO $pdo): array
{
    ensure_chatbot_tables($pdo);

    $defaults = [
        'chatbot_enabled' => '1',
        'chatbot_disabled_message' => 'The chatbot is temporarily unavailable. Please try again later.',
        'chatbot_greeting' => "Hello 👋 I'm Jazmine, your Barangay Assistant. How can I help you today?",
        'maintenance_mode' => '0',
        'maintenance_message' => 'The system is currently under maintenance. Only city administrators and employees may log in at this time. Please try again later.',
    ];

    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('chatbot_enabled','chatbot_disabled_message','chatbot_greeting','maintenance_mode','maintenance_message')");
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($row['setting_key'], $row['setting_value'])) {
            $defaults[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $defaults;
}

function save_chatbot_settings(PDO $pdo, bool $enabled, string $disabled_message): void
{
    ensure_chatbot_tables($pdo);

    $disabled_message = trim($disabled_message);
    if ($disabled_message === '') {
        $disabled_message = 'The chatbot is temporarily unavailable. Please try again later.';
    }

    $stmt = $pdo->prepare("REPLACE INTO chatbot_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute(['chatbot_enabled', $enabled ? '1' : '0']);
    $stmt->execute(['chatbot_disabled_message', $disabled_message]);
}

function save_maintenance_settings(PDO $pdo, bool $maintenance_mode, string $maintenance_message): void
{
    ensure_chatbot_tables($pdo);

    $maintenance_message = trim($maintenance_message);
    if ($maintenance_message === '') {
        $maintenance_message = 'The system is currently under maintenance. Only city administrators may log in at this time. Please try again later.';
    }

    $stmt = $pdo->prepare("REPLACE INTO chatbot_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute(['maintenance_mode', $maintenance_mode ? '1' : '0']);
    $stmt->execute(['maintenance_message', $maintenance_message]);
}

function is_chatbot_available(PDO $pdo): bool
{
    $settings = get_chatbot_settings($pdo);
    return ($settings['chatbot_enabled'] ?? '1') === '1' && ($settings['maintenance_mode'] ?? '0') !== '1';
}

/*
|--------------------------------------------------------------------------
| RESPONSES / MENU ITEMS (admin CRUD)
|--------------------------------------------------------------------------
*/

function get_chatbot_responses(PDO $pdo): array
{
    ensure_chatbot_tables($pdo);
    return $pdo->query("
        SELECT id, keywords, reply, intent_code, label, parent_intent,
               linked_document_type, sort_order, is_active
        FROM chatbot_responses
        ORDER BY (parent_intent IS NULL) DESC, parent_intent ASC, sort_order ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function get_chatbot_intent(PDO $pdo, string $intent_code): ?array
{
    ensure_chatbot_tables($pdo);
    $stmt = $pdo->prepare("SELECT * FROM chatbot_responses WHERE intent_code = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$intent_code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Buttons to show for a given parent ('' / null = root/main menu). */
function get_chatbot_menu_buttons(PDO $pdo, ?string $parent): array
{
    ensure_chatbot_tables($pdo);

    if ($parent === null || $parent === '') {
        $stmt = $pdo->prepare("
            SELECT intent_code, label FROM chatbot_responses
            WHERE is_active = 1 AND intent_code IS NOT NULL AND (parent_intent IS NULL OR parent_intent = '')
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT intent_code, label FROM chatbot_responses
            WHERE is_active = 1 AND intent_code IS NOT NULL AND parent_intent = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([$parent]);
    }

    $buttons = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $buttons[] = ['label' => $r['label'], 'intent' => $r['intent_code']];
    }
    return $buttons;
}

function chatbot_slugify(string $label): string
{
    $slug = strtoupper(trim($label));
    $slug = preg_replace('/[^A-Z0-9]+/', '_', $slug);
    $slug = trim($slug, '_');
    return $slug !== '' ? $slug : ('BTN_' . substr(md5($label . microtime()), 0, 8));
}

/**
 * Create or update a chatbot menu button / response.
 * $data keys: label, intent_code, parent_intent, reply, linked_document_type,
 *             keywords, sort_order, is_active
 */
function save_chatbot_response(PDO $pdo, ?int $id, array $data): int
{
    ensure_chatbot_tables($pdo);

    $label       = trim((string)($data['label'] ?? ''));
    $intentCode  = trim((string)($data['intent_code'] ?? ''));
    $intentCode  = $intentCode !== '' ? chatbot_slugify($intentCode) : ($label !== '' ? chatbot_slugify($label) : null);
    $parent      = trim((string)($data['parent_intent'] ?? ''));
    $reply       = trim((string)($data['reply'] ?? ''));
    $linkedDoc   = trim((string)($data['linked_document_type'] ?? ''));
    $keywords    = trim((string)($data['keywords'] ?? ''));
    $sortOrder   = (int)($data['sort_order'] ?? 0);
    $isActive    = !empty($data['is_active']) ? 1 : 0;

    $params = [
        'keywords'             => $keywords,
        'reply'                => $reply,
        'intent_code'          => $intentCode,
        'label'                => $label ?: null,
        'parent_intent'        => $parent !== '' ? $parent : null,
        'linked_document_type' => $linkedDoc !== '' ? $linkedDoc : null,
        'sort_order'           => $sortOrder,
        'is_active'            => $isActive,
    ];

    if ($id && $id > 0) {
        $pdo->prepare("
            UPDATE chatbot_responses SET
                keywords = :keywords, reply = :reply, intent_code = :intent_code,
                label = :label, parent_intent = :parent_intent,
                linked_document_type = :linked_document_type,
                sort_order = :sort_order, is_active = :is_active
            WHERE id = :id
        ")->execute($params + ['id' => $id]);
        return $id;
    }

    $stmt = $pdo->prepare("
        INSERT INTO chatbot_responses
            (keywords, reply, intent_code, label, parent_intent, linked_document_type, sort_order, is_active)
        VALUES
            (:keywords, :reply, :intent_code, :label, :parent_intent, :linked_document_type, :sort_order, :is_active)
    ");
    $stmt->execute($params);
    return (int) $pdo->lastInsertId();
}

function delete_chatbot_response(PDO $pdo, int $id): void
{
    ensure_chatbot_tables($pdo);
    $stmt = $pdo->prepare("DELETE FROM chatbot_responses WHERE id = ?");
    $stmt->execute([$id]);
}

/*
|--------------------------------------------------------------------------
| DEFAULT SEED (menu buttons only — runs once, count-guarded like
| seed_default_prerequisites() in core/prerequisites.php)
|--------------------------------------------------------------------------
*/

function seed_default_chatbot_intents(PDO $pdo): void
{
    ensure_chatbot_tables($pdo);

    $count = (int) $pdo->query("SELECT COUNT(*) FROM chatbot_responses WHERE intent_code IS NOT NULL")->fetchColumn();
    if ($count > 0) return;

    // [intent_code, label, reply, sort_order]
    $defaults = [
        ['TRACK_REQUEST', '📄 Track My Request',
            "To track your request, paste your tracking code here and I'll look it up! 🔍\n\nOr use the \"Track My Request\" box on the homepage — no login required.", 10],
        ['DOCUMENTS', '📑 Document Requirements',
            "Which document are you interested in? Tap an option below:", 20],
        ['REGISTER', '📝 How to Register',
            "To register, click the \"Get Started\" button on the homepage. Fill in your details and you'll be ready to submit inquiries right away! 📝", 30],
        ['LOGIN', '🔐 How to Login',
            "You can log in using your registered email and password. Click the \"Login\" button on the homepage. 🔐", 40],
        ['FORGOT_PASSWORD', '🔑 Forgot Password',
            "No worries! 👍 Please enter your email address so I can send you password reset instructions. 📩", 50],
        ['OFFICE_HOURS', '🕐 Office Hours',
            "🕐 <b>Office Hours:</b>\nMon – Fri: 8:00 AM – 5:00 PM\nSaturday: 8:00 AM – 12:00 PM\nSunday & Holidays: Closed\n\nYou can submit requests through eRequest Mo anytime! 😊", 60],
        ['CONTACT', '🏛️ Contact Barangay',
            "For official concerns, you may visit the barangay office directly or submit an inquiry through eRequest Mo. 🏛️", 70],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO chatbot_responses (keywords, reply, intent_code, label, parent_intent, sort_order, is_active)
        VALUES ('', ?, ?, ?, NULL, ?, 1)
    ");
    foreach ($defaults as [$code, $label, $reply, $order]) {
        $stmt->execute([$reply, $code, $label, $order]);
    }
}

/*
|--------------------------------------------------------------------------
| LIVE DOCUMENT-REQUIREMENT TEXT — sourced straight from
| document_prerequisites / document_settings (core/prerequisites.php),
| never duplicated/stored in chatbot_responses.
|--------------------------------------------------------------------------
*/

function build_document_requirement_reply(PDO $pdo, string $document_type): string
{
    $document_type = trim($document_type);
    $prereqs = get_prerequisites_for($pdo, $document_type);
    $setting = get_document_setting($pdo, $document_type);

    $lines   = [];
    $lines[] = "For a <b>" . htmlspecialchars($document_type) . "</b>, you'll need:";

    if (empty($prereqs)) {
        $lines[] = "• No prerequisites have been configured yet for this document — please check with your barangay.";
    } else {
        foreach ($prereqs as $p) {
            $tag = $p['is_required'] ? '' : ' <i>(optional)</i>';
            $lines[] = "• " . htmlspecialchars($p['prereq_name']) . $tag;
        }
    }

    $fee = $setting['is_free'] ? 'Free' : '₱' . number_format((float) $setting['price'], 2);
    $lines[] = "\n<b>Fee:</b> " . $fee;

    return implode("\n", $lines);
}