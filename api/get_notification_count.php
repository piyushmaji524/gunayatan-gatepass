<?php
/**
 * API endpoint to get notification count for the current user
 */
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = connectDB();

try {
    // Get unread notification count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND read_at IS NULL
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'count' => (int)$count
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching notification count: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
} finally {
    $conn->close();
}
?>
