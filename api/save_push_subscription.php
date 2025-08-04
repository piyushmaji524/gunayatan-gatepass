<?php
/**
 * Save Push Subscription API Endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid subscription data']);
    exit;
}

$user_id = $_SESSION['user_id'];
$subscription = json_encode($input);

try {
    $conn = connectDB();
    
    // Get current push tokens
    $stmt = $conn->prepare("SELECT push_tokens FROM user_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $current_tokens = [];
    if ($result && $result['push_tokens']) {
        $current_tokens = json_decode($result['push_tokens'], true) ?: [];
    }
    
    // Add new subscription if not already exists
    $subscription_endpoint = $input['endpoint'] ?? '';
    $already_exists = false;
    
    foreach ($current_tokens as $token) {
        if (isset($token['endpoint']) && $token['endpoint'] === $subscription_endpoint) {
            $already_exists = true;
            break;
        }
    }
    
    if (!$already_exists) {
        $current_tokens[] = $input;
        
        // Update or insert push tokens
        $push_tokens_json = json_encode($current_tokens);
        
        if ($result) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE user_preferences SET push_tokens = ? WHERE user_id = ?");
            $stmt->bind_param("si", $push_tokens_json, $user_id);
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO user_preferences (user_id, push_tokens, notification_preferences) 
                VALUES (?, ?, JSON_OBJECT('push', true))
            ");
            $stmt->bind_param("is", $user_id, $push_tokens_json);
        }
        
        $stmt->execute();
    }
    
    $conn->close();
    
    echo json_encode(['success' => true, 'message' => 'Push subscription saved']);
    
} catch (Exception $e) {
    error_log("Save push subscription error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save push subscription']);
}
?>
