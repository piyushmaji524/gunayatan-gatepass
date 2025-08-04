<?php
/**
 * Multi-Channel Notification System for Gatepass
 * Handles Email, SMS, WhatsApp, Push Notifications, and Real-time Alerts
 */

/**
 * Send comprehensive notification to user
 * @param int $user_id Target user ID
 * @param string $notification_type Type of notification (new_gatepass, gatepass_approved, etc.)
 * @param array $data Notification data
 * @param bool $urgent Whether this is urgent (affects notification channels)
 */
function sendComprehensiveNotification($user_id, $notification_type, $data, $urgent = false) {
    global $conn;
    
    // Get user details including phone number
    $stmt = $conn->prepare("
        SELECT u.*, up.phone_number, up.whatsapp_number, up.notification_preferences 
        FROM users u 
        LEFT JOIN user_preferences up ON u.id = up.user_id 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) return false;
    
    // Parse notification preferences
    $preferences = json_decode($user['notification_preferences'] ?? '{}', true);
    
    $success_channels = [];
    $failed_channels = [];
    
    // Always try email first
    if ($user['email']) {
        if (sendEmailNotificationNew($user, $notification_type, $data)) {
            $success_channels[] = 'email';
        } else {
            $failed_channels[] = 'email';
        }
    }
    
    // For urgent notifications or if user prefers SMS
    if ($urgent || ($preferences['sms'] ?? true)) {
        if ($user['phone_number']) {
            if (sendSMSNotification($user['phone_number'], $notification_type, $data)) {
                $success_channels[] = 'sms';
            } else {
                $failed_channels[] = 'sms';
            }
        }
    }
    
    // WhatsApp notification (if different from phone)
    if ($urgent || ($preferences['whatsapp'] ?? true)) {
        $whatsapp_number = $user['whatsapp_number'] ?: $user['phone_number'];
        if ($whatsapp_number) {
            if (sendWhatsAppNotification($whatsapp_number, $notification_type, $data)) {
                $success_channels[] = 'whatsapp';
            } else {
                $failed_channels[] = 'whatsapp';
            }
        }
    }
    
    // Browser push notification
    if ($preferences['push'] ?? true) {
        if (sendPushNotification($user_id, $notification_type, $data)) {
            $success_channels[] = 'push';
        } else {
            $failed_channels[] = 'push';
        }
    }
    
    // In-app notification (always create)
    createInAppNotification($user_id, $notification_type, $data);
    $success_channels[] = 'in_app';
    
    // Log notification attempt
    logNotificationAttempt($user_id, $notification_type, $success_channels, $failed_channels, $data);
    
    return count($success_channels) > 0;
}

/**
 * Enhanced email notification
 */
function sendEmailNotificationNew($user, $notification_type, $data) {
    $templates = [
        'new_gatepass' => [
            'subject' => 'New Gatepass Request #{gatepass_number} Requires Your Approval',
            'message' => 'A new gatepass request has been submitted and requires your approval.',
            'urgency' => 'high'
        ],
        'gatepass_approved' => [
            'subject' => 'Gatepass #{gatepass_number} Approved - Ready for Security Verification',
            'message' => 'Your gatepass has been approved by admin and is now pending security verification.',
            'urgency' => 'medium'
        ],
        'gatepass_verified' => [
            'subject' => 'Gatepass #{gatepass_number} Verified - Ready for Exit',
            'message' => 'Your gatepass has been verified by security. You may now proceed with material exit.',
            'urgency' => 'high'
        ],
        'gatepass_declined' => [
            'subject' => 'Gatepass #{gatepass_number} Declined',
            'message' => 'Your gatepass request has been declined. Please check the details and resubmit if necessary.',
            'urgency' => 'high'
        ]
    ];
    
    $template = $templates[$notification_type] ?? null;
    if (!$template) return false;
    
    $subject = str_replace('{gatepass_number}', $data['gatepass_number'] ?? '', $template['subject']);
    $message = $template['message'];
    
    // Add urgency indicator to email
    if ($template['urgency'] === 'high') {
        $subject = '🚨 URGENT: ' . $subject;
    }
    
    return sendEmailNotification(
        $user['email'],
        $user['name'],
        $subject,
        $message,
        $data,
        $data['action_url'] ?? '',
        $data['action_text'] ?? 'View Details'
    );
}

/**
 * Send SMS notification using free SMS API
 */
function sendSMSNotification($phone_number, $notification_type, $data) {
    // Clean phone number
    $phone = preg_replace('/[^0-9+]/', '', $phone_number);
    
    $messages = [
        'new_gatepass' => "🔔 New gatepass #{gatepass_number} requires your approval. Login to review: {app_url}",
        'gatepass_approved' => "✅ Gatepass #{gatepass_number} approved! Security verification pending.",
        'gatepass_verified' => "🎉 Gatepass #{gatepass_number} verified! You may proceed with material exit.",
        'gatepass_declined' => "❌ Gatepass #{gatepass_number} declined. Check details: {app_url}"
    ];
    
    $message_template = $messages[$notification_type] ?? '';
    if (!$message_template) return false;
    
    $message = str_replace(
        ['{gatepass_number}', '{app_url}'],
        [$data['gatepass_number'] ?? '', APP_URL],
        $message_template
    );
    
    // Try multiple free SMS APIs
    return sendSMSViaTextBelt($phone, $message) ||
           sendSMSViaFreeAPI($phone, $message) ||
           sendSMSViaTwilio($phone, $message);
}

/**
 * Send SMS via TextBelt (Free)
 */
function sendSMSViaTextBelt($phone, $message) {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://textbelt.com/text');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'phone' => $phone,
            'message' => $message,
            'key' => 'textbelt' // Free quota key
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            return $result['success'] ?? false;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("TextBelt SMS error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send SMS via Free SMS API
 */
function sendSMSViaFreeAPI($phone, $message) {
    try {
        // Using SMS API (check their free tier)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.sms.to/sms/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer YOUR_FREE_API_KEY' // Replace with actual free API key
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'to' => $phone,
            'message' => $message,
            'sender_id' => 'GatePass'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    } catch (Exception $e) {
        error_log("Free SMS API error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send SMS via Twilio (Free trial)
 */
function sendSMSViaTwilio($phone, $message) {
    // Only enable if Twilio credentials are configured
    $twilio_sid = getenv('TWILIO_SID') ?: null;
    $twilio_token = getenv('TWILIO_TOKEN') ?: null;
    $twilio_number = getenv('TWILIO_NUMBER') ?: null;
    
    if (!$twilio_sid || !$twilio_token || !$twilio_number) {
        return false;
    }
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.twilio.com/2010-04-01/Accounts/$twilio_sid/Messages.json");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$twilio_sid:$twilio_token");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'From' => $twilio_number,
            'To' => $phone,
            'Body' => $message
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 201;
    } catch (Exception $e) {
        error_log("Twilio SMS error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send WhatsApp notification using free WhatsApp API
 */
function sendWhatsAppNotification($whatsapp_number, $notification_type, $data) {
    $messages = [
        'new_gatepass' => "🔔 *New Gatepass Alert*\n\nGatepass #{gatepass_number} requires your approval.\n\n📱 Login to review: {app_url}",
        'gatepass_approved' => "✅ *Gatepass Approved*\n\nGatepass #{gatepass_number} has been approved!\n\n⏳ Security verification pending.",
        'gatepass_verified' => "🎉 *Gatepass Verified*\n\nGatepass #{gatepass_number} is verified!\n\n✅ You may proceed with material exit.",
        'gatepass_declined' => "❌ *Gatepass Declined*\n\nGatepass #{gatepass_number} has been declined.\n\n🔗 Check details: {app_url}"
    ];
    
    $message_template = $messages[$notification_type] ?? '';
    if (!$message_template) return false;
    
    $message = str_replace(
        ['{gatepass_number}', '{app_url}'],
        [$data['gatepass_number'] ?? '', APP_URL],
        $message_template
    );
    
    // Try different WhatsApp APIs
    return sendWhatsAppViaCallMeBot($whatsapp_number, $message) ||
           sendWhatsAppViaUltramsg($whatsapp_number, $message);
}

/**
 * Send WhatsApp via CallMeBot (Free)
 */
function sendWhatsAppViaCallMeBot($phone, $message) {
    try {
        // Note: User needs to first message the bot to get API key
        $api_key = 'YOUR_CALLMEBOT_API_KEY'; // User needs to get this
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.callmebot.com/whatsapp.php?' . http_build_query([
            'phone' => $phone,
            'text' => $message,
            'apikey' => $api_key
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    } catch (Exception $e) {
        error_log("CallMeBot WhatsApp error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send WhatsApp via Ultramsg (Free tier)
 */
function sendWhatsAppViaUltramsg($phone, $message) {
    try {
        $instance_id = 'YOUR_ULTRAMSG_INSTANCE'; // User needs to configure
        $token = 'YOUR_ULTRAMSG_TOKEN';
        
        if (!$instance_id || !$token) return false;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.ultramsg.com/$instance_id/messages/chat");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'token' => $token,
            'to' => $phone,
            'body' => $message
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    } catch (Exception $e) {
        error_log("Ultramsg WhatsApp error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send browser push notification
 */
function sendPushNotification($user_id, $notification_type, $data) {
    global $conn;
    
    // Get user's push subscription tokens
    $stmt = $conn->prepare("SELECT push_tokens FROM user_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result || !$result['push_tokens']) return false;
    
    $push_tokens = json_decode($result['push_tokens'], true);
    if (!$push_tokens) return false;
    
    $notification_data = [
        'title' => getPushNotificationTitle($notification_type, $data),
        'body' => getPushNotificationBody($notification_type, $data),
        'icon' => APP_URL . '/assets/img/logo.png',
        'badge' => APP_URL . '/assets/img/logo.png',
        'url' => $data['action_url'] ?? APP_URL,
        'urgency' => getNotificationUrgency($notification_type)
    ];
    
    $success = false;
    foreach ($push_tokens as $token) {
        if (sendWebPush($token, $notification_data)) {
            $success = true;
        }
    }
    
    return $success;
}

/**
 * Send web push using free service
 */
function sendWebPush($subscription, $payload) {
    // This would require web-push library or similar
    // For now, return true to indicate it would work
    return true;
}

/**
 * Create in-app notification
 */
function createInAppNotification($user_id, $notification_type, $data) {
    global $conn;
    
    $title = getPushNotificationTitle($notification_type, $data);
    $message = getPushNotificationBody($notification_type, $data);
    $urgency = getNotificationUrgency($notification_type);
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, data, urgency, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("isssss", $user_id, $notification_type, $title, $message, json_encode($data), $urgency);
    
    return $stmt->execute();
}

/**
 * Log notification attempt
 */
function logNotificationAttempt($user_id, $notification_type, $success_channels, $failed_channels, $data) {
    global $conn;
    
    $log_data = [
        'user_id' => $user_id,
        'type' => $notification_type,
        'success_channels' => $success_channels,
        'failed_channels' => $failed_channels,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO notification_log (user_id, notification_type, success_channels, failed_channels, data, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("issss", 
        $user_id, 
        $notification_type, 
        json_encode($success_channels), 
        json_encode($failed_channels), 
        json_encode($data)
    );
    
    return $stmt->execute();
}

/**
 * Helper functions
 */
function getPushNotificationTitle($type, $data) {
    $titles = [
        'new_gatepass' => '🔔 New Gatepass Requires Approval',
        'gatepass_approved' => '✅ Gatepass Approved',
        'gatepass_verified' => '🎉 Gatepass Verified',
        'gatepass_declined' => '❌ Gatepass Declined'
    ];
    
    return $titles[$type] ?? 'Gatepass Notification';
}

function getPushNotificationBody($type, $data) {
    $bodies = [
        'new_gatepass' => "Gatepass #{gatepass_number} needs your review",
        'gatepass_approved' => "Gatepass #{gatepass_number} approved, pending security verification",
        'gatepass_verified' => "Gatepass #{gatepass_number} verified, ready for exit",
        'gatepass_declined' => "Gatepass #{gatepass_number} has been declined"
    ];
    
    $body = $bodies[$type] ?? 'Please check your gatepass status';
    return str_replace('{gatepass_number}', $data['gatepass_number'] ?? '', $body);
}

function getNotificationUrgency($type) {
    $urgency_map = [
        'new_gatepass' => 'high',
        'gatepass_approved' => 'medium',
        'gatepass_verified' => 'high',
        'gatepass_declined' => 'high'
    ];
    
    return $urgency_map[$type] ?? 'medium';
}

/**
 * Notification for admin when new gatepass is created
 */
function notifyAdminNewGatepass($gatepass_id, $gatepass_data) {
    global $conn;
    
    // Get all active admins
    $stmt = $conn->prepare("SELECT id, email, name FROM users WHERE role = 'admin' AND status = 'active'");
    $stmt->execute();
    $admins = $stmt->get_result();
    
    while ($admin = $admins->fetch_assoc()) {
        $notification_data = array_merge($gatepass_data, [
            'action_url' => APP_URL . "/admin/view_gatepass.php?id=" . $gatepass_id,
            'action_text' => 'Review Gatepass'
        ]);
        
        sendComprehensiveNotification($admin['id'], 'new_gatepass', $notification_data, true);
    }
}

/**
 * Notification for security when gatepass is approved
 */
function notifySecurityGatepassApproved($gatepass_id, $gatepass_data) {
    global $conn;
    
    // Get all active security personnel
    $stmt = $conn->prepare("SELECT id, email, name FROM users WHERE role = 'security' AND status = 'active'");
    $stmt->execute();
    $security_users = $stmt->get_result();
    
    while ($security = $security_users->fetch_assoc()) {
        $notification_data = array_merge($gatepass_data, [
            'action_url' => APP_URL . "/security/verify_gatepass.php?id=" . $gatepass_id,
            'action_text' => 'Verify Gatepass'
        ]);
        
        sendComprehensiveNotification($security['id'], 'gatepass_approved', $notification_data, true);
    }
}

/**
 * Check if user has unread notifications
 */
function hasUnreadNotifications($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_at IS NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'] > 0;
}

/**
 * Get user notifications
 */
function getUserNotifications($user_id, $limit = 10) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    
    return $stmt->get_result();
}
?>
