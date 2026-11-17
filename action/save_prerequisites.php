<?php
/*
 * POST /action/save_prerequisites.php
 * Admin endpoint to add or delete prerequisite rules.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/prerequisites.php';

check_login_redirect(['city_administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboards/city_administrator");
    exit;
}

csrf_verify();

$action = $_POST['prereq_action'] ?? '';

switch ($action) {
    case 'add':
        $doc_type    = trim($_POST['document_type'] ?? '');
        $prereq_name = trim($_POST['prereq_name'] ?? '');
        $is_required = isset($_POST['is_required']) ? 1 : 0;

        if (empty($doc_type) || empty($prereq_name)) {
            $_SESSION['admin_error'] = "Document type and prerequisite name are required.";
            break;
        }

        add_prerequisite($pdo, $doc_type, $prereq_name, $is_required);

        log_action($pdo, $_SESSION['user_id'], 'Prerequisite Added', $doc_type,
            "Added prerequisite '$prereq_name' for '$doc_type'");

        bump_doc_settings_cache_version();
        $_SESSION['last_updated_doc'] = $doc_type;
        $_SESSION['admin_success'] = "Prerequisite '$prereq_name' added to '$doc_type'.";
        break;

    case 'save_document_setting':
        $doc_type = trim($_POST['document_type'] ?? '');
        $price = isset($_POST['price']) ? (float)$_POST['price'] : (float)($_POST['fee_amount'] ?? 0);
        $price = max(0.0, $price);

        if (empty($doc_type)) {
            $_SESSION['admin_error'] = "Document type is required.";
            break;
        }

        upsert_document_setting($pdo, $doc_type, $price);

        log_action($pdo, $_SESSION['user_id'], 'Document Fee Updated', $doc_type,
            "Updated fee setting for '$doc_type' to " . ($price <= 0 ? 'Free' : 'PHP ' . number_format($price, 2)));

        bump_doc_settings_cache_version();
        $_SESSION['last_updated_doc'] = $doc_type;
        $_SESSION['admin_success'] = "Fee setting updated for '$doc_type'.";
        break;

    case 'rename_document_type':
        $old_name = trim($_POST['old_document_type'] ?? '');
        $new_name = trim($_POST['new_document_type'] ?? '');

        if ($old_name === '' || $new_name === '') {
            $_SESSION['admin_error'] = "Both document names are required.";
            break;
        }

        if (rename_document_type($pdo, $old_name, $new_name)) {
            log_action($pdo, $_SESSION['user_id'], 'Document Renamed', $new_name,
                "Renamed document type '$old_name' to '$new_name'");
            bump_doc_settings_cache_version();
            $_SESSION['last_updated_doc'] = $new_name;
            $_SESSION['admin_success'] = "Document type renamed from '$old_name' to '$new_name'.";
        } else {
            $_SESSION['admin_error'] = "Unable to rename document type.";
        }
        break;

    case 'remove_document_type':
        $doc_type = trim($_POST['document_type'] ?? '');
        if ($doc_type === '') {
            $_SESSION['admin_error'] = "Document type is required.";
            break;
        }

        if (remove_document_type($pdo, $doc_type)) {
            log_action($pdo, $_SESSION['user_id'], 'Document Removed', $doc_type,
                "Removed document type '$doc_type'");

            bump_doc_settings_cache_version();

            // If the removed doc was the one highlighted as "recent", clear it
            // so the highlight doesn't point at a document that no longer exists.
            if (($_SESSION['last_updated_doc'] ?? null) === $doc_type) {
                unset($_SESSION['last_updated_doc']);
            }

            $_SESSION['admin_success'] = "Document type '$doc_type' removed.";
        } else {
            $_SESSION['admin_error'] = "Unable to remove document type.";
        }
        break;

    case 'delete':
        $id = intval($_POST['prereq_id'] ?? 0);
        if ($id > 0) {
            // Kunin muna ang document_type bago i-delete, para maihighlight ito
            $stmt = $pdo->prepare("SELECT document_type FROM document_prerequisites WHERE id = ?");
            $stmt->execute([$id]);
            $docTypeForHighlight = $stmt->fetchColumn();

            delete_prerequisite($pdo, $id);

            log_action($pdo, $_SESSION['user_id'], 'Prerequisite Deleted', 'ID: ' . $id,
                "Deleted prerequisite rule #$id");

            bump_doc_settings_cache_version();

            if ($docTypeForHighlight) {
                $_SESSION['last_updated_doc'] = $docTypeForHighlight;
            }

            $_SESSION['admin_success'] = "Prerequisite removed.";
        }
        break;

    // ── NEW: rename + price + add multiple prerequisites in ONE save ──
    case 'save_document_full':
        $originalType = trim($_POST['original_document_type'] ?? '');
        $newType      = trim($_POST['new_document_type'] ?? '');
        $price        = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
        $price        = max(0.0, $price);

        if ($originalType === '' || $newType === '') {
            $_SESSION['admin_error'] = "Document name cannot be empty.";
            break;
        }

        // Rename first (if changed) so price + prereqs below apply to the new name.
        if ($newType !== $originalType) {
            if (!rename_document_type($pdo, $originalType, $newType)) {
                $_SESSION['admin_error'] = "Unable to rename document type.";
                break;
            }
        }

        upsert_document_setting($pdo, $newType, $price);
        set_pwd_senior_enabled($pdo, $newType, isset($_POST['pwd_senior_enabled']));
        set_document_validity($pdo, $newType, (int)($_POST['validity_months'] ?? 0));
        
        $addedCount = 0;
        if (!empty($_POST['new_prereqs']) && is_array($_POST['new_prereqs'])) {
            foreach ($_POST['new_prereqs'] as $row) {
                $name = trim($row['name'] ?? '');
                if ($name === '') continue;
                $isRequired = !empty($row['required']) ? 1 : 0;
                add_prerequisite($pdo, $newType, $name, $isRequired);
                $addedCount++;
            }
        }

        $detail = "Updated '$newType'";
        if ($newType !== $originalType) $detail .= " (renamed from '$originalType')";
        $detail .= ", price: " . ($price <= 0 ? 'Free' : 'PHP ' . number_format($price, 2));
        if ($addedCount > 0) $detail .= ", added $addedCount new prerequisite(s)";

        log_action($pdo, $_SESSION['user_id'], 'Document Updated', $newType, $detail);

        bump_doc_settings_cache_version();
        $_SESSION['last_updated_doc'] = $newType;
        $_SESSION['admin_success'] = "Document '$newType' updated successfully.";
        break;

    // ── NEW: create a document type with price + prerequisites in ONE save ──
    case 'add_document_type_full':
        $docType = trim($_POST['document_type'] ?? '');
        $price   = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
        $price   = max(0.0, $price);

        if ($docType === '') {
            $_SESSION['admin_error'] = "Please provide a document type name.";
            break;
        }

        upsert_document_setting($pdo, $docType, $price);
        set_pwd_senior_enabled($pdo, $docType, isset($_POST['pwd_senior_enabled']));
        set_document_validity($pdo, $docType, (int)($_POST['validity_months'] ?? 0));
        
        $addedCount = 0;
        if (!empty($_POST['new_prereqs']) && is_array($_POST['new_prereqs'])) {
            foreach ($_POST['new_prereqs'] as $row) {
                $name = trim($row['name'] ?? '');
                if ($name === '') continue;
                $isRequired = !empty($row['required']) ? 1 : 0;
                add_prerequisite($pdo, $docType, $name, $isRequired);
                $addedCount++;
            }
        }

        $detail = "Added new document type '$docType', price: " . ($price <= 0 ? 'Free' : 'PHP ' . number_format($price, 2));
        if ($addedCount > 0) $detail .= ", with $addedCount prerequisite(s)";

        log_action($pdo, $_SESSION['user_id'], 'Document Added', $docType, $detail);

        bump_doc_settings_cache_version();
        $_SESSION['last_updated_doc'] = $docType;
        $_SESSION['admin_success'] = "Document type '$docType' added successfully.";
        break;

    default:
        $_SESSION['admin_error'] = "Invalid action.";
}

header("Location: ../dashboards/city_administrator?panel=doc-settings");
exit;