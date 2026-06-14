<?php
/**
 * Debug XLSX Reading - Check what data is extracted from tmp_drug_opd.xlsx
 */

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/Import/ExcelImporter.php';

echo "=== XLSX READING DEBUG ===\n\n";

$importer = new ExcelImporter();

// Get the last uploaded tmp_drug_opd.xlsx file
$db = new Database();
$conn = $db->connect();

$query = "SELECT file_name FROM imported_files WHERE original_name LIKE '%tmp_drug_opd.xlsx%' ORDER BY upload_date DESC LIMIT 1";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $file_path = __DIR__ . '/uploads/' . $row['file_name'];
    
    echo "File path: $file_path\n";
    echo "File exists: " . (file_exists($file_path) ? "Yes" : "No") . "\n";
    
    if (file_exists($file_path)) {
        echo "File size: " . filesize($file_path) . " bytes\n\n";
        
        // Try to read it using reflection or direct file reading
        echo "Reading file structure...\n";
        
        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        echo "Extension: $file_ext\n\n";
        
        if ($file_ext === 'xlsx') {
            // Try to read as ZIP
            $zip = new ZipArchive();
            if ($zip->open($file_path) === TRUE) {
                echo "✓ ZIP opened successfully\n";
                echo "Files in ZIP:\n";
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    echo "  - " . $zip->getNameIndex($i) . "\n";
                }
                
                // Try to read worksheet
                $sheet_path = 'xl/worksheets/sheet1.xml';
                if ($zip->locateName($sheet_path) !== false) {
                    echo "\n✓ Worksheet found\n";
                    $sheet_content = $zip->getFromName($sheet_path);
                    $sheet_size = strlen($sheet_content);
                    echo "  Size: $sheet_size bytes\n";
                    
                    // Check for data
                    if (strpos($sheet_content, '<sheetData>') !== false) {
                        echo "  Contains sheetData: Yes\n";
                        
                        // Count cells
                        preg_match_all('/<c/', $sheet_content, $matches);
                        echo "  Cell count: " . count($matches[0]) . "\n";
                    } else {
                        echo "  Contains sheetData: No\n";
                    }
                } else {
                    echo "\n✗ Worksheet not found\n";
                }
                
                // Check shared strings
                if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                    echo "\n✓ Shared strings found\n";
                    $strings = $zip->getFromName('xl/sharedStrings.xml');
                    preg_match_all('/<si>/', $strings, $matches);
                    echo "  String count: " . count($matches[0]) . "\n";
                } else {
                    echo "\n✗ Shared strings not found\n";
                }
                
                $zip->close();
            } else {
                echo "✗ Failed to open as ZIP\n";
            }
        }
    }
} else {
    echo "No previous tmp_drug_opd.xlsx import found\n";
    echo "Try uploading the file first\n";
}

$conn->close();
echo "\n=== DEBUG COMPLETE ===\n";
?>
