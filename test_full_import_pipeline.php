<?php
/**
 * Full Import Pipeline Test
 * Simulates the exact flow with sample data containing CLINIC, DATE_SERV, etc.
 */

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/Import/ExcelImporter.php';
require_once __DIR__ . '/src/HDC/HDCFileHandler.php';

// Create sample data that mimics what readXLSX would return
$sample_data = [
    // Headers as keys (exactly as they come from Excel)
    [
        'HOSPCODE' => '10001',
        'PID' => 'P001',
        'SEQ' => '1',
        'DATE_SERV' => '2025-02-13',
        'CLINIC' => 'OPD',
        'DIDSTD' => 'D001',
        'DNAME' => 'Paracetamol',
        'AMOUNT' => '10',
        'UNIT' => 'Tab',
        'UNIT_PACKING' => '100',
        'DRUGPRICE' => '5.00',
        'DRUGCOST' => '3.00'
    ],
    [
        'HOSPCODE' => '10001',
        'PID' => 'P002',
        'SEQ' => '1',
        'DATE_SERV' => '2025-02-13',
        'CLINIC' => 'OPD',
        'DIDSTD' => 'D002',
        'DNAME' => 'Ibuprofen',
        'AMOUNT' => '5',
        'UNIT' => 'Tab',
        'UNIT_PACKING' => '50',
        'DRUGPRICE' => '10.00',
        'DRUGCOST' => '6.00'
    ]
];

echo "=== IMPORT PIPELINE TEST ===\n\n";

// Step 1: Show raw data
echo "Step 1: Raw data from Excel\n";
echo "Sample keys: " . implode(", ", array_keys($sample_data[0])) . "\n\n";

// Step 2: Simulate normalization
echo "Step 2: Column Normalization\n";
$columns = array_keys($sample_data[0]);
$col_mapping = [];
foreach ($columns as $col) {
    $sanitized = sanitizeColumnName($col);
    $col_mapping[$col] = $sanitized;
    if ($col !== $sanitized) {
        echo "  $col → $sanitized\n";
    }
}

if (empty(array_diff($columns, array_keys($col_mapping)))) {
    echo "  ✅ All columns preserved (no special chars to sanitize)\n\n";
} else {
    echo "  ⚠️  Some columns changed\n\n";
}

// Step 3: Normalize data
echo "Step 3: Re-key data with sanitized column names\n";
$normalized_data = [];
foreach ($sample_data as $row) {
    $normalized_row = [];
    foreach ($row as $col => $value) {
        $sanitized_col = $col_mapping[$col] ?? sanitizeColumnName($col);
        $normalized_row[$sanitized_col] = $value;
    }
    $normalized_data[] = $normalized_row;
}
echo "  ✅ " . count($normalized_data) . " rows normalized\n";
echo "  First row keys: " . implode(", ", array_keys($normalized_data[0])) . "\n\n";

// Step 4: Test database operations
echo "Step 4: Database Operations\n";
$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo "  ❌ Database connection failed\n";
    exit;
}

// Create test table
$table_name = 'test_drug_opd_import';
echo "  Creating test table: $table_name\n";

// Drop if exists
$conn->query("DROP TABLE IF EXISTS $table_name");

// Create table
$create_sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_sql)) {
    echo "  ✅ Table created\n";
} else {
    echo "  ❌ Table creation failed: " . $conn->error . "\n";
    exit;
}

// Step 5: Add columns (simulating createDynamicTable)
echo "  Adding columns to table...\n";
$first_row = reset($normalized_data);
$sanitized_columns = array_keys($first_row);

foreach ($sanitized_columns as $col_name) {
    $add_col = "ALTER TABLE `$table_name` ADD COLUMN `$col_name` LONGTEXT COLLATE utf8mb4_unicode_ci";
    if (!$conn->query($add_col)) {
        // Column might already exist, that's ok
        echo "    Column $col_name: " . ($conn->errno === 1060 ? "Already exists" : $conn->error) . "\n";
    }
}

// Check actual table columns
echo "\n  Actual table columns:\n";
$result = $conn->query("SHOW COLUMNS FROM `$table_name`");
$table_cols = [];
while ($col = $result->fetch_assoc()) {
    echo "    ✓ " . $col['Field'] . "\n";
    $table_cols[] = $col['Field'];
}

// Step 6: Build and execute INSERT
echo "\n  Building INSERT query...\n";
$col_list = '`' . implode('`, `', $sanitized_columns) . '`';
$placeholders = implode(', ', array_fill(0, count($sanitized_columns) + 1, '?'));
$sql = "INSERT INTO `$table_name` (import_id, $col_list) VALUES ($placeholders)";

echo "  SQL: " . substr($sql, 0, 100) . "...\n\n";

// Execute INSERT for first row
echo "  Inserting first row...\n";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "  ❌ Prepare failed: " . $conn->error . "\n";
    exit;
}

$first_row = reset($normalized_data);
$values = [1]; // import_id = 1 for testing
foreach ($sanitized_columns as $col) {
    $values[] = $first_row[$col] ?? '';
}

// Build type string
$types = 'i' . str_repeat('s', count($sanitized_columns));

// Create references for bind_param
$refs = [];
foreach ($values as $key => $val) {
    $refs[$key] = &$values[$key];
}

if (!$stmt->bind_param($types, ...$refs)) {
    echo "  ❌ Bind failed: " . $stmt->error . "\n";
    exit;
}

if ($stmt->execute()) {
    echo "  ✅ INSERT successful\n";
    
    // Verify data was inserted
    $verify = $conn->query("SELECT * FROM `$table_name` WHERE import_id = 1");
    if ($verify->num_rows > 0) {
        $row = $verify->fetch_assoc();
        echo "    Verified: Found " . $verify->num_rows . " rows\n";
        echo "    First column value: " . $row['HOSPCODE'] . "\n";
    }
} else {
    echo "  ❌ Execute failed: " . $stmt->error . "\n";
    
    // Try to show what columns the query expected
    echo "\n  Query expected columns: " . implode(", ", array_merge(['import_id'], $sanitized_columns)) . "\n";
    echo "  Data provided columns: " . implode(", ", array_keys($values)) . "\n";
}

// Cleanup
$conn->query("DROP TABLE IF EXISTS $table_name");
$conn->close();

echo "\n=== TEST COMPLETE ===\n";

/**
 * Sanitize column names (same as in ExcelImporter and HDCFileHandler)
 */
function sanitizeColumnName($name) {
    $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    $name = preg_replace('/^[0-9]+/', '', $name);
    return substr($name, 0, 64) ?: 'column_' . md5($name);
}
?>
