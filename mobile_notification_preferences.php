<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = connectDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone_number = sanitizeInput($_POST['phone_number'] ?? '');
    $whatsapp_number = sanitizeInput($_POST['whatsapp_number'] ?? '');
    $telegram_chat_id = sanitizeInput($_POST['telegram_chat_id'] ?? '');
    
    $notification_preferences = [
        'email' => isset($_POST['notify_email']),
        'sms' => isset($_POST['notify_sms']),
        'whatsapp' => isset($_POST['notify_whatsapp']),
        'push' => isset($_POST['notify_push']),
        'telegram' => isset($_POST['notify_telegram']),
        'in_app' => isset($_POST['notify_in_app']),
        'urgent_only' => isset($_POST['urgent_only']),
        'quiet_hours_enabled' => isset($_POST['quiet_hours_enabled']),
        'quiet_hours_start' => sanitizeInput($_POST['quiet_hours_start'] ?? '22:00'),
        'quiet_hours_end' => sanitizeInput($_POST['quiet_hours_end'] ?? '08:00'),
    ];
    
    // Update or insert user preferences
    $stmt = $conn->prepare("
        INSERT INTO user_preferences (user_id, phone_number, whatsapp_number, telegram_chat_id, notification_preferences) 
        VALUES (?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        phone_number = VALUES(phone_number),
        whatsapp_number = VALUES(whatsapp_number),
        telegram_chat_id = VALUES(telegram_chat_id),
        notification_preferences = VALUES(notification_preferences),
        updated_at = NOW()
    ");
    
    $stmt->bind_param("issss", $user_id, $phone_number, $whatsapp_number, $telegram_chat_id, json_encode($notification_preferences));
    
    if ($stmt->execute()) {
        $_SESSION['flash_message'] = "Notification preferences updated successfully!";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Error updating preferences. Please try again.";
        $_SESSION['flash_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get current preferences
$stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$preferences = $stmt->get_result()->fetch_assoc();

if ($preferences) {
    $notification_prefs = json_decode($preferences['notification_preferences'], true) ?: [];
} else {
    $preferences = [];
    $notification_prefs = [];
}

// Set page title
$page_title = "Mobile Notification Preferences";

// Determine role-specific path
$role_path = '';
if ($_SESSION['role'] == 'admin') {
    $role_path = 'admin/';
} elseif ($_SESSION['role'] == 'security') {
    $role_path = 'security/';
} elseif ($_SESSION['role'] == 'user') {
    $role_path = 'user/';
}

// Include header
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-mobile-alt me-2"></i>Mobile Notification Preferences</h1>
        <a href="<?php echo $role_path; ?>dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Configure Your Notifications</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <!-- Contact Information -->
                        <h6 class="mb-3 text-primary"><i class="fas fa-address-book me-2"></i>Contact Information</h6>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label">Mobile Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                           value="<?php echo htmlspecialchars($preferences['phone_number'] ?? ''); ?>"
                                           placeholder="+91XXXXXXXXXX">
                                </div>
                                <small class="form-text text-muted">For SMS notifications (include country code)</small>
                            </div>
                            <div class="col-md-6">
                                <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-whatsapp"></i></span>
                                    <input type="tel" class="form-control" id="whatsapp_number" name="whatsapp_number" 
                                           value="<?php echo htmlspecialchars($preferences['whatsapp_number'] ?? ''); ?>"
                                           placeholder="+91XXXXXXXXXX">
                                </div>
                                <small class="form-text text-muted">Leave empty to use mobile number</small>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="telegram_chat_id" class="form-label">Telegram Chat ID</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-telegram"></i></span>
                                    <input type="text" class="form-control" id="telegram_chat_id" name="telegram_chat_id" 
                                           value="<?php echo htmlspecialchars($preferences['telegram_chat_id'] ?? ''); ?>"
                                           placeholder="Your Telegram Chat ID">
                                </div>
                                <small class="form-text text-muted">
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#telegramHelpModal">How to get Chat ID?</a>
                                </small>
                            </div>
                        </div>

                        <!-- Notification Channels -->
                        <h6 class="mb-3 text-primary"><i class="fas fa-bell me-2"></i>Notification Channels</h6>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notify_email" name="notify_email" 
                                           <?php echo ($notification_prefs['email'] ?? true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_email">
                                        <i class="fas fa-envelope me-2"></i>Email Notifications
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notify_sms" name="notify_sms" 
                                           <?php echo ($notification_prefs['sms'] ?? true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_sms">
                                        <i class="fas fa-sms me-2"></i>SMS Notifications
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notify_whatsapp" name="notify_whatsapp" 
                                           <?php echo ($notification_prefs['whatsapp'] ?? true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_whatsapp">
                                        <i class="fab fa-whatsapp me-2"></i>WhatsApp Notifications
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notify_telegram" name="notify_telegram" 
                                           <?php echo ($notification_prefs['telegram'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_telegram">
                                        <i class="fab fa-telegram me-2"></i>Telegram Notifications
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notify_push" name="notify_push" 
                                           <?php echo ($notification_prefs['push'] ?? true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_push">
                                        <i class="fas fa-desktop me-2"></i>Browser Push Notifications
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notify_in_app" name="notify_in_app" 
                                           <?php echo ($notification_prefs['in_app'] ?? true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_in_app">
                                        <i class="fas fa-bell me-2"></i>In-App Notifications
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Settings -->
                        <h6 class="mb-3 text-primary"><i class="fas fa-sliders-h me-2"></i>Notification Settings</h6>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="urgent_only" name="urgent_only" 
                                           <?php echo ($notification_prefs['urgent_only'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="urgent_only">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Urgent Notifications Only
                                    </label>
                                    <small class="form-text text-muted">Receive only high-priority notifications</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="quiet_hours_enabled" name="quiet_hours_enabled" 
                                           <?php echo ($notification_prefs['quiet_hours_enabled'] ?? true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="quiet_hours_enabled">
                                        <i class="fas fa-moon me-2"></i>Enable Quiet Hours
                                    </label>
                                    <small class="form-text text-muted">Limit notifications during specified hours</small>
                                </div>
                            </div>
                        </div>

                        <!-- Quiet Hours -->
                        <div class="row mb-4" id="quietHoursSettings">
                            <div class="col-md-6">
                                <label for="quiet_hours_start" class="form-label">Quiet Hours Start</label>
                                <input type="time" class="form-control" id="quiet_hours_start" name="quiet_hours_start" 
                                       value="<?php echo $notification_prefs['quiet_hours_start'] ?? '22:00'; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="quiet_hours_end" class="form-label">Quiet Hours End</label>
                                <input type="time" class="form-control" id="quiet_hours_end" name="quiet_hours_end" 
                                       value="<?php echo $notification_prefs['quiet_hours_end'] ?? '08:00'; ?>">
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4 border-info">
                <div class="card-header text-white bg-info">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Notifications</h5>
                </div>
                <div class="card-body">
                    <h6 class="text-info">Available Channels:</h6>
                    <ul class="list-unstyled mb-3">
                        <li><i class="fas fa-envelope text-primary me-2"></i>Email - Always reliable</li>
                        <li><i class="fas fa-sms text-success me-2"></i>SMS - For urgent alerts</li>
                        <li><i class="fab fa-whatsapp text-success me-2"></i>WhatsApp - Instant messaging</li>
                        <li><i class="fab fa-telegram text-info me-2"></i>Telegram - Fast & secure</li>
                        <li><i class="fas fa-desktop text-secondary me-2"></i>Browser Push - Real-time</li>
                    </ul>

                    <h6 class="text-info">Notification Types:</h6>
                    <ul class="list-unstyled mb-3">
                        <li><i class="fas fa-plus-circle text-warning me-2"></i>New gatepass created</li>
                        <li><i class="fas fa-check text-success me-2"></i>Gatepass approved</li>
                        <li><i class="fas fa-shield-alt text-primary me-2"></i>Security verification</li>
                        <li><i class="fas fa-times text-danger me-2"></i>Gatepass declined</li>
                    </ul>

                    <div class="alert alert-warning">
                        <small><i class="fas fa-exclamation-triangle me-1"></i>Configure your mobile number and WhatsApp for instant notifications even when you're not online!</small>
                    </div>
                </div>
            </div>

            <div class="card border-success">
                <div class="card-header text-white bg-success">
                    <h5 class="mb-0"><i class="fas fa-test me-2"></i>Test Notifications</h5>
                </div>
                <div class="card-body">
                    <p class="mb-3">Test your notification settings:</p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-success" onclick="testNotification('email')">
                            <i class="fas fa-envelope me-2"></i>Test Email
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="testNotification('sms')">
                            <i class="fas fa-sms me-2"></i>Test SMS
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="testNotification('whatsapp')">
                            <i class="fab fa-whatsapp me-2"></i>Test WhatsApp
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Telegram Help Modal -->
<div class="modal fade" id="telegramHelpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fab fa-telegram me-2"></i>How to Get Telegram Chat ID</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ol>
                    <li>Open Telegram and search for <strong>@userinfobot</strong></li>
                    <li>Start a conversation with the bot</li>
                    <li>Send any message to the bot</li>
                    <li>The bot will reply with your Chat ID</li>
                    <li>Copy the Chat ID and paste it in the field above</li>
                </ol>
                <div class="alert alert-info">
                    <strong>Note:</strong> You need to message our Telegram bot first before receiving notifications.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle quiet hours settings
document.getElementById('quiet_hours_enabled').addEventListener('change', function() {
    const quietHoursSettings = document.getElementById('quietHoursSettings');
    if (this.checked) {
        quietHoursSettings.style.display = 'flex';
    } else {
        quietHoursSettings.style.display = 'none';
    }
});

// Test notification function
function testNotification(channel) {
    fetch('/api/test_notification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ channel: channel }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Test notification sent successfully!');
        } else {
            alert('Failed to send test notification: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error sending test notification');
    });
}

// Initialize quiet hours visibility
document.addEventListener('DOMContentLoaded', function() {
    const quietHoursEnabled = document.getElementById('quiet_hours_enabled');
    const quietHoursSettings = document.getElementById('quietHoursSettings');
    
    if (!quietHoursEnabled.checked) {
        quietHoursSettings.style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
