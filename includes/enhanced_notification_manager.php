<?php
/**
 * Enhanced Real-Time Notification System
 * Provides instant notifications via multiple channels for mobile users
 */

require_once 'notification_system.php';

class EnhancedNotificationManager {
    private $conn;
    private $push_service_workers = [
        'fcm' => 'firebase',
        'webpush' => 'mozilla',
        'apn' => 'apple'
    ];

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /**
     * Send instant notification for new gatepass submission
     */
    public function notifyNewGatepass($gatepass_id, $gatepass_number, $created_by) {
        $gatepass_data = $this->getGatepassData($gatepass_id);
        
        // Get creator info
        $stmt = $this->conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param("i", $created_by);
        $stmt->execute();
        $creator = $stmt->get_result()->fetch_assoc();
        
        $notification_data = [
            'gatepass_id' => $gatepass_id,
            'gatepass_number' => $gatepass_number,
            'creator_name' => $creator['name'],
            'from_location' => $gatepass_data['from_location'],
            'to_location' => $gatepass_data['to_location'],
            'material_type' => $gatepass_data['material_type'],
            'created_at' => date('Y-m-d H:i:s'),
            'action_url' => APP_URL . "/admin/view_gatepass.php?id=" . $gatepass_id
        ];

        // Notify all active admins with multiple channels
        $this->notifyAdmins('new_gatepass', $notification_data, true);
        
        // Also create system alert for dashboard
        $this->createSystemAlert('new_gatepass', $notification_data);
        
        return true;
    }

    /**
     * Send instant notification when admin approves gatepass
     */
    public function notifyGatepassApproved($gatepass_id, $gatepass_number, $created_by, $approved_by) {
        $gatepass_data = $this->getGatepassData($gatepass_id);
        
        $notification_data = [
            'gatepass_id' => $gatepass_id,
            'gatepass_number' => $gatepass_number,
            'from_location' => $gatepass_data['from_location'],
            'to_location' => $gatepass_data['to_location'],
            'approved_at' => date('Y-m-d H:i:s'),
            'action_url' => APP_URL . "/user/view_gatepass.php?id=" . $gatepass_id
        ];

        // Notify the user who created the gatepass
        $this->notifyUser($created_by, 'gatepass_approved', $notification_data);
        
        // Notify all security personnel
        $this->notifySecurityPersonnel('gatepass_approved', $notification_data, true);
        
        // Create system alert
        $this->createSystemAlert('gatepass_approved', $notification_data);
        
        return true;
    }

    /**
     * Send instant notification when security verifies gatepass
     */
    public function notifyGatepassVerified($gatepass_id, $gatepass_number, $created_by, $verified_by) {
        $gatepass_data = $this->getGatepassData($gatepass_id);
        
        $notification_data = [
            'gatepass_id' => $gatepass_id,
            'gatepass_number' => $gatepass_number,
            'from_location' => $gatepass_data['from_location'],
            'to_location' => $gatepass_data['to_location'],
            'verified_at' => date('Y-m-d H:i:s'),
            'action_url' => APP_URL . "/user/view_gatepass.php?id=" . $gatepass_id
        ];

        // Notify the user who created the gatepass
        $this->notifyUser($created_by, 'gatepass_verified', $notification_data, true);
        
        // Create system alert
        $this->createSystemAlert('gatepass_verified', $notification_data);
        
        return true;
    }

    /**
     * Notify all active admins
     */
    private function notifyAdmins($type, $data, $urgent = false) {
        $stmt = $this->conn->prepare("
            SELECT u.id, u.name, u.email, up.phone_number, up.whatsapp_number, up.notification_preferences 
            FROM users u 
            LEFT JOIN user_preferences up ON u.id = up.user_id 
            WHERE u.role = 'admin' AND u.status = 'active'
        ");
        $stmt->execute();
        $admins = $stmt->get_result();

        while ($admin = $admins->fetch_assoc()) {
            // Send comprehensive notification
            sendComprehensiveNotification($admin['id'], $type, $data, $urgent);
            
            // Send instant mobile notification
            $this->sendInstantMobileNotification($admin, $type, $data);
            
            // Send browser notification if online
            $this->sendBrowserNotification($admin['id'], $type, $data);
        }
    }

    /**
     * Notify all active security personnel
     */
    private function notifySecurityPersonnel($type, $data, $urgent = false) {
        $stmt = $this->conn->prepare("
            SELECT u.id, u.name, u.email, up.phone_number, up.whatsapp_number, up.notification_preferences 
            FROM users u 
            LEFT JOIN user_preferences up ON u.id = up.user_id 
            WHERE u.role = 'security' AND u.status = 'active'
        ");
        $stmt->execute();
        $security_users = $stmt->get_result();

        while ($security = $security_users->fetch_assoc()) {
            // Send comprehensive notification
            sendComprehensiveNotification($security['id'], $type, $data, $urgent);
            
            // Send instant mobile notification
            $this->sendInstantMobileNotification($security, $type, $data);
            
            // Send browser notification if online
            $this->sendBrowserNotification($security['id'], $type, $data);
        }
    }

    /**
     * Notify specific user
     */
    private function notifyUser($user_id, $type, $data, $urgent = false) {
        $stmt = $this->conn->prepare("
            SELECT u.*, up.phone_number, up.whatsapp_number, up.notification_preferences 
            FROM users u 
            LEFT JOIN user_preferences up ON u.id = up.user_id 
            WHERE u.id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            // Send comprehensive notification
            sendComprehensiveNotification($user['id'], $type, $data, $urgent);
            
            // Send instant mobile notification
            $this->sendInstantMobileNotification($user, $type, $data);
            
            // Send browser notification if online
            $this->sendBrowserNotification($user['id'], $type, $data);
        }
    }

    /**
     * Send instant mobile notification via multiple channels
     */
    private function sendInstantMobileNotification($user, $type, $data) {
        $messages = [
            'new_gatepass' => [
                'title' => '🔔 New Gatepass Alert',
                'body' => "Gatepass #{$data['gatepass_number']} from {$data['creator_name']} requires approval",
                'priority' => 'high'
            ],
            'gatepass_approved' => [
                'title' => '✅ Gatepass Approved',
                'body' => "Gatepass #{$data['gatepass_number']} approved! Security verification needed.",
                'priority' => 'high'
            ],
            'gatepass_verified' => [
                'title' => '🎉 Gatepass Verified',
                'body' => "Gatepass #{$data['gatepass_number']} verified! You may proceed with exit.",
                'priority' => 'high'
            ]
        ];

        $message = $messages[$type] ?? null;
        if (!$message) return false;

        // Try multiple instant notification methods
        $success = false;

        // 1. Try Firebase Push Notification (if configured)
        if ($this->sendFirebasePush($user, $message, $data)) {
            $success = true;
        }

        // 2. Try Telegram Bot (instant and free)
        if ($this->sendTelegramNotification($user, $message, $data)) {
            $success = true;
        }

        // 3. Try WhatsApp instant notification
        if (!empty($user['whatsapp_number'])) {
            if ($this->sendInstantWhatsApp($user['whatsapp_number'], $message, $data)) {
                $success = true;
            }
        }

        // 4. Try SMS for urgent notifications
        if ($message['priority'] === 'high' && !empty($user['phone_number'])) {
            if ($this->sendInstantSMS($user['phone_number'], $message, $data)) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * Send Firebase push notification
     */
    private function sendFirebasePush($user, $message, $data) {
        // Get user's FCM token if available
        $stmt = $this->conn->prepare("SELECT fcm_token FROM user_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result || !$result['fcm_token']) return false;

        // Firebase Cloud Messaging payload
        $payload = [
            'to' => $result['fcm_token'],
            'notification' => [
                'title' => $message['title'],
                'body' => $message['body'],
                'icon' => APP_URL . '/assets/img/logo.png',
                'click_action' => $data['action_url'] ?? APP_URL
            ],
            'data' => [
                'type' => $data['type'] ?? 'gatepass',
                'gatepass_id' => $data['gatepass_id'] ?? '',
                'url' => $data['action_url'] ?? APP_URL
            ]
        ];

        // Send to Firebase (would need Firebase server key)
        return $this->sendToFirebase($payload);
    }

    /**
     * Send Telegram notification (Free and instant)
     */
    private function sendTelegramNotification($user, $message, $data) {
        // Get user's Telegram chat ID if available
        $stmt = $this->conn->prepare("SELECT telegram_chat_id FROM user_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result || !$result['telegram_chat_id']) return false;

        $bot_token = 'YOUR_TELEGRAM_BOT_TOKEN'; // Set in config
        if (!$bot_token) return false;

        $telegram_message = "*{$message['title']}*\n\n{$message['body']}\n\n[View Details]({$data['action_url']})";

        $payload = [
            'chat_id' => $result['telegram_chat_id'],
            'text' => $telegram_message,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$bot_token}/sendMessage");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $http_code === 200;
    }

    /**
     * Send instant WhatsApp via WhatsApp Business API
     */
    private function sendInstantWhatsApp($phone, $message, $data) {
        // Use Twilio WhatsApp API (has free tier)
        $account_sid = 'YOUR_TWILIO_ACCOUNT_SID';
        $auth_token = 'YOUR_TWILIO_AUTH_TOKEN';
        
        if (!$account_sid || !$auth_token) return false;

        $whatsapp_message = "*{$message['title']}*\n\n{$message['body']}\n\nView: {$data['action_url']}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$account_sid}:{$auth_token}");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'From' => 'whatsapp:+14155238886', // Twilio Sandbox number
            'To' => 'whatsapp:' . $phone,
            'Body' => $whatsapp_message
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $http_code === 201;
    }

    /**
     * Send instant SMS for urgent notifications
     */
    private function sendInstantSMS($phone, $message, $data) {
        $sms_text = "{$message['title']}: {$message['body']} - {$data['action_url']}";
        
        // Use TextBelt free SMS API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://textbelt.com/text');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'phone' => $phone,
            'message' => $sms_text,
            'key' => 'textbelt' // Free quota key
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $result = json_decode($response, true);
            return isset($result['success']) && $result['success'];
        }

        return false;
    }

    /**
     * Send browser notification for active users
     */
    private function sendBrowserNotification($user_id, $type, $data) {
        // Store notification for real-time retrieval via JavaScript
        $stmt = $this->conn->prepare("
            INSERT INTO browser_notifications (user_id, type, title, message, data, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $title = $this->getBrowserNotificationTitle($type, $data);
        $message = $this->getBrowserNotificationMessage($type, $data);
        
        $stmt->bind_param("issss", $user_id, $type, $title, $message, json_encode($data));
        return $stmt->execute();
    }

    /**
     * Create system alert for dashboard display
     */
    private function createSystemAlert($type, $data) {
        $alerts = [
            'new_gatepass' => [
                'type' => 'warning',
                'icon' => 'fas fa-bell',
                'message' => "New gatepass #{$data['gatepass_number']} awaiting approval"
            ],
            'gatepass_approved' => [
                'type' => 'info',
                'icon' => 'fas fa-check',
                'message' => "Gatepass #{$data['gatepass_number']} approved, pending security verification"
            ],
            'gatepass_verified' => [
                'type' => 'success',
                'icon' => 'fas fa-check-double',
                'message' => "Gatepass #{$data['gatepass_number']} fully verified"
            ]
        ];

        $alert = $alerts[$type] ?? null;
        if (!$alert) return false;

        $stmt = $this->conn->prepare("
            INSERT INTO system_alerts (type, icon, message, data, created_at, expires_at) 
            VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ");
        
        $stmt->bind_param("ssss", $alert['type'], $alert['icon'], $alert['message'], json_encode($data));
        return $stmt->execute();
    }

    /**
     * Get gatepass data for notifications
     */
    private function getGatepassData($gatepass_id) {
        $stmt = $this->conn->prepare("
            SELECT gatepass_number, from_location, to_location, material_type, purpose, created_at 
            FROM gatepasses WHERE id = ?
        ");
        $stmt->bind_param("i", $gatepass_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Helper methods for notification titles and messages
     */
    private function getBrowserNotificationTitle($type, $data) {
        $titles = [
            'new_gatepass' => '🔔 New Gatepass Requires Approval',
            'gatepass_approved' => '✅ Gatepass Approved',
            'gatepass_verified' => '🎉 Gatepass Verified'
        ];
        return $titles[$type] ?? 'Gatepass Notification';
    }

    private function getBrowserNotificationMessage($type, $data) {
        $messages = [
            'new_gatepass' => "Gatepass #{$data['gatepass_number']} from {$data['creator_name']} needs your approval",
            'gatepass_approved' => "Gatepass #{$data['gatepass_number']} approved, security verification pending",
            'gatepass_verified' => "Gatepass #{$data['gatepass_number']} verified and ready for exit"
        ];
        return $messages[$type] ?? 'Please check your gatepass dashboard';
    }

    /**
     * Send to Firebase Cloud Messaging
     */
    private function sendToFirebase($payload) {
        $server_key = 'YOUR_FIREBASE_SERVER_KEY'; // Set in config
        if (!$server_key) return false;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key=' . $server_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $http_code === 200;
    }
}

/**
 * Quick helper function to send notification from anywhere in the app
 */
function sendInstantNotification($type, $gatepass_id, $gatepass_number, $user_id = null, $approved_by = null) {
    global $conn;
    
    $notificationManager = new EnhancedNotificationManager($conn);
    
    switch ($type) {
        case 'new_gatepass':
            return $notificationManager->notifyNewGatepass($gatepass_id, $gatepass_number, $user_id);
            
        case 'gatepass_approved':
            return $notificationManager->notifyGatepassApproved($gatepass_id, $gatepass_number, $user_id, $approved_by);
            
        case 'gatepass_verified':
            return $notificationManager->notifyGatepassVerified($gatepass_id, $gatepass_number, $user_id, $approved_by);
            
        default:
            return false;
    }
}
?>
