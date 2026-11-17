<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../core/google_drive.php';

header('Location: ' . google_create_authorization_url());
exit;
