#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
until mysqladmin ping -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --silent; do
  echo 'waiting for MySQL...'
  sleep 1
done
echo "MySQL is ready!"

# Check if database is already initialized
if ! mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SELECT 1 FROM users LIMIT 1;" 2>/dev/null; then
  echo "Initializing database..."
  
  # Create tables
  mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" << EOF
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imported_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_upload_date (upload_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drug_opd (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_id INT NOT NULL,
    hospcode VARCHAR(5),
    pid VARCHAR(15),
    seq VARCHAR(15),
    date_serv DATE,
    didstd VARCHAR(24),
    dname VARCHAR(255),
    amount DECIMAL(10,2),
    unit VARCHAR(50),
    price DECIMAL(10,2),
    cost DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (import_id) REFERENCES imported_files(id) ON DELETE CASCADE,
    INDEX idx_import_id (import_id),
    INDEX idx_hospcode (hospcode),
    INDEX idx_pid (pid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imported_files_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_id INT NOT NULL,
    data_json LONGTEXT COLLATE utf8mb4_unicode_ci,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (import_id) REFERENCES imported_files(id) ON DELETE CASCADE,
    INDEX idx_import_id (import_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (username, password, full_name) 
VALUES ('admin', '\$2y\$10\$sR9cXxJ7V8rJ5Xq4B2eX8e9Y5q8Q3j7F6k8L4M5N6O7P8Q9R0S1T2U3V4', 'System Administrator')
ON DUPLICATE KEY UPDATE username=username;
EOF
  
  echo "Database initialized successfully!"
else
  echo "Database already initialized, skipping setup..."
fi

# Update database config with Docker environment
cat > /var/www/html/config/Database.php << 'PHPEOF'
<?php
/**
 * Database Connection Class
 */
class Database {
    private $host = 'DOCKER_DB_HOST';
    private $db_name = 'DOCKER_DB_NAME';
    private $user = 'DOCKER_DB_USER';
    private $pass = 'DOCKER_DB_PASSWORD';
    private $conn;

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->db_name);
            
            if ($this->conn->connect_error) {
                throw new Exception('Connection Failed: ' . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
            return $this->conn;
        } catch (Exception $e) {
            echo "Database Error: " . $e->getMessage();
            return null;
        }
    }

    public function disconnect() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
PHPEOF

# Replace placeholders with actual Docker environment variables
sed -i "s|DOCKER_DB_HOST|${DB_HOST}|g" /var/www/html/config/Database.php
sed -i "s|DOCKER_DB_NAME|${DB_NAME}|g" /var/www/html/config/Database.php
sed -i "s|DOCKER_DB_USER|${DB_USER}|g" /var/www/html/config/Database.php
sed -i "s|DOCKER_DB_PASSWORD|${DB_PASSWORD}|g" /var/www/html/config/Database.php

# Ensure uploads directory exists and has proper permissions
mkdir -p /var/www/html/uploads
chmod 777 /var/www/html/uploads

# Start Apache
echo "Starting Apache..."
apache2-foreground
