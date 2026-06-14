<?php
/**
 * HDC File Handler - Detects and processes different HDC file types
 * Dynamically creates tables based on file structure
 */

class HDCFileHandler {
    private $db;
    private $conn;
    
    // Map of known HDC file types to their expected columns
    private $hdc_types = [
        'drug_opd' => [
            'columns' => ['hospcode', 'pid', 'seq', 'date_serv', 'didstd', 'dname', 'amount', 'unit', 'price', 'cost'],
            'table' => 'drug_opd',
            'description' => 'ข้อมูลยาผู้ป่วยนอก'
        ],
        's_drug_opd' => [
            'columns' => ['hospcode', 'amphur', 'didstd', 'dname', 'sumamount', 'count', 'sumdrugcost', 'sumdrugprice'],
            'table' => 's_drug_opd',
            'description' => 'สรุปข้อมูลยาผู้ป่วยนอก'
        ],
        'drug_ipd' => [
            'columns' => ['hospcode', 'pid', 'seq', 'date_serv', 'didstd', 'dname', 'amount', 'unit', 'price', 'cost'],
            'table' => 'drug_ipd',
            'description' => 'ข้อมูลยาผู้ป่วยใน'
        ],
        'opd' => [
            'columns' => ['hospcode', 'pid', 'visit_date', 'type_visit', 'main_code', 'weight', 'height', 'temp'],
            'table' => 'opd_data',
            'description' => 'ข้อมูลผู้ป่วยนอก'
        ],
        'ipd' => [
            'columns' => ['hospcode', 'pid', 'admit_date', 'discharge_date', 'main_code', 'second_code'],
            'table' => 'ipd_data',
            'description' => 'ข้อมูลผู้ป่วยใน'
        ],
        'lab' => [
            'columns' => ['hospcode', 'pid', 'date_lab', 'lab_code', 'lab_name', 'lab_value', 'lab_unit'],
            'table' => 'lab_data',
            'description' => 'ข้อมูลห้องแล็บ'
        ]
    ];

    public function __construct() {
        require_once __DIR__ . '/../../config/Database.php';
        $this->db = new Database();
        $this->conn = $this->db->connect();
    }

    /**
     * Detect HDC file type from file name and columns
     */
    public function detectFileType($file_name, $columns = []) {
        $name_lower = strtolower($file_name);
        
        // First check for summary files (s_drug, s_tmp_drug, etc) - BEFORE regular patterns
        if (strpos($name_lower, 's_drug') !== false || strpos($name_lower, 's_tmp_drug') !== false) {
            return [
                'type' => 's_drug_opd',
                'config' => $this->hdc_types['s_drug_opd'],
                'detected_by' => 'filename'
            ];
        }
        
        // Try to match by filename patterns
        foreach ($this->hdc_types as $type => $config) {
            // Skip s_drug_opd as it's already handled above
            if ($type === 's_drug_opd') continue;
            
            $patterns = [
                $type,
                str_replace('_', '', $type),
                str_replace('_', ' ', $type),
                $type . 'data',
                $type . '_file'
            ];
            
            foreach ($patterns as $pattern) {
                if (stripos($name_lower, $pattern) !== false) {
                    return [
                        'type' => $type,
                        'config' => $config,
                        'detected_by' => 'filename'
                    ];
                }
            }
        }
        
        // Try to match by columns if available
        if (!empty($columns)) {
            foreach ($this->hdc_types as $type => $config) {
                $match_count = 0;
                foreach ($config['columns'] as $col) {
                    if (in_array($col, array_map('strtolower', $columns))) {
                        $match_count++;
                    }
                }
                if ($match_count >= 3) { // At least 3 columns match
                    return [
                        'type' => $type,
                        'config' => $config,
                        'detected_by' => 'columns'
                    ];
                }
            }
        }
        
        // Unknown type - will use generic handler
        return [
            'type' => 'generic',
            'config' => ['table' => 'generic_hdc_data', 'description' => 'ข้อมูล HDC ทั่วไป'],
            'detected_by' => 'generic'
        ];
    }

    /**
     * Get all supported HDC types
     */
    public function getSupportedTypes() {
        return $this->hdc_types;
    }

    /**
     * Get HDC type description
     */
    public function getTypeDescription($type) {
        if (isset($this->hdc_types[$type])) {
            return $this->hdc_types[$type]['description'];
        }
        return 'ไม่ทราบประเภท';
    }

    /**
     * Create dynamic table for HDC data type
     * NOTE: Column names should already be sanitized by caller
     * IMPORTANT: Handles existing tables without adding incompatible columns
     */
    public function createDynamicTable($table_name, $columns) {
        // Sanitize table name for safety
        $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
        
        // Check if table already exists
        $table_exists = $this->conn->query("SHOW TABLES LIKE '$table_name'")->num_rows > 0;
        
        if (!$table_exists) {
            // Table doesn't exist - create it
            $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                import_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (import_id) REFERENCES imported_files(id) ON DELETE CASCADE,
                INDEX idx_import_id (import_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            try {
                if (!$this->conn->query($sql)) {
                    return ['success' => false, 'message' => 'Failed to create table: ' . $this->conn->error];
                }
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Error creating table: ' . $e->getMessage()];
            }
        }

        // Get existing columns in the table
        $existing_columns = [];
        $cols_result = $this->conn->query("SHOW COLUMNS FROM `$table_name`");
        if ($cols_result) {
            while ($col = $cols_result->fetch_assoc()) {
                $existing_columns[] = strtolower($col['Field']);
            }
        }

        // Add missing columns to table (use as-is since already sanitized)
        if (!empty($columns)) {
            foreach ($columns as $col_name) {
                // Check if column already exists (case-insensitive)
                $col_lower = strtolower($col_name);
                if (in_array($col_lower, $existing_columns)) {
                    continue;
                }
                
                // Column doesn't exist - add it
                $add_col = "ALTER TABLE `$table_name` ADD COLUMN `$col_name` LONGTEXT COLLATE utf8mb4_unicode_ci";
                
                if (!$this->conn->query($add_col)) {
                    // Log but continue with other columns
                    error_log("Failed to add column $col_name to $table_name: " . $this->conn->error);
                    continue;
                }
                $existing_columns[] = $col_lower;
            }
        }
        
        return ['success' => true, 'message' => 'Table ready'];
    }

    /**
     * Sanitize column names to be MySQL safe
     */
    private function sanitizeColumnName($name) {
        // Remove special characters, keep only alphanumeric and underscore
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        // Remove leading numbers
        $name = preg_replace('/^[0-9]+/', '', $name);
        // Limit to 64 characters (MySQL limit)
        return substr($name, 0, 64) ?: 'column_' . md5($name);
    }

    /**
     * Insert data into detected table
     * NOTE: Maps normalized data column names to actual table columns (case-insensitive)
     */
    public function insertData($import_id, $table_name, $data) {
        if (empty($data)) {
            return ['success' => true, 'message' => 'ไม่มีข้อมูลที่จะบันทึก'];
        }

        $log = "=== INSERT DEBUG " . date('Y-m-d H:i:s') . " ===\n";
        $log .= "Table: $table_name\n";
        $log .= "Rows to insert: " . count($data) . "\n";

        // Get actual table columns
        $actual_table_columns = [];
        $cols_result = $this->conn->query("SHOW COLUMNS FROM `$table_name`");
        if ($cols_result) {
            while ($col = $cols_result->fetch_assoc()) {
                $actual_table_columns[strtolower($col['Field'])] = $col['Field'];
            }
        }

        $log .= "Actual table columns: " . count($actual_table_columns) . " - " . implode(", ", array_slice($actual_table_columns, 0, 5)) . "\n";

        // Ensure table has columns (this should run createDynamicTable if needed)
        $first_row = reset($data);
        $columns_from_data = array_keys($first_row);
        $log .= "Data columns: " . count($columns_from_data) . " - " . implode(", ", $columns_from_data) . "\n";

        $this->createDynamicTable($table_name, $columns_from_data);

        // Build mapping: data column (lower) => actual table column
        $col_mapping = [];
        foreach ($columns_from_data as $data_col) {
            $data_col_lower = strtolower($data_col);
            if (isset($actual_table_columns[$data_col_lower])) {
                $col_mapping[$data_col] = $actual_table_columns[$data_col_lower];
            } else {
                // Column doesn't exist in table yet, use as-is
                $col_mapping[$data_col] = $data_col;
            }
        }

        // Build INSERT query with actual table column names
        $actual_columns = array_values($col_mapping);
        $col_list = '`' . implode('`, `', $actual_columns) . '`';
        $placeholders = implode(', ', array_fill(0, count($actual_columns) + 1, '?'));
        
        $sql = "INSERT INTO `$table_name` (import_id, $col_list) VALUES ($placeholders)";
        $log .= "SQL: " . substr($sql, 0, 100) . "...\n";
        
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            $log .= "Prepare failed: " . $this->conn->error . "\n";
            error_log($log);
            return ['success' => false, 'message' => 'Database Error: ' . $this->conn->error];
        }

        $inserted = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($data as $row_index => $row) {
            // Build values array with import_id first
            $values = [$import_id];
            
            // Map data columns to actual table columns
            foreach ($columns_from_data as $data_col) {
                $values[] = isset($row[$data_col]) ? $row[$data_col] : '';
            }
            
            // Build type string for bind_param
            $types = 'i' . str_repeat('s', count($columns_from_data));
            
            // Create references for bind_param
            $refs = [];
            foreach ($values as $key => $val) {
                $refs[$key] = &$values[$key];
            }
            
            // Bind and execute
            try {
                if (!$stmt->bind_param($types, ...$refs)) {
                    $failed++;
                    $err = "Row " . ($row_index + 1) . ": bind_param failed - " . $stmt->error;
                    $errors[] = $err;
                    if ($row_index < 5) $log .= $err . "\n";
                    continue;
                }
                
                if ($stmt->execute()) {
                    $inserted++;
                } else {
                    $failed++;
                    $err = "Row " . ($row_index + 1) . ": execute failed - " . $stmt->error;
                    $errors[] = $err;
                    if ($row_index < 5) $log .= $err . "\n";
                }
            } catch (Exception $e) {
                $failed++;
                $err = "Row " . ($row_index + 1) . ": exception - " . $e->getMessage();
                $errors[] = $err;
                if ($row_index < 5) $log .= $err . "\n";
                continue;
            }
        }

        $log .= "Result: Inserted=$inserted, Failed=$failed\n";
        error_log($log);

        return [
            'success' => true, 
            'message' => "บันทึก $inserted แถว" . ($failed > 0 ? ", ล้มเหลว $failed แถว" : ""), 
            'inserted' => $inserted,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 5)
        ];
    }

    /**
     * Get data from import
     */
    public function getImportData($import_id, $limit = 1000, $offset = 0) {
        // Get import info
        $query = "SELECT * FROM imported_files WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $import_id);
        $stmt->execute();
        
        $import_info = $stmt->get_result()->fetch_assoc();
        if (!$import_info) {
            return null;
        }

        // Detect file type
        $detection = $this->detectFileType($import_info['original_name']);
        $table_name = $detection['config']['table'] ?? 'generic_hdc_data';

        // Get data
        $data_query = "SELECT * FROM `$table_name` WHERE import_id = ? ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($data_query);
        $stmt->bind_param("iii", $import_id, $limit, $offset);
        $stmt->execute();

        return [
            'import_info' => $import_info,
            'detection' => $detection,
            'data' => $stmt->get_result()
        ];
    }

    /**
     * Get data count for pagination
     */
    public function getImportDataCount($import_id) {
        $query = "SELECT original_name FROM imported_files WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $import_id);
        $stmt->execute();
        
        $import_info = $stmt->get_result()->fetch_assoc();
        if (!$import_info) {
            return 0;
        }

        $detection = $this->detectFileType($import_info['original_name']);
        $table_name = $detection['config']['table'] ?? 'generic_hdc_data';

        $count_query = "SELECT COUNT(*) as count FROM `$table_name` WHERE import_id = ?";
        $stmt = $this->conn->prepare($count_query);
        $stmt->bind_param("i", $import_id);
        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] ?? 0;
    }

    /**
     * Get all columns from import data
     */
    public function getImportColumns($import_id) {
        $query = "SELECT original_name FROM imported_files WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param("i", $import_id);
        $stmt->execute();
        
        $import_info = $stmt->get_result()->fetch_assoc();
        if (!$import_info) {
            return [];
        }

        $detection = $this->detectFileType($import_info['original_name']);
        $table_name = $detection['config']['table'] ?? 'generic_hdc_data';

        // Check if table exists first - use direct query since SHOW doesn't support prepared statements
        $check_table = "SHOW TABLES LIKE '" . $this->conn->real_escape_string($table_name) . "'";
        $result = $this->conn->query($check_table);
        
        if (!$result) {
            return [];
        }
        
        if ($result->num_rows === 0) {
            // Table doesn't exist yet - return empty array or expected columns from detection
            return $detection['config']['columns'] ?? [];
        }

        $col_query = "SHOW COLUMNS FROM `$table_name`";
        $result = $this->conn->query($col_query);
        
        if (!$result) {
            return $detection['config']['columns'] ?? [];
        }
        
        $columns = [];
        while ($col = $result->fetch_assoc()) {
            if ($col['Field'] !== 'id' && $col['Field'] !== 'import_id' && $col['Field'] !== 'created_at') {
                $columns[] = $col['Field'];
            }
        }

        return $columns;
    }

    /**
     * Export data to array
     */
    public function exportToArray($import_id) {
        $result = $this->getImportData($import_id, PHP_INT_MAX);
        if (!$result) {
            return null;
        }

        $rows = [];
        while ($row = $result['data']->fetch_assoc()) {
            unset($row['id'], $row['created_at']);
            $rows[] = $row;
        }

        return [
            'info' => $result['import_info'],
            'detection' => $result['detection'],
            'data' => $rows
        ];
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
