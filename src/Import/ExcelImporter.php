<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../HDC/HDCFileHandler.php';

/**
 * Excel Import Handler
 * Supports importing single and batch Excel files with HDC data handling
 */
class ExcelImporter {
    private $db;
    private $conn;
    private $hdc_handler;
    private $upload_dir = __DIR__ . '/../../uploads/';

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        $this->hdc_handler = new HDCFileHandler();
    }

    public function getConnection() {
        return $this->conn;
    }

    /**
     * Import single Excel file
     */
    public function importFile($file, $user_id, $sheet_name = '') {
        try {
            // Validate file
            if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                $error_msg = 'ข้อผิดพลาดการอัปโหลด';
                switch($file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $error_msg = 'ไฟล์ใหญ่เกินกว่าที่กำหนดใน php.ini';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_msg = 'ไฟล์ใหญ่เกินกว่าที่กำหนดในฟอร์ม';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_msg = 'อัปโหลดไฟล์ไม่สมบูรณ์';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_msg = 'ไม่มีไฟล์ที่อัปโหลด';
                        break;
                }
                return ['success' => false, 'message' => $error_msg, 'filename' => isset($file['name']) ? $file['name'] : 'Unknown'];
            }

            $file_name = basename($file['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_ext, ['xlsx', 'xls', 'csv'])) {
                return ['success' => false, 'message' => 'เฉพาะไฟล์ Excel เท่านั้น (.xlsx, .xls, .csv)', 'filename' => $file_name];
            }

            // Create uploads directory if not exists
            if (!is_dir($this->upload_dir)) {
                mkdir($this->upload_dir, 0755, true);
            }

            // Generate unique file name
            $unique_name = uniqid('import_') . '_' . $file_name;
            $file_path = $this->upload_dir . $unique_name;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                return ['success' => false, 'message' => 'ไม่สามารถบันทึกไฟล์ได้', 'filename' => $file_name];
            }

            // Sync with SPA's main data file if it matches the summary name
            if (strpos($file_name, 's_tmp_drug_opd') !== false && $file_ext === 'csv') {
                copy($file_path, $this->upload_dir . 's_tmp_drug_opd.csv');
            }

            // Read Excel file
            $data = $this->readExcelFile($file_path, $sheet_name);
            if (!$data['success']) {
                @unlink($file_path);
                return array_merge($data, ['filename' => $file_name]);
            }

            // Store in database
            $result = $this->storeImportedData($unique_name, $file_name, $user_id, $data['data']);
            
            if ($result['success']) {
                return [
                    'success' => true, 
                    'message' => 'นำเข้าไฟล์สำเร็จ', 
                    'import_id' => $result['import_id'], 
                    'filename' => $file_name,
                    'read_rows' => count($data['data']),
                    'inserted_rows' => $result['inserted_rows'] ?? 0,
                    'failed_rows' => $result['failed_rows'] ?? 0
                ];
            } else {
                @unlink($file_path);
                return array_merge($result, ['filename' => $file_name]);
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'ข้อผิดพลาด: ' . $e->getMessage(), 'filename' => $file['name'] ?? 'Unknown'];
        }
    }

    /**
     * Import multiple files (Batch Import)
     * $files should be $_FILES['files'] from multiple file input
     */
    public function importBatch($files, $user_id, $sheet_name = '') {
        // Handle $_FILES array for multiple files
        // $_FILES['excel_files']['name'] is array when multiple files
        // Need to restructure to array of individual file arrays
        $file_list = [];
        
        if (is_array($files['name'])) {
            // Multiple files uploaded
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $file_list[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
            }
        } else {
            // Single file (shouldn't happen in batch, but handle it)
            $file_list = [$files];
        }

        $results = [
            'success_count' => 0,
            'failed_count' => 0,
            'results' => [],
            'import_ids' => []
        ];

        foreach ($file_list as $file) {
            $result = $this->importFile($file, $user_id, $sheet_name);
            
            if ($result['success']) {
                $results['success_count']++;
                $results['import_ids'][] = $result['import_id'] ?? null;
            } else {
                $results['failed_count']++;
            }
            
            $results['results'][] = $result;
        }

        $total = count($file_list);
        $results['summary'] = "สำเร็จ: {$results['success_count']}/{$total} ไฟล์";
        
        return $results;
    }

    /**
     * Read Excel file using basic PHP (supports .csv and simple .xlsx)
     */
    private function readExcelFile($file_path, $sheet_name = '') {
        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if ($file_ext === 'csv') {
            return $this->readCSV($file_path);
        } elseif ($file_ext === 'xlsx' || $file_ext === 'xls') {
            return $this->readXLSX($file_path, $sheet_name);
        }

        return ['success' => false, 'message' => 'ไฟล์ประเภทนี้ไม่รองรับ'];
    }

    /**
     * Read CSV file
     */
    private function readCSV($file_path) {
        $data = [];
        $headers = [];
        $row_num = 0;

        if (($handle = fopen($file_path, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if ($row_num === 0) {
                    $headers = $row;
                } else {
                    $row_data = [];
                    foreach ($headers as $index => $header) {
                        $row_data[$header] = $row[$index] ?? '';
                    }
                    $data[] = $row_data;
                }
                $row_num++;
            }
            fclose($handle);
        }

        return ['success' => true, 'data' => $data];
    }

    /**
     * Convert zero-based column index back to Excel column letter (0->A, 1->B, 26->AA)
     */
    private function getColumnLetter($index) {
        $letter = '';
        $index++; // Convert to 1-based for calculation
        
        while ($index > 0) {
            $index--; // Adjust for 0-based offset
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intval($index / 26);
        }
        return $letter;
    }

    /**
     * Convert Excel column reference (A, B, AA, etc) to zero-based index
     */
    private function getCellColumnIndex($cell_ref) {
        // Extract column letters from cell reference (A1, B2, XFD1048576, etc.)
        preg_match('/^([A-Z]+)/', $cell_ref, $matches);
        if (!isset($matches[1])) {
            return -1;
        }
        
        $col_letters = $matches[1];
        $index = 0;
        foreach (str_split($col_letters) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }
        return $index - 1; // Convert to 0-based index
    }

    /**
     * Read XLSX file using zip extraction - robust version
     */
    private function readXLSX($file_path, $sheet_name = '') {
        try {
            $zip = new ZipArchive();
            if ($zip->open($file_path) !== true) {
                return ['success' => false, 'message' => 'ไม่สามารถอ่านไฟล์ Excel ได้'];
            }

            // Read sheet1.xml
            $sheet_xml_content = $zip->getFromName('xl/worksheets/sheet1.xml');
            if (!$sheet_xml_content) {
                $zip->close();
                return ['success' => false, 'message' => 'ไม่พบข้อมูลในไฟล์ Excel'];
            }

            // Get shared strings
            $shared_strings = [];
            $strings_xml_content = $zip->getFromName('xl/sharedStrings.xml');
            if ($strings_xml_content) {
                libxml_use_internal_errors(true);
                $strings_xml = simplexml_load_string($strings_xml_content);
                libxml_clear_errors();
                
                if ($strings_xml !== false) {
                    // Get all <si> elements (string items)
                    $namespaces = $strings_xml->getNamespaces(true);
                    $si_elements = isset($namespaces['']) 
                        ? $strings_xml->children($namespaces[''])->si 
                        : $strings_xml->si;
                    
                    if (!is_object($si_elements)) {
                        $si_elements = @$strings_xml->children()->si;
                    }
                    
                    if (is_object($si_elements)) {
                        foreach ($si_elements as $si) {
                            $text = '';
                            foreach ($si->children() as $child) {
                                if ($child->getName() === 't' || strpos($child->getName(), 't') !== false) {
                                    $text .= (string)$child;
                                }
                            }
                            $shared_strings[] = $text;
                        }
                    }
                }
            }

            // Parse sheet XML
            libxml_use_internal_errors(true);
            $sheet_xml = simplexml_load_string($sheet_xml_content);
            libxml_clear_errors();

            if ($sheet_xml === false) {
                $zip->close();
                return ['success' => false, 'message' => 'ไม่สามารถ parse ไฟล์ Excel'];
            }

            $data = [];
            $headers = [];

            // Get namespace
            $namespaces = $sheet_xml->getNamespaces(true);
            
            // Get <sheetData> element
            $sheet_data = null;
            if (isset($sheet_xml->sheetData)) {
                $sheet_data = $sheet_xml->sheetData;
            } else {
                // Try with namespaces
                $children = $sheet_xml->children();
                foreach ($children as $child) {
                    if ($child->getName() === 'sheetData' || strpos($child->getName(), 'sheetData') !== false) {
                        $sheet_data = $child;
                        break;
                    }
                }
            }

            if ($sheet_data === null) {
                $zip->close();
                return ['success' => false, 'message' => 'ไม่พบ sheetData ในไฟล์ Excel'];
            }

            $row_num = 0;

            // Process each row
            foreach ($sheet_data->children() as $row_elem) {
                $row_name = $row_elem->getName();
                if (strpos($row_name, 'row') === false && $row_name !== 'row') {
                    continue;
                }

                $row_data = [];
                $has_content = false;

                // Process each cell
                foreach ($row_elem->children() as $cell) {
                    $cell_name = $cell->getName();
                    if (strpos($cell_name, 'c') === false && $cell_name !== 'c') {
                        continue;
                    }

                    // Get cell reference
                    $cell_ref = (string)$cell['r'];
                    $col_index = $this->getCellColumnIndex($cell_ref);

                    // Get cell value
                    $cell_value = '';
                    $cell_type = (string)$cell['t'];

                    // Find <v> element
                    foreach ($cell->children() as $elem) {
                        $elem_name = $elem->getName();
                        if ($elem_name === 'v' || strpos($elem_name, 'v') !== false) {
                            $raw_value = (string)$elem;
                            
                            // If string type, lookup in shared strings
                            if ($cell_type === 's' && isset($shared_strings[(int)$raw_value])) {
                                $cell_value = $shared_strings[(int)$raw_value];
                            } else {
                                $cell_value = $raw_value;
                            }
                            break;
                        }
                    }

                    if ($row_num === 0) {
                        // Header row - ensure array is big enough
                        while (count($headers) <= $col_index) {
                            $headers[] = '';
                        }
                        $headers[$col_index] = $cell_value;
                    } else {
                        // Data row
                        if ($col_index >= 0 && $col_index < count($headers)) {
                            $header_name = $headers[$col_index];
                            if (empty($header_name)) {
                                $header_name = $this->getColumnLetter($col_index);
                            }
                            $row_data[$header_name] = $cell_value;
                            if (!empty($cell_value)) {
                                $has_content = true;
                            }
                        }
                    }
                }

                // Add row if it has content
                if ($row_num > 0 && $has_content) {
                    $data[] = $row_data;
                }

                $row_num++;
            }

            $zip->close();

            if (empty($data)) {
                return ['success' => false, 'message' => 'ไฟล์ Excel ว่างเปล่า'];
            }

            return ['success' => true, 'data' => $data];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'ข้อผิดพลาดการอ่านไฟล์: ' . $e->getMessage()];
        }
    }

    /**
     * Store imported data in database using HDC handler
     */
    private function storeImportedData($saved_name, $original_name, $user_id, $data) {
        try {
            // Insert into imported_files table
            $query = "INSERT INTO imported_files (file_name, original_name, user_id) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database Error: ' . $this->conn->error];
            }

            $stmt->bind_param("ssi", $saved_name, $original_name, $user_id);
            if (!$stmt->execute()) {
                return ['success' => false, 'message' => 'Database Error: ' . $this->conn->error];
            }

            $import_id = $stmt->insert_id;

            // Get all unique column names from all data rows
            $columns = [];
            foreach ($data as $row) {
                foreach (array_keys($row) as $col) {
                    if (!in_array($col, $columns)) {
                        $columns[] = $col;
                    }
                }
            }
            
            // Normalize column names for database (sanitize once, reuse)
            $col_mapping = []; // original => sanitized
            foreach ($columns as $col) {
                $sanitized = $this->sanitizeColumnName($col);
                $col_mapping[$col] = $sanitized;
            }
            
            // Re-key data array with sanitized column names and type conversion
            $normalized_data = [];
            foreach ($data as $row) {
                $normalized_row = [];
                foreach ($row as $col => $value) {
                    $sanitized_col = $col_mapping[$col] ?? $this->sanitizeColumnName($col);
                    
                    // Type conversion for common fields
                    $col_lower = strtolower($sanitized_col);
                    
                    // Convert Excel date serial to YYYY-MM-DD for date fields
                    if (strpos($col_lower, 'date') !== false || strpos($col_lower, 'serv') !== false) {
                        if (is_numeric($value) && $value > 1000) { // Probably a serial date
                            $value = $this->convertExcelDate($value);
                        }
                    }
                    
                    // Keep numeric values as strings for database
                    // (they'll be cast by MySQL based on column type)
                    
                    $normalized_row[$sanitized_col] = $value;
                }
                $normalized_data[] = $normalized_row;
            }
            
            // Use normalized column names
            $sanitized_columns = array_values(array_unique(array_values($col_mapping)));
            
            // Detect file type using HDC handler
            $detection = $this->hdc_handler->detectFileType($original_name, $sanitized_columns);
            $table_name = $detection['config']['table'] ?? 'generic_hdc_data';

            // Insert normalized data using HDC handler
            $insert_result = $this->hdc_handler->insertData($import_id, $table_name, $normalized_data);

            if ($insert_result['success']) {
                return [
                    'success' => true, 
                    'import_id' => $import_id,
                    'detection' => $detection,
                    'inserted_rows' => $insert_result['inserted'] ?? 0,
                    'failed_rows' => $insert_result['failed'] ?? 0,
                    'message' => $insert_result['message'] ?? 'บันทึกสำเร็จ'
                ];
            } else {
                return ['success' => false, 'message' => $insert_result['message'] ?? 'ไม่สามารถบันทึกข้อมูลได้'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * DEPRECATED - kept for backward compatibility
     */
    private function detectTable($file_name) {
        $lower_name = strtolower($file_name);
        
        if (strpos($lower_name, 'drug_opd') !== false || strpos($lower_name, 'drug') !== false) {
            return 'drug_opd';
        }
        
        return 'generic';
    }

    /**
     * DEPRECATED - kept for backward compatibility  
     */
    private function insertDrugOPDData($import_id, $data) {
        // Handled by HDC handler now
        return;
    }

    /**
     * DEPRECATED - kept for backward compatibility
     */
    private function insertGenericData($import_id, $file_name, $data) {
        // Handled by HDC handler now
        return;
    }

    /**
     * Get data from imported file (with pagination support)
     */
    public function getImportedData($import_id, $limit = 1000, $offset = 0) {
        return $this->hdc_handler->getImportData($import_id, $limit, $offset);
    }

    /**
     * Get imported files list with file type information
     */
    public function getImportedFiles($user_id = null, $limit = 50) {
        $query = "SELECT 
                    f.id, 
                    f.file_name, 
                    f.original_name, 
                    f.upload_date, 
                    f.user_id,
                    u.username,
                    u.full_name
                  FROM imported_files f
                  LEFT JOIN users u ON f.user_id = u.id";
        
        if ($user_id) {
            $query .= " WHERE f.user_id = " . intval($user_id);
        }
        
        $query .= " ORDER BY f.upload_date DESC LIMIT " . intval($limit);
        
        $result = $this->conn->query($query);
        $files = [];
        
        while ($row = $result->fetch_assoc()) {
            // Get file type info
            $detection = $this->hdc_handler->detectFileType($row['original_name']);
            $row['file_type'] = $detection['type'];
            $row['file_type_description'] = $detection['config']['description'];
            $row['row_count'] = $this->hdc_handler->getImportDataCount($row['id']);
            
            $files[] = $row;
        }
        
        return $files;
    }

    /**
     * Get data row count for import
     */
    public function getImportDataCount($import_id) {
        return $this->hdc_handler->getImportDataCount($import_id);
    }

    /**
     * Get column names for import data
     */
    public function getImportColumns($import_id) {
        return $this->hdc_handler->getImportColumns($import_id);
    }

    /**
     * Export import data to array (all rows)
     */
    public function exportToArray($import_id) {
        return $this->hdc_handler->exportToArray($import_id);
    }

    /**
     * Get supported HDC types
     */
    public function getSupportedHDCTypes() {
        return $this->hdc_handler->getSupportedTypes();
    }

    /**
     * Convert Excel serial date number to MySQL date format
     * Excel date serial: days since 1900-01-01
     */
    private function convertExcelDate($excel_date) {
        if (!is_numeric($excel_date) || empty($excel_date)) {
            return $excel_date; // Return as-is if not a number
        }
        
        // Excel serial date base: 1900-01-01
        // But Excel has a bug treating 1900 as a leap year, so serials > 59 need adjustment
        $serial = intval($excel_date);
        
        // Create base date
        $base_date = new DateTime('1899-12-30'); // This is serial 0
        
        try {
            // Add days
            $base_date->modify('+' . $serial . ' days');
            return $base_date->format('Y-m-d');
        } catch (Exception $e) {
            return $excel_date; // Return original if conversion fails
        }
    }

    /**
     * Sanitize column names to be MySQL safe
     * Matches HDCFileHandler::sanitizeColumnName()
     */
    private function sanitizeColumnName($name) {
        // Remove special characters, keep only alphanumeric and underscore
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        // Remove leading numbers
        $name = preg_replace('/^[0-9]+/', '', $name);
        // Limit to 64 characters (MySQL limit)
        return substr($name, 0, 64) ?: 'column_' . md5($name);
    }
}
