<?php
/**
 * API: Get Import History for SPA
 * Returns a list of recently imported files from the database
 */

require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

header('Content-Type: application/json');

try {
    $importer = new ExcelImporter();
    $files = $importer->getImportedFiles(null, 10);
    
    $history = array_map(function($f) {
        return [
            'date' => date('d/m/Y H:i', strtotime($f['upload_date'])),
            'filename' => $f['original_name'],
            'rows' => number_format($f['row_count']) . ' แถว',
            'user' => $f['username'] ?? 'System',
            'type' => $f['file_type']
        ];
    }, $files);

    echo json_encode([
        'success' => true,
        'history' => $history,
        'files_raw' => $files
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
