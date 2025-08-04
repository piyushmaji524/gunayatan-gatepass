<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Get admin ID to access their panel
$admin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Connect to database
$conn = connectDB();

// Verify the user exists and has 'admin' role
$stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ? AND role = 'admin'");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Admin not found or not an admin
    $conn->close();
    redirectWithMessage("manage_all_users.php", "Invalid user or user is not of role 'admin'", "danger");
    exit();
}

$admin = $result->fetch_assoc();

// Store original superadmin info for later restoration
$_SESSION['original_user_id'] = $_SESSION['user_id'];
$_SESSION['original_name'] = $_SESSION['name'];
$_SESSION['original_email'] = $_SESSION['email'];
$_SESSION['original_role'] = $_SESSION['role'];
$_SESSION['impersonating'] = true;

// Set session variables to impersonate the admin
$_SESSION['user_id'] = $admin['id'];
$_SESSION['name'] = $admin['name'];
$_SESSION['email'] = $admin['email'];
$_SESSION['role'] = $admin['role']; // This should be 'admin'

// Log the action
$action_details = "Superadmin (ID: {$_SESSION['original_user_id']}) accessed admin panel as {$admin['name']} (ID: {$admin['id']})";
logAction($_SESSION['original_user_id'], 'ADMIN_PANEL_ACCESS', $action_details);

// Redirect to the admin dashboard
$_SESSION['flash_message'] = "You are now viewing the system as admin: {$admin['name']}. Remember to exit this view when finished.";
$_SESSION['flash_type'] = "warning";
header("Location: ../admin/dashboard.php");
exit();
?>
