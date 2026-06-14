<?php
/**
 * User Management System
 * Complete CRUD interface for managing users
 */

session_start();
require_once __DIR__ . '/../src/Auth/Auth.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Check if user is admin (only admins can manage users)
if (!Auth::isAdmin()) {
    die('❌ ไม่มีสิทธิ์เข้าถึงหน้านี้ (เฉพาะ Admin เท่านั้น)');
}

$auth = new Auth();
$message = '';
$message_type = '';
$action = $_GET['action'] ?? 'list';
$user_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = $_POST['action_type'] ?? '';

    switch ($action_type) {
        case 'create':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $full_name = $_POST['full_name'] ?? '';
            $role = $_POST['role'] ?? 'user';

            if (empty($username) || empty($password) || empty($full_name)) {
                $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
                $message_type = 'error';
            } else if (strlen($password) < 6) {
                $message = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
                $message_type = 'error';
            } else {
                $result = $auth->register($username, $password, $full_name, $role);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    $action = 'list';
                }
            }
            break;

        case 'update':
            $user_id = $_POST['user_id'] ?? '';
            $username = $_POST['username'] ?? '';
            $full_name = $_POST['full_name'] ?? '';

            if (empty($username) || empty($full_name)) {
                $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
                $message_type = 'error';
            } else {
                $result = $auth->updateUser($user_id, $username, $full_name);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    $action = 'list';
                }
            }
            break;

        case 'delete':
            $user_id = $_POST['user_id'] ?? '';
            
            if (!empty($user_id)) {
                // Prevent deleting current logged-in user
                if ($user_id == Auth::getUserId()) {
                    $message = 'ไม่สามารถลบบัญชีของตัวเองได้';
                    $message_type = 'error';
                } else {
                    $result = $auth->deleteUser($user_id);
                    $message = $result['message'];
                    $message_type = $result['success'] ? 'success' : 'error';
                    $action = 'list';
                }
            }
            break;

        case 'reset_password':
            $user_id = $_POST['user_id'] ?? '';
            $new_password = $_POST['new_password'] ?? '';

            if (empty($new_password)) {
                $message = 'กรุณากรอกรหัสผ่านใหม่';
                $message_type = 'error';
            } else if (strlen($new_password) < 6) {
                $message = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
                $message_type = 'error';
            } else {
                $result = $auth->resetUserPassword($user_id, $new_password);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    $action = 'list';
                }
            }
            break;

        case 'update_role':
            $user_id = $_POST['user_id'] ?? '';
            $new_role = $_POST['new_role'] ?? 'user';

            if (!empty($user_id)) {
                // Prevent changing own role
                if ($user_id == Auth::getUserId()) {
                    $message = 'ไม่สามารถเปลี่ยนสิทธิ์ของตัวเองได้';
                    $message_type = 'error';
                } else {
                    $result = $auth->updateUserRole($user_id, $new_role);
                    $message = $result['message'];
                    $message_type = $result['success'] ? 'success' : 'error';
                    if ($result['success']) {
                        $action = 'list';
                    }
                }
            }
            break;

        case 'change_password':
            $old_password = $_POST['old_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
                $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
                $message_type = 'error';
            } else if ($new_password !== $confirm_password) {
                $message = 'รหัสผ่านใหม่ไม่ตรงกัน';
                $message_type = 'error';
            } else if (strlen($new_password) < 6) {
                $message = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
                $message_type = 'error';
            } else {
                $result = $auth->changePassword(Auth::getUserId(), $old_password, $new_password);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    $action = 'list';
                }
            }
            break;
    }
}

// Get all users
$all_users = $auth->getAllUsers();
$current_user_id = Auth::getUserId();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้ - rxmuk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 24px;
        }

        .page-header {
            background: white;
            padding: 30px 0;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #333;
            font-weight: 700;
            margin: 0;
        }

        .page-header p {
            color: #999;
            margin: 5px 0 0 0;
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: box-shadow 0.3s;
        }

        .card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 20px;
            font-weight: 600;
        }

        .card-body {
            padding: 25px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a3a8a 100%);
        }

        .btn-action {
            padding: 5px 10px;
            font-size: 13px;
        }

        .user-table {
            margin-bottom: 0;
        }

        .user-table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
            color: #555;
            font-weight: 600;
            padding: 15px;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .user-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }

        .user-table tbody tr:hover {
            background: #f8f9fa;
        }

        .user-table tbody td {
            padding: 15px;
            vertical-align: middle;
        }

        .user-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .user-badge.current {
            background: #e8f5e9;
            color: #388e3c;
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-control {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .nav-tabs {
            border-bottom: 2px solid #e0e0e0;
        }

        .nav-tabs .nav-link {
            color: #666;
            border: none;
            padding: 12px 20px;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .nav-tabs .nav-link:hover {
            color: #667eea;
        }

        .nav-tabs .nav-link.active {
            color: #667eea;
            background: transparent;
            border-bottom-color: #667eea;
        }

        .tab-content {
            padding-top: 20px;
        }

        .back-button {
            margin-bottom: 20px;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .modal-body {
            padding: 30px;
        }

        .confirmation-text {
            color: #d32f2f;
            font-weight: 600;
            margin-top: 15px;
        }

        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #667eea;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .created-date {
            color: #999;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 20px 0;
            }

            .card-body {
                padding: 15px;
            }

            .btn-action {
                display: block;
                width: 100%;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-pill"></i> rxmuk
            </a>
            <div>
                <span class="text-white me-3">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
                </span>
                <a href="logout.php" class="btn btn-sm btn-light">
                    <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                </a>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container-fluid">
            <h1><i class="fas fa-users"></i> จัดการผู้ใช้</h1>
            <p>ระบบควบคุมและดูแลบัญชีผู้ใช้</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Content Based on Action -->
        <?php if ($action === 'list'): ?>
            <!-- List Users -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>รายชื่อผู้ใช้ทั้งหมด</span>
                            <div>
                                <a href="?action=change_password" class="btn btn-sm btn-light me-2">
                                    <i class="fas fa-shield-alt"></i> เปลี่ยนรหัสผ่านของฉัน
                                </a>
                                <a href="?action=create" class="btn btn-sm btn-light">
                                    <i class="fas fa-plus"></i> เพิ่มผู้ใช้ใหม่
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($all_users) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table user-table">
                                        <thead>
                                            <tr>
                                                <th width="12%">ชื่อผู้ใช้</th>
                                                <th width="18%">ชื่อเต็ม</th>
                                                <th width="12%">สิทธิ์</th>
                                                <th width="20%">วันที่สร้าง</th>
                                                <th width="38%">การจัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($all_users as $user): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                        <?php if ($user['id'] == $current_user_id): ?>
                                                            <span class="user-badge current ms-2">
                                                                <i class="fas fa-check"></i> ของฉัน
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                    <td>
                                                        <span class="user-badge" style="background-color: <?php echo $user['role'] === 'admin' ? '#fff3cd' : '#e3f2fd'; ?>; color: <?php echo $user['role'] === 'admin' ? '#856404' : '#1976d2'; ?>;">
                                                            <?php echo $user['role'] === 'admin' ? '👮 Admin' : '👤 User'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="created-date">
                                                            <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary btn-action">
                                                            <i class="fas fa-edit"></i> แก้ไข
                                                        </a>
                                                        <a href="?action=reset_password&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning btn-action">
                                                            <i class="fas fa-key"></i> รีเซ็ตรหัส
                                                        </a>
                                                        <?php if ($user['id'] != $current_user_id): ?>
                                                            <button class="btn btn-sm btn-info btn-action" data-bs-toggle="modal" data-bs-target="#roleModal<?php echo $user['id']; ?>">
                                                                <i class="fas fa-shield-alt"></i> เปลี่ยนสิทธิ์
                                                            </button>
                                                            <button class="btn btn-sm btn-danger btn-action" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $user['id']; ?>">
                                                                <i class="fas fa-trash"></i> ลบ
                                                            </button>

                                                            <!-- Change Role Modal -->
                                                            <div class="modal fade" id="roleModal<?php echo $user['id']; ?>" tabindex="-1">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">เปลี่ยนสิทธิ์ผู้ใช้</h5>
                                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p>ผู้ใช้: <strong><?php echo htmlspecialchars($user['username']); ?></strong></p>
                                                                            <p>สิทธิ์ปัจจุบัน: <strong><?php echo $user['role'] === 'admin' ? '👮 Admin' : '👤 User'; ?></strong></p>
                                                                            <form method="POST" id="roleForm<?php echo $user['id']; ?>">
                                                                                <input type="hidden" name="action_type" value="update_role">
                                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                                <div class="form-group">
                                                                                    <label>เปลี่ยนเป็น:</label>
                                                                                    <select name="new_role" class="form-select" required>
                                                                                        <option value="user" <?php echo $user['role'] !== 'admin' ? 'selected' : ''; ?>>👤 User (ผู้ใช้ทั่วไป)</option>
                                                                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>👮 Admin (ผู้ดูแลระบบ)</option>
                                                                                    </select>
                                                                                </div>
                                                                            </form>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                                            <button type="submit" form="roleForm<?php echo $user['id']; ?>" class="btn btn-info">บันทึกการเปลี่ยนแปลง</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Delete Confirmation Modal -->
                                                            <div class="modal fade" id="deleteModal<?php echo $user['id']; ?>" tabindex="-1">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">ลบผู้ใช้</h5>
                                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p>คุณแน่ใจว่าต้องการลบผู้ใช้:</p>
                                                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                                            <p class="confirmation-text">⚠️ การทำดำเนินการนี้ไม่สามารถยกเลิกได้</p>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                                            <form method="POST" style="display: inline;">
                                                                                <input type="hidden" name="action_type" value="delete">
                                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                                <button type="submit" class="btn btn-danger">ลบถาวร</button>
                                                                            </form>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <p class="text-muted">ไม่มีผู้ใช้ในระบบ</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'create'): ?>
            <!-- Create User -->
            <div class="row">
                <div class="col-md-6">
                    <div class="back-button">
                        <a href="?action=list" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> กลับ
                        </a>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user-plus"></i> เพิ่มผู้ใช้ใหม่
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action_type" value="create">

                                <div class="form-group mb-3">
                                    <label for="username">ชื่อผู้ใช้</label>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        id="username" 
                                        name="username"
                                        placeholder="john_doe"
                                        required
                                    >
                                </div>

                                <div class="form-group mb-3">
                                    <label for="full_name">ชื่อเต็ม</label>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        id="full_name" 
                                        name="full_name"
                                        placeholder="John Doe"
                                        required
                                    >
                                </div>

                                <div class="form-group mb-3">
                                    <label for="password">รหัสผ่าน</label>
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="password" 
                                        name="password"
                                        placeholder="อย่างน้อย 6 ตัวอักษร"
                                        minlength="6"
                                        required
                                    >
                                    <small class="text-muted">ต้องมีความยาวอย่างน้อย 6 ตัวอักษร</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="role">สิทธิ์</label>
                                    <select 
                                        class="form-select" 
                                        id="role" 
                                        name="role"
                                        required
                                    >
                                        <option value="user">👤 User (ผู้ใช้ทั่วไป)</option>
                                        <option value="admin">👮 Admin (ผู้ดูแลระบบ)</option>
                                    </select>
                                </div>

                                <div class="info-box">
                                    <i class="fas fa-info-circle"></i>
                                    ผู้ใช้ใหม่สามารถใช้ชื่อผู้ใช้และรหัสผ่านนี้เข้าสู่ระบบได้ทันที
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save"></i> สร้างผู้ใช้
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'edit' && !empty($user_id)): ?>
            <!-- Edit User -->
            <?php
                $edit_user = $auth->getUserById($user_id);
                if (!$edit_user) {
                    echo '<div class="alert alert-danger">ไม่พบผู้ใช้</div>';
                } else {
            ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="back-button">
                        <a href="?action=list" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> กลับ
                        </a>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-edit"></i> แก้ไขข้อมูลผู้ใช้
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action_type" value="update">
                                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">

                                <div class="form-group mb-3">
                                    <label for="username">ชื่อผู้ใช้</label>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        id="username" 
                                        name="username"
                                        value="<?php echo htmlspecialchars($edit_user['username']); ?>"
                                        required
                                    >
                                </div>

                                <div class="form-group mb-3">
                                    <label for="full_name">ชื่อเต็ม</label>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        id="full_name" 
                                        name="full_name"
                                        value="<?php echo htmlspecialchars($edit_user['full_name']); ?>"
                                        required
                                    >
                                </div>

                                <div class="info-box">
                                    <i class="fas fa-info-circle"></i>
                                    วันที่สร้าง: <?php echo date('d/m/Y H:i', strtotime($edit_user['created_at'])); ?>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง
                                    </button>
                                    <a href="?action=list" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> ยกเลิก
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

        <?php elseif ($action === 'reset_password' && !empty($user_id)): ?>
            <!-- Reset Password -->
            <?php
                $reset_user = $auth->getUserById($user_id);
                if (!$reset_user) {
                    echo '<div class="alert alert-danger">ไม่พบผู้ใช้</div>';
                } else {
            ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="back-button">
                        <a href="?action=list" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> กลับ
                        </a>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-key"></i> รีเซ็ตรหัสผ่าน
                        </div>
                        <div class="card-body">
                            <div class="info-box mb-4">
                                <strong>ผู้ใช้:</strong> <?php echo htmlspecialchars($reset_user['username']); ?><br>
                                <strong>ชื่อเต็ม:</strong> <?php echo htmlspecialchars($reset_user['full_name']); ?>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="action_type" value="reset_password">
                                <input type="hidden" name="user_id" value="<?php echo $reset_user['id']; ?>">

                                <div class="form-group mb-3">
                                    <label for="new_password">รหัสผ่านใหม่</label>
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="new_password" 
                                        name="new_password"
                                        placeholder="อย่างน้อย 6 ตัวอักษร"
                                        minlength="6"
                                        required
                                    >
                                    <small class="text-muted">ต้องมีความยาวอย่างน้อย 6 ตัวอักษร</small>
                                </div>

                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    ผู้ใช้จะต้องใช้รหัสผ่านใหม่นี้เพื่อเข้าสู่ระบบ
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-sync"></i> รีเซ็ตรหัสผ่าน
                                    </button>
                                    <a href="?action=list" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> ยกเลิก
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

        <?php elseif ($action === 'change_password'): ?>
            <!-- Change Own Password -->
            <div class="row">
                <div class="col-md-6">
                    <div class="back-button">
                        <a href="?action=list" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> กลับ
                        </a>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-shield-alt"></i> เปลี่ยนรหัสผ่านของฉัน
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action_type" value="change_password">

                                <div class="form-group mb-3">
                                    <label for="old_password">รหัสผ่านเก่า</label>
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="old_password" 
                                        name="old_password"
                                        required
                                    >
                                </div>

                                <div class="form-group mb-3">
                                    <label for="new_password">รหัสผ่านใหม่</label>
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="new_password" 
                                        name="new_password"
                                        placeholder="อย่างน้อย 6 ตัวอักษร"
                                        minlength="6"
                                        required
                                    >
                                </div>

                                <div class="form-group mb-3">
                                    <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="confirm_password" 
                                        name="confirm_password"
                                        placeholder="อย่างน้อย 6 ตัวอักษร"
                                        minlength="6"
                                        required
                                    >
                                </div>

                                <div class="info-box">
                                    <i class="fas fa-info-circle"></i>
                                    รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> เปลี่ยนรหัสผ่าน
                                    </button>
                                    <a href="?action=list" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> ยกเลิก
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
