<?php
/**
 * Deep XLSX Debug - อ่านข้อมูลจาก XML โดยตรง
 */

$uploads_dir = __DIR__ . '/uploads';
$xlsx_files = glob($uploads_dir . '/*.xlsx');

if (empty($xlsx_files)) {
    die("No XLSX files found\n");
}

$file = end($xlsx_files); // Get latest file
echo "=== XLSX File Debug ===\n";
echo "File: " . basename($file) . "\n";
echo "Size: " . filesize($file) . " bytes\n\n";

$zip = new ZipArchive();
if (!$zip->open($file)) {
    die("Cannot open ZIP\n");
}

// List all files in ZIP
echo "=== Files in ZIP ===\n";
for ($i = 0; $i < $zip->numFiles; $i++) {
    echo $zip->getNameIndex($i) . "\n";
}

echo "\n=== Sheet XML Structure ===\n";

// Get sheet1.xml
$sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
if (!$sheet_xml) {
    die("No sheet1.xml\n");
}

echo "Sheet XML size: " . strlen($sheet_xml) . " bytes\n\n";

// Parse with SimpleXML
libxml_use_internal_errors(true);
$xml = simplexml_load_string($sheet_xml);

if ($xml === false) {
    echo "Failed to parse XML\n";
    foreach (libxml_get_errors() as $error) {
        echo "  " . $error->message . "\n";
    }
    die();
}

libxml_clear_errors();

// Count rows
echo "=== Row Count ===\n";
$row_count = 0;
$max_cells = 0;

foreach ($xml->children() as $child) {
    $name = $child->getName();
    echo "Child: $name\n";
    
    if (strpos($name, 'row') !== false) {
        $row_count++;
        $cell_count = count($child->children());
        if ($cell_count > $max_cells) {
            $max_cells = $cell_count;
        }
        
        if ($row_count <= 5) {
            echo "  Row $row_count: $cell_count cells\n";
            foreach ($child->children() as $cell) {
                $cell_ref = (string)$cell['r'];
                $cell_value = '';
                foreach ($cell->children() as $child_elem) {
                    if (strpos($child_elem->getName(), 'v') !== false) {
                        $cell_value = (string)$child_elem;
                        break;
                    }
                }
                echo "    $cell_ref: " . substr($cell_value, 0, 50) . "\n";
            }
        }
    }
}

echo "\nTotal rows: $row_count\n";
echo "Max cells per row: $max_cells\n\n";

// Check shared strings
echo "=== Shared Strings ===\n";
$strings_xml = $zip->getFromName('xl/sharedStrings.xml');
if ($strings_xml) {
    $strings_doc = simplexml_load_string($strings_xml);
    if ($strings_doc !== false) {
        $string_count = 0;
        foreach ($strings_doc->children() as $si) {
            $string_count++;
            if ($string_count <= 10) {
                $text = '';
                foreach ($si->children() as $child) {
                    if (isset($child->t)) {
                        $text .= (string)$child->t;
                    }
                }
                echo "  String $string_count: $text\n";
            }
        }
        echo "Total strings: $string_count\n";
    }
} else {
    echo "No sharedStrings.xml\n";
}

$zip->close();
echo "\n=== END DEBUG ===\n";
?>
