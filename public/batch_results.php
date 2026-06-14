<?php
/**
 * Batch Import Results Display Page
 */
require_once __DIR__ . '/../src/Auth/Auth.php';

if (!Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Get batch results from session
$batch_results = $_SESSION['batch_results'] ?? [];
if (empty($batch_results)) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการนำเข้า Batch - rxmuk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .result-card {
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #ddd;
        }
        .result-card.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .result-card.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .result-header {
            padding: 12px;
            font-weight: 600;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-lg">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-file-earmark-excel"></i> rxmuk
            </a>
            <div class="ms-auto">
                <a href="dashboard.php" class="nav-link text-white">
                    <i class="bi bi-arrow-left"></i> กลับไปยัง Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-lg mt-5">
        <h2 class="mb-4">
            <i class="bi bi-files"></i> ผลการนำเข้า Batch
        </h2>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number" style="color: #28a745;">
                    <?php 
                        $success_count = 0;
                        foreach ($batch_results as $r) {
                            if (isset($r['success']) && $r['success']) {
                                $success_count++;
                            }
                        }
                        echo $success_count;
                    ?>
                </div>
                <div class="stat-label">ไฟล์สำเร็จ</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #dc3545;">
                    <?php 
                        $failed_count = 0;
                        foreach ($batch_results as $r) {
                            if (!isset($r['success']) || !$r['success']) {
                                $failed_count++;
                            }
                        }
                        echo $failed_count;
                    ?>
                </div>
                <div class="stat-label">ไฟล์ล้มเหลว</div>
            </div>
            <div class="stat-box">
                <div class="stat-number">
                    <?php echo count($batch_results); ?>
                </div>
                <div class="stat-label">ไฟล์ทั้งหมด</div>
            </div>
        </div>

        <!-- Results -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 style="margin: 0;">รายละเอียดการนำเข้า</h5>
            </div>
            <div class="card-body">
                <?php foreach ($batch_results as $index => $result): 
                    $is_success = $result['success'] ?? false;
                ?>
                    <div class="result-card <?php echo $is_success ? 'success' : 'error'; ?>">
                        <div class="result-header">
                            <i class="bi bi-<?php echo $is_success ? 'check-circle' : 'x-circle'; ?>"></i>
                            ไฟล์ที่ <?php echo ($index + 1); ?>: 
                            <?php echo htmlspecialchars($result['filename'] ?? $result['message'] ?? 'Unknown'); ?>
                        </div>
                        <div style="padding: 12px; font-size: 13px;">
                            <?php if ($is_success): ?>
                                <div style="color: #155724;">
                                    <strong>✓ นำเข้าสำเร็จ</strong><br>
                                    Import ID: <code><?php echo $result['import_id'] ?? 'N/A'; ?></code><br>
                                    ข้อมูลที่อ่านได้: <?php echo number_format($result['read_rows'] ?? 0); ?> แถว<br>
                                    ข้อมูลที่บันทึก: <?php echo number_format($result['inserted_rows'] ?? 0); ?> แถว
                                    <?php if (isset($result['failed_rows']) && $result['failed_rows'] > 0): ?>
                                        <br><span style="color: #856404;">⚠ ข้อมูลล้มเหลว: <?php echo $result['failed_rows']; ?> แถว</span>
                                    <?php endif; ?>
                                    <div style="margin-top: 10px;">
                                        <?php if (isset($result['import_id'])): ?>
                                            <a href="view.php?id=<?php echo $result['import_id']; ?>" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-eye"></i> ดูข้อมูล
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="color: #721c24;">
                                    <strong>✗ นำเข้าล้มเหลว</strong><br>
                                    เหตุผล: <?php echo htmlspecialchars($result['message'] ?? 'Unknown error'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> กลับไปยัง Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Clear session results after display
unset($_SESSION['batch_results']);
?>
