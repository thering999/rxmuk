<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Import/ExcelImporter.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$diagnostics = [];
$import_result = null;
$test_file_uploaded = false;

// Test 1: Check PHP Extensions
$diagnostics['extensions'] = [
    'ZipArchive' => extension_loaded('zip') || class_exists('ZipArchive'),
    'SimpleXML' => extension_loaded('simplexml') || class_exists('SimpleXMLElement'),
    'XML' => extension_loaded('libxml'),
    'PDO' => extension_loaded('pdo'),
    'MySQLi' => extension_loaded('mysqli')
];

// Test 2: Check Server Configuration
$diagnostics['config'] = [
    'PHP Version' => phpversion(),
    'Upload Max Size' => ini_get('upload_max_filesize'),
    'Post Max Size' => ini_get('post_max_size'),
    'Memory Limit' => ini_get('memory_limit')
];

// Test 3: Check File System
$upload_dir = __DIR__ . '/../uploads';
$diagnostics['filesystem'] = [
    'Uploads Directory Exists' => is_dir($upload_dir),
    'Uploads Directory Writable' => is_writable($upload_dir),
    'Uploads Directory Path' => $upload_dir
];

// Test 4: Check Database
$db = new Database();
$conn = $db->connect();
$diagnostics['database'] = [
    'Connected' => ($conn !== null),
];

if ($conn) {
    $result = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $tables[] = array_values($row)[0];
    }
    $diagnostics['database']['Tables'] = implode(', ', $tables);
}

// Handle test file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $importer = new ExcelImporter();
    $user_id = Auth::getUserId();
    
    $result = $importer->importFile($_FILES['test_file'], $user_id);
    $import_result = $result;
    $test_file_uploaded = true;
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Test - rxmuk</title>
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
        .status-good { color: #28a745; font-weight: bold; }
        .status-bad { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-lg">
            <span class="navbar-brand"><i class="bi bi-file-earmark-excel"></i> rxmuk - Import Diagnostic</span>
        </div>
    </nav>

    <div class="container-lg mt-5">
        <!-- System Diagnostics -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <i class="bi bi-wrench"></i> System Diagnostics
            </div>
            <div class="card-body">
                <h5 class="mb-3">PHP Extensions</h5>
                <div class="row">
                    <?php foreach ($diagnostics['extensions'] as $ext => $status): ?>
                    <div class="col-md-4 mb-2">
                        <i class="bi <?php echo $status ? 'bi-check-circle text-success' : 'bi-x-circle text-danger'; ?>"></i>
                        <?php echo $ext; ?>: 
                        <span class="<?php echo $status ? 'status-good' : 'status-bad'; ?>">
                            <?php echo $status ? '✓' : '✗'; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <hr>
                <h5 class="mb-3">Server Configuration</h5>
                <table class="table table-sm">
                    <?php foreach ($diagnostics['config'] as $key => $value): ?>
                    <tr>
                        <td><strong><?php echo $key; ?></strong></td>
                        <td><?php echo $value; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <hr>
                <h5 class="mb-3">File System</h5>
                <table class="table table-sm">
                    <?php foreach ($diagnostics['filesystem'] as $key => $value): ?>
                    <tr>
                        <td><strong><?php echo $key; ?></strong></td>
                        <td>
                            <?php if (is_bool($value)): ?>
                                <span class="<?php echo $value ? 'status-good' : 'status-bad'; ?>">
                                    <?php echo $value ? '✓ Yes' : '✗ No'; ?>
                                </span>
                            <?php else: ?>
                                <?php echo $value; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <hr>
                <h5 class="mb-3">Database</h5>
                <table class="table table-sm">
                    <?php foreach ($diagnostics['database'] as $key => $value): ?>
                    <tr>
                        <td><strong><?php echo $key; ?></strong></td>
                        <td>
                            <?php if (is_bool($value)): ?>
                                <span class="<?php echo $value ? 'status-good' : 'status-bad'; ?>">
                                    <?php echo $value ? '✓ Connected' : '✗ Failed'; ?>
                                </span>
                            <?php else: ?>
                                <small><?php echo $value; ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Test Upload -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-file-arrow-up"></i> Test Excel Import
            </div>
            <div class="card-body">
                <p class="text-muted">Upload a sample Excel file to test the import functionality.</p>
                
                <?php if ($test_file_uploaded): ?>
                <div class="alert alert-<?php echo $import_result['success'] ? 'success' : 'danger'; ?>">
                    <strong><?php echo $import_result['success'] ? '✓ Success' : '✗ Error'; ?>:</strong>
                    <?php echo $import_result['message']; ?>
                    <?php if ($import_result['success']): ?>
                    <br><small>Import ID: <?php echo $import_result['import_id']; ?></small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Select Excel File (.xlsx, .xls, .csv)</label>
                        <input type="file" name="test_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                        <small class="form-text text-muted">
                            This tool supports files like: drug_opd, drug_ipd, opd, ipd, lab data
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Test Import
                    </button>
                </form>
            </div>
        </div>

        <!-- Instructions -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-info-circle"></i> Supported File Formats
            </div>
            <div class="card-body">
                <h6>File formats supported:</h6>
                <ul class="list-unstyled">
                    <li><strong>Drug OPD:</strong> Columns like HOSPCODE, PID, DATE_SERV, DIDSTD, DNAME, etc.</li>
                    <li><strong>Drug IPD:</strong> Similar to Drug OPD but for inpatient data</li>
                    <li><strong>OPD/IPD:</strong> Patient visit records</li>
                    <li><strong>Lab:</strong> Laboratory test results</li>
                </ul>

                <h6 class="mt-3">Example files from your uploads:</h6>
                <ul class="list-unstyled">
                    <li>📄 tmp_drug_opd.xlsx - Outpatient drug data</li>
                    <li>📄 s_tmp_drug_opd.xlsx - Summarized drug data</li>
                </ul>

                <p class="mt-3 mb-0">
                    <a href="dashboard.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
