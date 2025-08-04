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
    
    $notification_preferences = [
        'email' => isset($_POST['notify_email']),
        'sms' => isset($_POST['notify_sms']),
        'whatsapp' => isset($_POST['notify_whatsapp']),
        'push' => isset($_POST['notify_push']),
        'in_app' => isset($_POST['notify_in_app']),
        'urgent_only' => isset($_POST['urgent_only']),
        'quiet_hours_start' => sanitizeInput($_POST['quiet_hours_start'] ?? '22:00'),
        'quiet_hours_end' => sanitizeInput($_POST['quiet_hours_end'] ?? '08:00'),
        'weekend_notifications' => isset($_POST['weekend_notifications'])
    ];
    
    try {
        // Check if user preferences exist
        $stmt = $conn->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        
        if ($exists) {
            // Update existing preferences
            $stmt = $conn->prepare("
                UPDATE user_preferences 
                SET phone_number = ?, whatsapp_number = ?, notification_preferences = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->bind_param("sssi", $phone_number, $whatsapp_number, json_encode($notification_preferences), $user_id);
        } else {
            // Insert new preferences
            $stmt = $conn->prepare("
                INSERT INTO user_preferences (user_id, phone_number, whatsapp_number, notification_preferences) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $user_id, $phone_number, $whatsapp_number, json_encode($notification_preferences));
        }
        
        $stmt->execute();
        $_SESSION['flash_message'] = "Notification preferences updated successfully";
        $_SESSION['flash_type'] = "success";
        
        header("Location: notification_preferences.php");
        exit();
        
    } catch (Exception $e) {
        $error_message = "Failed to update preferences: " . $e->getMessage();
    }
}

// Get current preferences
$stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$preferences_result = $stmt->get_result()->fetch_assoc();

$phone_number = $preferences_result['phone_number'] ?? '';
$whatsapp_number = $preferences_result['whatsapp_number'] ?? '';
$notification_preferences = json_decode($preferences_result['notification_preferences'] ?? '{}', true);

// Set default preferences if none exist
$default_preferences = [
    'email' => true,
    'sms' => true,
    'whatsapp' => true,
    'push' => true,
    'in_app' => true,
    'urgent_only' => false,
    'quiet_hours_start' => '22:00',
    'quiet_hours_end' => '08:00',
    'weekend_notifications' => true
];

$notification_preferences = array_merge($default_preferences, $notification_preferences);

// Get recent notifications
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_notifications = $stmt->get_result();

$page_title = "Notification Preferences";
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Preferences</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <!-- Contact Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                       value="<?php echo htmlspecialchars($phone_number); ?>" 
                                       placeholder="+1234567890">
                                <div class="form-text">Required for SMS notifications</div>
                            </div>
                            <div class="col-md-6">
                                <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                                <input type="tel" class="form-control" id="whatsapp_number" name="whatsapp_number" 
                                       value="<?php echo htmlspecialchars($whatsapp_number); ?>" 
                                       placeholder="+1234567890">
                                <div class="form-text">Leave empty to use phone number</div>
                            </div>
                        </div>
                        
                        <!-- Notification Channels -->
                        <h6 class="mb-3">Notification Channels</h6>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="notify_email" name="notify_email" 
                                           <?php echo $notification_preferences['email'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_email">
                                        <i class="fas fa-envelope me-2"></i>Email Notifications
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="notify_sms" name="notify_sms" 
                                           <?php echo $notification_preferences['sms'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_sms">
                                        <i class="fas fa-sms me-2"></i>SMS Notifications
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="notify_whatsapp" name="notify_whatsapp" 
                                           <?php echo $notification_preferences['whatsapp'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_whatsapp">
                                        <i class="fab fa-whatsapp me-2"></i>WhatsApp Notifications
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="notify_push" name="notify_push" 
                                           <?php echo $notification_preferences['push'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_push">
                                        <i class="fas fa-desktop me-2"></i>Browser Push Notifications
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="notify_in_app" name="notify_in_app" 
                                           <?php echo $notification_preferences['in_app'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_in_app">
                                        <i class="fas fa-bell me-2"></i>In-App Notifications
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="urgent_only" name="urgent_only" 
                                           <?php echo $notification_preferences['urgent_only'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="urgent_only">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Urgent Notifications Only
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quiet Hours -->
                        <h6 class="mb-3">Quiet Hours</h6>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="quiet_hours_start" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="quiet_hours_start" name="quiet_hours_start" 
                                       value="<?php echo htmlspecialchars($notification_preferences['quiet_hours_start']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="quiet_hours_end" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="quiet_hours_end" name="quiet_hours_end" 
                                       value="<?php echo htmlspecialchars($notification_preferences['quiet_hours_end']); ?>">
                            </div>
                        </div>
                        
                        <!-- Additional Options -->
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="weekend_notifications" name="weekend_notifications" 
                                   <?php echo $notification_preferences['weekend_notifications'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="weekend_notifications">
                                Receive notifications on weekends
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Preferences
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Test Notification -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-test-tube me-2"></i>Test Notifications</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">Test your notification settings to ensure they work correctly.</p>
                    <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="testNotification()">
                        <i class="fas fa-paper-plane me-2"></i>Send Test Notification
                    </button>
                </div>
            </div>
            
            <!-- Recent Notifications -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Notifications</h6>
                </div>
                <div class="card-body">
                    <?php if ($recent_notifications->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($notification = $recent_notifications->fetch_assoc()): ?>
                                <div class="list-group-item px-0 py-2 <?php echo $notification['read_at'] ? 'text-muted' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <small><?php echo formatDateTime($notification['created_at'], 'd M Y H:i'); ?></small>
                                        </div>
                                        <?php if ($notification['urgency'] === 'high'): ?>
                                            <i class="fas fa-exclamation-circle text-danger"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No recent notifications</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function testNotification() {
    try {
        const response = await fetch('/api/test_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                test_type: 'all_channels'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Test notification sent! Check your email, phone, and browser for notifications.');
        } else {
            alert('Failed to send test notification: ' + result.error);
        }
    } catch (error) {
        alert('Error sending test notification: ' + error.message);
    }
}

// Enable browser notifications when push notifications checkbox is checked
document.getElementById('notify_push').addEventListener('change', function() {
    if (this.checked && 'Notification' in window) {
        Notification.requestPermission().then(function(permission) {
            if (permission !== 'granted') {
                alert('Please allow browser notifications to receive push notifications.');
                document.getElementById('notify_push').checked = false;
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
