<?php
/**
 * Admin Panel - User Management Helper
 * Use this to create new users without direct database access
 * 
 * SECURITY WARNING: This file should only be accessible to admins
 * Delete in production or protect with additional authentication
 */

require_once __DIR__ . '/../src/Auth/Auth.php';

// Simple admin key check (replace with proper auth in production)
if (empty($_GET['key']) || $_GET['key'] !== 'change_me_to_secure_key') {
    http_response_code(403);
    die('Access denied. Secure this file before using in production.');
}

session_start();

$message = '';
$message_type = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';

    if (empty($username) || empty($password) || empty($full_name)) {
        $message = 'All fields are required';
        $message_type = 'error';
    } else {
        try {
            $auth = new Auth();
            $result = $auth->register($username, $password, $full_name);
            
            if ($result['success']) {
                $message = $result['message'];
                $message_type = 'success';
                $_POST = []; // Clear form
            } else {
                $message = $result['message'];
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>rxmuk - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .admin-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 30px;
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }
        
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            color: #856404;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="admin-card">
        <h1>👤 Create New User</h1>
        
        <div class="warning">
            <strong>⚠️ Security Notice:</strong><br>
            This admin panel is for development only. 
            Delete this file in production or protect with proper authentication.
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    placeholder="e.g., john_doe"
                    required
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="full_name">Full Name:</label>
                <input 
                    type="text" 
                    id="full_name" 
                    name="full_name" 
                    placeholder="e.g., John Doe"
                    required
                    value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Minimum 6 characters"
                    required
                    minlength="6"
                >
            </div>
            
            <button type="submit" class="btn-submit">Create User</button>
        </form>
        
        <hr style="margin-top: 30px;">
        
        <h6 style="color: #666; margin-top: 20px;">📝 User Details (Minimum Requirements):</h6>
        <ul style="font-size: 13px; color: #999; margin-bottom: 0;">
            <li>Username: Unique identifier (alphanumeric)</li>
            <li>Full Name: Display name</li>
            <li>Password: At least 6 characters</li>
        </ul>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
