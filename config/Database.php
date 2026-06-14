<?php
/**
 * Database Connection Class
 */
class Database {
    private $host = 'mysql-docker';
    private $db_name = 'rxmuk_db';
    private $user = 'root';
    private $pass = 'Ssj4900036!@#';
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
