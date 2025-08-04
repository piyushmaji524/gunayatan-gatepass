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

if (!isset($input['userId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user ID']);
    exit();
}

$userId = (int)$input['userId'];

// Validate user ID matches session
if ($userId !== $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

try {
    $conn = connectDB();
    
    // Remove all subscriptions for this user
    $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Log the unsubscription
    logActivity($userId, 'PUSH_UNSUBSCRIPTION', 'User unsubscribed from push notifications');
    
    echo json_encode(['success' => true, 'message' => 'Unsubscribed successfully']);
    
} catch (Exception $e) {
    error_log("Push unsubscription error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
