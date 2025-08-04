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

if (!isset($input['subscription']) || !isset($input['userId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required data']);
    exit();
}

$subscription = $input['subscription'];
$userId = (int)$input['userId'];

// Validate user ID matches session
if ($userId !== $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

try {
    $conn = connectDB();
    
    // Create push_subscriptions table if it doesn't exist
    $conn->query("
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh_key TEXT NOT NULL,
            auth_key TEXT NOT NULL,
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_endpoint (user_id, endpoint(255))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Extract subscription data
    $endpoint = $subscription['endpoint'];
    $p256dh = $subscription['keys']['p256dh'];
    $auth = $subscription['keys']['auth'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Insert or update subscription
    $stmt = $conn->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key, user_agent) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            p256dh_key = VALUES(p256dh_key),
            auth_key = VALUES(auth_key),
            user_agent = VALUES(user_agent),
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param("issss", $userId, $endpoint, $p256dh, $auth, $userAgent);
    $stmt->execute();
    
    // Log the subscription
    logActivity($userId, 'PUSH_SUBSCRIPTION', 'User subscribed to push notifications');
    
    echo json_encode(['success' => true, 'message' => 'Subscription saved successfully']);
    
} catch (Exception $e) {
    error_log("Push subscription error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
