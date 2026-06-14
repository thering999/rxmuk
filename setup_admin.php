<?php
require_once 'config/Database.php';

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo "✗ Database connection failed\n";
    exit(1);
}

// Delete existing admin user
$conn->query("DELETE FROM users WHERE username='admin'");

// Insert new admin user with password hash for "123456"
$password_hash = password_hash('123456', PASSWORD_BCRYPT, ['cost' => 10]);
$username = 'admin';
$full_name = 'System Administrator';

$query = "INSERT INTO users (username, password, full_name) VALUES (?, ?, ?)";
$stmt = $conn->prepare($query);

if (!$stmt) {
    echo "✗ Prepare failed: " . $conn->error . "\n";
    exit(1);
}

$stmt->bind_param("sss", $username, $password_hash, $full_name);

if ($stmt->execute()) {
    echo "✓ Admin user created successfully\n";
    echo "Username: admin\n";
    echo "Password: 123456\n";
} else {
    echo "✗ Error: " . $conn->error . "\n";
}

$conn->close();
?>
