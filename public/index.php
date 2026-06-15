<?php
require_once __DIR__ . '/../src/Auth/Auth.php';

$message = '';
$message_type = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $auth = new Auth();
    $result = $auth->login($username, $password);

    if ($result['success']) {
        header('Location: dashboard.php');
        exit;
    } else {
        $message = $result['message'];
        $message_type = 'error';
    }
}

// If already logged in, redirect to dashboard
if (Auth::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>rxmuk - ระบบจัดการข้อมูล Excel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 15px;
        }
        
        .login-card {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.5), 0 0 15px var(--c-primary-glow);
            padding: 40px;
            backdrop-filter: blur(12px);
            animation: slideInUp 0.6s ease-out;
            color: #fff;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-card h1 {
            text-align: center;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            font-weight: 800;
            font-size: 36px;
            letter-spacing: -0.05em;
        }
        
        .login-card p.subtitle {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 13px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--card-border);
            background: rgba(17, 24, 39, 0.6);
            color: #fff;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--c-primary);
            box-shadow: 0 0 12px var(--c-primary-glow);
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--gradient-1);
            color: #0b0f19;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(0, 242, 254, 0.5);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 20px;
            animation: slideInDown 0.3s ease-out;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .footer-text {
            text-align: center;
            color: var(--text-muted);
            margin-top: 20px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h1>rxmuk</h1>
            <p class="subtitle">ระบบจัดการข้อมูล Excel และการนำเข้า</p>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้</label>
                    <input type="text" id="username" name="username" placeholder="กรุณาระบุชื่อผู้ใช้" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">รหัสผ่าน</label>
                    <input type="password" id="password" name="password" placeholder="กรุณาระบุรหัสผ่าน" required>
                </div>
                
                <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
            </form>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--card-border); text-align: center; color: var(--text-muted); font-size: 13px;">
                <p>ข้อมูลเริ่มต้น:</p>
                <p>ชื่อผู้ใช้: <strong style="color: var(--c-primary);">admin</strong><br>รหัสผ่าน: <strong style="color: var(--c-primary);">123456</strong></p>
            </div>
        </div>
        
        <div class="footer-text">
            <p>&copy; 2026 rxmuk System | ระบบจัดการข้อมูล Excel จากระบบ HDC</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
