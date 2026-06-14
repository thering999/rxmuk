<?php
/**
 * Get detailed insert errors
 */

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/Import/ExcelImporter.php';
require_once __DIR__ . '/src/HDC/HDCFileHandler.php';

echo "=== DETAILED INSERT ERROR ANALYSIS ===\n\n";

// Get the last temp import record
$db = new Database();
$conn = $db->connect();

$query = "SELECT * FROM imported_files WHERE original_name = 'tmp_drug_opd.xlsx' ORDER BY upload_date DESC LIMIT 1";
$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    echo "No imports found\n";
    exit;
}

$import_row = $result->fetch_assoc();
$import_id = $import_row['id'];

echo "Import ID: $import_id\n";
echo "File: " . $import_row['original_name'] . "\n\n";

// Check the drug_opd table for this import
$check = "SELECT COUNT(*) as cnt FROM drug_opd WHERE import_id = ?";
$stmt = $conn->prepare($check);
$stmt->bind_param("i", $import_id);
$stmt->execute();
$count_result = $stmt->get_result()->fetch_assoc();

echo "Rows in drug_opd for this import: " . $count_result['cnt'] . "\n\n";

// Show table structure
echo "Table structure:\n";
$cols = $conn->query("SHOW COLUMNS FROM drug_opd");
$table_cols = [];
while ($col = $cols->fetch_assoc()) {
    if ($col['Field'] !== 'id' && $col['Field'] !== 'import_id' && $col['Field'] !== 'created_at') {
        $table_cols[] = $col['Field'];
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}

echo "\nTotal columns: " . count($table_cols) . "\n";

// Now manually test an insert with actual data
echo "\n\n=== TESTING INSERT WITH SAMPLE DATA ===\n\n";

// Get actual file and re-read it
$uploads_dir = __DIR__ . '/uploads/';
$files = glob($uploads_dir . '*tmp_drug_opd.xlsx');
$tmp_file = null;

foreach ($files as $file) {
    if (strpos(basename($file), 's_tmp_drug') === false) {
        $tmp_file = $file;
        break;
    }
}

if ($tmp_file) {
    $importer = new ExcelImporter();
    $reflection = new ReflectionClass($importer);
    $readMethod = $reflection->getMethod('readExcelFile');
    $readMethod->setAccessible(true);
    
    $read_result = $readMethod->invoke($importer, $tmp_file, '');
    
    if ($read_result['success'] && count($read_result['data']) > 0) {
        $first_row = $read_result['data'][0];
        
        echo "First row keys: " . implode(", ", array_keys($first_row)) . "\n";
        echo "First row key count: " . count($first_row) . "\n";
        echo "Table column count: " . count($table_cols) . "\n\n";
        
        // Check missing columns
        $missing = [];
        foreach ($table_cols as $col) {
            if (!isset($first_row[$col])) {
                $missing[] = $col;
            }
        }
        
        if (!empty($missing)) {
            echo "Missing columns in data:\n";
            foreach ($missing as $col) {
                echo "  - $col\n";
            }
        } else {
            echo "✓ All table columns are present in data\n";
        }
        
        // Check extra columns in data
        $extra = [];
        foreach (array_keys($first_row) as $col) {
            if (!in_array($col, $table_cols) && !in_array(strtolower($col), $table_cols)) {
                $extra[] = $col;
            }
        }
        
        if (!empty($extra)) {
            echo "\nExtra columns in data (not in table):\n";
            foreach ($extra as $col) {
                echo "  - $col\n";
            }
        }
    }
}

$conn->close();
echo "\n=== ANALYSIS COMPLETE ===\n";
?>
