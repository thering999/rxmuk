<?php
/**
 * Chunked Importer - Handles large CSV files line-by-line
 * Memory efficient (O(1) memory) and supports Thai encoding
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../HDC/HDCFileHandler.php';

class ChunkedImporter {
    private $db;
    private $conn;
    private $hdc_handler;
    private $upload_dir = __DIR__ . '/../../uploads/';

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        $this->hdc_handler = new HDCFileHandler();
    }

    /**
     * Start the import process for a specific file
     */
    public function importCSV($import_id, $file_path, $batch_size = 2000) {
        if (!file_exists($file_path)) {
            $this->updateStatus($import_id, 'failed', 0, "File not found: $file_path");
            return ['success' => false, 'message' => "File not found"];
        }

        // 1. Detect Encoding
        $encoding = $this->detectEncoding($file_path);
        
        // 2. Open file
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            $this->updateStatus($import_id, 'failed', 0, "Could not open file");
            return ['success' => false, 'message' => "Could not open file"];
        }

        // Apply stream filter for UTF-16 if detected
        if ($encoding === 'UTF-16LE') {
            stream_filter_append($handle, 'convert.iconv.UTF-16LE/UTF-8');
            // Skip the BOM bytes which are now converted
            fread($handle, 3); // Read over the converted UTF-8 BOM
            $encoding = 'UTF-8'; // Data is now UTF-8
        } elseif ($encoding === 'UTF-16BE') {
            stream_filter_append($handle, 'convert.iconv.UTF-16BE/UTF-8');
            fread($handle, 3);
            $encoding = 'UTF-8';
        }

        // 3. Read Headers
        $headers_raw = fgetcsv($handle);
        if (!$headers_raw) {
            fclose($handle);
            $this->updateStatus($import_id, 'failed', 0, "File is empty or invalid CSV");
            return ['success' => false, 'message' => "Empty file"];
        }

        // Convert headers if needed and strip BOM from first header
        $headers = [];
        foreach ($headers_raw as $i => $h) {
            $converted = $this->convertEncoding($h, $encoding);
            if ($i === 0) {
                // Strip UTF-8 BOM if present
                if (substr($converted, 0, 3) === "\xEF\xBB\xBF") {
                    $converted = substr($converted, 3);
                }
            }
            $headers[] = trim($converted);
        }

        // Sanitize headers for database
        $sanitized_headers = [];
        foreach ($headers as $h) {
            $sanitized_headers[] = $this->sanitizeColumnName($h);
        }

        // 4. Detect File Type and Ensure Table
        $original_name = basename($file_path);
        $detection = $this->hdc_handler->detectFileType($original_name, $sanitized_headers);
        $table_name = $detection['config']['table'] ?? 'generic_hdc_data';

        $table_ready = $this->hdc_handler->createDynamicTable($table_name, $sanitized_headers);
        if (!$table_ready['success']) {
            fclose($handle);
            $this->updateStatus($import_id, 'failed', 0, $table_ready['message']);
            return $table_ready;
        }

        // 5. Estimate Total Rows (rough)
        $total_rows = $this->estimateTotalRows($file_path);
        $this->updateStatus($import_id, 'processing', 0, null, $total_rows);

        // 6. Process Rows in Batches
        $row_count = 0;
        $batch_data = [];
        $total_inserted = 0;
        $total_failed = 0;

        while (($row_raw = fgetcsv($handle)) !== false) {
            $row_count++;
            
            // Map raw row to associative array with sanitized headers
            $assoc_row = [];
            foreach ($sanitized_headers as $index => $col) {
                $val = $row_raw[$index] ?? '';
                $val = $this->convertEncoding($val, $encoding);
                
                // Specific sanitization for scientific notation (e.g. drug IDs)
                if (is_numeric($val) && (strlen($val) > 15 || strpos($val, 'E+') !== false)) {
                    $val = number_format((float)$val, 0, '', '');
                }

                $assoc_row[$col] = trim($val);
            }

            $batch_data[] = $assoc_row;

            if (count($batch_data) >= $batch_size) {
                $res = $this->hdc_handler->insertData($import_id, $table_name, $batch_data);
                $total_inserted += $res['inserted'] ?? 0;
                $total_failed += $res['failed'] ?? 0;
                $batch_data = [];
                
                // Update progress
                $this->updateStatus($import_id, 'processing', $total_inserted);
                echo "Processed $total_inserted rows...\n";
            }
        }

        // Process remaining rows
        if (!empty($batch_data)) {
            $res = $this->hdc_handler->insertData($import_id, $table_name, $batch_data);
            $total_inserted += $res['inserted'] ?? 0;
            $total_failed += $res['failed'] ?? 0;
        }

        fclose($handle);

        // 7. Finalize
        $status = $total_failed > 0 && $total_inserted == 0 ? 'failed' : 'completed';
        $msg = "Imported $total_inserted rows" . ($total_failed > 0 ? ", failed $total_failed rows" : "");
        $this->updateStatus($import_id, $status, $total_inserted, $msg);

        // 8. Auto-Aggregation
        if ($status === 'completed' && $table_name === 'drug_opd') {
            echo "Running auto-aggregation for dashboard...\n";
            $this->aggregateImport($import_id);
        }

        return [
            'success' => true,
            'inserted' => $total_inserted,
            'failed' => $total_failed,
            'status' => $status
        ];
    }

    /**
     * Detect file encoding (UTF-8, UTF-16, or Windows-874)
     */
    private function detectEncoding($file_path) {
        $handle = fopen($file_path, 'r');
        $bom = fread($handle, 4);
        fclose($handle);

        if (substr($bom, 0, 3) === "\xEF\xBB\xBF") return 'UTF-8';
        if (substr($bom, 0, 2) === "\xFF\xFE") return 'UTF-16LE';
        if (substr($bom, 0, 2) === "\xFE\xFF") return 'UTF-16BE';

        $handle = fopen($file_path, 'r');
        $sample = fread($handle, 8192);
        fclose($handle);

        if (mb_check_encoding($sample, 'UTF-8')) {
            return 'UTF-8';
        }
        return 'Windows-874';
    }

    /**
     * Convert string to UTF-8
     */
    private function convertEncoding($str, $from_encoding) {
        if ($from_encoding === 'UTF-8') return $str;
        
        try {
            if ($from_encoding === 'UTF-16LE' || $from_encoding === 'UTF-16BE') {
                return mb_convert_encoding($str, 'UTF-8', $from_encoding);
            }
            return iconv('Windows-874', 'UTF-8//IGNORE', $str);
        } catch (Exception $e) {
            return $str;
        }
    }

    /**
     * Update import status in database
     */
    private function updateStatus($import_id, $status, $processed = 0, $error = null, $total = null) {
        $sql = "UPDATE imported_files SET status = ?, processed_rows = ?";
        $params = [$status, $processed];
        $types = "si";

        if ($error !== null) {
            $sql .= ", error_message = ?";
            $params[] = $error;
            $types .= "s";
        }

        if ($total !== null) {
            $sql .= ", total_rows = ?";
            $params[] = $total;
            $types .= "i";
        }

        $sql .= " WHERE id = ?";
        $params[] = $import_id;
        $types .= "i";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }

    /**
     * Estimate total rows (line count)
     */
    private function estimateTotalRows($file_path) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // For Windows, just read file size as proxy or use generic PHP
            return 0; // Will update as we go
        }
        $line_count = shell_exec("wc -l < " . escapeshellarg($file_path));
        return intval($line_count);
    }

    /**
     * Sanitize column names (copied from HDCFileHandler for consistency)
     */
    private function sanitizeColumnName($name) {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        $name = preg_replace('/^[0-9]+/', '', $name);
        return substr($name, 0, 64) ?: 'column_' . md5($name);
    }

    /**
     * Aggregate imported drug_opd data into s_drug_opd
     */
    public function aggregateImport($import_id) {
        // First clear any existing aggregation for this import
        $this->conn->query("DELETE FROM s_drug_opd WHERE import_id = " . intval($import_id));

        $sql = "INSERT INTO s_drug_opd (`import_id`, `HOSPCODE`, `AMPHUR`, `DIDSTD`, `DNAME`, `SumAmount`, `Count`, `SumDrugCost`, `SumDrugPrice`)
                SELECT 
                    `import_id`,
                    `hospcode`,
                    COALESCE(`AMPHUR`, '') as `AMPHUR`,
                    `didstd`,
                    `dname`,
                    SUM(CAST(COALESCE(`amount`, 0) AS DECIMAL(15,2))) as `SumAmount`,
                    COUNT(*) as `row_count`,
                    SUM(CAST(COALESCE(`cost`, `DRUGCOST`, 0) AS DECIMAL(15,2))) as `SumDrugCost`,
                    SUM(CAST(COALESCE(`price`, `DRUGPRICE`, 0) AS DECIMAL(15,2))) as `SumDrugPrice`
                FROM `drug_opd`
                WHERE `import_id` = ?
                GROUP BY `import_id`, `hospcode`, `AMPHUR`, `didstd`, `dname`";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            echo "Aggregation PREPARE failed: " . $this->conn->error . "\n";
            return false;
        }
        
        $stmt->bind_param("i", $import_id);
        
        if ($stmt->execute()) {
            echo "Aggregation complete for import ID: $import_id\n";
            return true;
        } else {
            echo "Aggregation EXECUTE failed: " . $stmt->error . "\n";
            return false;
        }
    }
}
