<?php
/**
 * Diagnostic API for RxMuk
 * Helps identify why data might be missing
 */

require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

header('Content-Type: application/json');

try {
    $importer = new ExcelImporter();
    $conn = $importer->getConnection();
    
    // 1. Check imported_files
    $files_res = $conn->query("SELECT * FROM imported_files ORDER BY upload_date DESC LIMIT 10");
    $files = [];
    while ($f = $files_res->fetch_assoc()) {
        $import_id = $f['id'];
        
        // Count in raw table (if possible)
        $raw_count = 0;
        $table_name = '';
        if (stripos($f['original_name'], 's_tmp') !== false) $table_name = 's_drug_opd';
        else if (stripos($f['original_name'], 'drug_opd') !== false) $table_name = 'drug_opd';
        
        if ($table_name) {
            $count_res = $conn->query("SELECT COUNT(*) as cnt FROM `$table_name` WHERE import_id = $import_id");
            if ($count_res) {
                $row = $count_res->fetch_assoc();
                $raw_count = $row['cnt'];
            }
        }
        
        // Check imported_files_data
        $json_res = $conn->query("SELECT LENGTH(data_json) as len FROM imported_files_data WHERE import_id = $import_id");
        $json_len = 0;
        if ($json_res && $json_res->num_rows > 0) {
            $row = $json_res->fetch_assoc();
            $json_len = $row['len'];
        }

        $files[] = [
            'id' => $import_id,
            'name' => $f['original_name'],
            'date' => $f['upload_date'],
            'table' => $table_name,
            'db_rows' => $raw_count,
            'json_bytes' => $json_len
        ];
    }

    echo json_encode([
        'success' => true,
        'diagnostics' => [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'latest_files' => $files
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
