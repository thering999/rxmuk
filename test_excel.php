<?php
// Test Excel reading capabilities
echo "Testing Excel/ZIP/XML Support:\n";
echo "================================\n\n";

// Check extensions
$extensions = ['zip', 'ZipArchive', 'SimpleXML', 'xml'];
echo "Required Extensions:\n";
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext) || class_exists($ext);
    echo "✓ $ext: " . ($loaded ? "YES" : "NO") . "\n";
}

echo "\n\nPHP Version: " . phpversion() . "\n";
echo "Max Upload Size: " . ini_get('upload_max_filesize') . "\n";

// Check if uploads directory exists and is writable
$upload_dir = __DIR__ . '/../uploads';
echo "\nUploads Directory:\n";
echo "✓ Exists: " . (is_dir($upload_dir) ? "YES" : "NO") . "\n";
echo "✓ Writable: " . (is_writable($upload_dir) ? "YES" : "NO") . "\n";

?>
