<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Set page title
$page_title = "Database Management";

// Initialize variables
$success_message = '';
$error_message = '';
$db_info = [];
$tables_info = [];

// Connect to database
$conn = connectDB();

// Process backup request
if (isset($_POST['backup_database'])) {
    // Set maximum execution time to 300 seconds (5 minutes)
    ini_set('max_execution_time', 300);
    
    try {
        // Get tables
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $tables[] = $row[0];
        }
        
        $backup_file = '../uploads/backups/db_backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Make sure backup directory exists
        if (!is_dir('../uploads/backups')) {
            mkdir('../uploads/backups', 0777, true);
        }
        
        $handle = fopen($backup_file, 'w');
        
        // Add header comments
        fwrite($handle, "-- Database Backup\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Backup Tool: " . APP_NAME . " Superadmin Panel\n\n");
        
        // Process each table
        foreach ($tables as $table) {
            fwrite($handle, "-- Table structure for table `$table`\n");
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            
            $result = $conn->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch_row();
            
            fwrite($handle, $row[1] . ";\n\n");
            
            fwrite($handle, "-- Dumping data for table `$table`\n");
            
            $result = $conn->query("SELECT * FROM `$table`");
            $num_fields = $result->field_count;
            $num_rows = $result->num_rows;
            
            if ($num_rows > 0) {
                while ($row = $result->fetch_row()) {
                    fwrite($handle, "INSERT INTO `$table` VALUES (");
                    
                    for ($i = 0; $i < $num_fields; $i++) {
                        if (isset($row[$i])) {
                            $row[$i] = addslashes($row[$i]);
                            $row[$i] = str_replace("\n", "\\n", $row[$i]);
                            fwrite($handle, '"' . $row[$i] . '"');
                        } else {
                            fwrite($handle, 'NULL');
                        }
                        
                        if ($i < ($num_fields - 1)) {
                            fwrite($handle, ',');
                        }
                    }
                    
                    fwrite($handle, ");\n");
                }
            } else {
                fwrite($handle, "-- Table `$table` has no data\n");
            }
            
            fwrite($handle, "\n\n");
        }
        
        fclose($handle);
        
        // Log the backup
        logAction($_SESSION['user_id'], "Created database backup: " . basename($backup_file));
        
        $success_message = "Database backup created successfully: " . basename($backup_file);
        
    } catch (Exception $e) {
        $error_message = "Backup failed: " . $e->getMessage();
    }
}

// Process optimization request
if (isset($_POST['optimize_database'])) {
    try {
        // Get tables
        $result = $conn->query("SHOW TABLES");
        $tables = [];
        
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $tables[] = $row[0];
        }
        
        // Optimize each table
        $optimized = 0;
        
        foreach ($tables as $table) {
            $conn->query("OPTIMIZE TABLE `$table`");
            $optimized++;
        }
        
        // Log the action
        logAction($_SESSION['user_id'], "Optimized database tables: $optimized tables");
        
        $success_message = "$optimized tables optimized successfully.";
        
    } catch (Exception $e) {
        $error_message = "Optimization failed: " . $e->getMessage();
    }
}

// Get database info
try {
    // Database size
    $result = $conn->query("SELECT 
        SUM(data_length + index_length) / 1024 / 1024 AS size_mb 
        FROM information_schema.TABLES 
        WHERE table_schema = '" . DB_NAME . "'");
    
    if ($result) {
        $row = $result->fetch_assoc();
        $db_info['size_mb'] = round($row['size_mb'], 2);
    }
    
    // Number of tables
    $result = $conn->query("SELECT COUNT(*) as table_count FROM information_schema.TABLES 
        WHERE table_schema = '" . DB_NAME . "'");
    
    if ($result) {
        $row = $result->fetch_assoc();
        $db_info['table_count'] = $row['table_count'];
    }
    
    // Get MySQL version
    $db_info['mysql_version'] = $conn->server_info;
    
    // Get table information
    $result = $conn->query("SELECT 
        table_name, 
        engine, 
        table_rows, 
        data_length/1024/1024 as data_size_mb, 
        index_length/1024/1024 as index_size_mb,
        (data_length + index_length)/1024/1024 as total_size_mb,
        create_time,
        update_time
        FROM information_schema.TABLES 
        WHERE table_schema = '" . DB_NAME . "'
        ORDER BY (data_length + index_length) DESC");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['data_size_mb'] = round($row['data_size_mb'], 2);
            $row['index_size_mb'] = round($row['index_size_mb'], 2);
            $row['total_size_mb'] = round($row['total_size_mb'], 2);
            $tables_info[] = $row;
        }
    }
    
    // Get existing backups
    $backups = [];
    $backup_dir = '../uploads/backups/';
    
    if (is_dir($backup_dir)) {
        $files = scandir($backup_dir);
        
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && strpos($file, 'db_backup_') === 0) {
                $backups[] = [
                    'name' => $file,
                    'size' => round(filesize($backup_dir . $file) / 1024 / 1024, 2),
                    'date' => date('Y-m-d H:i:s', filemtime($backup_dir . $file))
                ];
            }
        }
        
        // Sort backups by date (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }
    
} catch (Exception $e) {
    $error_message = "Error retrieving database information: " . $e->getMessage();
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Database Management</li>
        </ol>
    </nav>

    <h1 class="mb-4"><i class="fas fa-database me-2"></i>Database Management</h1>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Database Info Card -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Database Information</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <th>Database Name</th>
                            <td><?php echo DB_NAME; ?></td>
                        </tr>
                        <tr>
                            <th>Database Size</th>
                            <td><?php echo isset($db_info['size_mb']) ? $db_info['size_mb'] . ' MB' : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th>Number of Tables</th>
                            <td><?php echo isset($db_info['table_count']) ? $db_info['table_count'] : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th>MySQL Version</th>
                            <td><?php echo isset($db_info['mysql_version']) ? $db_info['mysql_version'] : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th>Connection Charset</th>
                            <td><?php echo $conn->character_set_name(); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Card -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body d-flex flex-column">
                    <p class="text-muted mb-4">Perform database maintenance operations.</p>
                    
                    <form method="post" class="mb-3">
                        <button type="submit" name="backup_database" class="btn btn-primary mb-3 w-100">
                            <i class="fas fa-download me-2"></i>Backup Database
                        </button>
                        <div class="form-text mb-3">Creates a full SQL dump of the database.</div>
                        
                        <button type="submit" name="optimize_database" class="btn btn-warning mb-3 w-100">
                            <i class="fas fa-compress-arrows-alt me-2"></i>Optimize Tables
                        </button>
                        <div class="form-text">Optimizes database tables to improve performance.</div>
                    </form>
                    
                    <div class="mt-auto">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>Regular backups are recommended for data safety.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Backup History Card -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Backup History</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($backups)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Backup File</th>
                                        <th>Size</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr>
                                            <td><?php echo $backup['name']; ?></td>
                                            <td><?php echo $backup['size']; ?> MB</td>
                                            <td><?php echo $backup['date']; ?></td>
                                            <td>
                                                <a href="../uploads/backups/<?php echo $backup['name']; ?>" class="btn btn-sm btn-primary" download>
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No backup history available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tables Info Card -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-table me-2"></i>Database Tables</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Engine</th>
                            <th>Rows</th>
                            <th>Data Size (MB)</th>
                            <th>Index Size (MB)</th>
                            <th>Total Size (MB)</th>
                            <th>Created</th>
                            <th>Last Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tables_info as $table): ?>
                            <tr>
                                <td><?php echo $table['table_name']; ?></td>
                                <td><?php echo $table['engine']; ?></td>
                                <td><?php echo $table['table_rows'] ? number_format($table['table_rows']) : 'N/A'; ?></td>
                                <td><?php echo $table['data_size_mb']; ?></td>
                                <td><?php echo $table['index_size_mb']; ?></td>
                                <td><?php echo $table['total_size_mb']; ?></td>
                                <td><?php echo $table['create_time'] ? date('Y-m-d', strtotime($table['create_time'])) : 'N/A'; ?></td>
                                <td><?php echo $table['update_time'] ? date('Y-m-d', strtotime($table['update_time'])) : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>    </div>
</div>

<?php
// Close database connection
$conn->close();

// Include footer
include '../includes/footer.php';
?>
