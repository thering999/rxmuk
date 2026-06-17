<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

echo "Testing History API...\n";

try {
    $importer = new ExcelImporter();
    echo "Importer initialized.\n";
    
    $files = $importer->getImportedFiles(null, 10);
    echo "Files count: " . count($files) . "\n";
    
    foreach ($files as $f) {
        echo "- {$f['original_name']} ({$f['upload_date']}) [{$f['row_count']} rows]\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
