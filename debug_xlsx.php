<?php
/**
 * Debug XLSX reading
 */
require_once __DIR__ . '/src/Import/ExcelImporter.php';

// Test file path
$test_file = __DIR__ . '/uploads/s_tmp_drug_opd.xlsx';

if (!file_exists($test_file)) {
    echo "File not found: $test_file\n";
    exit;
}

// Create importer
$importer = new ExcelImporter();

// Read the file using reflection to access private method
$reflection = new ReflectionClass($importer);
$readXLSX = $reflection->getMethod('readXLSX');
$readXLSX->setAccessible(true);

// Call readXLSX
$result = $readXLSX->invoke($importer, $test_file, '');

echo "=== XLSX Read Result ===\n\n";
echo "Success: " . ($result['success'] ? "YES" : "NO") . "\n";

if (!$result['success']) {
    echo "Error: " . $result['message'] . "\n";
    exit;
}

$data = $result['data'];

echo "Total rows: " . count($data) . "\n";
echo "\n=== First Row (Headers) ===\n";
if (!empty($data)) {
    $first_row = reset($data);
    echo "Column Count: " . count(array_keys($first_row)) . "\n";
    echo "Columns: " . implode(", ", array_keys($first_row)) . "\n";
}

echo "\n=== Sample Data (First 3 rows) ===\n";
foreach (array_slice($data, 0, 3) as $index => $row) {
    echo "\nRow " . ($index + 1) . ":\n";
    foreach ($row as $col => $val) {
        echo "  $col: " . substr($val, 0, 50) . (strlen($val) > 50 ? "..." : "") . "\n";
    }
}

echo "\n=== Column Analysis ===\n";
if (!empty($data)) {
    $first_row = reset($data);
    foreach (array_keys($first_row) as $index => $col) {
        echo "Col $index: $col\n";
    }
}
?>
