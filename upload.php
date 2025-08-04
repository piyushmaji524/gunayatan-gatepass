<?php
// This file handles file uploads for the gatepass system

require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

// Define allowed file types and maximum size
$allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'application/pdf');
$max_size = 5 * 1024 * 1024; // 5MB

// Check if file was uploaded
if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('No file uploaded');
}

$file = $_FILES['file'];

// Validate file size
if ($file['size'] > $max_size) {
    header('HTTP/1.1 400 Bad Request');
    exit('File too large. Maximum size is 5MB.');
}

// Validate file type
if (!in_array($file['type'], $allowed_types)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid file type. Allowed types: JPEG, PNG, GIF, PDF.');
}

// Generate a unique filename
$timestamp = time();
$unique_id = uniqid();
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = $timestamp . '_' . $unique_id . '.' . $extension;

// Destination directory
$upload_dir = __DIR__ . '/uploads/';
$destination = $upload_dir . $new_filename;

// Create directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Move the uploaded file
if (move_uploaded_file($file['tmp_name'], $destination)) {
    // Log the upload
    logActivity($_SESSION['user_id'], 'FILE_UPLOADED', 'Uploaded file: ' . $file['name']);
    
    // Return success response with file details
    $response = array(
        'success' => true,
        'file' => array(
            'name' => $file['name'],
            'type' => $file['type'],
            'size' => $file['size'],
            'path' => 'uploads/' . $new_filename
        )
    );
    
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Failed to upload file');
}
?>
