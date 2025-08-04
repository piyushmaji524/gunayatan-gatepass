<?php
/**
 * API endpoint to get new notifications for the current user
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
    // Get unread notifications for the user
    $stmt = $conn->prepare("
        SELECT id, type, title, message, data, created_at 
        FROM notifications 
        WHERE user_id = ? AND read_at IS NULL 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $data = json_decode($row['data'], true) ?: [];
        $notifications[] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'urgency' => $data['urgency'] ?? 'medium',
            'action_url' => $data['action_url'] ?? null,
            'created_at' => $row['created_at']
        ];
    }
    
    // Get total unread count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND read_at IS NULL
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
} finally {
    $conn->close();
}
?>
