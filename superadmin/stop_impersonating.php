<?php
require_once '../includes/config.php';

// Check if user is impersonating someone
if (!isLoggedIn() || !isset($_SESSION['impersonating']) || $_SESSION['impersonating'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Log the end of impersonation
$action_details = "Stopped impersonating user: " . $_SESSION['name'] . " (ID: " . $_SESSION['user_id'] . ") with role: " . $_SESSION['role'];
logAction($_SESSION['original_user_id'], 'IMPERSONATION_ENDED', $action_details);

// Restore original superadmin session data
$_SESSION['user_id'] = $_SESSION['original_user_id'];
$_SESSION['name'] = $_SESSION['original_name'];
$_SESSION['role'] = $_SESSION['original_role'];
$_SESSION['email'] = $_SESSION['original_email'];
if (isset($_SESSION['original_username'])) {
    $_SESSION['username'] = $_SESSION['original_username'];
}

// Remove impersonation flags
unset($_SESSION['impersonating']);
unset($_SESSION['original_user_id']);
unset($_SESSION['original_username']);
unset($_SESSION['original_name']);
unset($_SESSION['original_role']);
unset($_SESSION['original_email']);

// Set flash message
$_SESSION['flash_message'] = "You have returned to your superadmin account.";
$_SESSION['flash_type'] = "success";

// Redirect back to superadmin dashboard
header("Location: dashboard.php");
exit();
?>
