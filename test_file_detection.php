<?php
/**
 * Test File Type Detection
 */

require_once __DIR__ . '/src/HDC/HDCFileHandler.php';

$hdc = new HDCFileHandler();

echo "=== FILE TYPE DETECTION TEST ===\n\n";

// Test both files
$files = [
    'tmp_drug_opd.xlsx',
    's_tmp_drug_opd.xlsx'
];

foreach ($files as $file) {
    echo "File: $file\n";
    
    $detection = $hdc->detectFileType($file);
    
    echo "  Type: " . $detection['type'] . "\n";
    echo "  Table: " . $detection['config']['table'] . "\n";
    echo "  Description: " . $detection['config']['description'] . "\n";
    echo "  Detected by: " . $detection['detected_by'] . "\n";
    echo "  Expected columns: " . implode(", ", array_slice($detection['config']['columns'], 0, 5)) . "...\n";
    echo "\n";
}

// Test with column matching
echo "\n=== WITH COLUMN MATCHING ===\n\n";

$s_drug_columns = ['HOSPCODE', 'AMPHUR', 'DIDSTD', 'DNAME', 'SumAmount', 'Count', 'SumDrugCost', 'SumDrugPrice'];
echo "File: summary file with columns: " . implode(", ", $s_drug_columns) . "\n";
$detection = $hdc->detectFileType('unknown.xlsx', $s_drug_columns);
echo "  Type: " . $detection['type'] . "\n";
echo "  Table: " . $detection['config']['table'] . "\n";
echo "  Detected by: " . $detection['detected_by'] . "\n";

echo "\n=== TEST COMPLETE ===\n";
?>
