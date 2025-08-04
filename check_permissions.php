<?php
// check_permissions.php - Check file and directory permissions for Gunayatan Gatepass System
require_once 'includes/config.php';

// Check if user is logged in and has admin role
$allow_access = false;

if (isLoggedIn() && $_SESSION['role'] == 'admin') {
    $allow_access = true;
} else {
    // Allow access if accessed from localhost for setup or troubleshooting
    $localhost_ips = array('127.0.0.1', '::1');
    if (in_array($_SERVER['REMOTE_ADDR'], $localhost_ips)) {
        $allow_access = true;
    }
}

if (!$allow_access) {
    header("Location: index.php");
    exit();
}

// Define directories and files that need specific permissions
$permissions_check = array(
    // Directories that need to be writable
    'directories' => array(
        'uploads' => array(
            'path' => 'uploads',
            'required_permission' => 'writable',
            'description' => 'Directory for uploaded files'
        ),
        'templates' => array(
            'path' => 'templates',
            'required_permission' => 'writable',
            'description' => 'Directory for email and PDF templates'
        )
    ),
    
    // Files that need to be writable
    'files' => array(
        'config' => array(
            'path' => 'includes/config.php',
            'required_permission' => 'readable',
            'description' => 'Database configuration file'
        ),
        'database' => array(
            'path' => 'database.sql',
            'required_permission' => 'readable',
            'description' => 'SQL database schema file'
        )
    )
);

// Get server info
$server_info = array(
    'server_software' => $_SERVER['SERVER_SOFTWARE'],
    'php_version' => PHP_VERSION,
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
    'os' => PHP_OS,
    'max_upload_size' => ini_get('upload_max_filesize'),
    'max_post_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time') . ' seconds',
    'server_time' => date('Y-m-d H:i:s'),
    'server_timezone' => date_default_timezone_get()
);

// Check PHP extensions
$required_extensions = array(
    'mysqli' => array(
        'name' => 'MySQLi',
        'required' => true,
        'description' => 'Required for database operations'
    ),
    'gd' => array(
        'name' => 'GD Library',
        'required' => true,
        'description' => 'Required for image processing'
    ),
    'mbstring' => array(
        'name' => 'Multibyte String',
        'required' => true,
        'description' => 'Required for handling UTF-8 characters'
    ),
    'curl' => array(
        'name' => 'cURL',
        'required' => false,
        'description' => 'Recommended for API communications'
    ),
    'zip' => array(
        'name' => 'ZIP',
        'required' => false,
        'description' => 'Recommended for handling ZIP files'
    )
);

// Function to check directory permissions
function checkDirectoryPermission($path, $permission) {
    $result = array(
        'exists' => file_exists($path),
        'is_dir' => is_dir($path),
        'is_readable' => is_readable($path),
        'is_writable' => is_writable($path)
    );
    
    if ($permission == 'writable') {
        $result['status'] = $result['exists'] && $result['is_dir'] && $result['is_writable'];
    } elseif ($permission == 'readable') {
        $result['status'] = $result['exists'] && $result['is_dir'] && $result['is_readable'];
    }
    
    return $result;
}

// Function to check file permissions
function checkFilePermission($path, $permission) {
    $result = array(
        'exists' => file_exists($path),
        'is_file' => is_file($path),
        'is_readable' => is_readable($path),
        'is_writable' => is_writable($path)
    );
    
    if ($permission == 'writable') {
        $result['status'] = $result['exists'] && $result['is_file'] && $result['is_writable'];
    } elseif ($permission == 'readable') {
        $result['status'] = $result['exists'] && $result['is_file'] && $result['is_readable'];
    }
    
    return $result;
}

// Function to get file permissions in octal format
function getFilePermissions($path) {
    if (file_exists($path)) {
        return substr(sprintf('%o', fileperms($path)), -4);
    }
    return 'N/A';
}

// Function to fix directory permissions
function fixDirectoryPermissions($path) {
    if (file_exists($path)) {
        return chmod($path, 0755); // rwxr-xr-x
    }
    return false;
}

// Function to fix file permissions
function fixFilePermissions($path) {
    if (file_exists($path)) {
        return chmod($path, 0644); // rw-r--r--
    }
    return false;
}

// Handle permission fixes
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fix_permissions'])) {
    $fixed_count = 0;
    
    // Fix directory permissions
    foreach ($permissions_check['directories'] as $key => $dir) {
        if (isset($_POST['fix_' . $key])) {
            if (fixDirectoryPermissions($dir['path'])) {
                $fixed_count++;
            }
        }
    }
    
    // Fix file permissions
    foreach ($permissions_check['files'] as $key => $file) {
        if (isset($_POST['fix_' . $key])) {
            if (fixFilePermissions($file['path'])) {
                $fixed_count++;
            }
        }
    }
    
    if ($fixed_count > 0) {
        $message = "Successfully attempted to fix permissions for $fixed_count item(s).";
    } else {
        $message = "No permissions were fixed. This could be due to insufficient server permissions.";
    }
    
    // Log the action
    if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], "PERMISSIONS_FIXED", "Admin attempted to fix $fixed_count file/directory permissions");
    }
}

// Create missing directories
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_directories'])) {
    $created_count = 0;
    
    // Create directories
    foreach ($permissions_check['directories'] as $key => $dir) {
        if (isset($_POST['create_' . $key])) {
            if (!file_exists($dir['path'])) {
                if (mkdir($dir['path'], 0755, true)) {
                    $created_count++;
                }
            }
        }
    }
    
    if ($created_count > 0) {
        $message = "Successfully created $created_count directory/directories.";
    } else {
        $message = "No directories were created. This could be due to insufficient server permissions.";
    }
    
    // Log the action
    if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], "DIRECTORIES_CREATED", "Admin created $created_count directories");
    }
}

// Check all directory permissions
$directory_checks = array();
foreach ($permissions_check['directories'] as $key => $dir) {
    $directory_checks[$key] = array(
        'info' => $dir,
        'check' => checkDirectoryPermission($dir['path'], $dir['required_permission']),
        'permissions' => getFilePermissions($dir['path'])
    );
}

// Check all file permissions
$file_checks = array();
foreach ($permissions_check['files'] as $key => $file) {
    $file_checks[$key] = array(
        'info' => $file,
        'check' => checkFilePermission($file['path'], $file['required_permission']),
        'permissions' => getFilePermissions($file['path'])
    );
}

// Check extensions
$extension_checks = array();
foreach ($required_extensions as $ext => $info) {
    $extension_checks[$ext] = array(
        'info' => $info,
        'loaded' => extension_loaded($ext)
    );
}

// Overall status
$all_permissions_ok = true;
$all_extensions_ok = true;

foreach ($directory_checks as $check) {
    if (!$check['check']['status']) {
        $all_permissions_ok = false;
        break;
    }
}

if ($all_permissions_ok) {
    foreach ($file_checks as $check) {
        if (!$check['check']['status']) {
            $all_permissions_ok = false;
            break;
        }
    }
}

foreach ($extension_checks as $ext => $check) {
    if ($check['info']['required'] && !$check['loaded']) {
        $all_extensions_ok = false;
        break;
    }
}

// App is ready if all required permissions and extensions are OK
$app_ready = $all_permissions_ok && $all_extensions_ok;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Permissions Check - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 40px 0;
        }
        .check-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .check-item {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .check-pass {
            background-color: #d1e7dd;
            border-left: 4px solid #198754;
        }
        .check-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .check-fail {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .status-badge {
            font-size: 0.8em;
            padding: 3px 8px;
        }
        .overall-status {
            font-size: 1.2em;
            padding: 10px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        .server-info {
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="check-container">
            <div class="logo">
                <h1><?php echo APP_NAME; ?></h1>
                <p class="text-muted">System Permissions Check</p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-info">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="overall-status <?php echo $app_ready ? 'check-pass' : 'check-fail'; ?>">
                <i class="fas <?php echo $app_ready ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                System Status: <?php echo $app_ready ? 'Ready' : 'Not Ready'; ?>
            </div>
            
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Directory Permissions</h5>
                    <div>
                        <span class="badge bg-<?php echo $all_permissions_ok ? 'success' : 'danger'; ?>">
                            <?php echo $all_permissions_ok ? 'All OK' : 'Issues Found'; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?php foreach ($directory_checks as $key => $check): ?>
                            <div class="check-item <?php echo $check['check']['status'] ? 'check-pass' : 'check-fail'; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h5 class="mb-0"><?php echo $check['info']['path']; ?></h5>
                                        <small><?php echo $check['info']['description']; ?></small>
                                    </div>
                                    <div>
                                        <?php if ($check['check']['status']): ?>
                                            <span class="badge bg-success status-badge">
                                                <i class="fas fa-check me-1"></i> OK
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger status-badge">
                                                <i class="fas fa-times me-1"></i> Error
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row g-2 mb-2">
                                    <div class="col-md-3">
                                        <span class="badge bg-<?php echo $check['check']['exists'] ? 'success' : 'danger'; ?>">
                                            <?php echo $check['check']['exists'] ? 'Exists' : 'Missing'; ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="badge bg-<?php echo $check['check']['is_dir'] ? 'success' : 'danger'; ?>">
                                            <?php echo $check['check']['is_dir'] ? 'Is Directory' : 'Not Directory'; ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="badge bg-<?php echo $check['check']['is_readable'] ? 'success' : 'danger'; ?>">
                                            <?php echo $check['check']['is_readable'] ? 'Readable' : 'Not Readable'; ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="badge bg-<?php echo $check['check']['is_writable'] ? 'success' : 'danger'; ?>">
                                            <?php echo $check['check']['is_writable'] ? 'Writable' : 'Not Writable'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <div>
                                        Permissions: <code><?php echo $check['permissions']; ?></code>
                                    </div>
                                    <div>
                                        <?php if (!$check['check']['exists']): ?>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="create_<?php echo $key; ?>" id="create_<?php echo $key; ?>">
                                                <label class="form-check-label" for="create_<?php echo $key; ?>">Create</label>
                                            </div>
                                        <?php elseif (!$check['check']['status']): ?>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="fix_<?php echo $key; ?>" id="fix_<?php echo $key; ?>">
                                                <label class="form-check-label" for="fix_<?php echo $key; ?>">Fix</label>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (!$all_permissions_ok): ?>
                            <div class="mt-3">
                                <button type="submit" name="fix_permissions" class="btn btn-primary">
                                    <i class="fas fa-wrench me-2"></i>Fix Selected Permissions
                                </button>
                                <button type="submit" name="create_directories" class="btn btn-success ms-2">
                                    <i class="fas fa-folder-plus me-2"></i>Create Selected Directories
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">File Permissions</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?php foreach ($file_checks as $key => $check): ?>
                            <div class="check-item <?php echo $check['check']['status'] ? 'check-pass' : 'check-fail'; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h5 class="mb-0"><?php echo $check['info']['path']; ?></h5>
                                        <small><?php echo $check['info']['description']; ?></small>
                                    </div>
                                    <div>
                                        <?php if ($check['check']['status']): ?>
                                            <span class="badge bg-success status-badge">
                                                <i class="fas fa-check me-1"></i> OK
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger status-badge">
                                                <i class="fas fa-times me-1"></i> Error
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row g-2 mb-2">
                                    <div class="col-md-3">
                                        <span class="badge bg-<?php echo $check['check']['exists'] ? 'success' : 'danger'; ?>">
                                            <?php echo $check['check']['exists'] ? 'Exists' : 'Missing'; ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="badge bg-<?php echo $check['check']['is_file'] ? 'success' : 'danger'; ?>">
                                            <?php echo $check['check']['is_file'] ? 'Is File' : 'Not File'; ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="badge bg-<?php echo $check['check']['is_readable'] ? 'success' : 'danger'; ?>">
                                            <?php echo $check['check']['is_readable'] ? 'Readable' : 'Not Readable'; ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="badge bg-<?php echo $check['check']['is_writable'] ? 'success' : 'danger'; ?>">
                                            <?php echo $check['check']['is_writable'] ? 'Writable' : 'Not Writable'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <div>
                                        Permissions: <code><?php echo $check['permissions']; ?></code>
                                    </div>
                                    <div>
                                        <?php if (!$check['check']['status'] && $check['check']['exists']): ?>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="fix_<?php echo $key; ?>" id="fix_<?php echo $key; ?>">
                                                <label class="form-check-label" for="fix_<?php echo $key; ?>">Fix</label>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (!$all_permissions_ok): ?>
                            <div class="mt-3">
                                <button type="submit" name="fix_permissions" class="btn btn-primary">
                                    <i class="fas fa-wrench me-2"></i>Fix Selected Permissions
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">PHP Extensions</h5>
                    <div>
                        <span class="badge bg-<?php echo $all_extensions_ok ? 'success' : 'danger'; ?>">
                            <?php echo $all_extensions_ok ? 'All Required Extensions OK' : 'Missing Required Extensions'; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach ($extension_checks as $ext => $check): ?>
                        <div class="check-item <?php if ($check['info']['required']) { echo $check['loaded'] ? 'check-pass' : 'check-fail'; } else { echo $check['loaded'] ? 'check-pass' : 'check-warning'; } ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0"><?php echo $check['info']['name']; ?></h5>
                                    <small>
                                        <?php echo $check['info']['description']; ?> 
                                        (<?php echo $check['info']['required'] ? 'Required' : 'Recommended'; ?>)
                                    </small>
                                </div>
                                <div>
                                    <?php if ($check['loaded']): ?>
                                        <span class="badge bg-success status-badge">
                                            <i class="fas fa-check me-1"></i> Loaded
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-<?php echo $check['info']['required'] ? 'danger' : 'warning'; ?> status-badge">
                                            <i class="fas <?php echo $check['info']['required'] ? 'fa-times' : 'fa-exclamation-triangle'; ?> me-1"></i>
                                            <?php echo $check['info']['required'] ? 'Not Loaded' : 'Missing'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Server Information</h5>
                </div>
                <div class="card-body">
                    <table class="table server-info">
                        <tbody>
                            <?php foreach ($server_info as $key => $value): ?>
                                <tr>
                                    <th><?php echo ucwords(str_replace('_', ' ', $key)); ?></th>
                                    <td><?php echo $value; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="<?php echo isLoggedIn() ? 'admin/dashboard.php' : 'index.php'; ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
                
                <a href="install.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-database me-2"></i>Database Installation
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
