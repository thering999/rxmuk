<?php
/**
 * Import Status Checker
 * Checks recent imports and their status
 */

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/HDC/HDCFileHandler.php';

$db = new Database();
$conn = $db->connect();

echo "=== IMPORT STATUS CHECK ===\n\n";

// Get recent imports
$query = "SELECT * FROM imported_files ORDER BY upload_date DESC LIMIT 5";
$result = $conn->query($query);

echo "Recent Imports:\n";
while ($row = $result->fetch_assoc()) {
    echo sprintf(
        "\nID: %d\n  File: %s\n  Date: %s\n  User: %d\n",
        $row['id'],
        $row['original_name'],
        $row['upload_date'],
        $row['user_id']
    );
    
    // Detect type
    $hdc = new HDCFileHandler();
    $detection = $hdc->detectFileType($row['original_name']);
    echo "  Table: " . $detection['config']['table'] . "\n";
    
    // Check data count
    $table_name = $detection['config']['table'];
    $count_query = "SELECT COUNT(*) as cnt FROM `$table_name` WHERE import_id = ?";
    $stmt = $conn->prepare($count_query);
    if ($stmt) {
        $stmt->bind_param("i", $row['id']);
        $stmt->execute();
        $count_result = $stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        echo "  Rows imported: " . $count_row['cnt'] . "\n";
    }
}

// Check table row counts
echo "\n\n=== TABLE ROW COUNTS ===\n";
$tables = ['drug_opd', 's_drug_opd'];
foreach ($tables as $table) {
    $count = $conn->query("SELECT COUNT(*) as cnt FROM `$table`");
    $row = $count->fetch_assoc();
    echo "$table: " . $row['cnt'] . " rows\n";
}

// Check if there are any errors in PHP error log
echo "\n\n=== CHECKING FOR ERROR LOGS ===\n";
$error_log = '/var/www/html/uploads/import_errors.log';
if (file_exists($error_log)) {
    echo "Error log found:\n";
    $lines = file_get_contents($error_log);
    echo substr($lines, -2000); // Last 2000 chars
} else {
    echo "No error log found at $error_log\n";
}

$conn->close();
echo "\n=== CHECK COMPLETE ===\n";
?>
