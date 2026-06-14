<?php
/**
 * Diagnostic Tool - Track what data is actually being inserted
 * Logs column names and INSERT statements to see where the mismatch occurs
 */

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/HDC/HDCFileHandler.php';

// Enable logging to file
$log_file = __DIR__ . '/uploads/import_diagnostic.log';
file_put_contents($log_file, "=== Import Diagnostic Started " . date('Y-m-d H:i:s') . " ===\n");

// Create a wrapper to debug insertData
class DebugHDCFileHandler extends HDCFileHandler {
    public function insertDataDebug($import_id, $table_name, $data) {
        global $log_file;
        
        $log = "\n--- INSERT DEBUG ---\n";
        $log .= "Table: $table_name\n";
        $log .= "Rows: " . count($data) . "\n";
        
        if ($data) {
            $first_row = reset($data);
            $columns = array_keys($first_row);
            $log .= "Column Count: " . count($columns) . "\n";
            $log .= "Columns: " . implode(", ", $columns) . "\n";
            
            // Check what columns actually exist in the table
            $check_query = "SHOW COLUMNS FROM `$table_name`";
            try {
                $result = $this->getConnection()->query($check_query);
                $log .= "\nActual Table Columns:\n";
                while ($col = $result->fetch_assoc()) {
                    $log .= "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
                }
            } catch (Exception $e) {
                $log .= "Error checking table: " . $e->getMessage() . "\n";
            }
            
            // Show first row data
            $log .= "\nFirst Row Data:\n";
            foreach ($first_row as $key => $value) {
                $log .= "  $key => " . substr($value, 0, 50) . (strlen($value) > 50 ? "..." : "") . "\n";
            }
            
            // Build the INSERT statement
            $col_list = '`' . implode('`, `', $columns) . '`';
            $placeholders = implode(', ', array_fill(0, count($columns) + 1, '?'));
            $sql = "INSERT INTO `$table_name` (import_id, $col_list) VALUES ($placeholders)";
            $log .= "\nSQL Template:\n$sql\n";
        }
        
        file_put_contents($log_file, $log, FILE_APPEND);
        
        // Call parent method
        return parent::insertData($import_id, $table_name, $data);
    }
    
    private function getConnection() {
        return $this->conn ?? null;
    }
}

echo "✅ Diagnostic wrapper created\n";
echo "📝 Log file: $log_file\n";
echo "\nNext step: Upload the Excel file again. The diagnostic details will be logged.\n";
?>
