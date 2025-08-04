<?php
// install.php - Database installation script for Gunayatan Gatepass System
// Check if config file exists, if not create a temporary one
if (!file_exists('includes/config.php')) {
    // Create empty config file with default values
    $config_content = '<?php
// Database Configuration
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "");
define("DB_NAME", "gatepass");

// Application Constants
define("APP_NAME", "Gunayatan Gatepass System");
define("APP_URL", "http://localhost/gatepass");

// Start session
session_start();

// Error Reporting (turn off in production)
ini_set("display_errors", 0);
ini_set("display_startup_errors", 0);
error_reporting(E_ALL);

// Date/Time Configuration
date_default_timezone_set("Asia/Kolkata");

// Placeholder functions
function connectDB() { return null; }
function sanitizeInput($data) { return $data; }
function logActivity($userId, $action, $details = "") { return true; }
function isLoggedIn() { return false; }
?>';
    file_put_contents('includes/config.php.tmp', $config_content);
    require_once 'includes/config.php.tmp';
} else {
    require_once 'includes/config.php';
}

// Define variables
$error = '';
$success = '';
$installed = false;
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$total_steps = 4;

// Check if already installed
$config_exists = file_exists('includes/config.php') && filesize('includes/config.php') > 100;
$db_connected = false;
$tables_created = false;

if ($config_exists) {
    // Try connecting to database with current config
    try {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
        if (!$conn->connect_error) {
            $db_connected = true;
            
            // Check if database exists
            $check_db = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
            
            if ($check_db->num_rows > 0) {
                // Check if users table exists
                $conn->select_db(DB_NAME);
                $check_table = $conn->query("SHOW TABLES LIKE 'users'");
                
                if ($check_table->num_rows > 0) {
                    $tables_created = true;
                    $installed = true;
                }
            }
        }
    } catch (Exception $e) {
        // Connection failed, continue with the installation
    }
}

// Handle database configuration (Step 2)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['configure_db'])) {
    $db_host = trim($_POST['db_host']);
    $db_user = trim($_POST['db_user']);
    $db_pass = $_POST['db_pass']; // Don't trim password as it may contain spaces
    $db_name = trim($_POST['db_name']);
    
    if (empty($db_host) || empty($db_user) || empty($db_name)) {
        $error = "Please fill out all required database fields.";
    } else {
        // Try to connect to the database server
        $test_conn = @new mysqli($db_host, $db_user, $db_pass);
        
        if ($test_conn->connect_error) {
            $error = "Database connection failed: " . $test_conn->connect_error;
        } else {
            // Success - update config.php
            $config_template = file_get_contents('includes/config.php');
            
            // Replace database configuration
            $config_template = preg_replace('/define\(\'DB_HOST\',\s*\'.*?\'\);/', "define('DB_HOST', '$db_host');", $config_template);
            $config_template = preg_replace('/define\(\'DB_USER\',\s*\'.*?\'\);/', "define('DB_USER', '$db_user');", $config_template);
            $config_template = preg_replace('/define\(\'DB_PASS\',\s*\'.*?\'\);/', "define('DB_PASS', '$db_pass');", $config_template);
            $config_template = preg_replace('/define\(\'DB_NAME\',\s*\'.*?\'\);/', "define('DB_NAME', '$db_name');", $config_template);
            
            if (file_put_contents('includes/config.php.new', $config_template)) {
                // Rename the config file
                if (rename('includes/config.php.new', 'includes/config.php')) {
                    $success = "Database configuration saved successfully!";
                    // Move to the next step
                    header("Location: install.php?step=3");
                    exit;
                } else {
                    $error = "Failed to update configuration file. Please check file permissions.";
                }
            } else {
                $error = "Failed to write configuration file. Please check file permissions.";
            }
            
            $test_conn->close();
        }
    }
}

// Handle database installation (Step 3)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['install_db'])) {
    // Read the database.sql file
    $sql_contents = file_get_contents('database.sql');
    
    if ($sql_contents === false) {
        $error = "Could not read the database.sql file. Please check file permissions.";
    } else {
        // First load the updated config
        if (file_exists('includes/config.php')) {
            require_once 'includes/config.php';
        }
        
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($conn->connect_error) {
            $error = "Connection failed: " . $conn->connect_error;
        } else {
            // Create database if it doesn't exist
            if ($conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4") === false) {
                $error = "Error creating database: " . $conn->error;
            } else {
                // Select the database
                $conn->select_db(DB_NAME);
                
                // Execute SQL statements one by one
                $statements = explode(';', $sql_contents);
                $success_count = 0;
                $total_statements = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    
                    if (!empty($statement)) {
                        $total_statements++;
                        
                        if ($conn->query($statement) === true) {
                            $success_count++;
                        } else {
                            $error .= "Error executing SQL: " . $conn->error . "<br>";
                        }
                    }
                }
                
                if ($success_count == $total_statements) {
                    $success = "Database installed successfully! $success_count statements executed.";
                    $tables_created = true;
                    
                    // Move to the next step
                    header("Location: install.php?step=4");
                    exit;
                } else {
                    $error = "Some SQL statements failed. $success_count of $total_statements statements executed successfully.";
                }
            }
            
            $conn->close();
        }
    }
}

// Handle creating admin account (Step 4)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_admin'])) {
    $admin_username = trim($_POST['admin_username']);
    $admin_password = $_POST['admin_password'];
    $admin_confirm_password = $_POST['admin_confirm_password'];
    $admin_name = trim($_POST['admin_name']);
    $admin_email = trim($_POST['admin_email']);
    
    if (empty($admin_username) || empty($admin_password) || empty($admin_name) || empty($admin_email)) {
        $error = "Please fill out all fields.";
    } else if ($admin_password !== $admin_confirm_password) {
        $error = "Passwords do not match.";
    } else if (strlen($admin_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Load config
        require_once 'includes/config.php';
        
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                $error = "Connection failed: " . $conn->connect_error;
            } else {
                // Check if username or email already exists
                $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $check->bind_param("ss", $admin_username, $admin_email);
                $check->execute();
                $result = $check->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "Username or email already exists.";
                } else {
                    // Hash password
                    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                    
                    // Insert admin user
                    $stmt = $conn->prepare("INSERT INTO users (username, password, name, email, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
                    $role = 'admin';
                    $status = 'active';
                    $stmt->bind_param("ssss", $admin_username, $hashed_password, $admin_name, $admin_email);
                    
                    if ($stmt->execute()) {
                        // Log the installation
                        $admin_id = $conn->insert_id;
                        $conn->query("INSERT INTO logs (user_id, action, details, ip_address) VALUES ($admin_id, 'SYSTEM_INSTALLED', 'System installed successfully', '" . $_SERVER['REMOTE_ADDR'] . "')");
                        
                        $success = "Installation completed successfully!";
                        $installed = true;
                        
                        // Create an installation completion file
                        file_put_contents('.installed', date('Y-m-d H:i:s'));
                        
                        // Redirect to login page after 2 seconds
                        header("Refresh: 2; URL=index.php");
                    } else {
                        $error = "Error creating admin user: " . $stmt->error;
                    }
                    
                    $stmt->close();
                }
                
                $conn->close();
            }
        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

// Handle uninstallation process (for development purposes only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['uninstall']) && isset($_GET['dev'])) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        $error = "Connection failed: " . $conn->connect_error;
    } else {
        // Drop the database
        if ($conn->query("DROP DATABASE IF EXISTS " . DB_NAME) === true) {
            $success = "Database uninstalled successfully!";
            $installed = false;
            $tables_created = false;
            
            // Remove installation marker
            if (file_exists('.installed')) {
                unlink('.installed');
            }
        } else {
            $error = "Error uninstalling database: " . $conn->error;
        }
        
        $conn->close();
    }
}

// Handle site settings (Step 2)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['site_settings'])) {
    $app_name = trim($_POST['app_name']);
    $app_url = trim($_POST['app_url']);
    $timezone = trim($_POST['timezone']);
    
    if (empty($app_name) || empty($app_url)) {
        $error = "Please fill out all required fields.";
    } else {
        // Update config.php
        $config_template = file_get_contents('includes/config.php');
        
        // Replace site configuration
        $config_template = preg_replace('/define\(\'APP_NAME\',\s*\'.*?\'\);/', "define('APP_NAME', '$app_name');", $config_template);
        $config_template = preg_replace('/define\(\'APP_URL\',\s*\'.*?\'\);/', "define('APP_URL', '$app_url');", $config_template);
        $config_template = preg_replace('/date_default_timezone_set\(\'.*?\'\);/', "date_default_timezone_set('$timezone');", $config_template);
        
        if (file_put_contents('includes/config.php.new', $config_template)) {
            // Rename the config file
            if (rename('includes/config.php.new', 'includes/config.php')) {
                $success = "Site settings saved successfully!";
                // Move to the next step
                header("Location: install.php?step=3");
                exit;
            } else {
                $error = "Failed to update configuration file. Please check file permissions.";
            }
        } else {
            $error = "Failed to write configuration file. Please check file permissions.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            padding: 40px 0;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background */
        .area {
            background: transparent;
            width: 100%;
            height: 100%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: -1;
        }
        
        .circles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .circles li {
            position: absolute;
            display: block;
            list-style: none;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.2);
            animation: animate 25s linear infinite;
            bottom: -150px;
            border-radius: 50%;
        }
        
        .circles li:nth-child(1) {
            left: 25%;
            width: 80px;
            height: 80px;
            animation-delay: 0s;
        }
        
        .circles li:nth-child(2) {
            left: 10%;
            width: 20px;
            height: 20px;
            animation-delay: 2s;
            animation-duration: 12s;
        }
        
        .circles li:nth-child(3) {
            left: 70%;
            width: 20px;
            height: 20px;
            animation-delay: 4s;
        }
        
        .circles li:nth-child(4) {
            left: 40%;
            width: 60px;
            height: 60px;
            animation-delay: 0s;
            animation-duration: 18s;
        }
        
        .circles li:nth-child(5) {
            left: 65%;
            width: 20px;
            height: 20px;
            animation-delay: 0s;
        }
        
        .circles li:nth-child(6) {
            left: 75%;
            width: 110px;
            height: 110px;
            animation-delay: 3s;
        }
        
        .circles li:nth-child(7) {
            left: 35%;
            width: 150px;
            height: 150px;
            animation-delay: 7s;
        }
        
        .circles li:nth-child(8) {
            left: 50%;
            width: 25px;
            height: 25px;
            animation-delay: 15s;
            animation-duration: 45s;
        }
        
        .circles li:nth-child(9) {
            left: 20%;
            width: 15px;
            height: 15px;
            animation-delay: 2s;
            animation-duration: 35s;
        }
        
        .circles li:nth-child(10) {
            left: 85%;
            width: 150px;
            height: 150px;
            animation-delay: 0s;
            animation-duration: 11s;
        }
        
        @keyframes animate {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
                border-radius: 0;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
                border-radius: 50%;
            }
        }
        
        .install-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            position: relative;
            z-index: 10;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo img {
            max-width: 150px;
            height: auto;
            filter: drop-shadow(0px 4px 6px rgba(0, 0, 0, 0.1));
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .logo img:hover {
            transform: scale(1.05);
        }
        
        .logo h2 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .step-container {
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .step-completed {
            background-color: #d1e7dd;
            border-left: 4px solid #198754;
        }
        
        .step-pending {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .step-active {
            background-color: #e8f4fd;
            border-left: 4px solid #0d6efd;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .steps-indicator {
            display: flex;
            margin-bottom: 30px;
            position: relative;
            justify-content: space-between;
        }
        
        .steps-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #ddd;
            z-index: 1;
        }
        
        .step-dot {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            z-index: 2;
            transition: all 0.3s;
        }
        
        .step-dot.completed {
            background: #198754;
            border-color: #198754;
            color: white;
        }
        
        .step-dot.active {
            background: #0d6efd;
            border-color: #0d6efd;
            color: white;
            transform: scale(1.2);
            box-shadow: 0 0 10px rgba(13, 110, 253, 0.5);
        }
        
        .step-label {
            position: absolute;
            top: 40px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            white-space: nowrap;
            color: #555;
        }
        
        .form-control, .input-group-text {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
            border-color: #3498db;
            background-color: #fff;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            background: linear-gradient(135deg, #2980b9, #2c3e50);
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 25px;
            transition: all 0.3s;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .completed-checkmark {
            width: 100px;
            height: 100px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            margin: 0 auto 30px;
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="area">
        <ul class="circles">
            <li></li><li></li><li></li><li></li><li></li>
            <li></li><li></li><li></li><li></li><li></li>
        </ul>
    </div>

    <div class="container">
        <div class="install-container">
            <div class="logo">
                <img src="assets/img/logo.png" alt="Gunayatan Logo" class="img-fluid">
                <h2><?php echo APP_NAME; ?></h2>
                <p class="text-muted">Installation Wizard</p>
            </div>
            
            <?php if (!$installed || isset($_GET['reset'])): ?>
                <!-- Progress Indicator -->
                <div class="steps-indicator">
                    <?php for($i = 1; $i <= $total_steps; $i++): ?>
                        <div class="step-indicator">
                            <div class="step-dot <?php 
                                if($step > $i) echo 'completed';
                                else if($step == $i) echo 'active';
                            ?>">
                                <?php if($step > $i): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <?php echo $i; ?>
                                <?php endif; ?>
                            </div>
                            <div class="step-label">
                                <?php 
                                    switch($i){
                                        case 1: echo "Requirements"; break;
                                        case 2: echo "Configuration"; break;
                                        case 3: echo "Database"; break;
                                        case 4: echo "Admin Account"; break;
                                    }
                                ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- STEP 1: System Requirements -->
            <?php if ($step == 1): ?>
                <div class="card mb-4 step-active">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-server me-2"></i> System Requirements</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">Please make sure your server meets the following requirements before proceeding with the installation.</p>
                        
                        <?php
                        $all_requirements_met = true;
                        $requirements = [
                            'PHP Version (>= 7.4)' => [
                                'status' => version_compare(PHP_VERSION, '7.4.0') >= 0,
                                'icon' => 'fab fa-php'
                            ],
                            'MySQLi Extension' => [
                                'status' => extension_loaded('mysqli'),
                                'icon' => 'fas fa-database'
                            ],
                            'PDO Extension' => [
                                'status' => extension_loaded('pdo'),
                                'icon' => 'fas fa-plug'
                            ],
                            'File Permissions' => [
                                'status' => is_writable('.'),
                                'icon' => 'fas fa-folder-open'
                            ],
                            'GD Library (for images)' => [
                                'status' => extension_loaded('gd'),
                                'icon' => 'fas fa-images'
                            ],
                            'ZIP Extension' => [
                                'status' => extension_loaded('zip'),
                                'icon' => 'fas fa-file-archive'
                            ]
                        ];
                        
                        foreach($requirements as $req => $data) {
                            if (!$data['status']) {
                                $all_requirements_met = false;
                            }
                        }
                        ?>
                        
                        <div class="row">
                            <?php foreach($requirements as $req => $data): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 <?php echo $data['status'] ? 'border-success' : 'border-danger'; ?>">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3 text-<?php echo $data['status'] ? 'success' : 'danger'; ?>">
                                                <i class="<?php echo $data['icon']; ?> fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo $req; ?></h6>
                                                <?php if ($data['status']): ?>
                                                    <small class="text-success"><i class="fas fa-check-circle"></i> Passed</small>
                                                <?php else: ?>
                                                    <small class="text-danger"><i class="fas fa-times-circle"></i> Failed</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($all_requirements_met): ?>
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle me-2"></i> All system requirements are met! You can proceed with the installation.
                            </div>
                            <div class="text-end mt-4">
                                <a href="install.php?step=2" class="btn btn-primary px-4">
                                    Next Step <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mt-3">
                                <i class="fas fa-exclamation-triangle me-2"></i> Your server does not meet all requirements. Please fix the issues before proceeding.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <!-- STEP 2: Configuration -->
            <?php elseif ($step == 2): ?>
                <div class="card mb-4 step-active">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i> Site Configuration</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">Configure your site settings and database connection.</p>
                        
                        <form method="post" action="">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Site Settings</h5>
                                    
                                    <div class="mb-3">
                                        <label for="app_name" class="form-label">Application Name <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-signature"></i></span>
                                            <input type="text" class="form-control" id="app_name" name="app_name" value="Gunayatan Gatepass System" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="app_url" class="form-label">Site URL <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-link"></i></span>
                                            <input type="text" class="form-control" id="app_url" name="app_url" value="<?php echo isset($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']) : 'http://localhost/gatepass'; ?>" required>
                                        </div>
                                        <small class="text-muted">URL of your application without trailing slash</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="timezone" class="form-label">Timezone</label>
                                        <select class="form-select" id="timezone" name="timezone">
                                            <?php
                                            $timezones = DateTimeZone::listIdentifiers();
                                            $current_timezone = 'Asia/Kolkata';
                                            foreach ($timezones as $timezone) {
                                                $selected = ($timezone == $current_timezone) ? 'selected' : '';
                                                echo "<option value='$timezone' $selected>$timezone</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Database Settings</h5>
                                    
                                    <div class="mb-3">
                                        <label for="db_host" class="form-label">Database Host <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-server"></i></span>
                                            <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                                        </div>
                                        <small class="text-muted">Usually localhost or 127.0.0.1</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="db_user" class="form-label">Database Username <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="db_user" name="db_user" value="root" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="db_pass" class="form-label">Database Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="db_pass" name="db_pass">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="db_name" class="form-label">Database Name <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-database"></i></span>
                                            <input type="text" class="form-control" id="db_name" name="db_name" value="gatepass" required>
                                        </div>
                                        <small class="text-muted">If it doesn't exist, we'll create it for you</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="install.php?step=1" class="btn btn-light px-4">
                                    <i class="fas fa-arrow-left me-2"></i> Previous
                                </a>
                                <button type="submit" name="configure_db" class="btn btn-primary px-4">
                                    Next Step <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
            <!-- STEP 3: Database Installation -->
            <?php elseif ($step == 3): ?>
                <div class="card mb-4 step-active">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-database me-2"></i> Database Installation</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$tables_created): ?>
                            <p class="text-muted mb-4">We will now create the necessary tables in your database.</p>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> This step will create all required tables and data for your Gatepass System. This may take a moment.
                            </div>
                            
                            <form method="post" action="">
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="install.php?step=2" class="btn btn-light px-4">
                                        <i class="fas fa-arrow-left me-2"></i> Previous
                                    </a>
                                    <button type="submit" name="install_db" class="btn btn-primary px-4">
                                        Install Database <i class="fas fa-database ms-2"></i>
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="circle-checkmark mb-4">
                                    <i class="fas fa-check-circle text-success fa-5x"></i>
                                </div>
                                <h4 class="mb-3">Database Installed Successfully!</h4>
                                <p class="text-muted mb-4">All database tables have been created successfully. You can now proceed to create your admin account.</p>
                                
                                <div class="d-flex justify-content-center mt-4">
                                    <a href="install.php?step=4" class="btn btn-primary px-4">
                                        Continue <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <!-- STEP 4: Create Admin Account -->
            <?php elseif ($step == 4): ?>
                <div class="card mb-4 step-active">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i> Create Admin Account</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">Create your administrator account to manage the gatepass system.</p>
                        
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="admin_username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="admin_username" name="admin_username" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="admin_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="admin_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                    <input type="text" class="form-control" id="admin_name" name="admin_name" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="admin_password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                                    </div>
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="admin_confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="admin_confirm_password" name="admin_confirm_password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="install.php?step=3" class="btn btn-light px-4">
                                    <i class="fas fa-arrow-left me-2"></i> Previous
                                </a>
                                <button type="submit" name="create_admin" class="btn btn-primary px-4">
                                    Complete Installation <i class="fas fa-check ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
            <!-- Installation Complete -->
            <?php elseif ($installed && ($tables_created || $step > 4)): ?>
                <div class="text-center py-4">
                    <div class="completed-checkmark mb-4">
                        <i class="fas fa-check"></i>
                    </div>
                    
                    <h3 class="mb-3">Installation Complete!</h3>
                    <p class="lead text-muted mb-4">Gunayatan Gatepass System has been successfully installed and is ready to use.</p>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 offset-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="mb-3">Next Steps</h5>
                                    <p class="mb-3">You can now log in to your admin account and start using the system.</p>
                                    <a href="index.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i> Go to Login
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($_GET['dev'])): ?>
                        <form method="post" action="?dev" class="mt-4">
                            <div class="alert alert-danger">
                                <strong>Warning:</strong> This will delete all data. For development purposes only.
                            </div>
                            <button type="submit" name="uninstall" class="btn btn-danger" onclick="return confirm('Are you sure you want to uninstall the database? All data will be lost!');">
                                <i class="fas fa-trash me-2"></i> Uninstall Database
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <p class="text-muted">© <?php echo date('Y'); ?> Gunayatan. All rights reserved.</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script to auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
            
            // Password toggle
            const togglePassword = document.querySelectorAll('.toggle-password');
            if (togglePassword) {
                togglePassword.forEach(function(button) {
                    button.addEventListener('click', function() {
                        const input = document.getElementById(this.dataset.target);
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        this.querySelector('i').classList.toggle('fa-eye');
                        this.querySelector('i').classList.toggle('fa-eye-slash');
                    });
                });
            }
        });
    </script>
</body>
</html>