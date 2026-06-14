<?php
/**
 * Test tm drug_opd import with logging
 */

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/Import/ExcelImporter.php';
require_once __DIR__ . '/src/HDC/HDCFileHandler.php';

echo "=== IMPORT SIMULATION WITH LOGGING ===\n\n";

// Use the last tmp_drug_opd.xlsx file
$uploads_dir = __DIR__ . '/uploads/';
$files = glob($uploads_dir . '*tmp_drug_opd.xlsx');
$tmp_file = null;

foreach ($files as $file) {
    if (strpos(basename($file), 's_tmp_drug') === false) {
        $tmp_file = $file;
        break;
    }
}

if ($tmp_file === null) {
    echo "No tmp_drug_opd.xlsx found\n";
    exit;
}

echo "Test file: " . basename($tmp_file) . "\n";
echo "File size: " . filesize($tmp_file) . " bytes\n\n";

// Create ExcelImporter and test readXLSX
$importer = new ExcelImporter();

// Manually call readExcelFile
$reflection = new ReflectionClass($importer);
$method = $reflection->getMethod('readExcelFile');
$method->setAccessible(true);

echo "Reading XLSX...\n";
$read_result = $method->invoke($importer, $tmp_file, '');

if ($read_result['success']) {
    echo "✓ XLSX read successful\n";
    echo "  Rows read: " . count($read_result['data']) . "\n";
    
    if (count($read_result['data']) > 0) {
        echo "  Columns in first row: " . count($read_result['data'][0]) . "\n";
        echo "  Column names: " . implode(", ", array_keys($read_result['data'][0])) . "\n";
        echo "\n  Sample first row data:\n";
        foreach (array_slice($read_result['data'][0], 0, 5) as $col => $val) {
            echo "    $col => " . substr($val, 0, 30) . (strlen($val) > 30 ? "..." : "") . "\n";
        }
    }
} else {
    echo "✗ XLSX read failed: " . $read_result['message'] . "\n";
    exit;
}

// Now test storeImportedData
echo "\n\nTesting storeImportedData...\n";

$storeMethod = $reflection->getMethod('storeImportedData');
$storeMethod->setAccessible(true);

// Simulate parameters
$saved_name = 'test_import_' . time() . '_tmp_drug_opd.xlsx';
$original_name = 'tmp_drug_opd.xlsx';
$user_id = 3;

echo "Parameters:\n";
echo "  Saved name: $saved_name\n";
echo "  Original name: $original_name\n";
echo "  User ID: $user_id\n";
echo "  Data rows: " . count($read_result['data']) . "\n\n";

$store_result = $storeMethod->invoke($importer, $saved_name, $original_name, $user_id, $read_result['data']);

echo "Store result:\n";
echo "  Success: " . ($store_result['success'] ? "Yes" : "No") . "\n";
echo "  Message: " . ($store_result['message'] ?? 'N/A') . "\n";
echo "  Import ID: " . ($store_result['import_id'] ?? 'N/A') . "\n";
echo "  Inserted rows: " . ($store_result['inserted_rows'] ?? 'N/A') . "\n";
echo "  Failed rows: " . ($store_result['failed_rows'] ?? 'N/A') . "\n";

if (isset($store_result['detection'])) {
    echo "  Detection: " . $store_result['detection']['type'] . "\n";
    echo "  Table: " . $store_result['detection']['config']['table'] . "\n";
}

if (!empty($store_result['errors'])) {
    echo "\nFirst 5 errors:\n";
    foreach ($store_result['errors'] as $err) {
        echo "  - " . $err . "\n";
    }
}

echo "\n=== TEST COMPLETE ===\n";
?>
