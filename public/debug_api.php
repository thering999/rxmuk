<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

echo "Testing API logic...\n";

try {
    $importer = new ExcelImporter();
    $files = $importer->getImportedFiles(null, 10);
    
    echo "Found " . count($files) . " files.\n";
    foreach ($files as $f) {
        echo "- ID: {$f['id']}, File: {$f['original_name']}, Type: {$f['file_type']}, Rows: {$f['row_count']}\n";
    }

    // Try to simulate the latest data fetch
    $latest_id = null;
    $found_file = null;
    foreach ($files as $file) {
        if ($file['file_type'] === 'drug_opd' || $file['file_type'] === 's_drug_opd') {
            $latest_id = $file['id'];
            $found_file = $file;
            break;
        }
    }

    if ($latest_id) {
        echo "Fetching data for ID: $latest_id\n";
        $file_type = $found_file['file_type'];
        $row_count = intval($found_file['row_count']);
        
        if ($file_type === 'drug_opd' && $row_count > 50000) {
            echo "Using optimized grouping query...\n";
            $sql = "SELECT hospcode, didstd, dname, 
                           SUM(amount) as sumamount, COUNT(*) as count, 
                           SUM(cost) as sumdrugcost, SUM(price) as sumdrugprice,
                           MAX(date_serv) as date_serv
                    FROM drug_opd 
                    WHERE import_id = ? 
                    GROUP BY hospcode, didstd, dname";
            $stmt = $importer->getConnection()->prepare($sql);
            $stmt->bind_param('i', $latest_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo "Grouped rows: " . $result->num_rows . "\n";
        } else {
            echo "Fetching all rows...\n";
            $data = $importer->exportToArray($latest_id);
            echo "Data rows: " . count($data) . "\n";
        }
    } else {
        echo "No suitable file found.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
