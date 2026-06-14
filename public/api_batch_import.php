<?php
/**
 * Batch Import API
 * Endpoint for monitoring and managing batch imports
 * Access: api_batch_import.php?action=status|list|delete
 */

require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!Auth::isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'list';
$user_id = Auth::getUserId();

$importer = new ExcelImporter();
$response = ['success' => false, 'message' => 'Unknown action'];

switch ($action) {
    case 'list':
        // Get list of all imports for user
        $imports = $importer->getImportedFiles($user_id, 100);
        $response = [
            'success' => true,
            'imports' => $imports,
            'total' => count($imports)
        ];
        break;

    case 'detail':
        // Get details of a specific import
        $import_id = intval($_GET['id'] ?? 0);
        if ($import_id > 0) {
            $data = $importer->getImportedData($import_id, 1, 0);
            if ($data) {
                $response = [
                    'success' => true,
                    'import_info' => $data['import_info'],
                    'detection' => $data['detection'],
                    'row_count' => $importer->getImportDataCount($import_id),
                    'columns' => $importer->getImportColumns($import_id)
                ];
            }
        }
        break;

    case 'statistics':
        // Get statistics about imports
        $imports = $importer->getImportedFiles($user_id, PHP_INT_MAX);
        
        $stats = [
            'total_imports' => count($imports),
            'total_rows' => 0,
            'file_types' => [],
            'latest_imports' => []
        ];

        foreach ($imports as $import) {
            $stats['total_rows'] += $import['row_count'] ?? 0;
            
            $type = $import['file_type'] ?? 'unknown';
            if (!isset($stats['file_types'][$type])) {
                $stats['file_types'][$type] = 0;
            }
            $stats['file_types'][$type]++;
        }

        // Get latest 5 imports
        $stats['latest_imports'] = array_slice($imports, 0, 5);

        $response = [
            'success' => true,
            'statistics' => $stats
        ];
        break;

    case 'export_status':
        // Get batch export status
        $batch_results = $_SESSION['batch_results'] ?? [];
        
        $stats = [
            'success_count' => 0,
            'failed_count' => 0,
            'results' => $batch_results
        ];

        foreach ($batch_results as $result) {
            if ($result['success']) {
                $stats['success_count']++;
            } else {
                $stats['failed_count']++;
            }
        }

        $response = [
            'success' => true,
            'batch_status' => $stats
        ];
        break;

    case 'supported_types':
        // Get list of supported HDC types
        $types = $importer->getSupportedHDCTypes();
        $response = [
            'success' => true,
            'supported_types' => $types
        ];
        break;

    case 'delete':
        // Delete an import (requires admin or owner)
        $import_id = intval($_POST['id'] ?? 0);
        if ($import_id > 0) {
            require_once __DIR__ . '/../config/Database.php';
            $db = new Database();
            $conn = $db->connect();
            
            // Verify user owns this import or is admin
            $query = "SELECT user_id FROM imported_files WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $import_id);
            $stmt->execute();
            
            $result = $stmt->get_result()->fetch_assoc();
            if ($result && ($result['user_id'] == $user_id || Auth::isAdmin())) {
                // Delete from database
                $delete_query = "DELETE FROM imported_files WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->bind_param("i", $import_id);
                
                if ($delete_stmt->execute()) {
                    // Attempt to remove uploaded file from disk
                    if (!empty($import_info['file_name'])) {
                        $filePath = __DIR__ . '/../uploads/' . $import_info['file_name'];
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }

                    $response = [
                        'success' => true,
                        'message' => 'ลบข้อมูลสำเร็จ'
                    ];
                }
            } else {
                http_response_code(403);
                $response = [
                    'success' => false,
                    'message' => 'คุณไม่มีสิทธิ์ลบข้อมูลนี้'
                ];
            }
        }
        break;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
