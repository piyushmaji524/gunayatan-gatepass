<?php
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWithMessage("manage_users.php", "Invalid user ID", "danger");
}

$user_id = (int)$_GET['id'];

// Connect to database
$conn = connectDB();

// Check if user exists and is in pending status
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $conn->close();
    redirectWithMessage("manage_users.php", "User not found or not in pending status", "danger");
}

$username = $result->fetch_assoc()['username'];

// Update user status to active
$stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Log the action
    logActivity($_SESSION['user_id'], 'USER_APPROVED', "Approved user account: $username");
    
    // Success message
    redirectWithMessage("manage_users.php", "User account approved successfully", "success");
} else {
    redirectWithMessage("manage_users.php", "Failed to approve user", "danger");
}

$conn->close();
?>