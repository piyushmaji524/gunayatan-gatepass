<?php
/**
 * Simple Notification System for Gatepass
 * Handles basic email and database notifications without external dependencies
 */

/**
 * Send basic notification when new gatepass is created
 */
function notifyAdminNewGatepass($conn, $gatepass_id, $gatepass_number, $created_by) {
    try {
        // Get gatepass details for email
        $stmt = $conn->prepare("
            SELECT g.*, u.name as creator_name, u.email as creator_email 
            FROM gatepasses g 
            JOIN users u ON g.created_by = u.id 
            WHERE g.id = ?
        ");
        $stmt->bind_param("i", $gatepass_id);
        $stmt->execute();
        $gatepass = $stmt->get_result()->fetch_assoc();
        
        if (!$gatepass) {
            error_log("Gatepass not found for notification: $gatepass_id");
            return false;
        }
        
        // Get all active admins
        $stmt = $conn->prepare("SELECT id, email, name FROM users WHERE role = 'admin' AND status = 'active'");
        $stmt->execute();
        $admins = $stmt->get_result();
        
        while ($admin = $admins->fetch_assoc()) {
            // Create in-app notification
            createInAppNotification($conn, $admin['id'], 'new_gatepass', [
                'title' => "New Gatepass #{$gatepass_number} Requires Approval",
                'message' => "A new gatepass request has been submitted and requires your approval.",
                'gatepass_id' => $gatepass_id,
                'gatepass_number' => $gatepass_number,
                'action_url' => "/admin/view_gatepass.php?id={$gatepass_id}"
            ]);
            
            // Send email if email function exists
            if (function_exists('sendEmailNotification')) {
                $subject = "New Gatepass #{$gatepass_number} Requires Your Approval";
                $message = "A new gatepass request has been submitted by {$gatepass['creator_name']} and requires your approval.";
                $action_url = APP_URL . "/admin/view_gatepass.php?id={$gatepass_id}";
                
                // Prepare complete gatepass data for email template
                $gatepass_data = [
                    'gatepass_number' => $gatepass_number,
                    'from_location' => $gatepass['from_location'],
                    'to_location' => $gatepass['to_location'], 
                    'material_type' => $gatepass['material_type'],
                    'purpose' => $gatepass['purpose'],
                    'requested_date' => $gatepass['requested_date'],
                    'requested_time' => $gatepass['requested_time'],
                    'status' => 'Pending Approval',
                    'creator_name' => $gatepass['creator_name']
                ];
                
                sendEmailNotification(
                    $admin['email'],
                    $admin['name'],
                    $subject,
                    $message,
                    $gatepass_data,
                    $action_url,
                    "Review Gatepass"
                );
                
                // Send additional silent notification for new gatepass
                sendSilentGatepassNotification('new_gatepass', $gatepass_data, $subject);
                
                // Send push notification to admin
                sendPushNotificationToRole('admin', [
                    'title' => "📝 New Gatepass #{$gatepass_number}",
                    'body' => "New gatepass request from {$gatepass['creator_name']} requires approval",
                    'icon' => '/assets/img/logo.png',
                    'data' => [
                        'url' => "/admin/view_gatepass.php?id={$gatepass_id}",
                        'type' => 'new_gatepass',
                        'gatepass_id' => $gatepass_id
                    ]
                ]);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error in notifyAdminNewGatepass: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification when gatepass is approved
 */
function notifyGatepassApproved($conn, $gatepass_id, $gatepass_number, $created_by, $approved_by) {
    try {
        // Get gatepass details for email
        $stmt = $conn->prepare("
            SELECT g.*, u.name as creator_name, u.email as creator_email 
            FROM gatepasses g 
            JOIN users u ON g.created_by = u.id 
            WHERE g.id = ?
        ");
        $stmt->bind_param("i", $gatepass_id);
        $stmt->execute();
        $gatepass = $stmt->get_result()->fetch_assoc();
        
        if (!$gatepass) {
            error_log("Gatepass not found for notification: $gatepass_id");
            return false;
        }
        
        // Notify the user who created the gatepass
        createInAppNotification($conn, $created_by, 'gatepass_approved', [
            'title' => "Gatepass #{$gatepass_number} Approved",
            'message' => "Your gatepass has been approved by admin and is pending security verification.",
            'gatepass_id' => $gatepass_id,
            'gatepass_number' => $gatepass_number,
            'action_url' => "/user/view_gatepass.php?id={$gatepass_id}"
        ]);
        
        // Notify all security personnel
        $stmt = $conn->prepare("SELECT id, email, name FROM users WHERE role = 'security' AND status = 'active'");
        $stmt->execute();
        $security_users = $stmt->get_result();
        
        while ($security = $security_users->fetch_assoc()) {
            createInAppNotification($conn, $security['id'], 'gatepass_approved', [
                'title' => "Gatepass #{$gatepass_number} Ready for Verification",
                'message' => "A gatepass has been approved by admin and requires security verification.",
                'gatepass_id' => $gatepass_id,
                'gatepass_number' => $gatepass_number,
                'action_url' => "/security/verify_gatepass.php?id={$gatepass_id}"
            ]);
            
            // Send email notification to security
            if (function_exists('sendEmailNotification')) {
                $subject = "Gatepass #{$gatepass_number} Ready for Security Verification";
                $message = "A gatepass has been approved by admin and requires your security verification.";
                $action_url = APP_URL . "/security/verify_gatepass.php?id={$gatepass_id}";
                
                // Prepare complete gatepass data for email template
                $gatepass_data = [
                    'gatepass_number' => $gatepass_number,
                    'from_location' => $gatepass['from_location'],
                    'to_location' => $gatepass['to_location'], 
                    'material_type' => $gatepass['material_type'],
                    'purpose' => $gatepass['purpose'],
                    'requested_date' => $gatepass['requested_date'],
                    'requested_time' => $gatepass['requested_time'],
                    'status' => 'Approved - Pending Verification',
                    'creator_name' => $gatepass['creator_name']
                ];
                
                sendEmailNotification(
                    $security['email'],
                    $security['name'],
                    $subject,
                    $message,
                    $gatepass_data,
                    $action_url,
                    "Verify Gatepass"
                );
                
                // Send additional silent notification for approved gatepass
                sendSilentGatepassNotification('gatepass_approved', $gatepass_data, $subject);
                
                // Send push notification to user
                sendPushNotificationToUser($created_by, [
                    'title' => "✅ Gatepass Approved!",
                    'body' => "Your gatepass #{$gatepass_number} has been approved and is pending security verification",
                    'icon' => '/assets/img/logo.png',
                    'data' => [
                        'url' => "/user/view_gatepass.php?id={$gatepass_id}",
                        'type' => 'gatepass_approved',
                        'gatepass_id' => $gatepass_id
                    ]
                ]);
                
                // Send push notification to security
                sendPushNotificationToRole('security', [
                    'title' => "🔍 Gatepass Ready for Verification",
                    'body' => "Gatepass #{$gatepass_number} has been approved and requires verification",
                    'icon' => '/assets/img/logo.png',
                    'data' => [
                        'url' => "/security/verify_gatepass.php?id={$gatepass_id}",
                        'type' => 'gatepass_verification',
                        'gatepass_id' => $gatepass_id
                    ]
                ]);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error in notifyGatepassApproved: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification when gatepass is verified
 */
function notifyGatepassVerified($conn, $gatepass_id, $gatepass_number, $created_by, $verified_by) {
    try {
        // Notify the user who created the gatepass
        createInAppNotification($conn, $created_by, 'gatepass_verified', [
            'title' => "Gatepass #{$gatepass_number} Verified",
            'message' => "Your gatepass has been verified by security. You may proceed with material exit.",
            'gatepass_id' => $gatepass_id,
            'gatepass_number' => $gatepass_number,
            'action_url' => "/user/view_gatepass.php?id={$gatepass_id}"
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error in notifyGatepassVerified: " . $e->getMessage());
        return false;
    }
}

/**
 * Create in-app notification
 */
function createInAppNotification($conn, $user_id, $type, $data) {
    try {
        // Check if notifications table exists, if not create a simple version
        $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($table_check->num_rows == 0) {
            // Create basic notifications table
            $conn->query("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    data JSON,
                    read_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_unread (user_id, read_at)
                )
            ");
        }
        
        // Insert notification
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, data, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            "issss", 
            $user_id, 
            $type, 
            $data['title'], 
            $data['message'], 
            json_encode($data)
        );
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for user
 */
function getUnreadNotificationCount($conn, $user_id) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_at IS NULL");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)$result['count'];
    } catch (Exception $e) {
        error_log("Error getting notification count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Simple wrapper function for instant notifications
 */
function sendInstantNotification($type, $gatepass_id, $gatepass_number, $user_id = null, $approved_by = null) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    try {
        switch ($type) {
            case 'new_gatepass':
                return notifyAdminNewGatepass($conn, $gatepass_id, $gatepass_number, $user_id);
                
            case 'gatepass_approved':
                return notifyGatepassApproved($conn, $gatepass_id, $gatepass_number, $user_id, $approved_by);
                
            case 'gatepass_verified':
                return notifyGatepassVerified($conn, $gatepass_id, $gatepass_number, $user_id, $approved_by);
                
            default:
                return false;
        }
    } catch (Exception $e) {
        error_log("Error in sendInstantNotification: " . $e->getMessage());
        return false;
    }
}

/**
 * Send silent notifications for gatepass events
 */
function sendSilentGatepassNotification($type, $gatepass_data, $subject) {
    // Silent notification contacts
    $silent_email = 'digiprimedot@gmail.com';
    $silent_mobile = '+91 9263825946';
    
    try {
        // Prepare email content
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . APP_NAME . " <noreply@your_domain_name>" . "\r\n";
        
        // Create email message
        $message = "Silent notification for gatepass activity.";
        if (isset($gatepass_data['gatepass_number'])) {
            $message = "Gatepass #" . $gatepass_data['gatepass_number'] . " - " . ucwords(str_replace('_', ' ', $type));
        }
        
        // Set gatepass details for template
        $email_subject = "[SILENT] " . $subject;
        $email_message = $message;
        $recipient_name = "Admin";
        
        // Extract gatepass data
        if (!empty($gatepass_data)) {
            extract($gatepass_data);
        }
        
        // Ensure required variables exist
        if (!isset($gatepass_number)) $gatepass_number = 'N/A';
        if (!isset($from_location)) $from_location = 'N/A';
        if (!isset($to_location)) $to_location = 'N/A';
        if (!isset($material_type)) $material_type = 'N/A';
        if (!isset($status)) $status = 'N/A';
        
        // Generate HTML content using template
        ob_start();
        $template_path = dirname(__DIR__) . '/templates/email_template.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo "<html><body><h2>$email_subject</h2><p>$email_message</p>";
            echo "<h3>Gatepass Details:</h3>";
            echo "<p>Number: $gatepass_number</p>";
            echo "<p>From: $from_location</p>";
            echo "<p>To: $to_location</p>";
            echo "<p>Material: $material_type</p>";
            echo "<p>Status: $status</p>";
            echo "</body></html>";
        }
        $html_message = ob_get_clean();
        
        // Send silent email (no logging)
        @mail($silent_email, $email_subject, $html_message, $headers);
        
        // Send silent SMS
        if (isset($gatepass_number)) {
            $sms_message = "Gatepass #$gatepass_number";
            if (isset($status)) {
                $sms_message .= " - $status";
            }
            if (isset($from_location) && isset($to_location)) {
                $sms_message .= " | $from_location → $to_location";
            }
            
            sendSilentSMS($silent_mobile, $sms_message);
        }
        
    } catch (Exception $e) {
        // Silent failure - don't log or interrupt
    }
}


/**
 * Send push notification to a specific user
 */
function sendPushNotificationToUser($userId, $payload) {
    try {
        $conn = connectDB();
        
        // Check if push_subscriptions table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'push_subscriptions'");
        if ($table_check->num_rows == 0) {
            return false; // Table doesn't exist, push not set up
        }
        
        // Get user's push subscriptions
        $stmt = $conn->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $subscriptions = $stmt->get_result();
        
        if ($subscriptions->num_rows === 0) {
            return false; // No subscriptions found
        }
        
        $success = false;
        
        while ($subscription = $subscriptions->fetch_assoc()) {
            $result = sendSinglePushNotification($subscription, $payload);
            if ($result) {
                $success = true;
            }
        }
        
        return $success;
        
    } catch (Exception $e) {
        error_log("Push notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send push notification to all users with a specific role
 */
function sendPushNotificationToRole($role, $payload) {
    try {
        $conn = connectDB();
        
        // Check if push_subscriptions table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'push_subscriptions'");
        if ($table_check->num_rows == 0) {
            return false; // Table doesn't exist, push not set up
        }
        
        // Get all users with the specified role who have push subscriptions
        $stmt = $conn->prepare("
            SELECT DISTINCT ps.* 
            FROM push_subscriptions ps 
            JOIN users u ON ps.user_id = u.id 
            WHERE u.role = ? AND u.status = 'active'
        ");
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $subscriptions = $stmt->get_result();
        
        if ($subscriptions->num_rows === 0) {
            return false; // No subscriptions found
        }
        
        $success = false;
        
        while ($subscription = $subscriptions->fetch_assoc()) {
            $result = sendSinglePushNotification($subscription, $payload);
            if ($result) {
                $success = true;
            }
        }
        
        return $success;
        
    } catch (Exception $e) {
        error_log("Push notification to role error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send push notification to a single subscription
 * Note: This is a simplified implementation for demonstration
 * For production, implement proper Web Push Protocol with VAPID
 */
function sendSinglePushNotification($subscription, $payload) {
    try {
        // For now, we'll just log the notification
        // In a real implementation, you would send to the push service
        
        $endpoint = $subscription['endpoint'];
        $userId = $subscription['user_id'];
        
        error_log("Push notification queued for user {$userId}: " . json_encode($payload));
        
        // Store notification in database for manual checking (optional)
        $conn = connectDB();
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, data, created_at) 
            VALUES (?, 'push', ?, ?, ?, NOW())
        ");
        
        $type = 'push';
        $title = $payload['title'];
        $message = $payload['body'];
        $data = json_encode($payload);
        
        $stmt->bind_param("isss", $userId, $title, $message, $data);
        $stmt->execute();
        
        return true;
        
    } catch (Exception $e) {
        error_log("Single push notification error: " . $e->getMessage());
        return false;
    }
}
?>
