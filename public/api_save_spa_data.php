<?php
/**
 * API: Save SPA Data to Server
 * Saves the client-side processed data to a temporary file for persistence
 */

require_once __DIR__ . '/../src/Auth/Auth.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['transactions'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = 'last_spa_data.json';

    // If transactions is empty, delete the cache file
    if (empty($data['transactions'])) {
        if (file_exists($upload_dir . $filename)) {
            unlink($upload_dir . $filename);
        }
        echo json_encode(['success' => true, 'message' => 'Cache cleared']);
        exit;
    }

    if (file_put_contents($upload_dir . $filename, $json)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Data cached on server',
            'count' => count($data['transactions'])
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
