<?php
require_once 'includes/config.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Log the logout activity
    logActivity($_SESSION['user_id'], 'USER_LOGOUT', 'User logged out successfully');
    
    // Destroy session
    session_unset();
    session_destroy();
}

// Redirect to login page
header("Location: index.php");
exit();
?>
