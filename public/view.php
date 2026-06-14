<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$import_id = $_GET['id'] ?? 0;
if (!$import_id) {
    header('Location: dashboard.php');
    exit;
}

$page = intval($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$per_page = 20;
$offset = ($page - 1) * $per_page;

$importer = new ExcelImporter();
$imported_data = $importer->getImportedData($import_id, $per_page, $offset);

if (!$imported_data) {
    header('Location: dashboard.php');
    exit;
}

$file_info = $imported_data['import_info'];
$data_result = $imported_data['data'];
$detection = $imported_data['detection'];
$rows = [];

while ($row = $data_result->fetch_assoc()) {
    $rows[] = $row;
}

// Get total count and columns
$total_rows = $importer->getImportDataCount($import_id);
$total_pages = ceil($total_rows / $per_page);
$columns = $importer->getImportColumns($import_id);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>rxmuk - ดูข้อมูล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .navbar-brand {
            font-size: 24px;
            font-weight: 700;
            color: white !important;
        }
        
        .main-container {
            padding: 30px 0;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px 15px 0 0;
            padding: 20px;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
        }
        
        .breadcrumb-item {
            color: #667eea;
        }
        
        .breadcrumb-item a {
            color: #667eea;
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: #999;
        }
        
        .data-table {
            font-size: 14px;
        }
        
        .data-table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .data-table tbody tr:hover {
            background: #f0f0f0;
        }
        
        .btn-group-custom {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .btn-custom {
            border-radius: 8px;
            padding: 8px 15px;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-file-earmark-excel"></i>rxmuk
            </a>
            <div class="ms-auto">
                <a href="logout.php" class="nav-link" style="color: white; display: inline-block;">
                    <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
                </a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($file_info['original_name']); ?></li>
                </ol>
            </nav>

            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-table"></i> <?php echo htmlspecialchars($file_info['original_name']); ?></h5>
                </div>
                <div class="card-body">
                    <!-- File Information -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                        <div class="row">
                            <div class="col-md-4">
                                <strong>ประเภทไฟล์:</strong><br>
                                <span style="color: #667eea; font-weight: 600;">
                                    <?php echo htmlspecialchars($detection['config']['description']); ?>
                                </span>
                            </div>
                            <div class="col-md-4">
                                <strong>จำนวนแถว (ทั้งหมด):</strong><br>
                                <span style="color: #667eea; font-weight: 600;">
                                    <?php echo number_format($total_rows); ?> รายการ
                                </span>
                            </div>
                            <div class="col-md-4">
                                <strong>วันที่นำเข้า:</strong><br>
                                <span style="color: #667eea; font-weight: 600;">
                                    <?php 
                                    $date = new DateTime($file_info['upload_date']);
                                    echo $date->format('d/m/Y H:i');
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($total_rows > 0): ?>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                                <strong>จำนวนคอลัมน์:</strong>
                                <span style="color: #667eea; font-weight: 600;"><?php echo count($columns); ?> คอลัมน์</span>
                                <div style="margin-top: 10px; font-size: 13px;">
                                    <strong>ชื่อคอลัมน์:</strong><br>
                                    <span style="color: #666; font-family: monospace;">
                                        <?php echo htmlspecialchars(implode(', ', $columns)); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="btn-group-custom">
                        <a href="dashboard.php" class="btn btn-custom btn-secondary">
                            <i class="bi bi-arrow-left"></i> กลับ
                        </a>
                        <a href="download.php?id=<?php echo $import_id; ?>" class="btn btn-custom btn-success">
                            <i class="bi bi-download"></i> ดาวน์โหลด (CSV)
                        </a>
                        <button class="btn btn-custom btn-info" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> รีโหลด
                        </button>
                    </div>

                    <?php if (empty($rows)): ?>
                        <!-- No Data But Show Structure -->
                        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                            <div style="color: #856404;">
                                <h6 style="margin-bottom: 15px;">
                                    <i class="bi bi-info-circle"></i> ข้อมูลไม่พบในฐานข้อมูล
                                </h6>
                                <p style="margin-bottom: 10px; font-size: 14px;">
                                    ไฟล์ได้รับการนำเข้า มีจำนวน <strong><?php echo number_format($total_rows); ?> แถว</strong> 
                                    ประเภท: <strong><?php echo htmlspecialchars($detection['config']['description']); ?></strong>
                                </p>
                                <?php if ($total_rows > 0): ?>
                                    <div style="background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 12px; margin-top: 15px;">
                                        <strong style="display: block; margin-bottom: 10px;">โครงสร้างข้อมูล (Columns):</strong>
                                        <div style="font-size: 13px; line-height: 1.8; font-family: monospace; color: #555;">
                                            <?php 
                                            foreach ($columns as $index => $col) {
                                                echo ($index + 1) . ". <strong>" . htmlspecialchars($col) . "</strong><br>";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(133, 100, 4, 0.2);">
                                        <p style="font-size: 13px; margin: 0;">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            หากข้อมูลไม่แสดง อาจเป็นเพราะการเชื่อมต่อฐานข้อมูล 
                                            กรุณาลองคลิกปุ่ม "<strong>รีโหลด</strong>" เพื่อลองดึงข้อมูลอีกครั้ง
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <p style="font-size: 13px; color: #856404; margin-top: 15px;">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        ไฟล์ไม่มีข้อมูลหรือไม่สามารถประมวลผลได้
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="text-align: center; padding: 40px; color: #999;">
                            <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                            <p>ไม่มีข้อมูลที่สามารถแสดงได้ในขณะนี้</p>
                        </div>
                    <?php else: ?>
                        <!-- Pagination Info -->
                        <div style="background: #f0f0f0; padding: 10px 15px; border-radius: 8px; margin-bottom: 15px; font-size: 13px;">
                            แสดงหน้า <strong><?php echo $page; ?></strong> จาก <strong><?php echo $total_pages; ?></strong> 
                            (แสดง <?php echo min($per_page, count($rows)); ?> จาก <?php echo number_format($total_rows); ?> แถว)
                        </div>

                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; border-radius: 8px;">
                            <table class="table table-striped data-table" id="dataTable" style="margin-bottom: 0;">
                                <thead style="position: sticky; top: 0;">
                                    <tr>
                                        <?php
                                        $first_row = $rows[0];
                                        foreach (array_keys($first_row) as $column):
                                            if ($column !== 'id' && $column !== 'import_id' && $column !== 'created_at'):
                                        ?>
                                            <th><?php echo htmlspecialchars($column); ?></th>
                                        <?php endif; endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $key => $value): 
                                                if ($key !== 'id' && $key !== 'import_id' && $key !== 'created_at'):
                                            ?>
                                                <td style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;" title="<?php echo htmlspecialchars($value); ?>">
                                                    <?php echo htmlspecialchars(substr((string)$value, 0, 100)); ?>
                                                </td>
                                            <?php endif; endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" style="margin-top: 20px;">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?id=<?php echo $import_id; ?>&page=1">หน้าแรก</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?id=<?php echo $import_id; ?>&page=<?php echo $page - 1; ?>">ก่อนหน้า</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?id=<?php echo $import_id; ?>&page=<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?id=<?php echo $import_id; ?>&page=<?php echo $page + 1; ?>">ถัดไป</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?id=<?php echo $import_id; ?>&page=<?php echo $total_pages; ?>">หน้าสุดท้าย</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#dataTable').DataTable({
                "language": {
                    "lengthMenu": "แสดง _MENU_ รายการต่อหน้า",
                    "zeroRecords": "ไม่พบข้อมูล",
                    "info": "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
                    "infoEmpty": "ไม่มีข้อมูล",
                    "infoFiltered": "(ค้นหาจาก _MAX_ รายการทั้งหมด)",
                    "search": "ค้นหา:",
                    "paginate": {
                        "first": "หน้าแรก",
                        "last": "หน้าสุดท้าย",
                        "next": "ถัดไป",
                        "previous": "ก่อนหน้า"
                    }
                },
                "pageLength": 25,
                "ordering": true,
                "searching": true
            });
        });
    </script>
</body>
</html>
