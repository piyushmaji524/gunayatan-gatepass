<?php
/**
 * Get Notifications API Endpoint
 * Returns user's notifications and unread count
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;

try {
    $conn = connectDB();
    
    // Get notifications
    $stmt = $conn->prepare("
        SELECT id, type, title, message, data, urgency, read_at, created_at
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['data'] = json_decode($row['data'], true);
        $notifications[] = $row;
    }
    
    // Get unread count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND read_at IS NULL
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unread_result = $stmt->get_result()->fetch_assoc();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => (int)$unread_result['unread_count']
    ]);
    
} catch (Exception $e) {
    error_log("Get notifications error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch notifications']);
}
?>
