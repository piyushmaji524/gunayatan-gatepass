<?php
require_once '../includes/config.php';

// Check if user is logged in and has security role
if (!isLoggedIn() || $_SESSION['role'] != 'security') {
    header("Location: ../index.php");
    exit();
}

// Check if ID and decline_reason are provided in POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['gatepass_id']) || !isset($_POST['decline_reason'])) {
    redirectWithMessage("search_gatepass.php", "Invalid request", "danger");
}

$gatepass_id = (int)$_POST['gatepass_id'];
$decline_reason = sanitizeInput($_POST['decline_reason']);

// Validate inputs
if (empty($decline_reason)) {
    redirectWithMessage("verify_gatepass.php?id=$gatepass_id", "Please provide a reason for declining", "warning");
}

// Connect to database
$conn = connectDB();

// Check if gatepass exists and is in approved_by_admin status
$stmt = $conn->prepare("SELECT gatepass_number FROM gatepasses WHERE id = ? AND status = 'approved_by_admin'");
$stmt->bind_param("i", $gatepass_id);
$stmt->execute();
$result = $stmt->get_result();

// If no gatepass found or not in appropriate status
if ($result->num_rows !== 1) {
    $conn->close();
    redirectWithMessage("search_gatepass.php", "Gatepass not found or cannot be declined", "danger");
}

$gatepass_number = $result->fetch_assoc()['gatepass_number'];

// Update gatepass status using MySQL's NOW() function for consistent server timezone
$stmt = $conn->prepare("
    UPDATE gatepasses 
    SET status = 'declined', 
        declined_by = ?,
        declined_at = NOW(),
        decline_reason = ?
    WHERE id = ? AND status = 'approved_by_admin'
");
$stmt->bind_param("isi", $_SESSION['user_id'], $decline_reason, $gatepass_id);
$stmt->execute();

// Check if update was successful
if ($stmt->affected_rows > 0) {
    // Log the action
    logActivity($_SESSION['user_id'], 'GATEPASS_DECLINED', "Security declined gatepass $gatepass_number: $decline_reason");
    
    // Redirect with success message
    redirectWithMessage("search_gatepass.php", "Gatepass #$gatepass_number has been declined", "success");
} else {
    // If update failed
    redirectWithMessage("verify_gatepass.php?id=$gatepass_id", "Failed to decline gatepass. It may have been processed already.", "danger");
}

// Close the database connection
$conn->close();
?>