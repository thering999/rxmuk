<?php
/**
 * Database Connection Class
 */
class Database {
    private $host;
    private $db_name;
    private $user;
    private $pass;
    private $conn;

    // Credentials come from the environment (docker-compose / .env), never from the public repo
    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'mysql-docker';
        $this->db_name = getenv('DB_NAME') ?: 'rxmuk_db';
        $this->user = getenv('DB_USER') ?: 'root';
        $this->pass = getenv('DB_PASSWORD') ?: '';
    }

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
