<?php
/**
 * API: Get Latest Imported Data for SPA (Optimized)
 * Returns the most recent data from Cache or Database as JSON
 */

require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

header('Content-Type: application/json');

$upload_dir = __DIR__ . '/../uploads/';
$spa_cache_file = $upload_dir . 'last_spa_data.json';

try {
    // 1. Priority: Check if there's a recent SPA Cache (saved via API)
    if (file_exists($spa_cache_file)) {
        $mtime = filemtime($spa_cache_file);
        // If file is fresh (less than 24 hours old), use it
        if (time() - $mtime < 86400) {
            $cache_json = file_get_contents($spa_cache_file);
            $cache_data = json_decode($cache_json, true);
            if ($cache_data && isset($cache_data['transactions'])) {
                echo json_encode([
                    'success' => true,
                    'source' => 'Server Cache',
                    'count' => count($cache_data['transactions']),
                    'filename' => $cache_data['filename'] ?? 'Cached Data',
                    'data' => $cache_data['transactions']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    // 2. Second Priority: Latest data from Database
    $importer = new ExcelImporter();
    $files = $importer->getImportedFiles(null, 10);
    
    $latest_import_id = null;
    $found_file = null;
    
    // Try to find drug_opd first
    foreach ($files as $file) {
        if ($file['file_type'] === 'drug_opd' || $file['file_type'] === 's_drug_opd') {
            $latest_import_id = $file['id'];
            $found_file = $file;
            break;
        }
    }

    // If not found, just take the latest file with any data
    if (!$latest_import_id && !empty($files)) {
        foreach ($files as $file) {
            if ($file['row_count'] > 0) {
                $latest_import_id = $file['id'];
                $found_file = $file;
                break;
            }
        }
    }

    if ($latest_import_id) {
        $file_type = $found_file['file_type'] ?? '';
        $row_count = intval($found_file['row_count'] ?? 0);
        
        // If it's the raw large table (drug_opd) and has many rows, summarize in SQL
        if ($file_type === 'drug_opd' && $row_count > 50000) {
            $sql = "SELECT hospcode, amphurCode as amphur, didstd, dname, 
                           SUM(amount) as sumamount, COUNT(*) as count, 
                           SUM(cost) as sumdrugcost, SUM(price) as sumdrugprice,
                           MAX(date_serv) as date_serv
                    FROM drug_opd 
                    WHERE import_id = ? 
                    GROUP BY hospcode, amphurCode, didstd, dname";
            $stmt = $importer->getConnection()->prepare($sql);
            $stmt->bind_param('i', $latest_import_id);
            $stmt->execute();
            $db_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $db_data = $importer->exportToArray($latest_import_id);
        }
        
        // Helper to find column regardless of case
        $findCol = function($row, $possibilities) {
            foreach ($possibilities as $p) {
                foreach ($row as $key => $val) {
                    if (strcasecmp(trim($key), $p) == 0) return $val;
                }
            }
            // Substring search as fallback
            foreach ($possibilities as $p) {
                foreach ($row as $key => $val) {
                    if (stripos(trim($key), $p) !== false) return $val;
                }
            }
            return null;
        };

        $mapped_data = array_map(function($row) use ($findCol) {
            $date = $findCol($row, ['date_serv', 'date', 'visit_date', 'HDC_DATE', 'serv', 'd_update']);
            return [
                'pid' => $findCol($row, ['pid', 'CID', 'id', 'hn']) ?? 'Unknown',
                'didstd' => $findCol($row, ['didstd', 'did', 'drug_code', 'standard_code']) ?? 'N/A',
                'dname' => $findCol($row, ['dname', 'drug_name', 'name', 'item_name']) ?? 'ไม่ระบุตัวยา',
                'hospcode' => $findCol($row, ['hospcode', 'hosp', 'unit_code', 'pcucode']) ?? 'Unknown',
                'hospname' => $row['hospname'] ?? '',
                'amphurCode' => $row['amphurCode'] ?? $row['amphur_code'] ?? $row['AMPHUR'] ?? '',
                'amphurName' => $row['amphurName'] ?? '',
                'date' => $date ?? '',
                'month' => (isset($date) && strlen($date) >= 7) ? substr($date, 0, 7) : '',
                'amount' => floatval($findCol($row, ['amount', 'sumamount', 'qty', 'quantity']) ?? 0),
                'price' => floatval($findCol($row, ['price', 'sumdrugprice', 'sumprice', 'total_price']) ?? 0),
                'cost' => floatval($findCol($row, ['cost', 'sumdrugcost', 'sumcost', 'total_cost']) ?? 0),
                'uploadTime' => $row['upload_date'] ?? ''
            ];
        }, $db_data);

        echo json_encode([
            'success' => true,
            'source' => 'Database',
            'count' => count($mapped_data),
            'filename' => $found_file['original_name'] ?? 'Database',
            'data' => $mapped_data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. Final Fallback: Empty response
    echo json_encode([
        'success' => false,
        'message' => 'No data found in cache or database',
        'data' => []
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
