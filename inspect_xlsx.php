<?php
/**
 * Deep XLSX inspection - read XML directly
 */

// Find any xlsx file in the workspace
$uploads_dir = __DIR__ . '/uploads';
if (!is_dir($uploads_dir)) {
    echo "Uploads directory not found\n";
    exit;
}

$xlsx_files = glob($uploads_dir . '/*.xlsx');
if (empty($xlsx_files)) {
    echo "No XLSX files found\n";
    exit;
}

$file = $xlsx_files[0];
echo "Testing: " . basename($file) . "\n\n";

$zip = new ZipArchive();
if (!$zip->open($file)) {
    echo "Cannot open ZIP\n";
    exit;
}

// Get sheet1.xml
$sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
if (!$sheet_xml) {
    echo "No sheet1.xml found\n";
    exit;
}

// Parse and show structure
libxml_use_internal_errors(true);
$xml = simplexml_load_string($sheet_xml);
libxml_clear_errors();

echo "=== Sheet Structure ===\n";
$row_count = 0;
$max_col_index = -1;

foreach ($xml->children() as $row) {
    if (strpos($row->getName(), 'row') !== false) {
        $row_count++;
        $cells = [];
        
        foreach ($row->children() as $cell) {
            if (strpos($cell->getName(), 'c') !== false) {
                $cell_ref = (string)$cell['r'];
                
                // Extract column letter
                preg_match('/^([A-Z]+)/', $cell_ref, $matches);
                if (isset($matches[1])) {
                    $col_letters = $matches[1];
                    // Calculate column index
                    $col_index = 0;
                    foreach (str_split($col_letters) as $char) {
                        $col_index = $col_index * 26 + (ord($char) - ord('A') + 1);
                    }
                    $col_index--; // 0-based
                    
                    if ($col_index > $max_col_index) {
                        $max_col_index = $col_index;
                    }
                    
                    // Get cell value
                    $cell_value = '';
                    foreach ($cell->children() as $child) {
                        if (strpos($child->getName(), 'v') !== false) {
                            $cell_value = (string)$child;
                            break;
                        }
                    }
                    
                    $cells[$col_index] = [
                        'ref' => $cell_ref,
                        'index' => $col_index,
                        'value' => $cell_value,
                        'type' => (string)$cell['t'] ?? 'n'
                    ];
                }
            }
        }
        
        // Show first 5 rows detail
        if ($row_count <= 5) {
            echo "\nRow $row_count:\n";
            ksort($cells);
            foreach ($cells as $idx => $cell_data) {
                echo "  Col[$idx] {$cell_data['ref']}: '{$cell_data['value']}' (type: {$cell_data['type']})\n";
            }
            echo "  Total cells in row: " . count($cells) . "\n";
        }
        
        if ($row_count > 10) break;
    }
}

$zip->close();

echo "\n=== Summary ===\n";
echo "Total rows read: $row_count\n";
echo "Max column index: $max_col_index\n";
echo "Total columns: " . ($max_col_index + 1) . "\n";
?>
