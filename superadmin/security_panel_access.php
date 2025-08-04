<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Get security ID to access their panel
$security_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Connect to database
$conn = connectDB();

// Verify the user exists and has 'security' role
$stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ? AND role = 'security'");
$stmt->bind_param("i", $security_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Security not found or not a security
    $conn->close();
    redirectWithMessage("manage_all_users.php", "Invalid user or user is not of role 'security'", "danger");
    exit();
}

$security = $result->fetch_assoc();

// Store original superadmin info for later restoration
$_SESSION['original_user_id'] = $_SESSION['user_id'];
$_SESSION['original_name'] = $_SESSION['name'];
$_SESSION['original_email'] = $_SESSION['email'];
$_SESSION['original_role'] = $_SESSION['role'];
$_SESSION['impersonating'] = true;

// Set session variables to impersonate the security
$_SESSION['user_id'] = $security['id'];
$_SESSION['name'] = $security['name'];
$_SESSION['email'] = $security['email'];
$_SESSION['role'] = $security['role']; // This should be 'security'

// Log the action
$action_details = "Superadmin (ID: {$_SESSION['original_user_id']}) accessed security panel as {$security['name']} (ID: {$security['id']})";
logAction($_SESSION['original_user_id'], 'SECURITY_PANEL_ACCESS', $action_details);

// Redirect to the security dashboard
$_SESSION['flash_message'] = "You are now viewing the system as security staff: {$security['name']}. Remember to exit this view when finished.";
$_SESSION['flash_type'] = "warning";
header("Location: ../security/dashboard.php");
exit();
?>
