<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Initialize settings array
$settings = [];

// Create system_settings table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Initial settings if table is empty
$check_settings = $conn->query("SELECT COUNT(*) AS count FROM system_settings");
$settings_count = $check_settings->fetch_assoc()['count'];

if ($settings_count == 0) {
    $initial_settings = [
        ['site_name', 'Gunayatan Gatepass System', 'The name of the system displayed in various places'],
        ['company_name', 'Gunayatan', 'The company name used in reports and PDFs'],
        ['company_address', '123 Main Street, City, Country', 'Company address for official documents'],
        ['company_email', 'info@your_domain_name', 'Official company email'],
        ['company_phone', '+1234567890', 'Official company phone number'],
        ['logo_path', '../assets/img/logo.png', 'Path to company logo file'],
        ['allow_self_registration', '1', 'Allow users to register themselves (1 = yes, 0 = no)'],
        ['require_admin_approval', '1', 'Require admin approval for new user registrations (1 = yes, 0 = no)'],
        ['gatepass_prefix', 'GP-', 'Prefix for gatepass numbers'],
        ['timezone', 'Asia/Kolkata', 'System timezone'],
        ['email_notifications', '0', 'Enable email notifications (1 = yes, 0 = no)'],
        ['system_maintenance_mode', '0', 'Put system in maintenance mode (1 = yes, 0 = no)']
    ];
    
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
    
    foreach ($initial_settings as $setting) {
        $stmt->bind_param("sss", $setting[0], $setting[1], $setting[2]);
        $stmt->execute();
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect all settings from form
    $setting_keys = isset($_POST['setting_key']) ? $_POST['setting_key'] : [];
    $setting_values = isset($_POST['setting_value']) ? $_POST['setting_value'] : [];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        $update_stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        
        for ($i = 0; $i < count($setting_keys); $i++) {
            $key = sanitizeInput($setting_keys[$i]);
            $value = sanitizeInput($setting_values[$i]);
            
            $update_stmt->bind_param("ss", $value, $key);
            $update_stmt->execute();
        }
        
        // Handle file upload for logo
        if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            $file_type = $_FILES['logo_upload']['type'];
            $file_size = $_FILES['logo_upload']['size'];
            
            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                $file_name = 'company_logo_' . time() . '.' . pathinfo($_FILES['logo_upload']['name'], PATHINFO_EXTENSION);
                $upload_path = '../assets/img/' . $file_name;
                
                if (move_uploaded_file($_FILES['logo_upload']['tmp_name'], $upload_path)) {
                    // Update logo path in settings
                    $logo_path = '../assets/img/' . $file_name;
                    $update_stmt->bind_param("ss", $logo_path, 'logo_path');
                    $update_stmt->execute();
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Log the action
        logAction($_SESSION['user_id'], 'SETTINGS_UPDATED', 'System settings updated');
        
        $_SESSION['flash_message'] = "System settings updated successfully";
        $_SESSION['flash_type'] = "success";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['flash_message'] = "Failed to update settings: " . $e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
    
    // Redirect to refresh the page and prevent form resubmission
    header("Location: system_settings.php");
    exit();
}

// Get all settings
$result = $conn->query("SELECT * FROM system_settings ORDER BY id ASC");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = [
        'value' => $row['setting_value'],
        'description' => $row['setting_description']
    ];
}

// Set page title
$page_title = "System Settings";

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-cogs me-2"></i>System Settings</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
    
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i> Changing system settings can affect the entire application. Please proceed with caution.
    </div>

    <form method="post" enctype="multipart/form-data">
        <!-- General Settings -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>General Settings</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="site_name" class="form-label">Site Name</label>
                        <input type="text" class="form-control" id="site_name" name="setting_value[]" 
                               value="<?php echo htmlspecialchars($settings['site_name']['value']); ?>">
                        <input type="hidden" name="setting_key[]" value="site_name">
                        <div class="form-text"><?php echo htmlspecialchars($settings['site_name']['description']); ?></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="company_name" class="form-label">Company Name</label>
                        <input type="text" class="form-control" id="company_name" name="setting_value[]" 
                               value="<?php echo htmlspecialchars($settings['company_name']['value']); ?>">
                        <input type="hidden" name="setting_key[]" value="company_name">
                        <div class="form-text"><?php echo htmlspecialchars($settings['company_name']['description']); ?></div>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label for="company_address" class="form-label">Company Address</label>
                        <textarea class="form-control" id="company_address" name="setting_value[]" rows="2"><?php echo htmlspecialchars($settings['company_address']['value']); ?></textarea>
                        <input type="hidden" name="setting_key[]" value="company_address">
                        <div class="form-text"><?php echo htmlspecialchars($settings['company_address']['description']); ?></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="company_email" class="form-label">Company Email</label>
                        <input type="email" class="form-control" id="company_email" name="setting_value[]" 
                               value="<?php echo htmlspecialchars($settings['company_email']['value']); ?>">
                        <input type="hidden" name="setting_key[]" value="company_email">
                        <div class="form-text"><?php echo htmlspecialchars($settings['company_email']['description']); ?></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="company_phone" class="form-label">Company Phone</label>
                        <input type="text" class="form-control" id="company_phone" name="setting_value[]" 
                               value="<?php echo htmlspecialchars($settings['company_phone']['value']); ?>">
                        <input type="hidden" name="setting_key[]" value="company_phone">
                        <div class="form-text"><?php echo htmlspecialchars($settings['company_phone']['description']); ?></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="logo_path" class="form-label">Current Logo</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="logo_path" name="setting_value[]" 
                                   value="<?php echo htmlspecialchars($settings['logo_path']['value']); ?>" readonly>
                            <input type="hidden" name="setting_key[]" value="logo_path">
                        </div>
                        <div class="form-text"><?php echo htmlspecialchars($settings['logo_path']['description']); ?></div>
                        <?php if (file_exists('..' . str_replace('..', '', $settings['logo_path']['value']))): ?>
                            <div class="mt-2">
                                <img src="<?php echo htmlspecialchars($settings['logo_path']['value']); ?>" alt="Company Logo" class="img-thumbnail" style="max-height: 100px;">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="logo_upload" class="form-label">Upload New Logo</label>
                        <input type="file" class="form-control" id="logo_upload" name="logo_upload" accept="image/*">
                        <div class="form-text">Upload a new logo (JPEG, PNG, GIF, max 2MB)</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Configuration -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-tools me-2"></i>System Configuration</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="timezone" class="form-label">System Timezone</label>
                        <select class="form-select" id="timezone" name="setting_value[]">
                            <?php
                            $timezones = DateTimeZone::listIdentifiers();
                            foreach ($timezones as $tz) {
                                $selected = ($settings['timezone']['value'] == $tz) ? 'selected' : '';
                                echo "<option value=\"$tz\" $selected>$tz</option>";
                            }
                            ?>
                        </select>
                        <input type="hidden" name="setting_key[]" value="timezone">
                        <div class="form-text"><?php echo htmlspecialchars($settings['timezone']['description']); ?></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="gatepass_prefix" class="form-label">Gatepass Number Prefix</label>
                        <input type="text" class="form-control" id="gatepass_prefix" name="setting_value[]" 
                               value="<?php echo htmlspecialchars($settings['gatepass_prefix']['value']); ?>">
                        <input type="hidden" name="setting_key[]" value="gatepass_prefix">
                        <div class="form-text"><?php echo htmlspecialchars($settings['gatepass_prefix']['description']); ?></div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="allow_self_registration" 
                                   name="setting_value[]" value="1" <?php echo $settings['allow_self_registration']['value'] == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_self_registration">Allow Self Registration</label>
                            <input type="hidden" name="setting_key[]" value="allow_self_registration">
                            <input type="hidden" name="setting_value_hidden[]" value="0">
                        </div>
                        <div class="form-text"><?php echo htmlspecialchars($settings['allow_self_registration']['description']); ?></div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="require_admin_approval" 
                                   name="setting_value[]" value="1" <?php echo $settings['require_admin_approval']['value'] == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="require_admin_approval">Require Admin Approval</label>
                            <input type="hidden" name="setting_key[]" value="require_admin_approval">
                            <input type="hidden" name="setting_value_hidden[]" value="0">
                        </div>
                        <div class="form-text"><?php echo htmlspecialchars($settings['require_admin_approval']['description']); ?></div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="email_notifications" 
                                   name="setting_value[]" value="1" <?php echo $settings['email_notifications']['value'] == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="email_notifications">Email Notifications</label>
                            <input type="hidden" name="setting_key[]" value="email_notifications">
                            <input type="hidden" name="setting_value_hidden[]" value="0">
                        </div>
                        <div class="form-text"><?php echo htmlspecialchars($settings['email_notifications']['description']); ?></div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="system_maintenance_mode" 
                                   name="setting_value[]" value="1" <?php echo $settings['system_maintenance_mode']['value'] == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="system_maintenance_mode">Maintenance Mode</label>
                            <input type="hidden" name="setting_key[]" value="system_maintenance_mode">
                            <input type="hidden" name="setting_value_hidden[]" value="0">
                        </div>
                        <div class="form-text"><?php echo htmlspecialchars($settings['system_maintenance_mode']['description']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <button type="reset" class="btn btn-outline-secondary me-2">
                <i class="fas fa-undo me-2"></i>Reset Changes
            </button>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save me-2"></i>Save All Settings
            </button>
        </div>
    </form>
</div>

<script>
// Handle checkboxes for settings
document.addEventListener('DOMContentLoaded', function() {
    // Process the form before submission to handle unchecked checkboxes
    document.querySelector('form').addEventListener('submit', function(e) {
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(function(checkbox) {
            if (!checkbox.checked) {
                // Create a hidden input to send a value of 0 for unchecked checkboxes
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = checkbox.name;
                hiddenInput.value = '0';
                checkbox.parentNode.appendChild(hiddenInput);
            }
        });
    });
});
</script>

<?php
// Close database connection
$conn->close();

// Include footer
include '../includes/footer.php';
?>
