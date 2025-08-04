<?php
/**
 * Mark Notification as Read API Endpoint
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

if (!isset($input['notification_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Notification ID required']);
    exit;
}

$notification_id = (int)$input['notification_id'];
$user_id = $_SESSION['user_id'];

try {
    $conn = connectDB();
    
    // Mark notification as read (only if it belongs to the current user)
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET read_at = NOW() 
        WHERE id = ? AND user_id = ? AND read_at IS NULL
    ");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    
    $affected_rows = $stmt->affected_rows;
    $conn->close();
    
    if ($affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Notification not found or already read']);
    }
    
} catch (Exception $e) {
    error_log("Mark notification read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
}
?>
