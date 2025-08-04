<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Set page title
$page_title = "Database Backup";

// Initialize variables
$success_message = '';
$error_message = '';
$backup_settings = [];
$backup_files = [];

// Connect to database
$conn = connectDB();

// Fetch current backup settings
try {
    $result = $conn->query("SELECT * FROM system_settings WHERE setting_key LIKE 'backup_%'");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $key_name = str_replace('backup_', '', $row['setting_key']);
            $backup_settings[$key_name] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    $error_message = "Error retrieving backup settings: " . $e->getMessage();
}

// Create backup directory if it doesn't exist
$backup_dir = '../backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Get list of backup files
if (file_exists($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($backup_dir . '/' . $file),
                'date' => filemtime($backup_dir . '/' . $file)
            ];
        }
    }
    
    // Sort backup files by date (newest first)
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Handle manual backup
if (isset($_POST['create_backup'])) {
    try {
        // Generate backup filename
        $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Get database credentials from config
        $db_host = DB_HOST;
        $db_name = DB_NAME;
        $db_user = DB_USER;
        $db_pass = DB_PASS;
        
        // Create backup command
        $command = "mysqldump --host={$db_host} --user={$db_user} --password={$db_pass} {$db_name} > {$backup_file}";
        
        // Execute backup command
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $success_message = "Database backup created successfully.";
            
            // Log the action
            logAction($_SESSION['user_id'], "Created database backup: " . basename($backup_file));
            
            // Refresh page to update backup files list
            header("Location: backup_system.php?success=Backup created successfully");
            exit();
        } else {
            $error_message = "Failed to create database backup. Error code: {$return_var}";
        }
    } catch (Exception $e) {
        $error_message = "Error creating backup: " . $e->getMessage();
    }
}

// Handle backup deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $delete_file = basename($_GET['delete']);
    $file_path = $backup_dir . '/' . $delete_file;
    
    // Validate file exists and is within backup directory
    if (file_exists($file_path) && is_file($file_path) && pathinfo($delete_file, PATHINFO_EXTENSION) == 'sql') {
        if (unlink($file_path)) {
            // Log the action
            logAction($_SESSION['user_id'], "Deleted database backup: {$delete_file}");
            
            // Redirect with success message
            header("Location: backup_system.php?success=Backup file deleted successfully");
            exit();
        } else {
            $error_message = "Failed to delete backup file.";
        }
    } else {
        $error_message = "Invalid backup file.";
    }
}

// Handle backup download
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $download_file = basename($_GET['download']);
    $file_path = $backup_dir . '/' . $download_file;
    
    // Validate file exists and is within backup directory
    if (file_exists($file_path) && is_file($file_path) && pathinfo($download_file, PATHINFO_EXTENSION) == 'sql') {
        // Log the action
        logAction($_SESSION['user_id'], "Downloaded database backup: {$download_file}");
        
        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $download_file . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        
        // Output file and exit
        readfile($file_path);
        exit();
    } else {
        $error_message = "Invalid backup file.";
    }
}

// Handle database restore
if (isset($_POST['restore_backup']) && !empty($_POST['backup_file'])) {
    try {
        $restore_file = basename($_POST['backup_file']);
        $file_path = $backup_dir . '/' . $restore_file;
        
        // Validate file exists and is within backup directory
        if (file_exists($file_path) && is_file($file_path) && pathinfo($restore_file, PATHINFO_EXTENSION) == 'sql') {
            // Get database credentials from config
            $db_host = DB_HOST;
            $db_name = DB_NAME;
            $db_user = DB_USER;
            $db_pass = DB_PASS;
            
            // Create restore command
            $command = "mysql --host={$db_host} --user={$db_user} --password={$db_pass} {$db_name} < {$file_path}";
            
            // Execute restore command
            exec($command, $output, $return_var);
            
            if ($return_var === 0) {
                // Log the action
                logAction($_SESSION['user_id'], "Restored database from backup: {$restore_file}");
                
                $success_message = "Database restored successfully from backup.";
            } else {
                $error_message = "Failed to restore database. Error code: {$return_var}";
            }
        } else {
            $error_message = "Invalid backup file.";
        }
    } catch (Exception $e) {
        $error_message = "Error restoring backup: " . $e->getMessage();
    }
}

// Handle backup settings update
if (isset($_POST['save_settings'])) {
    try {
        // Get form data
        $auto_backup = isset($_POST['auto_backup']) ? 1 : 0;
        $backup_frequency = isset($_POST['backup_frequency']) ? $_POST['backup_frequency'] : 'daily';
        $backup_retention = isset($_POST['backup_retention']) ? (int)$_POST['backup_retention'] : 30;
        $backup_time = isset($_POST['backup_time']) ? $_POST['backup_time'] : '00:00';
        
        // Validate data
        if ($backup_retention <= 0) {
            $error_message = "Backup retention period must be greater than zero.";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            // Update settings
            $settings = [
                'auto_backup' => $auto_backup,
                'backup_frequency' => $backup_frequency,
                'backup_retention' => $backup_retention,
                'backup_time' => $backup_time
            ];
              foreach ($settings as $name => $value) {
                $setting_key = 'backup_' . $name;
                $setting_desc = 'Backup setting for ' . str_replace('_', ' ', $name);
                
                // Check if setting exists
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM system_settings WHERE setting_key = ?");
                $stmt->bind_param("s", $setting_key);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    // Update
                    $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->bind_param("ss", $value, $setting_key);
                } else {
                    // Insert
                    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $setting_key, $value, $setting_desc);
                }
                
                $stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Update backup settings variable
            $backup_settings = $settings;
            
            // Log the action
            logAction($_SESSION['user_id'], "Updated backup settings");
            
            $success_message = "Backup settings updated successfully.";
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        $error_message = "Failed to update backup settings: " . $e->getMessage();
    }
}

// Show success message from URL parameter
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Database Backup</li>
        </ol>
    </nav>

    <h1 class="mb-4"><i class="fas fa-database me-2"></i>Database Backup & Restore</h1>
    
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
        <div class="col-lg-8">
            <!-- Backup Files -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-archive me-2"></i>Available Backups</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($backup_files)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No backup files found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Filename</th>
                                        <th>Size</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backup_files as $file): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($file['name']); ?></td>
                                        <td><?php echo formatBytes($file['size']); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', $file['date']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="backup_system.php?download=<?php echo urlencode($file['name']); ?>" class="btn btn-info" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#restoreModal" data-backup-file="<?php echo htmlspecialchars($file['name']); ?>" title="Restore">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-backup-file="<?php echo htmlspecialchars($file['name']); ?>" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="" class="mt-3">
                        <button type="submit" name="create_backup" class="btn btn-success">
                            <i class="fas fa-plus-circle me-2"></i>Create New Backup
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Backup Tips -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Backup & Restore Tips</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        <h6><strong>Best Practices for Database Backups</strong></h6>
                        <ul class="mb-3">
                            <li>Schedule regular automated backups</li>
                            <li>Store backups in multiple locations (local and cloud)</li>
                            <li>Test restoration process periodically</li>
                            <li>Create manual backups before major system changes</li>
                            <li>Monitor backup success and storage space</li>
                        </ul>
                        
                        <h6><strong>Restoring a Database</strong></h6>
                        <ul class="mb-0">
                            <li>Restoring will <strong>replace all current data</strong> with data from the backup file</li>
                            <li>The application will be temporarily unavailable during restoration</li>
                            <li>User sessions will be terminated</li>
                            <li>Always verify backup integrity before restoring</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Backup Settings -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Backup Settings</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="auto_backup" name="auto_backup" 
                                <?php echo (isset($backup_settings['auto_backup']) && $backup_settings['auto_backup']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="auto_backup">Enable Automated Backups</label>
                        </div>
                        
                        <div class="mb-3">
                            <label for="backup_frequency" class="form-label">Backup Frequency</label>
                            <select class="form-select" id="backup_frequency" name="backup_frequency">
                                <option value="daily" <?php echo (isset($backup_settings['backup_frequency']) && $backup_settings['backup_frequency'] === 'daily') ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo (isset($backup_settings['backup_frequency']) && $backup_settings['backup_frequency'] === 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo (isset($backup_settings['backup_frequency']) && $backup_settings['backup_frequency'] === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="backup_time" class="form-label">Backup Time</label>
                            <input type="time" class="form-control" id="backup_time" name="backup_time" 
                                value="<?php echo isset($backup_settings['backup_time']) ? htmlspecialchars($backup_settings['backup_time']) : '00:00'; ?>">
                            <div class="form-text">Server time (24-hour format)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="backup_retention" class="form-label">Retention Period (days)</label>
                            <input type="number" class="form-control" id="backup_retention" name="backup_retention" min="1" max="365" 
                                value="<?php echo isset($backup_settings['backup_retention']) ? (int)$backup_settings['backup_retention'] : 30; ?>">
                            <div class="form-text">Older backups will be automatically deleted</div>
                        </div>
                        
                        <button type="submit" name="save_settings" class="btn btn-success w-100">
                            <i class="fas fa-save me-2"></i>Save Backup Settings
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Backup Statistics -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Backup Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Calculate backup statistics
                    $total_backups = count($backup_files);
                    $total_size = 0;
                    $latest_backup = null;
                    $oldest_backup = null;
                    
                    foreach ($backup_files as $file) {
                        $total_size += $file['size'];
                        
                        if ($latest_backup === null || $file['date'] > $latest_backup) {
                            $latest_backup = $file['date'];
                        }
                        
                        if ($oldest_backup === null || $file['date'] < $oldest_backup) {
                            $oldest_backup = $file['date'];
                        }
                    }
                    ?>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><strong>Total Backups:</strong></span>
                            <span><?php echo $total_backups; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span><strong>Total Size:</strong></span>
                            <span><?php echo formatBytes($total_size); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span><strong>Latest Backup:</strong></span>
                            <span><?php echo $latest_backup ? date('Y-m-d H:i', $latest_backup) : 'None'; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><strong>Oldest Backup:</strong></span>
                            <span><?php echo $oldest_backup ? date('Y-m-d H:i', $oldest_backup) : 'None'; ?></span>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php if ($latest_backup && (time() - $latest_backup) > (86400 * 3)): ?>
                            Last backup is more than 3 days old. Consider creating a new backup.
                        <?php else: ?>
                            Regular backups are essential for data safety.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Restore Database</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <strong>Warning!</strong> Restoring from a backup will replace all current data with data from the backup file. This action cannot be undone.
                </div>
                <p>Are you sure you want to restore the database from the selected backup file?</p>
                <p class="mb-0"><strong>File:</strong> <span id="restore-filename"></span></p>
            </div>
            <div class="modal-footer">
                <form method="post" action="">
                    <input type="hidden" name="backup_file" id="restore-file-input" value="">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="restore_backup" class="btn btn-warning">
                        <i class="fas fa-undo me-2"></i>Restore Database
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Delete Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this backup file?</p>
                <p class="mb-0"><strong>File:</strong> <span id="delete-filename"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="delete-file-link" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Restore modal
    const restoreModal = document.getElementById('restoreModal');
    if (restoreModal) {
        restoreModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const backupFile = button.getAttribute('data-backup-file');
            
            document.getElementById('restore-filename').textContent = backupFile;
            document.getElementById('restore-file-input').value = backupFile;
        });
    }
    
    // Delete modal
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const backupFile = button.getAttribute('data-backup-file');
            
            document.getElementById('delete-filename').textContent = backupFile;
            document.getElementById('delete-file-link').href = 'backup_system.php?delete=' + encodeURIComponent(backupFile);
        });
    }
});
</script>

<?php
/**
 * Format bytes to human readable format
 * @param int $bytes Number of bytes
 * @param int $precision Precision of rounding
 * @return string Formatted size with unit
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
      return round($bytes, $precision) . ' ' . $units[$pow];
}

// Close database connection
if (isset($conn) && $conn) {
    $conn->close();
}

// Include footer
include '../includes/footer.php';
?>
