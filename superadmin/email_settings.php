<?php
require_once '../includes/config.php';

// Try to load Composer's autoloader if it exists, but don't fail if it doesn't
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Set page title
$page_title = "Email Settings";

// Initialize variables
$success_message = '';
$error_message = '';

// Default email settings to prevent undefined index warnings
$email_settings = [
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_auth' => 0,
    'smtp_username' => '',
    'smtp_password' => '',
    'from_email' => '',
    'from_name' => APP_NAME,
    'notification_emails' => 0,
    'notify_new_user' => 0,
    'notify_gatepass' => 0,
    'notify_approval' => 0,
    'notify_failed_login' => 0,
    'notify_backup' => 0
];

// Connect to database
$conn = connectDB();

// Fetch current email settings
try {
    $result = $conn->query("SELECT * FROM system_settings WHERE setting_key LIKE 'email_%'");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $key_name = str_replace('email_', '', $row['setting_key']);
            $email_settings[$key_name] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    $error_message = "Error retrieving email settings: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $smtp_host = trim($_POST['smtp_host']);
    $smtp_port = (int)$_POST['smtp_port'];
    $smtp_secure = trim($_POST['smtp_secure']);
    $smtp_auth = isset($_POST['smtp_auth']) ? 1 : 0;
    $smtp_username = trim($_POST['smtp_username']);
    $smtp_password = trim($_POST['smtp_password']);
    $from_email = trim($_POST['from_email']);
    $from_name = trim($_POST['from_name']);
    $notification_emails = isset($_POST['notification_emails']) ? 1 : 0;
    
    // Validate inputs
    $validation_errors = [];
    
    if (empty($smtp_host)) {
        $validation_errors[] = "SMTP Host is required";
    }
    
    if ($smtp_port <= 0 || $smtp_port > 65535) {
        $validation_errors[] = "Invalid SMTP Port";
    }
    
    if (empty($from_email)) {
        $validation_errors[] = "From Email is required";
    } elseif (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = "Invalid From Email format";
    }
    
    if (empty($from_name)) {
        $validation_errors[] = "From Name is required";
    }
    
    // If no validation errors, update settings
    if (empty($validation_errors)) {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // Update or insert settings
            $settings = [
                'smtp_host' => $smtp_host,
                'smtp_port' => $smtp_port,
                'smtp_secure' => $smtp_secure,
                'smtp_auth' => $smtp_auth,
                'smtp_username' => $smtp_username,
                'from_email' => $from_email,
                'from_name' => $from_name,
                'notification_emails' => $notification_emails
            ];
            
            // Only update password if it's not empty (to avoid overwriting with blank)
            if (!empty($smtp_password)) {
                $settings['smtp_password'] = $smtp_password;
            }
              foreach ($settings as $name => $value) {
                $setting_key = 'email_' . $name;
                $setting_desc = 'Email setting for ' . str_replace('_', ' ', $name);
                
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
            
            // Update email settings variable for the view
            $email_settings = $settings;
            
            // Log the action
            logAction($_SESSION['user_id'], "Updated email settings");
            
            // Set success message
            $success_message = "Email settings saved successfully.";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Failed to save email settings: " . $e->getMessage();
        }
    } else {
        // Set error message from validation errors
        $error_message = implode("<br>", $validation_errors);
    }
}

// Process notification settings submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notifications'])) {
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Array of notification settings
        $notification_settings = [
            'notify_new_user' => isset($_POST['notify_new_user']) ? 1 : 0,
            'notify_gatepass' => isset($_POST['notify_gatepass']) ? 1 : 0,
            'notify_approval' => isset($_POST['notify_approval']) ? 1 : 0,
            'notify_failed_login' => isset($_POST['notify_failed_login']) ? 1 : 0,
            'notify_backup' => isset($_POST['notify_backup']) ? 1 : 0
        ];
          foreach ($notification_settings as $name => $value) {
            $setting_key = 'email_' . $name;
            $setting_desc = 'Email notification setting for ' . str_replace('_', ' ', $name);
            
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
        
        // Update email settings variable for the view
        foreach ($notification_settings as $name => $value) {
            $email_settings[$name] = $value;
        }
        
        // Log the action
        logAction($_SESSION['user_id'], "Updated email notification settings");
        
        // Set success message
        $success_message = "Email notification settings saved successfully.";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Failed to save notification settings: " . $e->getMessage();
    }
}

// Test email functionality
if (isset($_POST['test_email'])) {
    $test_recipient = trim($_POST['test_recipient']);
    
    // Validate recipient email
    if (empty($test_recipient) || !filter_var($test_recipient, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please provide a valid recipient email address.";
    } elseif (empty($email_settings['smtp_host']) || empty($email_settings['from_email'])) {
        $error_message = "Please configure and save SMTP settings before sending a test email.";
    } else {
        // Include PHPMailer
        require_once '../includes/phpmailer/PHPMailer.php';
        require_once '../includes/phpmailer/SMTP.php';
        require_once '../includes/phpmailer/Exception.php';
          try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $email_settings['smtp_host'];
            $mail->Port = (int)$email_settings['smtp_port'];
            $mail->SMTPDebug = 0; // Set to 2 for detailed debug output
            
            if (!empty($email_settings['smtp_secure'])) {
                if ($email_settings['smtp_secure'] == 'tls') {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($email_settings['smtp_secure'] == 'ssl') {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                }
            }
            
            if (!empty($email_settings['smtp_auth'])) {
                $mail->SMTPAuth = true;
                if (!empty($email_settings['smtp_username'])) {
                    $mail->Username = $email_settings['smtp_username'];
                }
                if (!empty($email_settings['smtp_password'])) {
                    $mail->Password = $email_settings['smtp_password'];
                }
            }
            
            // Recipients
            $mail->setFrom($email_settings['from_email'], $email_settings['from_name']);
            $mail->addAddress($test_recipient);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Test Email from ' . APP_NAME;
            $mail->Body = '<div style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                <h1 style="color: #3498db;">Test Email</h1>
                <p>This is a test email from ' . APP_NAME . ' at ' . date('Y-m-d H:i:s') . '</p>
                <p>If you received this email, your email settings are configured correctly.</p>
                <hr>
                <p style="font-size: 12px; color: #777;">This is an automated message, please do not reply.</p>
            </div>';
            
            $mail->AltBody = 'This is a test email from ' . APP_NAME . ' at ' . date('Y-m-d H:i:s') . "\n\n" .
                             'If you received this email, your email settings are configured correctly.';
              // Debug info (for development only)
            $debug_info = "<strong>Email Configuration:</strong><br>";
            $debug_info .= "Host: " . htmlspecialchars($mail->Host) . "<br>";
            $debug_info .= "Port: " . (int)$mail->Port . "<br>";
            $debug_info .= "Secure: " . htmlspecialchars($mail->SMTPSecure) . "<br>";
            $debug_info .= "Auth: " . ($mail->SMTPAuth ? 'Yes' : 'No') . "<br>";
            if ($mail->SMTPAuth) {
                $debug_info .= "Username: " . htmlspecialchars($mail->Username) . "<br>";
            }
            $debug_info .= "From: " . htmlspecialchars($mail->From) . "<br>";
            $debug_info .= "From Name: " . htmlspecialchars($mail->FromName) . "<br>";
            $debug_info .= "To: " . htmlspecialchars($test_recipient) . "<br>";
            
            // This implementation logs email data for debugging purposes
            if ($mail->send()) {
                $success_message = "Test email sent successfully to " . htmlspecialchars($test_recipient) . ".";
                $success_message .= "<div class='mt-3 alert alert-info'>" . $debug_info . "<br><small>Note: A log of the email has been created in includes/mail_log.txt</small></div>";
                logAction($_SESSION['user_id'], "Sent test email to $test_recipient");
            } else {
                $error_message = "Failed to send test email: " . htmlspecialchars($mail->ErrorInfo);
            }} catch (Exception $e) {
            $error_message = "Failed to send test email: " . $e->getMessage();
            // Add additional error details
            $error_message .= "<div class='mt-2'><strong>Troubleshooting:</strong><ul>
                <li>Verify that the SMTP host and port are correct</li>
                <li>Check if SSL/TLS settings match your mail server</li>
                <li>Confirm username and password are correct</li>
                <li>Ensure your mail server allows this connection</li>
                <li>Check if any firewall is blocking outgoing connections</li>
            </ul></div>";
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Email Settings</li>
        </ol>
    </nav>

    <h1 class="mb-4"><i class="fas fa-envelope me-2"></i>Email Settings</h1>
    
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
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>SMTP Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="smtp_host" class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo isset($email_settings['smtp_host']) ? htmlspecialchars($email_settings['smtp_host']) : ''; ?>" required>
                                <div class="form-text">e.g., smtp.gmail.com, mail.example.com</div>
                            </div>
                            <div class="col-md-6">
                                <label for="smtp_port" class="form-label">SMTP Port</label>
                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo isset($email_settings['smtp_port']) ? (int)$email_settings['smtp_port'] : 587; ?>" required>
                                <div class="form-text">Common ports: 25, 465, 587</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="smtp_secure" class="form-label">Encryption</label>
                                <select class="form-select" id="smtp_secure" name="smtp_secure">
                                    <option value="" <?php echo (!isset($email_settings['smtp_secure']) || $email_settings['smtp_secure'] === '') ? 'selected' : ''; ?>>None</option>
                                    <option value="tls" <?php echo (isset($email_settings['smtp_secure']) && $email_settings['smtp_secure'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo (isset($email_settings['smtp_secure']) && $email_settings['smtp_secure'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                </select>
                                <div class="form-text">Transport Layer Security (TLS) is recommended</div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="smtp_auth" name="smtp_auth" <?php echo (isset($email_settings['smtp_auth']) && $email_settings['smtp_auth']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="smtp_auth">
                                        SMTP Authentication
                                    </label>
                                </div>
                                <div class="form-text">Most SMTP servers require authentication</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="smtp_username" class="form-label">SMTP Username</label>
                                <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo isset($email_settings['smtp_username']) ? htmlspecialchars($email_settings['smtp_username']) : ''; ?>">
                                <div class="form-text">Usually your email address</div>
                            </div>
                            <div class="col-md-6">
                                <label for="smtp_password" class="form-label">SMTP Password</label>
                                <input type="password" class="form-control" id="smtp_password" name="smtp_password" placeholder="<?php echo isset($email_settings['smtp_password']) ? '••••••••' : ''; ?>">
                                <div class="form-text">Leave blank to keep existing password</div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="from_email" class="form-label">From Email</label>
                                <input type="email" class="form-control" id="from_email" name="from_email" value="<?php echo isset($email_settings['from_email']) ? htmlspecialchars($email_settings['from_email']) : ''; ?>" required>
                                <div class="form-text">The email address that will appear as sender</div>
                            </div>
                            <div class="col-md-6">
                                <label for="from_name" class="form-label">From Name</label>
                                <input type="text" class="form-control" id="from_name" name="from_name" value="<?php echo isset($email_settings['from_name']) ? htmlspecialchars($email_settings['from_name']) : APP_NAME; ?>" required>
                                <div class="form-text">The name that will appear as sender</div>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="notification_emails" name="notification_emails" <?php echo (isset($email_settings['notification_emails']) && $email_settings['notification_emails']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notification_emails">
                                Enable Email Notifications
                            </label>
                            <div class="form-text">Send email notifications for important system events</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Test Email</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Send a test email to verify your configuration.</p>
                    
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="test_recipient" class="form-label">Recipient Email</label>
                            <input type="email" class="form-control" id="test_recipient" name="test_recipient" required>
                        </div>
                        
                        <button type="submit" name="test_email" class="btn btn-success w-100">
                            <i class="fas fa-paper-plane me-2"></i>Send Test Email
                        </button>
                    </form>
                    
                    <hr>
                    
                    <div class="alert alert-info mb-0">
                        <h6><i class="fas fa-info-circle me-2"></i>Troubleshooting Tips</h6>
                        <ul class="mb-0 small">
                            <li>Verify SMTP server address and port</li>
                            <li>Check username and password</li>
                            <li>Make sure the email account allows SMTP access</li>
                            <li>For Gmail, enable "Less secure apps" or use App Passwords</li>
                            <li>Check if your server allows outgoing connections on SMTP ports</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Settings</h5>
                </div>
                <div class="card-body">                    <p class="text-muted mb-3">Configure which events trigger email notifications.</p>
                    
                    <form method="post" action="" id="notification-form">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="notify_new_user" name="notify_new_user" value="1"
                                <?php echo (isset($email_settings['notify_new_user']) && $email_settings['notify_new_user'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notify_new_user">New user registration</label>
                        </div>
                        
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="notify_gatepass" name="notify_gatepass" value="1"
                                <?php echo (isset($email_settings['notify_gatepass']) && $email_settings['notify_gatepass'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notify_gatepass">New gatepass created</label>
                        </div>
                        
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="notify_approval" name="notify_approval" value="1"
                                <?php echo (isset($email_settings['notify_approval']) && $email_settings['notify_approval'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notify_approval">Gatepass approval status</label>
                        </div>
                        
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="notify_failed_login" name="notify_failed_login" value="1"
                                <?php echo (isset($email_settings['notify_failed_login']) && $email_settings['notify_failed_login'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notify_failed_login">Failed login attempts</label>
                        </div>
                        
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="notify_backup" name="notify_backup" value="1"
                                <?php echo (isset($email_settings['notify_backup']) && $email_settings['notify_backup'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notify_backup">Database backup completed</label>
                        </div>
                        
                        <button type="submit" name="save_notifications" class="btn btn-info mt-3">
                            <i class="fas fa-save me-2"></i>Save Notification Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle SMTP authentication fields
    const smtpAuthCheck = document.getElementById('smtp_auth');
    const smtpUsername = document.getElementById('smtp_username');
    const smtpPassword = document.getElementById('smtp_password');
    
    function toggleAuthFields() {
        const isAuth = smtpAuthCheck.checked;
        smtpUsername.required = isAuth;
        smtpUsername.disabled = !isAuth;
        smtpPassword.disabled = !isAuth;
    }
    
    smtpAuthCheck.addEventListener('change', toggleAuthFields);
    toggleAuthFields(); // Initialize on page load
});
</script>

<?php
// Close database connection
$conn->close();

// Include footer
include '../includes/footer.php';
?>
