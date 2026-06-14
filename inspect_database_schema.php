<?php
/**
 * Database Schema Inspector
 * Shows existing tables and their columns
 */

require_once __DIR__ . '/config/Database.php';

$db = new Database();
$conn = $db->connect();

echo "=== DATABASE SCHEMA INSPECTOR ===\n\n";

// List all tables
$tables_query = "SHOW TABLES";
$result = $conn->query($tables_query);

$hdc_tables = [];
while ($row = $result->fetch_row()) {
    $table = $row[0];
    if (strpos($table, 'drug') !== false || strpos($table, 'opd') !== false || 
        strpos($table, 'ipd') !== false || strpos($table, 'lab') !== false ||
        strpos($table, 'hdc') !== false || strpos($table, 'generic') !== false) {
        $hdc_tables[] = $table;
    }
}

echo "HDC-Related Tables Found: " . count($hdc_tables) . "\n";
if (empty($hdc_tables)) {
    echo "  (None)\n";
} else {
    echo "  " . implode(", ", $hdc_tables) . "\n";
}

// For each table, show columns
echo "\n=== TABLE STRUCTURES ===\n";
foreach ($hdc_tables as $table) {
    echo "\nTable: `$table`\n";
    $cols_query = "SHOW COLUMNS FROM `$table`";
    $cols_result = $conn->query($cols_query);
    
    if ($cols_result) {
        while ($col = $cols_result->fetch_assoc()) {
            echo sprintf("  %-30s %s\n", $col['Field'], $col['Type']);
        }
    } else {
        echo "  Error: " . $conn->error . "\n";
    }
    
    // Count rows
    $count_query = "SELECT COUNT(*) as cnt FROM `$table`";
    $count_result = $conn->query($count_query);
    if ($count_result) {
        $row = $count_result->fetch_assoc();
        echo "  Rows: " . $row['cnt'] . "\n";
    }
}

// Check imported_files table
echo "\n=== IMPORT HISTORY ===\n";
$import_query = "SELECT * FROM imported_files ORDER BY upload_date DESC LIMIT 5";
$import_result = $conn->query($import_query);

if ($import_result && $import_result->num_rows > 0) {
    echo "Recent imports:\n";
    while ($row = $import_result->fetch_assoc()) {
        echo sprintf("  ID: %d | File: %s | Date: %s\n", 
            $row['id'], $row['original_name'], $row['upload_date']);
    }
} else {
    echo "No import records found\n";
}

$conn->close();
echo "\n=== END INSPECTOR ===\n";
?>
