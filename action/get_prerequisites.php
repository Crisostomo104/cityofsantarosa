<?php
/*
 * GET /action/get_prerequisites.php?doc=Business+Permit
 * Returns JSON array of prerequisites for a given document type.
 * Called via AJAX when resident selects a document type.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../core/prerequisites.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$doc = trim($_GET['doc'] ?? '');

if (empty($doc)) {
    echo json_encode([]);
    exit;
}

// Seed defaults on first access
seed_default_prerequisites($pdo);

$prereqs = get_prerequisites_for($pdo, $doc);
echo json_encode($prereqs);
