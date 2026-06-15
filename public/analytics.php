<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$user_id = Auth::getUserId();
$importer = new ExcelImporter();
$imports = $importer->getImportedFiles($user_id, PHP_INT_MAX);

// Calculate statistics
$stats = [
    'total_imports' => count($imports),
    'total_rows' => 0,
    'file_types' => [],
    'storage_size' => 0,
    'last_import_date' => null,
    'import_dates' => []
];

foreach ($imports as $import) {
    $stats['total_rows'] += $import['row_count'] ?? 0;
    
    $type = $import['file_type'] ?? 'unknown';
    if (!isset($stats['file_types'][$type])) {
        $stats['file_types'][$type] = ['count' => 0, 'description' => ''];
    }
    $stats['file_types'][$type]['count']++;
    $stats['file_types'][$type]['description'] = $import['file_type_description'] ?? 'ไม่ทราบชนิด';
    
    // Track dates
    $date = new DateTime($import['upload_date']);
    $date_str = $date->format('Y-m-d');
    if (!isset($stats['import_dates'][$date_str])) {
        $stats['import_dates'][$date_str] = 0;
    }
    $stats['import_dates'][$date_str]++;
    
    if (is_null($stats['last_import_date']) || $date > new DateTime($stats['last_import_date'])) {
        $stats['last_import_date'] = $import['upload_date'];
    }
}

ksort($stats['import_dates']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>rxmuk - สถิติและรายงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.0.1/dist/chart.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .main-container {
            padding: 30px 0;
        }
        .chart-container {
            position: relative;
            height: 350px;
            margin-bottom: 20px;
        }
        .type-badge {
            display: inline-block;
            background: rgba(0, 242, 254, 0.1);
            border: 1px solid rgba(0, 242, 254, 0.2);
            color: var(--c-primary);
            padding: 8px 15px;
            border-radius: 8px;
            margin: 5px;
            font-size: 13px;
            font-weight: 600;
        }
        .type-count {
            display: inline-block;
            background: rgba(255, 255, 255, 0.1);
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 8px;
            font-weight: 700;
            color: #fff;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-file-earmark-excel"></i>rxmuk
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-house"></i> แดชบอร์ด
                        </a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link" style="color: white;">
                            <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="container">
            <!-- Main Statistics -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-box">
                        <div class="stat-icon"><i class="bi bi-file-earmark"></i></div>
                        <div class="stat-number"><?php echo $stats['total_imports']; ?></div>
                        <div class="stat-label">ไฟล์ที่นำเข้า</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <div class="stat-icon"><i class="bi bi-list"></i></div>
                        <div class="stat-number"><?php echo number_format($stats['total_rows']); ?></div>
                        <div class="stat-label">จำนวนแถวข้อมูล</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <div class="stat-icon"><i class="bi bi-grid-3x3"></i></div>
                        <div class="stat-number"><?php echo count($stats['file_types']); ?></div>
                        <div class="stat-label">ประเภทไฟล์</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
                        <div class="stat-number">
                            <?php 
                            if ($stats['last_import_date']) {
                                $date = new DateTime($stats['last_import_date']);
                                echo $date->format('d M');
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                        <div class="stat-label">นำเข้าล่าสุด</div>
                    </div>
                </div>
            </div>

            <!-- Charts and Details -->
            <div class="row">
                <!-- File Types Distribution -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-pie-chart"></i> การกระจายประเภทไฟล์</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="fileTypesChart"></canvas>
                            </div>
                            <div style="margin-top: 20px;">
                                <?php foreach ($stats['file_types'] as $type => $data): ?>
                                    <div style="margin-bottom: 10px;">
                                        <span class="type-badge">
                                            <?php echo htmlspecialchars($data['description']); ?>
                                            <span class="type-count"><?php echo $data['count']; ?></span>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Import Timeline -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-bar-chart"></i> ไทม์ไลน์การนำเข้า</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="timelineChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed File Types -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-table"></i> รายละเอียดประเภทไฟล์</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th>ประเภท</th>
                                    <th>คำอธิบาย</th>
                                    <th>จำนวน</th>
                                    <th>ร้อยละ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = $stats['total_imports'];
                                foreach ($stats['file_types'] as $type => $data):
                                    $percentage = ($total > 0) ? number_format(($data['count'] / $total) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <span style="color: #667eea; font-weight: 600;">
                                                <?php echo htmlspecialchars($type); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($data['description']); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $data['count']; ?></span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 25px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo $percentage; ?>%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                                    <?php echo $percentage; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-info-circle"></i> สรุปข้อมูล</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>จำนวนไฟล์ที่นำเข้า:</strong> <?php echo $stats['total_imports']; ?> ไฟล์</p>
                            <p><strong>จำนวนแถวข้อมูลทั้งหมด:</strong> <?php echo number_format($stats['total_rows']); ?> แถว</p>
                            <p><strong>จำนวนประเภทไฟล์:</strong> <?php echo count($stats['file_types']); ?> ประเภท</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>การนำเข้าครั้งล่าสุด:</strong> 
                                <?php 
                                if ($stats['last_import_date']) {
                                    $date = new DateTime($stats['last_import_date']);
                                    echo $date->format('d/m/Y H:i');
                                } else {
                                    echo 'ไม่มี';
                                }
                                ?>
                            </p>
                            <p><strong>เป้าหมาย (43 ไฟล์ HDC):</strong> <?php echo $stats['total_imports']; ?> / 43</p>
                            <p><strong>ความสมบูรณ์:</strong> 
                                <span style="color: #667eea; font-weight: 600;">
                                    <?php echo number_format(($stats['total_imports'] / 43) * 100, 1); ?>%
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.0.1/dist/chart.min.js"></script>
    <script>
        // Set Chart.js defaults for dark theme
        Chart.defaults.color = '#9ca3af';
        Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.08)';

        // Colors
        const primaryColor = '#00f2fe';
        const secondaryColor = '#c084fc';
        
        // File Types Chart
        const fileTypesCtx = document.getElementById('fileTypesChart').getContext('2d');
        const fileTypesData = {
            labels: [
                <?php foreach ($stats['file_types'] as $type => $data): ?>
                    '<?php echo htmlspecialchars($data['description']); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($stats['file_types'] as $type => $data): ?>
                        <?php echo $data['count']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    'rgba(0, 242, 254, 0.6)',
                    'rgba(192, 132, 252, 0.6)',
                    'rgba(16, 185, 129, 0.6)',
                    'rgba(245, 158, 11, 0.6)',
                    'rgba(239, 68, 68, 0.6)',
                    'rgba(79, 172, 254, 0.6)'
                ],
                borderColor: [
                    '#00f2fe',
                    '#c084fc',
                    '#10b981',
                    '#f59e0b',
                    '#ef4444',
                    '#4facfe'
                ],
                borderWidth: 2
            }]
        };
        
        new Chart(fileTypesCtx, {
            type: 'doughnut',
            data: fileTypesData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Timeline Chart
        const timelineCtx = document.getElementById('timelineChart').getContext('2d');
        const timelineData = {
            labels: [
                <?php foreach ($stats['import_dates'] as $date => $count): ?>
                    '<?php echo $date; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'จำนวนการนำเข้า',
                data: [
                    <?php foreach ($stats['import_dates'] as $date => $count): ?>
                        <?php echo $count; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: primaryColor,
                backgroundColor: 'rgba(0, 242, 254, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        };

        new Chart(timelineCtx, {
            type: 'line',
            data: timelineData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: Math.max(...timelineData.datasets[0].data) + 1
                    }
                }
            }
        });
    </script>
</body>
</html>
