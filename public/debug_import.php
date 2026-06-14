<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';
require_once __DIR__ . '/../src/HDC/HDCFileHandler.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$debug_info = [];
$test_result = null;

// If form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['debug_file'])) {
    $file = $_FILES['debug_file'];
    
    // Check file
    $debug_info['file_name'] = $file['name'];
    $debug_info['file_size'] = $file['size'];
    $debug_info['file_type'] = $file['type'];
    $debug_info['file_tmp'] = $file['tmp_name'];
    
    // Read the file
    $importer = new ExcelImporter();
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($importer);
    $method = $reflection->getMethod('readExcelFile');
    $method->setAccessible(true);
    
    $file_path = $file['tmp_name'];
    $result = $method->invoke($importer, $file_path);
    
    if ($result['success']) {
        $data = $result['data'];
        $debug_info['read_status'] = '✓ Successfully read file';
        $debug_info['total_rows'] = count($data);
        
        if (!empty($data)) {
            $debug_info['first_row'] = array_slice($data[0], 0, 10);  // First 10 columns
            $debug_info['column_names'] = array_keys($data[0]);
            $debug_info['sample_rows'] = array_slice($data, 0, 5);   // First 5 rows
        } else {
            $debug_info['read_status'] = '⚠ File read successfully but NO DATA found';
        }
    } else {
        $debug_info['read_status'] = '✗ Error: ' . $result['message'];
    }
    
    $test_result = $debug_info;
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Import - rxmuk</title>
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
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        code {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            display: block;
            overflow-x: auto;
            font-size: 12px;
        }
        .table-sm td {
            padding: 0.5rem;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-lg">
            <span class="navbar-brand"><i class="bi bi-bug"></i> Debug Import Tool</span>
        </div>
    </nav>

    <div class="container-lg mt-4">
        <!-- Upload Form -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-file-arrow-up"></i> Test Excel File Reading
            </div>
            <div class="card-body">
                <p class="text-muted">Upload a file to see exactly what our import system is reading from it.</p>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Select Excel File</label>
                        <input type="file" name="debug_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-bug"></i> Debug File
                    </button>
                </form>
            </div>
        </div>

        <?php if ($test_result): ?>
        <!-- Debug Results -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle"></i> Debug Results
            </div>
            <div class="card-body">
                <h6>File Information:</h6>
                <table class="table table-sm table-bordered">
                    <tr>
                        <td><strong>Filename:</strong></td>
                        <td><?php echo htmlspecialchars($test_result['file_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>File Size:</strong></td>
                        <td><?php echo $test_result['file_size']; ?> bytes</td>
                    </tr>
                    <tr>
                        <td><strong>Read Status:</strong></td>
                        <td><?php echo $test_result['read_status']; ?></td>
                    </tr>
                </table>

                <?php if (isset($test_result['total_rows'])): ?>
                <hr>
                <h6>Data Found:</h6>
                <table class="table table-sm table-bordered">
                    <tr>
                        <td><strong>Total Rows:</strong></td>
                        <td><strong class="text-success"><?php echo $test_result['total_rows']; ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Total Columns:</strong></td>
                        <td><?php echo count($test_result['column_names']); ?></td>
                    </tr>
                </table>

                <?php if ($test_result['total_rows'] > 0): ?>
                <h6>Column Names:</h6>
                <code><?php echo implode(', ', array_map('htmlspecialchars', $test_result['column_names'])); ?></code>

                <h6 class="mt-3">First 5 Rows (Sample):</h6>
                <div style="overflow-x: auto;">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <?php foreach (array_slice($test_result['column_names'], 0, 10) as $col): ?>
                                <th><?php echo htmlspecialchars($col); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($test_result['sample_rows'] as $row): ?>
                            <tr>
                                <?php foreach (array_slice($row, 0, 10) as $cell): ?>
                                <td><?php echo htmlspecialchars(substr($cell, 0, 50)); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php else: ?>
                <div class="alert alert-warning">
                    <strong>⚠ No Data Found!</strong> Your Excel file was read successfully but contains no data rows. 
                    Please check if:
                    <ul>
                        <li>The file has a header row in the first row</li>
                        <li>There are data rows below the header</li>
                        <li>You're uploading the correct file</li>
                    </ul>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="alert alert-danger">
                    <strong>✗ Error Reading File!</strong><br>
                    <?php echo $test_result['read_status']; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <hr>
        
        <!-- Instructions -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-lightbulb"></i> What to Check
            </div>
            <div class="card-body">
                <h6>Your Excel file must have:</h6>
                <ul>
                    <li><strong>Header Row:</strong> First row with column names (HOSPCODE, PID, DATE_SERV, etc.)</li>
                    <li><strong>Data Rows:</strong> At least one row of actual data below the headers</li>
                    <li><strong>Proper Format:</strong> .xlsx or .xls files (CSV also supported)</li>
                </ul>

                <h6 class="mt-3">Example of Correct Format:</h6>
                <table class="table table-sm table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>HOSPCODE</th>
                            <th>PID</th>
                            <th>DATE_SERV</th>
                            <th>DIDSTD</th>
                            <th>DNAME</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>05790</td>
                            <td>183728311133</td>
                            <td>01/10/25</td>
                            <td>1620021</td>
                            <td>แก้โอคลีโนมายาน่ใจ</td>
                        </tr>
                        <tr>
                            <td>05790</td>
                            <td>507214364342</td>
                            <td>01/10/25</td>
                            <td>1001760</td>
                            <td>AMOXYCILLIN CAP 500 MG</td>
                        </tr>
                    </tbody>
                </table>

                <p class="mt-3 mb-0">
                    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
