<?php
/*
 * POST /action/save_cb_response.php
 * Save or delete chatbot menu buttons / responses from admin UI.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/chatbot.php';

check_login_redirect(['city_administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['admin_error'] = 'Invalid request method.';
    header('Location: ../dashboards/city_administrator?panel=chatbot');
    exit;
}

csrf_verify();

$action      = $_POST['response_action'] ?? 'save';
$response_id = isset($_POST['response_id']) ? (int) $_POST['response_id'] : 0;

try {
    if ($action === 'delete' && $response_id > 0) {
        delete_chatbot_response($pdo, $response_id);
        log_action($pdo, $_SESSION['user_id'], 'Chatbot Response Deleted', "ID: $response_id", null);
        $_SESSION['admin_success'] = 'Chatbot button deleted successfully.';

    } elseif ($action === 'save') {
        $label              = trim($_POST['label'] ?? '');
        $intentCodeInput    = trim($_POST['intent_code'] ?? '');
        $parentIntent       = trim($_POST['parent_intent'] ?? '');
        $replyMode          = ($_POST['reply_mode'] ?? 'custom') === 'document' ? 'document' : 'custom';
        $reply              = trim($_POST['reply'] ?? '');
        $linkedDocumentType = trim($_POST['linked_document_type'] ?? '');
        $keywords           = trim($_POST['keywords'] ?? '');
        $sortOrder          = (int) ($_POST['sort_order'] ?? 0);
        $isActive           = isset($_POST['is_active']) ? 1 : 0;

        if ($label === '') {
            $_SESSION['admin_error'] = 'Button label is required.';
        } elseif ($replyMode === 'document' && $linkedDocumentType === '') {
            $_SESSION['admin_error'] = 'Please choose a document type to link.';
        } elseif ($replyMode === 'custom' && $reply === '') {
            $_SESSION['admin_error'] = 'Reply text is required.';
        } else {
            if ($replyMode === 'document') {
                $reply = ''; // ignored — generated live from prerequisites at chat time
            } else {
                $linkedDocumentType = '';
            }

            $savedId = save_chatbot_response($pdo, $response_id > 0 ? $response_id : null, [
                'label'                => $label,
                'intent_code'          => $intentCodeInput,
                'parent_intent'        => $parentIntent,
                'reply'                => $reply,
                'linked_document_type' => $linkedDocumentType,
                'keywords'             => $keywords,
                'sort_order'           => $sortOrder,
                'is_active'            => $isActive,
            ]);

            log_action($pdo, $_SESSION['user_id'], 'Chatbot Response Saved', "ID: $savedId, Label: $label", null);
            $_SESSION['admin_success'] = $response_id > 0 ? 'Chatbot button updated successfully.' : 'Chatbot button added successfully.';
        }
    } else {
        $_SESSION['admin_error'] = 'Invalid chatbot response action.';
    }
} catch (PDOException $e) {
    error_log('[Chatbot Response] ' . $e->getMessage());
    $_SESSION['admin_error'] = 'Unable to save chatbot response. Please try again.';
}

header('Location: ../dashboards/city_administrator?panel=chatbot');
exit;