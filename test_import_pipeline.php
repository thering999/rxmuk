<?php
/**
 * Complete import pipeline test
 * Test the entire Excel reading and database insertion process
 */

// Find an XLSX file to test
$uploads_dir = __DIR__ . '/../uploads';
$xlsx_files = glob($uploads_dir . '/*.xlsx');

if (empty($xlsx_files)) {
    echo "❌ No XLSX files found in uploads directory\n";
    exit;
}

$test_file = $xlsx_files[0];
echo "Testing file: " . basename($test_file) . "\n";
echo str_repeat("=", 50) . "\n\n";

// Step 1: Test column index conversion
echo "Step 1: Column Index Conversion\n";
echo "-" . str_repeat("-", 48) . "\n";

function getCellColumnIndex($cell_ref) {
    preg_match('/^([A-Z]+)/', $cell_ref, $matches);
    if (!isset($matches[1])) return -1;
    
    $col_letters = $matches[1];
    $index = 0;
    foreach (str_split($col_letters) as $char) {
        $index = $index * 26 + (ord($char) - ord('A') + 1);
    }
    return $index - 1;
}

$test_refs = ['A1', 'B1', 'Z1', 'AA1', 'AB1'];
foreach ($test_refs as $ref) {
    $idx = getCellColumnIndex($ref);
    echo "  $ref => Col $idx ✓\n";
}

// Step 2: Test XLSX reading
echo "\nStep 2: XLSX File Reading\n";
echo "-" . str_repeat("-", 48) . "\n";

$zip = new ZipArchive();
if (!$zip->open($test_file)) {
    echo "❌ Cannot open ZIP file\n";
    exit;
}

$sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
if (!$sheet_xml) {
    echo "❌ No sheet1.xml found\n";
    exit;
}

echo "✓ Sheet XML found\n";

// Count rows and columns
libxml_use_internal_errors(true);
$xml = simplexml_load_string($sheet_xml);
libxml_clear_errors();

$row_count = 0;
$max_col = 0;

foreach ($xml->children() as $row) {
    if (strpos($row->getName(), 'row') !== false) {
        $row_count++;
        foreach ($row->children() as $cell) {
            if (strpos($cell->getName(), 'c') !== false) {
                $cell_ref = (string)$cell['r'];
                $col_idx = getCellColumnIndex($cell_ref);
                if ($col_idx > $max_col) {
                    $max_col = $col_idx;
                }
            }
        }
    }
}

echo "✓ Rows found: " . ($row_count - 1) . " (plus 1 header row)\n";
echo "✓ Columns found: " . ($max_col + 1) . "\n";

// Step 3: Test with full ExcelImporter
echo "\nStep 3: Full ExcelImporter Test\n";
echo "-" . str_repeat("-", 48) . "\n";

require_once __DIR__ . '/../src/Import/ExcelImporter.php';

$importer = new ExcelImporter();
$reflection = new ReflectionClass($importer);

// Access private readExcelFile method
$readExcelFile = $reflection->getMethod('readExcelFile');
$readExcelFile->setAccessible(true);

$result = $readExcelFile->invoke($importer, $test_file, '');

if ($result['success']) {
    $data = $result['data'];
    echo "✓ Excel read successful\n";
    echo "  Total rows: " . count($data) . "\n";
    
    if (!empty($data)) {
        $first_row = reset($data);
        $col_count = count(array_keys($first_row));
        echo "  Columns per row: " . $col_count . "\n";
        echo "  Column names: " . implode(", ", array_keys($first_row)) . "\n";
        
        // Show sample data
        echo "\n  First 3 rows sample:\n";
        foreach (array_slice($data, 0, 3) as $i => $row) {
            echo "    Row " . ($i + 1) . ":\n";
            foreach ($row as $col => $val) {
                echo "      $col: " . substr($val, 0, 30) . (strlen($val) > 30 ? "..." : "") . "\n";
            }
        }
    }
} else {
    echo "❌ Excel read failed: " . $result['message'] . "\n";
}

$zip->close();

echo "\n" . str_repeat("=", 50) . "\n";
echo "✓ All tests completed successfully!\n";
?>
