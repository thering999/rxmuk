<?php
/**
 * XLSX Parsing Deep Debug
 * Shows exactly what's being extracted from tmp_drug_opd.xlsx
 */

echo "=== DEEP XLSX PARSING DEBUG ===\n\n";

// Get the tmp_drug_opd file (not s_tmp_drug_opd)  
// Manually check for files in uploads directory
$uploads_dir = __DIR__ . '/uploads/';
$files = glob($uploads_dir . '*tmp_drug_opd.xlsx');
$tmp_file = null;

foreach ($files as $file) {
    if (strpos(basename($file), 's_tmp_drug') === false) {
        // Found a non-summary file
        $tmp_file = $file;
        break;
    }
}

if ($tmp_file === null) {
    echo "No tmp_drug_opd.xlsx file found in uploads\n";
    exit;
}

$file_path = $tmp_file;

// Manual XLSX parsing with debugging
$zip = new ZipArchive();
if ($zip->open($file_path) !== true) {
    echo "Failed to open ZIP\n";
    exit;
}

// Get sheet XML
$sheet_xml_content = $zip->getFromName('xl/worksheets/sheet1.xml');
$strings_xml_content = $zip->getFromName('xl/sharedStrings.xml');

// Parse shared strings first
$shared_strings = [];
libxml_use_internal_errors(true);
$strings_xml = simplexml_load_string($strings_xml_content);
if ($strings_xml !== false) {
    foreach ($strings_xml->children() as $si) {
        $text = '';
        foreach ($si->children() as $child) {
            if (strpos($child->getName(), 't') !== false) {
                $text .= (string)$child;
            }
        }
        $shared_strings[] = $text;
    }
}
libxml_clear_errors();

echo "Shared strings loaded: " . count($shared_strings) . "\n\n";

// Parse sheet XML
$sheet_xml = simplexml_load_string($sheet_xml_content);
if ($sheet_xml === false) {
    echo "Failed to parse sheet XML\n";
    exit;
}

// Get sheetData
$sheet_data = $sheet_xml->sheetData;
if ($sheet_data === null) {
    echo "No sheetData found\n";
    exit;
}

echo "Processing rows...\n";

$headers = [];
$row_num = 0;
$data_rows = 0;
$empty_rows = 0;

// Process first 10 rows to debug
foreach ($sheet_data->children() as $row_elem) {
    if ($row_num >= 10) break; // Just first 10 rows for debug
    
    $cells = [];
    $col_index_max = 0;
    
    foreach ($row_elem->children() as $cell) {
        $cell_ref = (string)$cell['r'];
        $col_index = getCellColumnIndex($cell_ref);
        $cell_type = (string)$cell['t'];
        
        // Get value
        $cell_value = '';
        foreach ($cell->children() as $elem) {
            if (strpos($elem->getName(), 'v') !== false) {
                $raw_value = (string)$elem;
                if ($cell_type === 's' && isset($shared_strings[(int)$raw_value])) {
                    $cell_value = $shared_strings[(int)$raw_value];
                } else {
                    $cell_value = $raw_value;
                }
                break;
            }
        }
        
        $cells[$col_index] = $cell_value;
        $col_index_max = max($col_index_max, $col_index);
    }
    
    // Debug output
    echo sprintf("Row %d: %d cells (max col: %d)\n", $row_num, count($cells), $col_index_max);
    
    if ($row_num === 0) {
        // Headers
        $headers = $cells;
        echo "  Headers: " . implode(", ", array_slice(array_values($cells), 0, 5)) . "...\n";
    } else {
        // Data
        $has_content = false;
        foreach ($cells as $val) {
            if (!empty($val)) {
                $has_content = true;
                break;
            }
        }
        
        if ($has_content) {
            $data_rows++;
            echo "  Data row - values: " . implode(", ", array_slice(array_values($cells), 0, 5)) . "...\n";
        } else {
            $empty_rows++;
            echo "  Empty row\n";
        }
    }
    
    $row_num++;
}

// Count total rows
echo "\n\nScanning all rows...\n";
$row_num = 0;
$data_count = 0;
$empty_count = 0;
$header_cols = 0;

foreach ($sheet_data->children() as $row_elem) {
    if ($row_num === 0) {
        // Count header columns
        $header_cols = count($row_elem->children());
    } else {
        $data_cols = count($row_elem->children());
        $has_content = false;
        
        foreach ($row_elem->children() as $cell) {
            foreach ($cell->children() as $elem) {
                if (strpos($elem->getName(), 'v') !== false) {
                    if (!empty((string)$elem)) {
                        $has_content = true;
                        break 2;
                    }
                }
            }
        }
        
        if ($has_content) {
            $data_count++;
        } else {
            $empty_count++;
        }
    }
    
    $row_num++;
}

echo "\nTotal rows in file: $row_num\n";
echo "Header columns: $header_cols\n";
echo "Data rows with content: $data_count\n";
echo "Empty rows: $empty_count\n";

$zip->close();

echo "\n=== DEBUG COMPLETE ===\n";

function getCellColumnIndex($cell_ref) {
    preg_match('/^([A-Z]+)/', $cell_ref, $matches);
    if (!isset($matches[1])) {
        return -1;
    }
    
    $col_letters = $matches[1];
    $index = 0;
    foreach (str_split($col_letters) as $char) {
        $index = $index * 26 + (ord($char) - ord('A') + 1);
    }
    return $index - 1;
}
?>
