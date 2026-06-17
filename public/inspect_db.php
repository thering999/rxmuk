<?php
require_once __DIR__ . '/../src/Import/ExcelImporter.php';
$importer = new ExcelImporter();
$conn = $importer->getConnection();

echo "Table: drug_opd\n";
$res = $conn->query("DESCRIBE drug_opd");
while($row = $res->fetch_assoc()) {
    echo "- {$row['Field']} ({$row['Type']})\n";
}

echo "\nLatest 3 rows for drug_opd (any import):\n";
$res = $conn->query("SELECT * FROM drug_opd ORDER BY id DESC LIMIT 3");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\nImport history counts:\n";
$res = $conn->query("SELECT import_id, COUNT(*) as cnt FROM drug_opd GROUP BY import_id");
while($row = $res->fetch_assoc()) {
    echo "- ID {$row['import_id']}: {$row['cnt']} rows\n";
}

echo "\nSummary table: s_drug_opd\n";
$res = $conn->query("SELECT import_id, COUNT(*) as cnt FROM s_drug_opd GROUP BY import_id");
while($row = $res->fetch_assoc()) {
    echo "- ID {$row['import_id']}: {$row['cnt']} rows\n";
}
