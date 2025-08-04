<?php
/**
 * Quick User Credentials Script
 * Simple command-line style output for all user credentials
 */

require_once 'includes/config.php';

// Security check
if (!isLoggedIn() || $_SESSION['role'] !== 'superadmin') {
    $localhost_ips = array('127.0.0.1', '::1', 'localhost');
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $localhost_ips)) {
        die("Access denied. Superadmin access required.");
    }
}

// Connect to database
$conn = connectDB();
$stmt = $conn->prepare("SELECT id, username, name, email, role, status FROM users ORDER BY role, name");
$stmt->execute();
$users = $stmt->get_result();

// Default passwords
$passwords = [
    'superadmin' => 'admin123',
    'admin' => 'admin123', 
    'security' => 'security123',
    'user' => 'user123'
];

header('Content-Type: text/plain; charset=utf-8');
echo "========================================\n";
echo "GUNAYATAN GATEPASS - USER CREDENTIALS\n";
echo "========================================\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n";
echo "Login URL: gunayatangatepass.com\n";
echo "========================================\n\n";

while ($user = $users->fetch_assoc()) {
    $password = $passwords[$user['role']] ?? 'user123';
    
    echo "USER: " . $user['name'] . "\n";
    echo "ROLE: " . strtoupper($user['role']) . "\n";
    echo "USERNAME: " . $user['username'] . "\n";
    echo "PASSWORD: " . $password . "\n";
    echo "EMAIL: " . $user['email'] . "\n";
    echo "STATUS: " . strtoupper($user['status']) . "\n";
    echo "----------------------------------------\n";
    
    // Welcome message format
    echo "WELCOME MESSAGE:\n";
    echo "GUNAYATAN GATEPASS\n";
    echo "YOUR ROLL - " . strtoupper($user['role']) . "\n";
    echo "YOUR USER ID - " . $user['username'] . "\n";
    echo "YOUR PASSWORD - " . $password . "\n";
    echo "\nPLEASE LOGIN FROM THERE - gunayatangatepass.com\n";
    
    // Role-specific instructions
    switch ($user['role']) {
        case 'superadmin':
            echo "\nHOW TO USE: You have complete system control. Manage users, settings, database, and security.\n";
            break;
        case 'admin':
            echo "\nHOW TO USE: Approve gatepass requests, manage users, and view system reports.\n";
            break;
        case 'security':
            echo "\nHOW TO USE: Verify approved gatepasses and manage final authorization process.\n";
            break;
        case 'user':
            echo "\nHOW TO USE: Create gatepass requests and track your submission status.\n";
            break;
    }
    
    echo "\nTHANK YOU.\n";
    echo "========================================\n\n";
}

echo "SUMMARY:\n";
echo "Total Users: " . $users->num_rows . "\n";
echo "Default Passwords by Role:\n";
foreach ($passwords as $role => $pwd) {
    echo "- " . ucfirst($role) . ": " . $pwd . "\n";
}
echo "\nNote: Users should change passwords after first login.\n";
echo "========================================\n";

$conn->close();
?>
