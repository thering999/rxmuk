<?php
session_start();

require_once __DIR__ . '/../../config/Database.php';

/**
 * Authentication Class
 */
class Auth {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
    }

    /**
     * User Login
     */
    public function login($username, $password) {
        $query = "SELECT id, username, full_name, password, role FROM users WHERE username = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Query Error: ' . $this->conn->error];
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }

        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'] ?? 'user';
            $_SESSION['logged_in'] = true;
            
            return ['success' => true, 'message' => 'เข้าสู่ระบบสำเร็จ'];
        } else {
            return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }
    }

    /**
     * User Logout
     */
    public function logout() {
        // Ensure session is started before destroying
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear all session data
        $_SESSION = [];
        
        // Destroy the session
        session_destroy();
        
        return ['success' => true, 'message' => 'ออกจากระบบสำเร็จ'];
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Get current user ID
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user role
     */
    public static function getUserRole() {
        return $_SESSION['role'] ?? 'user';
    }

    /**
     * Check if current user is admin
     */
    public static function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    /**
     * Check if user has permission
     */
    public static function hasPermission($required_role = 'user') {
        if (!self::isLoggedIn()) {
            return false;
        }

        if ($required_role === 'admin') {
            return self::isAdmin();
        }

        return true;
    }

    /**
     * Register new user (for admin only)
     */
    public function register($username, $password, $full_name, $role = 'user') {
        // Validate role
        if (!in_array($role, ['admin', 'user'])) {
            $role = 'user';
        }

        // Check if username exists
        $query = "SELECT id FROM users WHERE username = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'ชื่อผู้ใช้นี้มีอยู่แล้ว'];
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        $query = "INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssss", $username, $hashed_password, $full_name, $role);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'สร้างบัญชีผู้ใช้สำเร็จ'];
        } else {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $this->conn->error];
        }
    }

    /**
     * Get all users
     */
    public function getAllUsers() {
        $query = "SELECT id, username, full_name, role, created_at FROM users ORDER BY created_at DESC";
        $result = $this->conn->query($query);
        
        if (!$result) {
            return [];
        }
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return $users;
    }

    /**
     * Get user by ID
     */
    public function getUserById($user_id) {
        $query = "SELECT id, username, full_name, role, created_at FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        return $result->fetch_assoc();
    }

    /**
     * Update user (username and full_name)
     */
    public function updateUser($user_id, $username, $full_name) {
        // Check if new username exists (if changed)
        $current_user = $this->getUserById($user_id);
        
        if ($current_user['username'] !== $username) {
            $query = "SELECT id FROM users WHERE username = ? AND id != ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("si", $username, $user_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                return ['success' => false, 'message' => 'ชื่อผู้ใช้นี้มีอยู่แล้ว'];
            }
        }

        $query = "UPDATE users SET username = ?, full_name = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $username, $full_name, $user_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'อัปเดตข้อมูลผู้ใช้สำเร็จ'];
        } else {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $this->conn->error];
        }
    }

    /**
     * Change user password
     */
    public function changePassword($user_id, $old_password, $new_password) {
        // Get current password
        $query = "SELECT password FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'ไม่พบผู้ใช้'];
        }
        
        $user = $result->fetch_assoc();
        
        // Verify old password
        if (!password_verify($old_password, $user['password'])) {
            return ['success' => false, 'message' => 'รหัสผ่านเก่าไม่ถูกต้อง'];
        }
        
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        // Update password
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'];
        } else {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $this->conn->error];
        }
    }

    /**
     * Delete user
     */
    public function deleteUser($user_id) {
        // Prevent deleting if it's the only admin-like user
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'ลบผู้ใช้สำเร็จ'];
        } else {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $this->conn->error];
        }
    }

    /**
     * Reset user password (admin function)
     */
    public function resetUserPassword($user_id, $new_password) {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'รีเซ็ตรหัสผ่านสำเร็จ'];
        } else {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $this->conn->error];
        }
    }

    /**
     * Update user role (admin only)
     */
    public function updateUserRole($user_id, $new_role) {
        // Validate role
        if (!in_array($new_role, ['admin', 'user'])) {
            return ['success' => false, 'message' => 'สิทธิ์ไม่ถูกต้อง'];
        }

        // Prevent demoting the last admin
        if ($new_role === 'user') {
            $query = "SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin' AND id != ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result['admin_count'] == 0) {
                return ['success' => false, 'message' => 'ต้องมี Admin อย่างน้อย 1 คน'];
            }
        }

        $query = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $new_role, $user_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'อัปเดตสิทธิ์สำเร็จ'];
        } else {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $this->conn->error];
        }
    }

    /**
     * Get role label
     */
    public static function getRoleLabel($role) {
        $labels = [
            'admin' => '👮 Admin (ผู้ดูแลระบบ)',
            'user' => '👤 User (ผู้ใช้ทั่วไป)'
        ];
        return $labels[$role] ?? $role;
    }
}

