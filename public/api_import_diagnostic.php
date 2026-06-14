<?php
/**
 * Diagnostic API to check import status
 */
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$import_id = intval($_GET['id'] ?? 0);
if (!$import_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing import ID']);
    exit;
}

try {
    $importer = new ExcelImporter();
    
    // Test 1: Get import file info
    $query = "SELECT id, file_name, original_name, upload_date FROM imported_files WHERE id = ?";
    require_once __DIR__ . '/../config/Database.php';
    $db = new Database();
    $conn = $db->connect();
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $import_id);
    $stmt->execute();
    $import_info = $stmt->get_result()->fetch_assoc();
    
    if (!$import_info) {
        echo json_encode(['success' => false, 'message' => 'Import not found']);
        exit;
    }
    
    // Test 2: Get column info
    $columns = $importer->getImportColumns($import_id);
    
    // Test 3: Get data count
    $count = $importer->getImportDataCount($import_id);
    
    // Test 4: Get sample data
    $sample_data = $importer->getImportedData($import_id, 5, 0);
    $sample_rows = [];
    if ($sample_data && $sample_data['data']) {
        while ($row = $sample_data['data']->fetch_assoc()) {
            $sample_rows[] = $row;
        }
    }
    
    // Test 5: Check table status
    $detection = $sample_data['detection'] ?? [];
    $table_name = $detection['config']['table'] ?? 'generic_hdc_data';
    
    $table_check = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE()";
    $stmt = $conn->prepare($table_check);
    $stmt->bind_param("s", $table_name);
    $stmt->execute();
    $table_exists = $stmt->get_result()->fetch_assoc()['count'] > 0;
    
    echo json_encode([
        'success' => true,
        'import_info' => $import_info,
        'column_count' => count($columns),
        'columns' => $columns,
        'total_rows' => $count,
        'table_name' => $table_name,
        'table_exists' => $table_exists,
        'sample_data_count' => count($sample_rows),
        'sample_data' => array_slice($sample_rows, 0, 3),
        'detected_type' => $detection['type'] ?? 'unknown'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
