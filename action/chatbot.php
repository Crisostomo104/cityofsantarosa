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

$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    echo json_encode(["success" => false, "type" => "error", "reply" => "Message is empty."]);
    exit;
}

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
        "success" => true,
        "type"    => "rate_limit",
        "reply"   => "⏳ You're sending messages too fast! Please wait a moment before trying again."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| ENSURE DEFAULT RESPONSES EXIST IN DATABASE
|--------------------------------------------------------------------------
*/
$defaults = [
    ['track,track my request,check my request,request status,check status,my request',
     "To track your request, paste your tracking code here and I'll look it up! 🔍\n\nOr use the \"Track My Request\" box on the homepage — no login required."],
    ['hello,hi,hey',
     "Hello 👋 I'm Jazmine, your Barangay Assistant. How can I help you today?"],
    ['register,sign up,create account',
     "To register, click the \"Get Started\" button on the homepage. Fill in your details and you'll be ready to submit inquiries right away! 📝"],
    ['login,log in',
     "You can log in using your registered email and password. Click the \"Login\" button on the homepage. 🔐"],
    ['forgot password,reset password',
     "No worries! 👍 Please enter your email address so I can send you password reset instructions. 📩"],
    ['contact,barangay,office',
     "For official concerns, you may visit the barangay office directly or submit an inquiry through eRequest Mo. 🏛️"],
    ['barangay clearance,clearance requirement',
     "For a <b>Barangay Clearance</b>, you'll need:\n• Valid ID\n• Cedula\n• Valid ID with Address"],
    ['certificate of indigency,indigency requirement',
     "For a <b>Certificate of Indigency</b>, you'll need:\n• Valid ID\n• Valid ID with Address\n• Letter Request"],
    ['business permit,permit requirement',
     "For a <b>Business Permit</b>, you'll need:\n• DTI Registration\n• Valid ID\n• Barangay Clearance\n• Business Location Proof"],
    ['residency certificate,residency requirement',
     "For a <b>Residency Certificate</b>, you'll need:\n• Valid ID\n• Valid ID with Address"],
    ['office hours,what time,schedule,when open',
     "🕐 <b>Office Hours:</b>\nMon – Fri: 8:00 AM – 5:00 PM\nSaturday: 8:00 AM – 12:00 PM\nSunday & Holidays: Closed\n\nYou can submit requests through eRequest Mo anytime! 😊"],
];

try {
    foreach ($defaults as [$kw, $rep]) {
        $pdo->prepare("
            INSERT INTO chatbot_responses (keywords, reply)
            SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM chatbot_responses WHERE keywords = ?)
        ")->execute([$kw, $rep, $kw]);
    }
} catch (PDOException $e) { /* skip */ }

/*
|--------------------------------------------------------------------------
| TRACKING CODE DETECTION
|--------------------------------------------------------------------------
*/
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

        if (!$req) {
            $parts = explode('-', $code);
            $reqId = (int)end($parts);
            if ($reqId > 0) {
                $stmt = $pdo->prepare("
                    SELECT r.tracking_code, r.document_type, r.status, r.created_at, r.payment_status, u.barangay
                    FROM requests r JOIN users u ON r.user_id = u.user_id
                    WHERE r.request_id = ? LIMIT 1
                ");
                $stmt->execute([$reqId]);
                $req = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        if ($req) {
            $type  = 'tracking';
            $date  = date('M d, Y', strtotime($req['created_at']));
            $brgy  = ucwords(strtolower($req['barangay'] ?? 'N/A'));
            $emoji = match(strtolower($req['status'])) {
                'pending'   => '🕐', 'approved'  => '✅',
                'completed' => '🎉', 'rejected'  => '❌', default => '📋'
            };
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
| KEYWORD MATCH FROM DB
|--------------------------------------------------------------------------
*/
if ($reply === null) {
    try {
        $rows = $pdo->query("SELECT keywords, reply FROM chatbot_responses ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            foreach (explode(',', $row['keywords']) as $kw) {
                $kw = trim(strtolower($kw));
                if (!empty($kw) && strpos($lower, $kw) !== false) {
                    $reply = $row['reply'];
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
    if (str_contains($lower, 'clearance'))    $reply = "For a <b>Barangay Clearance</b>:\n• Valid ID\n• Cedula\n• Valid ID with Address";
    elseif (str_contains($lower, 'indigency'))$reply = "For a <b>Certificate of Indigency</b>:\n• Valid ID\n• Valid ID with Address\n• Letter Request";
    elseif (str_contains($lower, 'business')) $reply = "For a <b>Business Permit</b>:\n• DTI Registration\n• Valid ID\n• Barangay Clearance\n• Business Location Proof";
    elseif (str_contains($lower, 'residency'))$reply = "For a <b>Residency Certificate</b>:\n• Valid ID\n• Valid ID with Address";
    elseif (str_contains($lower, 'hours') || str_contains($lower, 'open') || str_contains($lower, 'schedule'))
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

echo json_encode(["success" => true, "type" => $type, "reply" => $reply]);
exit;
