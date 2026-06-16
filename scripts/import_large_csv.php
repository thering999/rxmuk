<?php
/**
 * CLI Runner for large CSV imports
 * Usage: php scripts/import_large_csv.php <filename> <user_id>
 */

if (PHP_SAPI !== 'cli') {
    die("This script can only be run from the command line.\n");
}

if ($argc < 3) {
    echo "Usage: php scripts/import_large_csv.php <filename_in_uploads> <user_id>\n";
    echo "Example: php scripts/import_large_csv.php tmp_drug_opd.csv 1\n";
    exit(1);
}

$filename = $argv[1];
$user_id = intval($argv[2]);

require_once __DIR__ . '/../src/Import/ChunkedImporter.php';
require_once __DIR__ . '/../config/Database.php';

$upload_dir = __DIR__ . '/../uploads/';
$file_path = $upload_dir . $filename;

if (!file_exists($file_path)) {
    echo "Error: File not found at $file_path\n";
    exit(1);
}

echo "=== RxMuk Large File Importer ===\n";
echo "File: $filename\n";
echo "User ID: $user_id\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

$db = new Database();
$conn = $db->connect();

// 1. Create entry in imported_files to get an ID
$stmt = $conn->prepare("INSERT INTO imported_files (file_name, original_name, user_id, status) VALUES (?, ?, ?, 'pending')");
$stmt->bind_param("ssi", $filename, $filename, $user_id);
if (!$stmt->execute()) {
    die("Error creating import record: " . $conn->error . "\n");
}

$import_id = $stmt->insert_id;
echo "Import ID: $import_id created.\n";

// 2. Start Chunked Importer
$importer = new ChunkedImporter();
$startTime = microtime(true);

try {
    $result = $importer->importCSV($import_id, $file_path, 2500);
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    if ($result['success']) {
        echo "\n✅ Import Successful!\n";
        echo "Total Rows: " . ($result['inserted'] + $result['failed']) . "\n";
        echo "Inserted: " . $result['inserted'] . "\n";
        echo "Failed: " . $result['failed'] . "\n";
        echo "Duration: $duration seconds\n";
    } else {
        echo "\n❌ Import Failed: " . $result['message'] . "\n";
    }
} catch (Exception $e) {
    echo "\n💥 Critical Error: " . $e->getMessage() . "\n";
    $conn->query("UPDATE imported_files SET status = 'failed', error_message = '" . $conn->real_escape_string($e->getMessage()) . "' WHERE id = $import_id");
}

echo "\nFinished at: " . date('Y-m-d H:i:s') . "\n";
