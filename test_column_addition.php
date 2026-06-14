<?php
/**
 * Verify Column Addition to Existing Table
 */

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/HDC/HDCFileHandler.php';

echo "=== COLUMN ADDITION TEST ===\n\n";

$db = new Database();
$conn = $db->connect();

// Get current drug_opd columns
echo "Current drug_opd columns:\n";
$result = $conn->query("SHOW COLUMNS FROM drug_opd");
$current_cols = [];
while ($col = $result->fetch_assoc()) {
    echo "  - " . $col['Field'] . "\n";
    $current_cols[] = $col['Field'];
}

// Required columns from test files
$required_cols = [
    'HOSPCODE', 'PID', 'SEQ', 'DATE_SERV', 'CLINIC', 'DIDSTD', 'DNAME', 
    'AMOUNT', 'UNIT', 'UNIT_PACKING', 'DRUGPRICE', 'DRUGCOST', 'PROVIDER', 
    'D_UPDATE', 'CID', 'HDC_DATE', 'nation', 'sex', 'check_hosp', 'check_vhid', 
    'check_typearea', 'vhid', 'typearea', 'drug_name', 'drug_type', 'ed', 'age_y', 
    'instype', 'instypegroup', 'groupcode060'
];

// Find missing columns
$missing = [];
$current_lower = array_map('strtolower', $current_cols);
foreach ($required_cols as $col) {
    if (!in_array(strtolower($col), $current_lower)) {
        $missing[] = $col;
    }
}

echo "\nMissing columns: " . count($missing) . "\n";
if (!empty($missing)) {
    echo "  " . implode(", ", $missing) . "\n";
}

// Test the createDynamicTable method
echo "\n\nTesting HDCFileHandler::createDynamicTable()...\n";
$hdc = new HDCFileHandler();
$result = $hdc->createDynamicTable('drug_opd', $required_cols);

if ($result['success']) {
    echo "✅ " . $result['message'] . "\n";
} else {
    echo "❌ " . $result['message'] . "\n";
}

// Check columns again
echo "\nColumns after createDynamicTable:\n";
$result = $conn->query("SHOW COLUMNS FROM drug_opd");
$new_cols = [];
while ($col = $result->fetch_assoc()) {
    $new_cols[] = $col['Field'];
}
echo "Total: " . count($new_cols) . " columns\n";

// Show newly added columns
$added = array_diff($new_cols, $current_cols);
if (!empty($added)) {
    echo "\nNewly added columns:\n";
    foreach ($added as $col) {
        echo "  ✓ $col\n";
    }
}

// Check if all required columns exist now
echo "\n\nVerifying all required columns exist:\n";
$missing_now = [];
$new_cols_lower = array_map('strtolower', $new_cols);
foreach ($required_cols as $col) {
    if (!in_array(strtolower($col), $new_cols_lower)) {
        $missing_now[] = $col;
    }
}

if (empty($missing_now)) {
    echo "✅ All required columns are now present in the table!\n";
} else {
    echo "❌ Still missing: " . implode(", ", $missing_now) . "\n";
}

$conn->close();
echo "\n=== TEST COMPLETE ===\n";
?>
