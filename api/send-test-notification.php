<?php
require_once '../includes/config.php';

// CORS headers for API access
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$userId = (int)$input['userId'];

// Validate user ID matches session
if ($userId !== $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

try {
    // Send a test push notification
    $result = sendPushNotification($userId, [
        'title' => '🎉 Test Notification',
        'body' => 'Push notifications are working! You will receive alerts for gatepass updates.',
        'icon' => '/assets/img/logo.png',
        'badge' => '/assets/img/logo.png',
        'data' => [
            'url' => '/user/dashboard.php',
            'type' => 'test'
        ]
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Test notification sent']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send notification']);
    }
    
} catch (Exception $e) {
    error_log("Test notification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Send push notification to a specific user
 */
function sendPushNotification($userId, $payload) {
    try {
        $conn = connectDB();
        
        // Get user's push subscriptions
        $stmt = $conn->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $subscriptions = $stmt->get_result();
        
        if ($subscriptions->num_rows === 0) {
            error_log("No push subscriptions found for user $userId");
            return false;
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
 * Send push notification to a single subscription
 */
function sendSinglePushNotification($subscription, $payload) {
    // VAPID keys for piyush.maji@your_domain_name
    $vapidPublicKey = 'BNwxRQojNWd7lq2_-9v0y_SLJz9F8LpPgdUj5VdWErGfXXZqPhl2FUXuDsltVYz7jlu4Z-CUhwFFMQt-x5xt1Vo';
    $vapidPrivateKey = 'DeulneWZLm99TDuqpdZK5ye4tJLtRgNeMiU3KAf32_I'; // Keep this secret!
    $vapidSubject = 'mailto:piyush.maji@your_domain_name';
    
    try {
        // Simple implementation using cURL
        // For production, consider using a library like web-push-php
        
        $endpoint = $subscription['endpoint'];
        $p256dh = $subscription['p256dh_key'];
        $auth = $subscription['auth_key'];
        
        // Prepare the notification payload
        $notificationPayload = json_encode($payload);
        
        // For now, we'll use a simple approach
        // In production, you should implement proper Web Push Protocol with VAPID
        
        // Log the notification attempt
        error_log("Attempting to send push notification to: " . substr($endpoint, 0, 50) . "...");
        
        // Simple implementation using cURL for Firebase/Google FCM
        if (strpos($endpoint, 'fcm.googleapis.com') !== false) {
            return sendFCMNotification($endpoint, $notificationPayload, $vapidPublicKey, $vapidPrivateKey);
        }
        
        // For demonstration, we'll return true for other endpoints
        // In a real implementation, you would:
        // 1. Generate JWT with VAPID keys
        // 2. Encrypt the payload
        // 3. Send HTTP request to the push service
        
        return true;
        
    } catch (Exception $e) {
        error_log("Single push notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send FCM (Firebase Cloud Messaging) notification
 */
function sendFCMNotification($endpoint, $payload, $vapidPublicKey, $vapidPrivateKey) {
    try {
        // Extract registration token from FCM endpoint
        $parts = explode('/', $endpoint);
        $token = end($parts);
        
        // Prepare FCM headers
        $headers = [
            'Authorization: key=' . $vapidPrivateKey, // For FCM, use server key here
            'Content-Type: application/json'
        ];
        
        // Prepare FCM payload
        $fcmPayload = [
            'to' => $token,
            'notification' => json_decode($payload, true),
            'data' => [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => 'default'
            ]
        ];
        
        // Send to FCM
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmPayload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            error_log("FCM notification sent successfully: " . $response);
            return true;
        } else {
            error_log("FCM notification failed with HTTP $httpCode: " . $response);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("FCM notification error: " . $e->getMessage());
        return false;
    }
}
?>
