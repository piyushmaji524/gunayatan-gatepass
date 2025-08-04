<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_all_users.php");
    exit();
}

$user_id = (int)$_GET['id'];

// Don't allow impersonating yourself
if ($user_id === (int)$_SESSION['user_id']) {
    $_SESSION['error'] = "You cannot impersonate yourself.";
    header("Location: manage_all_users.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows == 0) {
    $_SESSION['error'] = "User not found.";
    header("Location: manage_all_users.php");
    exit();
}

$user = $user_result->fetch_assoc();

// Store superadmin's original session data for later restoration
$_SESSION['impersonating'] = true;
$_SESSION['original_user_id'] = $_SESSION['user_id'];
$_SESSION['original_username'] = $_SESSION['username'];
$_SESSION['original_name'] = $_SESSION['name'];
$_SESSION['original_role'] = $_SESSION['role'];
$_SESSION['original_email'] = $_SESSION['email'];

// Log the impersonation
logAction($_SESSION['user_id'], "Started impersonating user: " . $user['name'] . " (ID: " . $user_id . ") with role: " . $user['role']);

// Close database connection
$conn->close();

// Update session with impersonated user's data
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['name'] = $user['name'];
$_SESSION['role'] = $user['role'];
$_SESSION['email'] = $user['email'];

// Redirect to appropriate dashboard based on role
switch ($user['role']) {
    case 'admin':
        header("Location: ../admin/dashboard.php");
        break;
    case 'security':
        header("Location: ../security/dashboard.php");
        break;
    case 'user':
        header("Location: ../user/dashboard.php");
        break;
    default:
        header("Location: dashboard.php"); // Fallback to superadmin dashboard
        break;
}
exit();
?>
