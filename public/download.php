<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    http_response_code(403);
    exit('Unauthorized');
}

$import_id = $_GET['id'] ?? 0;
if (!$import_id) {
    http_response_code(400);
    exit('Invalid request');
}

$importer = new ExcelImporter();
$export_data = $importer->exportToArray($import_id);

if (!$export_data) {
    http_response_code(404);
    exit('File not found');
}

$file_info = $export_data['info'];
$data = $export_data['data'];
$detection = $export_data['detection'];

// Generate filename
$file_name_without_ext = preg_replace('/\.(xlsx|xls|csv)$/i', '', $file_info['original_name']);
$download_name = $file_name_without_ext . '_' . date('Ymd_His') . '.csv';

// Setup headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $download_name . '"');

// UTF-8 BOM for Excel to recognize Thai characters
echo "\xEF\xBB\xBF";

// Open output stream
$output = fopen('php://output', 'w');

if (!empty($data)) {
    // Write headers
    $headers = array_keys($data[0]);
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($data as $row) {
        $values = [];
        foreach ($headers as $header) {
            $values[] = $row[$header] ?? '';
        }
        fputcsv($output, $values);
    }
}

fclose($output);
exit;
