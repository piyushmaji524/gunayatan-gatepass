<?php
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Check if ID and status are provided
if (!isset($_GET['id']) || !isset($_GET['status']) || !is_numeric($_GET['id'])) {
    redirectWithMessage("manage_users.php", "Invalid request", "danger");
}

$user_id = (int)$_GET['id'];
$new_status = sanitizeInput($_GET['status']);

// Validate status
if (!in_array($new_status, ['active', 'inactive'])) {
    redirectWithMessage("manage_users.php", "Invalid status", "danger");
}

// Connect to database
$conn = connectDB();

// Check if user exists and is not the current user (admin can't change own status)
$stmt = $conn->prepare("SELECT username, status FROM users WHERE id = ? AND id != ?");
$stmt->bind_param("ii", $user_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $conn->close();
    redirectWithMessage("manage_users.php", "User not found or you cannot change your own status", "danger");
}

$user = $result->fetch_assoc();

// Update user status
$stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
$stmt->bind_param("si", $new_status, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Log the action
    logActivity($_SESSION['user_id'], 'USER_STATUS_CHANGED', "Changed user {$user['username']} status to $new_status");
    
    // Success message
    $message = "User status changed to " . ($new_status == 'active' ? 'active' : 'inactive');
    redirectWithMessage("manage_users.php", $message, "success");
} else {
    redirectWithMessage("manage_users.php", "Failed to change user status", "danger");
}

$conn->close();
?>