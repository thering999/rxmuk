<?php
/**
 * Create s_drug_opd table if it doesn't exist
 */

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/HDC/HDCFileHandler.php';

$db = new Database();
$conn = $db->connect();

echo "=== S_DRUG_OPD TABLE SETUP ===\n\n";

// Check if table exists
$table_name = 's_drug_opd';
$check = "SHOW TABLES LIKE '$table_name'";
$result = $conn->query($check);

if ($result->num_rows === 0) {
    echo "Table doesn't exist. Creating...\n";
    
    // Create base table
    $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        import_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (import_id) REFERENCES imported_files(id) ON DELETE CASCADE,
        INDEX idx_import_id (import_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "✅ Table created\n";
    } else {
        echo "❌ Failed: " . $conn->error . "\n";
        exit;
    }
    
    // Add columns for s_drug_opd
    $columns = ['HOSPCODE', 'AMPHUR', 'DIDSTD', 'DNAME', 'SumAmount', 'Count', 'SumDrugCost', 'SumDrugPrice'];
    echo "\nAdding columns:\n";
    foreach ($columns as $col) {
        $add_col = "ALTER TABLE `$table_name` ADD COLUMN `$col` LONGTEXT COLLATE utf8mb4_unicode_ci";
        if ($conn->query($add_col)) {
            echo "  ✓ $col\n";
        } else {
            echo "  ✗ $col: " . $conn->error . "\n";
        }
    }
} else {
    echo "✅ Table already exists\n";
    
    // Show columns
    echo "\nCurrent columns:\n";
    $show_cols = "SHOW COLUMNS FROM `$table_name`";
    $col_result = $conn->query($show_cols);
    while ($col = $col_result->fetch_assoc()) {
        echo "  - " . $col['Field'] . "\n";
    }
}

// Verify final structure
echo "\n\nFinal table structure:\n";
$show_cols = "SHOW COLUMNS FROM `$table_name`";
$col_result = $conn->query($show_cols);
$col_count = 0;
while ($col = $col_result->fetch_assoc()) {
    $col_count++;
}
echo "Total columns: $col_count\n";

// Check for data
$count = "SELECT COUNT(*) as cnt FROM `$table_name`";
$count_result = $conn->query($count);
$count_row = $count_result->fetch_assoc();
echo "Data rows: " . $count_row['cnt'] . "\n";

$conn->close();
echo "\n=== SETUP COMPLETE ===\n";
?>
