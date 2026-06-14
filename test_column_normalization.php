<?php
/**
 * Test Column Name Normalization
 * Verifies that Excel columns are properly sanitized for database storage
 */

require_once __DIR__ . '/src/Import/ExcelImporter.php';

// Simulate the column names from both test files
$file1_columns = [
    'HOSPCODE', 'PID', 'SEQ', 'DATE_SERV', 'CLINIC', 'DIDSTD', 'DNAME', 
    'AMOUNT', 'UNIT', 'UNIT_PACKING', 'DRUGPRICE', 'DRUGCOST', 'PROVIDER', 
    'D_UPDATE', 'CID', 'HDC_DATE', 'nation', 'sex', 'check_hosp', 'check_vhid', 
    'check_typearea', 'vhid', 'typearea', 'drug_name', 'drug_type', 'ed', 'age_y', 
    'instype', 'instypegroup', 'groupcode060'
];

$file2_columns = [
    'HOSPCODE', 'AMPHUR', 'DIDSTD', 'DNAME', 'SumAmount', 'Count', 
    'SumDrugCost', 'SumDrugPrice'
];

// Create an instance of ExcelImporter just to access sanitizeColumnName
$importer = new ExcelImporter();

echo "=== FILE 1 (tmp_drug_opd.xlsx) ===\n";
echo "Total Columns: " . count($file1_columns) . "\n\n";

$normalized_file1 = [];
foreach ($file1_columns as $col) {
    // We can't call private method directly, but we can test the logic
    $sanitized = sanitizeForDatabase($col);
    echo sprintf("%-30s => %-30s\n", $col, $sanitized);
    $normalized_file1[$col] = $sanitized;
}

// Check for duplicates
$unique_count = count(array_unique(array_values($normalized_file1)));
if ($unique_count < count($normalized_file1)) {
    echo "\n⚠️  WARNING: Column name collisions detected!\n";
} else {
    echo "\n✅ All column names are unique after normalization\n";
}

echo "\n\n=== FILE 2 (s_tmp_drug_opd.xlsx) ===\n";
echo "Total Columns: " . count($file2_columns) . "\n\n";

$normalized_file2 = [];
foreach ($file2_columns as $col) {
    $sanitized = sanitizeForDatabase($col);
    echo sprintf("%-30s => %-30s\n", $col, $sanitized);
    $normalized_file2[$col] = $sanitized;
}

$unique_count = count(array_unique(array_values($normalized_file2)));
if ($unique_count < count($normalized_file2)) {
    echo "\n⚠️  WARNING: Column name collisions detected!\n";
} else {
    echo "\n✅ All column names are unique after normalization\n";
}

/**
 * Replicate the sanitization logic used in both ExcelImporter and HDCFileHandler
 */
function sanitizeForDatabase($name) {
    // Remove special characters, keep only alphanumeric and underscore
    $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    // Remove leading numbers
    $name = preg_replace('/^[0-9]+/', '', $name);
    // Limit to 64 characters (MySQL limit)
    return substr($name, 0, 64) ?: 'column_' . md5($name);
}

echo "\n\n=== SUMMARY ===\n";
echo "File 1 unique columns after sanitization: " . count(array_unique(array_values($normalized_file1))) . " / " . count($file1_columns) . "\n";
echo "File 2 unique columns after sanitization: " . count(array_unique(array_values($normalized_file2))) . " / " . count($file2_columns) . "\n";
?>
