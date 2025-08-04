<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
    // Redirect back to manage users page if not properly submitted
    header("Location: manage_all_users.php");
    exit();
}

$user_id = (int)$_POST['user_id'];

// Don't allow deleting yourself
if ($user_id === (int)$_SESSION['user_id']) {
    $_SESSION['error'] = "You cannot delete your own account.";
    header("Location: manage_all_users.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Get user info before deletion for log
$stmt = $conn->prepare("SELECT name, username, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows == 0) {
    $_SESSION['error'] = "User not found.";
    header("Location: manage_all_users.php");
    exit();
}

$user = $user_result->fetch_assoc();

// Begin transaction for safe deletion of related data
$conn->begin_transaction();

try {
    // Delete related data first
    
    // 1. Delete user's logs
    $stmt = $conn->prepare("DELETE FROM logs WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // 2. Update admin_approved_by and security_approved_by to NULL for gatepasses
    $stmt = $conn->prepare("UPDATE gatepasses SET admin_approved_by = NULL WHERE admin_approved_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $stmt = $conn->prepare("UPDATE gatepasses SET security_approved_by = NULL WHERE security_approved_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // 3. Delete gatepasses created by the user
    $stmt = $conn->prepare("DELETE FROM gatepasses WHERE created_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // 4. Finally delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // If we got here, commit the transaction
    $conn->commit();
    
    // Log the action
    logAction($_SESSION['user_id'], "Deleted user: " . $user['name'] . " (" . $user['username'] . ") with role: " . $user['role']);
    
    // Set success message
    $_SESSION['success'] = "User " . $user['name'] . " has been permanently deleted along with all associated data.";
    
} catch (Exception $e) {
    // If there was an error, roll back the transaction
    $conn->rollback();
    
    // Set error message
    $_SESSION['error'] = "Failed to delete user: " . $e->getMessage();
}

// Close database connection
$conn->close();

// Redirect back to manage users page
header("Location: manage_all_users.php");
exit();
?>
