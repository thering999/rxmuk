<?php
/**
 * Upgrade database schema for user roles
 */

require_once __DIR__ . '/config/Database.php';

$db = new Database();
$conn = $db->connect();

echo "=== UPGRADING DATABASE SCHEMA ===\n\n";

// Check if role column exists
$result = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'role'");

if ($result->num_rows === 0) {
    echo "Adding 'role' column to users table...\n";
    
    $sql = "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user' AFTER full_name";
    
    if ($conn->query($sql)) {
        echo "✓ Role column added successfully\n\n";
    } else {
        echo "✗ Failed to add role column: " . $conn->error . "\n\n";
    }
} else {
    echo "✓ Role column already exists\n\n";
}

// Display updated schema
echo "Final users table structure:\n";
$result = $conn->query("SHOW COLUMNS FROM users");
while ($col = $result->fetch_assoc()) {
    echo sprintf("  %-15s %s %s\n", 
        $col['Field'], 
        $col['Type'],
        ($col['Null'] === 'NO' ? 'NOT NULL' : 'NULLABLE')
    );
}

echo "\n=== SCHEMA UPDATE COMPLETE ===\n";

$conn->close();
?>
