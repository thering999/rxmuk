<?php
require_once __DIR__ . '/../src/Import/ExcelImporter.php';
$importer = new ExcelImporter();
$files = $importer->getImportedFiles(null, 10);
header('Content-Type: application/json');
echo json_encode($files, JSON_PRETTY_PRINT);
