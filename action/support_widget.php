<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/chatbot.php';

header('Content-Type: application/json');

$chatbot_settings = get_chatbot_settings($pdo);
if (($chatbot_settings['chatbot_enabled'] ?? '1') !== '1') {
    echo json_encode([
        'success' => false,
        'type'    => 'disabled',
        'reply'   => $chatbot_settings['chatbot_disabled_message'] ?? 'The chatbot is temporarily unavailable. Please try again later.'
    ]);
    exit;
}

// Make sure the default menu buttons exist (guests hit this endpoint
// before any admin ever logs in, so seeding can't only live in the
// admin dashboard bootstrap).
seed_default_chatbot_intents($pdo);

/*
|--------------------------------------------------------------------------
| RATE LIMITING — max 10 messages per 60 seconds per session
|--------------------------------------------------------------------------
*/
$now = time();
if (!isset($_SESSION['bot_count']))  $_SESSION['bot_count']  = 0;
if (!isset($_SESSION['bot_window'])) $_SESSION['bot_window'] = $now;

if ($now - $_SESSION['bot_window'] > 60) {
    $_SESSION['bot_count']  = 0;
    $_SESSION['bot_window'] = $now;
}
$_SESSION['bot_count']++;

if ($_SESSION['bot_count'] > 10) {
    echo json_encode([
        "success"       => true,
        "type"          => "rate_limit",
        "reply"         => "⏳ You're sending messages too fast! Please wait a moment before trying again.",
        "quick_replies" => [['label' => '🏠 Main Menu', 'intent' => 'MENU_MAIN']],
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| BUTTON / MENU PATH — tap-driven flow
|--------------------------------------------------------------------------
*/
$intent = trim($_POST['intent'] ?? '');
if ($intent !== '') {
    handle_chatbot_intent($pdo, $intent);
    exit;
}

function handle_chatbot_intent(PDO $pdo, string $intent): void
{
    // Dynamic per-document-type answer — pulled LIVE from
    // document_prerequisites / document_settings, never stored here.
    if (str_starts_with($intent, 'DOC:')) {
        $docType = rawurldecode(substr($intent, 4));
        $reply = build_document_requirement_reply($pdo, $docType);
        respond_chatbot_json($reply, [
            ['label' => '📑 Other Documents', 'intent' => 'DOCUMENTS'],
            ['label' => '🏠 Main Menu',       'intent' => 'MENU_MAIN'],
        ]);
        return;
    }

    if ($intent === 'MENU_MAIN') {
        $settings = get_chatbot_settings($pdo);
        $greeting = $settings['chatbot_greeting'] ?? "Hello 👋 I'm Jazmine, your Barangay Assistant. How can I help you today?";
        respond_chatbot_json($greeting, get_chatbot_menu_buttons($pdo, null));
        return;
    }

    // Documents category: buttons are generated live from whatever
    // document types currently exist in Document Settings.
    if ($intent === 'DOCUMENTS') {
        $row = get_chatbot_intent($pdo, 'DOCUMENTS');
        $reply = $row['reply'] ?? 'Which document are you interested in? Tap an option below:';

        $buttons = [];
        foreach (get_available_document_types($pdo) as $docType) {
            $buttons[] = ['label' => $docType, 'intent' => 'DOC:' . rawurlencode($docType)];
        }
        $buttons[] = ['label' => '🏠 Main Menu', 'intent' => 'MENU_MAIN'];

        respond_chatbot_json($reply, $buttons);
        return;
    }

    // Any other admin-defined button.
    $row = get_chatbot_intent($pdo, $intent);
    if (!$row) {
        respond_chatbot_json(
            "Sorry, I don't recognize that option anymore. Here's the main menu:",
            get_chatbot_menu_buttons($pdo, null)
        );
        return;
    }

    $reply = !empty($row['linked_document_type'])
        ? build_document_requirement_reply($pdo, $row['linked_document_type'])
        : $row['reply'];

    $children = get_chatbot_menu_buttons($pdo, $intent);
    $children[] = ['label' => '🏠 Main Menu', 'intent' => 'MENU_MAIN'];

    respond_chatbot_json($reply, $children);
}

function respond_chatbot_json(string $reply, array $quick_replies): void
{
    echo json_encode([
        'success'       => true,
        'type'          => 'intent',
        'reply'         => $reply,
        'quick_replies' => $quick_replies,
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| TYPED-MESSAGE PATH — tracking codes, forgot-password email,
| free-text keyword fallback (unchanged from before, plus a
| "Main Menu" button now appended for consistency)
|--------------------------------------------------------------------------
*/
$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    echo json_encode(["success" => false, "type" => "error", "reply" => "Message is empty."]);
    exit;
}

$upper = strtoupper(trim($message));
$lower = strtolower($message);
$reply = null;
$type  = 'general';

if (preg_match('/REQ\d{9,}-\d+/', $upper, $m) || preg_match('/SR-[A-Z]{2,4}-\d{8}-\d+/', $upper, $m)) {
    $code = $m[0];
    $req  = null;
    try {
        $stmt = $pdo->prepare("
            SELECT r.tracking_code, r.document_type, r.status, r.created_at, r.payment_status, u.barangay
            FROM requests r JOIN users u ON r.user_id = u.user_id
            WHERE r.tracking_code = ? LIMIT 1
        ");
        $stmt->execute([$code]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        // No exact tracking_code match — do NOT fall back to guessing a
        // request_id from a substring of whatever the user typed. That
        // treated any trailing number as a raw request_id and returned
        // an unrelated (and possibly private) request that happened to
        // share that ID. If it's not an exact tracking_code, it's simply
        // not found.

        if ($req) {
            $type  = 'tracking';
            $date  = date('M d, Y', strtotime($req['created_at']));
            $brgy  = ucwords(strtolower($req['barangay'] ?? 'N/A'));
            $reply = "Here's what I found for <b>{$req['tracking_code']}</b>:\n\n"
                   . "<b>DOCS:</b> {$req['document_type']}\n"
                   . "<b>BRGY:</b> {$brgy}\n"
                   . "<b>DATE:</b> {$date}\n"
                   . "<b>STATUS:</b> {$req['status']}\n"
                   . "<b>PAYMENT:</b> " . ucfirst($req['payment_status'] ?? 'unpaid');
        } else {
            $reply = "I searched for <b>{$code}</b> but couldn't find a matching request. 🔍\n\nPlease double-check the code and try again.";
        }
    } catch (PDOException $e) {
        error_log('[Chatbot] ' . $e->getMessage());
        $reply = "I found a tracking code but ran into a database error. Try the Track My Request box on the homepage. 🙏";
    }
}

/*
|--------------------------------------------------------------------------
| KEYWORD MATCH FROM DB (typed fallback — still reads chatbot_responses)
|--------------------------------------------------------------------------
*/
if ($reply === null) {
    try {
        $rows = $pdo->query("SELECT keywords, reply, linked_document_type FROM chatbot_responses WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            foreach (explode(',', $row['keywords']) as $kw) {
                $kw = trim(strtolower($kw));
                if (!empty($kw) && strpos($lower, $kw) !== false) {
                    $reply = !empty($row['linked_document_type'])
                        ? build_document_requirement_reply($pdo, $row['linked_document_type'])
                        : $row['reply'];
                    break 2;
                }
            }
        }
    } catch (PDOException $e) { /* fall through */ }
}

/*
|--------------------------------------------------------------------------
| HARDCODED FALLBACK
|--------------------------------------------------------------------------
*/
if ($reply === null) {
    if (str_contains($lower, 'hours') || str_contains($lower, 'open') || str_contains($lower, 'schedule'))
        $reply = "🕐 Mon–Fri: 8AM–5PM | Sat: 8AM–12PM | Sun & Holidays: Closed";
    elseif (str_contains($lower, 'track') || str_contains($lower, 'status'))
        $reply = "Paste your tracking code (e.g. <b>REQ20260628115-013</b>) here and I'll check it for you! 🔍";
    elseif (str_contains($lower, 'register') || str_contains($lower, 'sign up'))
        $reply = "Click \"Get Started\" on the homepage to create your account. 📝";
    elseif (str_contains($lower, 'login') || str_contains($lower, 'log in'))
        $reply = "Click \"Login\" on the homepage and enter your email and password. 🔐";
    elseif (str_contains($lower, 'forgot') || str_contains($lower, 'password'))
        $reply = "No worries! 👍 Please enter your email so I can send reset instructions. 📩";
    elseif (str_contains($lower, 'hello') || str_contains($lower, 'hi') || str_contains($lower, 'hey'))
        $reply = "Hello 👋 I'm Jazmine! How can I help you today?";
    else
        $reply = "Sorry, I don't understand that yet. Try asking about tracking, document requirements, office hours, or registering. 😊";
}

echo json_encode([
    "success"       => true,
    "type"          => $type,
    "reply"         => $reply,
    "quick_replies" => [['label' => '🏠 Main Menu', 'intent' => 'MENU_MAIN']],
]);
exit;