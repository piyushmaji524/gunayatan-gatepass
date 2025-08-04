<?php
/**
 * API endpoint to get notification dropdown data
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
    // Get recent notifications for dropdown
    $stmt = $conn->prepare("
        SELECT id, type, title, message, data, read_at, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 15
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $data = json_decode($row['data'], true) ?: [];
        
        // Calculate time ago
        $time_ago = timeAgo($row['created_at']);
        
        $notifications[] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'action_url' => $data['action_url'] ?? null,
            'read_at' => $row['read_at'],
            'time_ago' => $time_ago,
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching notification dropdown: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
} finally {
    $conn->close();
}

/**
 * Helper function to calculate time ago
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>
