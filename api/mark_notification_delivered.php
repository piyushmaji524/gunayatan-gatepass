<?php
/**
 * API endpoint to mark notification as delivered
 */
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = connectDB();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['notification_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID required']);
        exit();
    }
    
    $notification_id = (int)$input['notification_id'];
    
    // Log delivery in notification_delivery_log
    $stmt = $conn->prepare("
        INSERT INTO notification_delivery_log (user_id, notification_type, channel, status, delivered_at) 
        SELECT user_id, type, 'browser', 'delivered', NOW() 
        FROM browser_notifications 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Notification marked as delivered']);
    
} catch (Exception $e) {
    error_log("Error marking notification as delivered: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
} finally {
    $conn->close();
}
?>
