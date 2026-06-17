<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$user_id = Auth::getUserId();
$imports = [];
$upload_message = '';
$upload_type = '';

// Handle file upload (single or batch)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user has permission to upload
    if (!Auth::isAdmin()) {
        $upload_message = '❌ ไม่มีสิทธิ์ในการอัปโหลดไฟล์ (เฉพาะ Admin เท่านั้น)';
        $upload_type = 'danger';
    } else {
        $importer = new ExcelImporter();
        
        if (isset($_FILES['excel_file'])) {
            // Single file upload
            $result = $importer->importFile($_FILES['excel_file'], $user_id);
            $upload_message = $result['message'];
            $upload_type = $result['success'] ? 'success' : 'danger';
        } elseif (isset($_FILES['excel_files'])) {
            // Batch upload
            $batch_result = $importer->importBatch($_FILES['excel_files'], $user_id);
            $upload_message = $batch_result['summary'];
            $upload_type = ($batch_result['failed_count'] === 0) ? 'success' : 'warning';
            
            // Store batch results for display
            $_SESSION['batch_results'] = $batch_result['results'];
            
            // Redirect to results page
            header('Location: batch_results.php');
            exit;
        }
    }
}

// Get imported files for current user only
$importer = new ExcelImporter();
$imports = $importer->getImportedFiles($user_id); // Filter by current user
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>rxmuk - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .main-container {
            padding: 30px 0;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .dropzone .file-input {
            display: none;
        }
        .btn-upload {
            background: var(--gradient-1) !important;
            border: none !important;
            color: #0b0f19 !important;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 700;
            transition: all 0.3s ease;
            margin-top: 15px;
        }
        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(0, 242, 254, 0.5);
            color: #0b0f19 !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-file-earmark-excel"></i>rxmuk
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php">
                            <i class="bi bi-bar-chart"></i> สถิติ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="advanced-dashboard.html" target="_blank">
                            <i class="bi bi-cpu"></i> Advanced Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="user-management.php">
                            <i class="bi bi-people"></i> จัดการผู้ใช้
                        </a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link" style="color: white;">
                            สวัสดี, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
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
            <!-- Stats -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($imports); ?></div>
                    <div class="stat-label">ไฟล์ที่นำเข้า</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">43</div>
                    <div class="stat-label">ไฟล์ HDC ทั้งหมด</div>
                </div>
                <?php if (Auth::isAdmin()): ?>
                <div class="stat-card" style="border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05);">
                    <button id="btn-clear-all-system" class="btn btn-danger w-100 h-100" style="font-weight: 700;">
                        <i class="bi bi-trash3-fill"></i> ล้างข้อมูลระบบทั้งหมด
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($upload_message)): ?>
                <div class="alert alert-<?php echo $upload_type; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $upload_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($upload_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Upload Section (Admin Only) -->
            <?php if (Auth::isAdmin()): ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-cloud-upload"></i> นำเข้าไฟล์ Excel</h5>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="uploadTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab">
                                <i class="bi bi-file"></i> นำเข้าไฟล์เดียว
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="batch-tab" data-bs-toggle="tab" data-bs-target="#batch" type="button" role="tab">
                                <i class="bi bi-files"></i> นำเข้าหลายไฟล์ (Batch)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="uploadTabContent">
                        <!-- Single File Upload -->
                        <div class="tab-pane fade show active" id="single" role="tabpanel">
                            <form id="uploadForm" method="POST" enctype="multipart/form-data">
                                <div class="dropzone" id="dropzone">
                                    <i class="bi bi-file-earmark-arrow-up"></i>
                                    <p>ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</p>
                                    <small style="color: #666;">รองรับไฟล์ .xlsx, .xls, .csv ขนาดสูงสุด 50MB</small>
                                    <input type="file" name="excel_file" class="file-input" accept=".xlsx,.xls,.csv" id="fileInput" required>
                                </div>
                                <button type="submit" class="btn btn-upload">
                                    <i class="bi bi-upload"></i> อัปโหลด
                                </button>
                            </form>
                        </div>

                        <!-- Batch Upload -->
                        <div class="tab-pane fade" id="batch" role="tabpanel">
                            <form id="batchUploadForm" method="POST" enctype="multipart/form-data">
                                <div class="dropzone" id="batchDropzone">
                                    <i class="bi bi-files"></i>
                                    <p>ลากไฟล์หลายไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือก</p>
                                    <small style="color: #666;">เลือกหลายไฟล์พร้อมกัน (Ctrl+Click หรือ Cmd+Click)</small>
                                    <input type="file" name="excel_files[]" class="file-input" accept=".xlsx,.xls,.csv" id="batchFileInput" multiple required>
                                </div>
                                <div id="filePreview" class="mt-3"></div>
                                <button type="submit" class="btn btn-upload">
                                    <i class="bi bi-upload"></i> อัปโหลดทั้งหมด
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <strong>ข้อมูล:</strong> เฉพาะ Admin เท่านั้นที่สามารถอัปโหลดไฟล์ได้ โปรดติดต่อผู้ดูแลระบบ
            </div>
            <?php endif; ?>

            <!-- Files List -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-list-ul"></i> ไฟล์ที่นำเข้า (ทั้งหมด)</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>💡 สามารถดูและโหลดไฟล์ทั้งหมด:</strong> เพื่อให้ผู้ใช้ทั้งหมดสามารถเข้าถึงข้อมูล สามารถดู (View) และดาวน์โหลด (CSV) ไฟล์ที่ Admin อัปโหลดไปได้
                    </div>
                    <?php if (empty($imports)): ?>
                        <div style="text-align: center; padding: 40px; color: #999;">
                            <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                            <p>ยังไม่มีไฟล์ที่นำเข้า</p>
                        </div>
                    <?php else: ?>
                        <div class="file-list">
                            <?php foreach ($imports as $file): ?>
                                <div class="file-item">
                                    <div class="file-icon">
                                        <i class="bi bi-file-earmark-excel"></i>
                                    </div>
                                    <div class="file-info">
                                        <div class="file-name">
                                            <?php echo htmlspecialchars($file['original_name']); ?>
                                            <span style="font-size: 12px; background: #667eea; color: white; padding: 2px 8px; border-radius: 5px; margin-left: 8px;">
                                                <?php echo htmlspecialchars($file['file_type_description'] ?? 'ไฟล์ข้อมูล'); ?>
                                            </span>
                                        </div>
                                        <div class="file-date">
                                            <i class="bi bi-person"></i> 
                                            <strong><?php echo htmlspecialchars($file['full_name'] ?? $file['username'] ?? 'ไม่ระบุ'); ?></strong>
                                            <span style="margin-left: 15px;">
                                                <i class="bi bi-calendar"></i> <?php 
                                                $date = new DateTime($file['upload_date']);
                                                echo $date->format('d/m/Y H:i');
                                                ?>
                                            </span>
                                            <span style="margin-left: 15px;">
                                                <i class="bi bi-file-text"></i> <?php echo number_format($file['row_count'] ?? 0); ?> แถว
                                            </span>
                                        </div>
                                    </div>
                                    <div class="file-actions">
                                        <a href="view.php?id=<?php echo $file['id']; ?>" class="btn btn-sm btn-primary" title="ดูข้อมูล">
                                            <i class="bi bi-eye"></i> ดู
                                        </a>
                                        <a href="download.php?id=<?php echo $file['id']; ?>" class="btn btn-sm btn-success" title="ดาวน์โหลดเป็น CSV">
                                            <i class="bi bi-download"></i> ดาวน์โหลด
                                        </a>

                                        <!-- Delete button (admin or file owner only) -->
                                        <?php if (Auth::isAdmin() || $file['user_id'] == $user_id): ?>
                                        <button class="btn btn-sm btn-danger btn-delete-file" data-id="<?php echo $file['id']; ?>" title="ลบไฟล์">
                                            <i class="bi bi-trash"></i> ลบ
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Single file upload
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('fileInput');
        const uploadForm = document.getElementById('uploadForm');

        if (dropzone) {
            dropzone.addEventListener('click', () => fileInput.click());
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.classList.add('drag-over');
            });
            dropzone.addEventListener('dragleave', () => {
                dropzone.classList.remove('drag-over');
            });
            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('drag-over');
                fileInput.files = e.dataTransfer.files;
            });
            fileInput.addEventListener('change', (e) => {
                if (fileInput.files.length > 0) {
                    const fileName = fileInput.files[0].name;
                    const dropzoneText = dropzone.querySelector('p');
                    dropzoneText.textContent = `ไฟล์ที่เลือก: ${fileName}`;
                }
            });
        }

        // Batch file upload
        const batchDropzone = document.getElementById('batchDropzone');
        const batchFileInput = document.getElementById('batchFileInput');
        const batchUploadForm = document.getElementById('batchUploadForm');
        const filePreview = document.getElementById('filePreview');

        if (batchDropzone) {
            batchDropzone.addEventListener('click', () => batchFileInput.click());
            batchDropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                batchDropzone.classList.add('drag-over');
            });
            batchDropzone.addEventListener('dragleave', () => {
                batchDropzone.classList.remove('drag-over');
            });
            batchDropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                batchDropzone.classList.remove('drag-over');
                batchFileInput.files = e.dataTransfer.files;
                updateFilePreview();
            });
            batchFileInput.addEventListener('change', updateFilePreview);
        }

        function updateFilePreview() {
            filePreview.innerHTML = '';
            const files = batchFileInput.files;
            if (files.length > 0) {
                const fileList = document.createElement('div');
                fileList.style.marginTop = '15px';
                for (let i = 0; i < files.length; i++) {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'alert alert-info mb-2';
                    fileItem.textContent = `📄 ${files[i].name}`;
                    fileList.appendChild(fileItem);
                }
                filePreview.appendChild(fileList);
            }
        }

        // Delete file handler (calls API to remove import + cascade data)
        function attachDeleteHandlers() {
            document.querySelectorAll('.btn-delete-file').forEach(btn => {
                btn.removeEventListener('click', btn._deleteHandler || (() => {}));

                const handler = function() {
                    const id = this.getAttribute('data-id');
                    if (!confirm('คุณแน่ใจว่าต้องการลบไฟล์นี้? การกระทำนี้ไม่สามารถยกเลิกได้')) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    fetch('api_batch_import.php?action=delete', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(res => res.json())
                    .then(json => {
                        if (json.success) {
                            const fileItem = btn.closest('.file-item');
                            if (fileItem) fileItem.remove();
                            alert('ลบไฟล์เรียบร้อยแล้ว');
                        } else {
                            alert('ไม่สามารถลบไฟล์ได้: ' + (json.message || 'Unknown error'));
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('เกิดข้อผิดพลาดขณะลบไฟล์');
                    });
                };

                btn.addEventListener('click', handler);
                btn._deleteHandler = handler;
            });
        }

        // Attach handlers on load
        attachDeleteHandlers();

        // Clear All System Data handler
        const btnClearAll = document.getElementById('btn-clear-all-system');
        if (btnClearAll) {
            btnClearAll.addEventListener('click', () => {
                if (!confirm('🛑 คำเตือน: คุณแน่ใจว่าต้องการล้างข้อมูล "ทั้งหมด" ในระบบ? \nการกระทำนี้จะลบฐานข้อมูลทุกตารางและไฟล์อัปโหลดทั้งหมดถาวร ไม่สามารถกู้คืนได้!')) return;

                fetch('api_batch_import.php?action=clear_all', {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        alert(json.message);
                        window.location.reload();
                    } else {
                        alert('ข้อผิดพลาด: ' + json.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
                });
            });
        }
    </script>
</body>
</html>
