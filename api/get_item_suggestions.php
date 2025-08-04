<?php
require_once '../includes/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get the search term from query parameter
$search_term = isset($_GET['term']) ? trim($_GET['term']) : '';

// Validate search term (minimum 1 character)
if (strlen($search_term) < 1) {
    echo json_encode([]);
    exit();
}

// Connect to database
$conn = connectDB();

try {
    // Get unique item names that match the search term, ordered by frequency of use
    $stmt = $conn->prepare("
        SELECT 
            item_name,
            COUNT(*) as usage_count,
            MAX(gi.id) as last_used_id
        FROM gatepass_items gi
        INNER JOIN gatepasses g ON gi.gatepass_id = g.id
        WHERE item_name LIKE ? 
        GROUP BY LOWER(TRIM(item_name))
        ORDER BY usage_count DESC, last_used_id DESC
        LIMIT 10
    ");
    
    $search_param = $search_term . '%';
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = [
            'value' => trim($row['item_name']),
            'label' => trim($row['item_name']) . ' (' . $row['usage_count'] . ' times used)',
            'usage_count' => (int)$row['usage_count']
        ];
    }
    
    // If no exact matches found and search term is at least 2 characters, try partial matches
    if (empty($suggestions) && strlen($search_term) >= 2) {
        $stmt = $conn->prepare("
            SELECT 
                item_name,
                COUNT(*) as usage_count,
                MAX(gi.id) as last_used_id
            FROM gatepass_items gi
            INNER JOIN gatepasses g ON gi.gatepass_id = g.id
            WHERE item_name LIKE ? 
            GROUP BY LOWER(TRIM(item_name))
            ORDER BY usage_count DESC, last_used_id DESC
            LIMIT 10
        ");
        
        $search_param = '%' . $search_term . '%';
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = [
                'value' => trim($row['item_name']),
                'label' => trim($row['item_name']) . ' (' . $row['usage_count'] . ' times used)',
                'usage_count' => (int)$row['usage_count']
            ];
        }
    }
    
    // Remove duplicates and ensure we have clean data
    $unique_suggestions = [];
    $seen_items = [];
    
    foreach ($suggestions as $suggestion) {
        $clean_value = trim(strtolower($suggestion['value']));
        if (!empty($clean_value) && !in_array($clean_value, $seen_items)) {
            $seen_items[] = $clean_value;
            $unique_suggestions[] = $suggestion;
        }
    }
    
    echo json_encode($unique_suggestions);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
    error_log('Item suggestions API error: ' . $e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
