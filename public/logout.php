<?php
// Ensure session is available before including Auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../src/Auth/Auth.php';

$auth = new Auth();
$result = $auth->logout();

// Clear any output buffering before redirect
if (ob_get_level()) {
    ob_end_clean();
}

header('Location: index.php');
exit;
