<?php
require_once __DIR__ . '/config/Database.php';
$db = new Database();
$conn = $db->connect();
$result = $conn->query('SELECT DISTINCT original_name FROM imported_files');
while($row = $result->fetch_assoc()) {
    echo $row['original_name'] . "\n";
}
$conn->close();
?>
