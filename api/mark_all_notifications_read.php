<?php
/**
 * API endpoint to mark all notifications as read
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
    // Mark all notifications as read for the current user
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET read_at = NOW() 
        WHERE user_id = ? AND read_at IS NULL
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $affected_rows = $stmt->affected_rows;
    
    echo json_encode([
        'success' => true, 
        'message' => "Marked {$affected_rows} notifications as read"
    ]);
    
} catch (Exception $e) {
    error_log("Error marking all notifications as read: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
} finally {
    $conn->close();
}
?>
