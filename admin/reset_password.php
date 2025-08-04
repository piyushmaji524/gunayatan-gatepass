<?php
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Check if it's a POST request with the required fields
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['user_id']) || 
    !isset($_POST['new_password']) || 
    !isset($_POST['confirm_new_password'])) {
    redirectWithMessage("manage_users.php", "Invalid request", "danger");
}

$user_id = (int)$_POST['user_id'];
$new_password = $_POST['new_password'];
$confirm_new_password = $_POST['confirm_new_password'];

// Validate inputs
$errors = array();

if (empty($new_password)) {
    $errors[] = "Password is required";
} elseif (strlen($new_password) < 8) {
    $errors[] = "Password must be at least 8 characters long";
}

if ($new_password !== $confirm_new_password) {
    $errors[] = "Passwords do not match";
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    redirectWithMessage("manage_users.php", "Password reset failed", "danger");
}

// Connect to database
$conn = connectDB();

// Check if user exists
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $conn->close();
    redirectWithMessage("manage_users.php", "User not found", "danger");
}

$username = $result->fetch_assoc()['username'];

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update the password
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashed_password, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Log the action
    logActivity($_SESSION['user_id'], 'USER_PASSWORD_RESET', "Reset password for user: $username");
    
    // Success message
    redirectWithMessage("manage_users.php", "Password reset successfully for $username", "success");
} else {
    redirectWithMessage("manage_users.php", "Failed to reset password", "danger");
}

$conn->close();
?>
