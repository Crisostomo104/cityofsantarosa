<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/prerequisites.php';
require_once __DIR__ . '/../core/request_actions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, request_id, file_path FROM request_attachments WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$att = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$att) {
    echo json_encode(['success' => false, 'error' => 'Attachment not found']);
    exit;
}

$requestId = (int)$att['request_id'];

// determine request owner + current status
$stmt2 = $pdo->prepare('SELECT user_id, status FROM requests WHERE request_id = ? LIMIT 1');
$stmt2->execute([$requestId]);
$req = $stmt2->fetch(PDO::FETCH_ASSOC);
$ownerId = $req['user_id'] ?? null;
$requestStatus = $req['status'] ?? '';

$currentUser = (int)$_SESSION['user_id'];

// allow if owner or employee/admin
$stmtUser = $pdo->prepare('SELECT user_type FROM users WHERE user_id = ? LIMIT 1');
$stmtUser->execute([$currentUser]);
$u = $stmtUser->fetch(PDO::FETCH_ASSOC);
$userType = $u['user_type'] ?? '';

// Residents may no longer delete their own uploaded prerequisites —
// once submitted, only barangay employees/admins manage attachments.
// This prevents a resident from stripping a required document off a
// request that's already being processed.
if (!in_array($userType, ['employee', 'city_administrator'], true)) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$filePath = __DIR__ . '/..' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $att['file_path']);

// Remove DB record first, then unlink file
try {
    $pdo->beginTransaction();
    $del = $pdo->prepare('DELETE FROM request_attachments WHERE id = ?');
    $del->execute([$id]);
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Failed to remove attachment']);
    exit;
}

if (file_exists($filePath)) {
    @unlink($filePath);
    // attempt to remove parent dir if empty
    $parent = dirname($filePath);
    if (is_dir($parent)) {
        $files = array_diff(scandir($parent), ['.','..']);
        if (empty($files)) @rmdir($parent);
    }
}

// Auto-reject: a prerequisite document is required for the request to be
// valid, so removing one — whether by the resident themselves or an
// employee reviewing it — invalidates the request. Skip this if the
// request is ready to be claimed, Completed, or Rejected, since those are
// final states we shouldn't overwrite.
$autoRejected = false;
$statusLower = strtolower($requestStatus);
if (!in_array($statusLower, ['to be claim', 'completed', 'rejected'], true)) {
    $rejectResult = reject_request_core($pdo, $requestId, 'Removed Document', $currentUser, 'Attachment Removed (Auto-Rejected)');
    $autoRejected = (bool)($rejectResult['success'] ?? false);
}

echo json_encode(['success' => true, 'auto_rejected' => $autoRejected]);
exit;