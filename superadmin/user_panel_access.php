<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Get user ID to access their panel
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Connect to database
$conn = connectDB();

// Verify the user exists and has 'user' role
$stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ? AND role = 'user'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // User not found or not a regular user
    $conn->close();
    redirectWithMessage("manage_all_users.php", "Invalid user or user is not of role 'user'", "danger");
    exit();
}

$user = $result->fetch_assoc();

// Store original superadmin info for later restoration
$_SESSION['original_user_id'] = $_SESSION['user_id'];
$_SESSION['original_name'] = $_SESSION['name'];
$_SESSION['original_email'] = $_SESSION['email'];
$_SESSION['original_role'] = $_SESSION['role'];
$_SESSION['impersonating'] = true;

// Set session variables to impersonate the user
$_SESSION['user_id'] = $user['id'];
$_SESSION['name'] = $user['name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role']; // This should be 'user'

// Log the action
$action_details = "Superadmin (ID: {$_SESSION['original_user_id']}) accessed user panel as {$user['name']} (ID: {$user['id']})";
logAction($_SESSION['original_user_id'], 'USER_PANEL_ACCESS', $action_details);

// Redirect to the user dashboard
$_SESSION['flash_message'] = "You are now viewing the system as user: {$user['name']}. Remember to exit this view when finished.";
$_SESSION['flash_type'] = "warning";
header("Location: ../user/dashboard.php");
exit();
?>
