<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Set page title
$page_title = "Security Settings";

// Initialize variables for displaying messages
$success_message = '';
$error_message = '';

// Connect to the database
$conn = connectDB();

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Password Policy Updates
    if (isset($_POST['update_password_policy'])) {
        $min_length = sanitizeInput($_POST['min_length']);
        $require_uppercase = isset($_POST['require_uppercase']) ? 1 : 0;
        $require_lowercase = isset($_POST['require_lowercase']) ? 1 : 0;
        $require_numbers = isset($_POST['require_numbers']) ? 1 : 0;
        $require_special = isset($_POST['require_special']) ? 1 : 0;
        $password_expiry_days = sanitizeInput($_POST['password_expiry_days']);
        
        // Update settings in the database
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'password_min_length'");
        $stmt->bind_param("s", $min_length);
        $stmt->execute();
        
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'password_require_uppercase'");
        $stmt->bind_param("s", $require_uppercase);
        $stmt->execute();
        
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'password_require_lowercase'");
        $stmt->bind_param("s", $require_lowercase);
        $stmt->execute();
        
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'password_require_numbers'");
        $stmt->bind_param("s", $require_numbers);
        $stmt->execute();
        
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'password_require_special'");
        $stmt->bind_param("s", $require_special);
        $stmt->execute();
        
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'password_expiry_days'");
        $stmt->bind_param("s", $password_expiry_days);
        $stmt->execute();
        
        $success_message = "Password policy updated successfully!";
        
        // Log the activity
        logActivity($_SESSION['user_id'], 'PASSWORD_POLICY_UPDATED', 'Updated password policy settings');
    }
    
    // Handle Session Security Updates
    if (isset($_POST['update_session_security'])) {
        $session_timeout = sanitizeInput($_POST['session_timeout']);
        $max_login_attempts = sanitizeInput($_POST['max_login_attempts']);
        $lockout_time = sanitizeInput($_POST['lockout_time']);
        
        // Update settings in the database
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'session_timeout'");
        $stmt->bind_param("s", $session_timeout);
        $stmt->execute();
        
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'max_login_attempts'");
        $stmt->bind_param("s", $max_login_attempts);
        $stmt->execute();
        
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'lockout_time'");
        $stmt->bind_param("s", $lockout_time);
        $stmt->execute();
        
        $success_message = "Session security settings updated successfully!";
        
        // Log the activity
        logActivity($_SESSION['user_id'], 'SESSION_SECURITY_UPDATED', 'Updated session security settings');
    }
    
    // Handle 2FA Settings
    if (isset($_POST['update_2fa_settings'])) {
        $enable_2fa = isset($_POST['enable_2fa']) ? 1 : 0;
        $enforce_2fa_admin = isset($_POST['enforce_2fa_admin']) ? 1 : 0;
        $enforce_2fa_security = isset($_POST['enforce_2fa_security']) ? 1 : 0;
        
        // Update settings in the database
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'enable_2fa'");
        $stmt->bind_param("s", $enable_2fa);
        $stmt->execute();
        
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'enforce_2fa_admin'");
        $stmt->bind_param("s", $enforce_2fa_admin);
        $stmt->execute();
        
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'enforce_2fa_security'");
        $stmt->bind_param("s", $enforce_2fa_security);
        $stmt->execute();
        
        $success_message = "Two-factor authentication settings updated successfully!";
        
        // Log the activity
        logActivity($_SESSION['user_id'], '2FA_SETTINGS_UPDATED', 'Updated 2FA settings');
    }
    
    // Handle IP Restriction Settings
    if (isset($_POST['update_ip_restrictions'])) {
        $enable_ip_restriction = isset($_POST['enable_ip_restriction']) ? 1 : 0;
        $allowed_ips = sanitizeInput($_POST['allowed_ips']);
        
        // Update settings in the database
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'enable_ip_restriction'");
        $stmt->bind_param("s", $enable_ip_restriction);
        $stmt->execute();
        
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'allowed_ips'");
        $stmt->bind_param("s", $allowed_ips);
        $stmt->execute();
        
        $success_message = "IP restriction settings updated successfully!";
        
        // Log the activity
        logActivity($_SESSION['user_id'], 'IP_RESTRICTIONS_UPDATED', 'Updated IP restriction settings');
    }
}

// Get current settings from database
function getSetting($conn, $setting_name, $default = '') {
    $stmt = $conn->prepare("SELECT value FROM settings WHERE name = ?");
    $stmt->bind_param("s", $setting_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['value'];
    }
    
    return $default;
}

// Get current security settings
$password_min_length = getSetting($conn, 'password_min_length', 8);
$password_require_uppercase = getSetting($conn, 'password_require_uppercase', 1);
$password_require_lowercase = getSetting($conn, 'password_require_lowercase', 1);
$password_require_numbers = getSetting($conn, 'password_require_numbers', 1);
$password_require_special = getSetting($conn, 'password_require_special', 1);
$password_expiry_days = getSetting($conn, 'password_expiry_days', 90);

$session_timeout = getSetting($conn, 'session_timeout', 30);
$max_login_attempts = getSetting($conn, 'max_login_attempts', 5);
$lockout_time = getSetting($conn, 'lockout_time', 15);

$enable_2fa = getSetting($conn, 'enable_2fa', 0);
$enforce_2fa_admin = getSetting($conn, 'enforce_2fa_admin', 0);
$enforce_2fa_security = getSetting($conn, 'enforce_2fa_security', 0);

$enable_ip_restriction = getSetting($conn, 'enable_ip_restriction', 0);
$allowed_ips = getSetting($conn, 'allowed_ips', '');

$conn->close();

// Include header
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $page_title; ?></h1>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Password Policy Card -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Password Policy</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="min_length" class="form-label">Minimum Password Length</label>
                            <input type="number" class="form-control" id="min_length" name="min_length" value="<?php echo $password_min_length; ?>" min="6" max="32">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="require_uppercase" name="require_uppercase" <?php echo ($password_require_uppercase ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="require_uppercase">Require Uppercase Letters</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="require_lowercase" name="require_lowercase" <?php echo ($password_require_lowercase ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="require_lowercase">Require Lowercase Letters</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="require_numbers" name="require_numbers" <?php echo ($password_require_numbers ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="require_numbers">Require Numbers</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="require_special" name="require_special" <?php echo ($password_require_special ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="require_special">Require Special Characters</label>
                        </div>
                        <div class="mb-3">
                            <label for="password_expiry_days" class="form-label">Password Expiry (days)</label>
                            <input type="number" class="form-control" id="password_expiry_days" name="password_expiry_days" value="<?php echo $password_expiry_days; ?>" min="0" max="365">
                            <small class="form-text text-muted">Set to 0 to never expire</small>
                        </div>
                        <button type="submit" name="update_password_policy" class="btn btn-primary">Update Password Policy</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Session Security Card -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Session Security</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                            <input type="number" class="form-control" id="session_timeout" name="session_timeout" value="<?php echo $session_timeout; ?>" min="5" max="1440">
                        </div>
                        <div class="mb-3">
                            <label for="max_login_attempts" class="form-label">Maximum Login Attempts</label>
                            <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" value="<?php echo $max_login_attempts; ?>" min="3" max="10">
                        </div>
                        <div class="mb-3">
                            <label for="lockout_time" class="form-label">Account Lockout Duration (minutes)</label>
                            <input type="number" class="form-control" id="lockout_time" name="lockout_time" value="<?php echo $lockout_time; ?>" min="5" max="1440">
                        </div>
                        <button type="submit" name="update_session_security" class="btn btn-primary">Update Session Security</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Two-Factor Authentication Card -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Two-Factor Authentication</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="enable_2fa" name="enable_2fa" <?php echo ($enable_2fa ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="enable_2fa">Enable Two-Factor Authentication (2FA)</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="enforce_2fa_admin" name="enforce_2fa_admin" <?php echo ($enforce_2fa_admin ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="enforce_2fa_admin">Enforce 2FA for Admin Users</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="enforce_2fa_security" name="enforce_2fa_security" <?php echo ($enforce_2fa_security ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="enforce_2fa_security">Enforce 2FA for Security Personnel</label>
                        </div>
                        <button type="submit" name="update_2fa_settings" class="btn btn-primary">Update 2FA Settings</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- IP Restriction Card -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">IP Restrictions</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="enable_ip_restriction" name="enable_ip_restriction" <?php echo ($enable_ip_restriction ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="enable_ip_restriction">Enable IP Restrictions</label>
                        </div>
                        <div class="mb-3">
                            <label for="allowed_ips" class="form-label">Allowed IP Addresses</label>
                            <textarea class="form-control" id="allowed_ips" name="allowed_ips" rows="4" placeholder="Enter one IP address or range per line (e.g., 192.168.1.1 or 192.168.1.0/24)"><?php echo $allowed_ips; ?></textarea>
                            <small class="form-text text-muted">Leave empty to allow all IPs</small>
                        </div>
                        <button type="submit" name="update_ip_restrictions" class="btn btn-primary">Update IP Restrictions</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Security Audit Log Card -->
        <div class="col-xl-12 col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Security Audit Log</h6>
                    <a href="export_logs.php?type=security" class="btn btn-sm btn-info">Export Security Logs</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="securityAuditTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Activity</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Connect again for the logs query
                                $conn = connectDB();
                                
                                // Get security audit logs - limit to recent 50 security-related actions
                                $sql = "SELECT l.id, u.username, l.activity_type, l.description, l.ip_address, l.timestamp 
                                        FROM logs l
                                        LEFT JOIN users u ON l.user_id = u.id
                                        WHERE l.activity_type LIKE '%PASSWORD%' 
                                           OR l.activity_type LIKE '%LOGIN%'
                                           OR l.activity_type LIKE '%SECURITY%'
                                           OR l.activity_type LIKE '%2FA%'
                                        ORDER BY l.timestamp DESC 
                                        LIMIT 50";
                                        
                                $result = $conn->query($sql);
                                
                                if ($result->num_rows > 0) {
                                    while($row = $result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . $row['id'] . "</td>";
                                        echo "<td>" . ($row['username'] ?? 'System') . "</td>";
                                        echo "<td>" . $row['activity_type'] . "</td>";
                                        echo "<td>" . $row['description'] . "</td>";
                                        echo "<td>" . $row['ip_address'] . "</td>";
                                        echo "<td>" . $row['timestamp'] . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center'>No security logs found</td></tr>";
                                }
                                
                                $conn->close();
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize DataTable for security audit logs
        $('#securityAuditTable').DataTable({
            "order": [[0, "desc"]]
        });    });
</script>

<?php 
// Close database connection
$conn->close();

include '../includes/footer.php'; 
?>
