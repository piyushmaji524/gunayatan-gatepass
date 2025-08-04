<?php
// reset_password_tool.php - Reset user passwords to default for Gunayatan Gatepass System
// This file should be accessible to admin users only

require_once 'includes/config.php';

// Check if user is logged in and has admin role
$allow_access = false;

if (isLoggedIn() && $_SESSION['role'] == 'admin') {
    $allow_access = true;
} else {
    // Allow access if accessed from localhost for emergency recovery
    $localhost_ips = array('127.0.0.1', '::1');
    if (in_array($_SERVER['REMOTE_ADDR'], $localhost_ips)) {
        $allow_access = true;
    }
}

if (!$allow_access) {
    header("Location: index.php");
    exit();
}

// Define variables
$error = '';
$success = '';
$users = array();

// Connect to database
$conn = connectDB();

// Get list of users
$result = $conn->query("SELECT id, username, name, email, role, status FROM users ORDER BY role, name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    
    // Validate input
    if (empty($user_id)) {
        $error = "Please select a user.";
    } elseif (empty($new_password)) {
        $error = "Please enter a new password.";
    } else {
        // Hash the password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the password
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Find the username for the log message
            $username = "";
            foreach ($users as $user) {
                if ($user['id'] == $user_id) {
                    $username = $user['username'];
                    break;
                }
            }
            
            $success = "Password for user '$username' has been reset successfully.";
            
            // Log the action
            if (isLoggedIn()) {
                logActivity($_SESSION['user_id'], "PASSWORD_RESET", "Admin reset password for user $username (ID: $user_id)");
            } else {
                logActivity(null, "PASSWORD_RESET", "Emergency password reset for user $username (ID: $user_id) from " . $_SERVER['REMOTE_ADDR']);
            }
        } else {
            $error = "Error resetting password: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Handle resetting all passwords to default
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_all_passwords']) && isset($_POST['confirm_reset_all'])) {
    $default_password = 'password123'; // Change this to your desired default password
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
    
    // Reset all passwords
    if ($conn->query("UPDATE users SET password = '$hashed_password'")) {
        $success = "All user passwords have been reset to the default password.";
        
        // Log the action
        if (isLoggedIn()) {
            logActivity($_SESSION['user_id'], "ALL_PASSWORDS_RESET", "Admin reset all user passwords to default");
        } else {
            logActivity(null, "ALL_PASSWORDS_RESET", "Emergency reset of all passwords from " . $_SERVER['REMOTE_ADDR']);
        }
    } else {
        $error = "Error resetting all passwords: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Tool - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 40px;
            padding-bottom: 40px;
        }
        .tool-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .danger-zone {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-top: 30px;
        }
        .admin-notice {
            background-color: #cff4fc;
            border-left: 4px solid #0dcaf0;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="tool-container">
            <div class="logo">
                <h1><?php echo APP_NAME; ?></h1>
                <p class="text-muted">Password Reset Tool</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>Success:</strong> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <div class="admin-notice">
                <h5>Administrator Notice</h5>
                <p>This tool allows you to reset user passwords in the system. Use with caution.</p>
                <?php if (isLoggedIn()): ?>
                    <p class="mb-0">You are logged in as: <strong><?php echo $_SESSION['name']; ?> (<?php echo $_SESSION['username']; ?>)</strong></p>
                <?php else: ?>
                    <p class="mb-0 text-danger">You are accessing this tool via localhost emergency access. Please log in normally after resetting necessary passwords.</p>
                <?php endif; ?>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Reset Individual User Password</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Select User</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">-- Select User --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo $user['name']; ?> (<?php echo $user['username']; ?>) - <?php echo ucfirst($user['role']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" name="reset_password" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Reset Password
                        </button>
                        
                        <a href="<?php echo isLoggedIn() ? 'admin/dashboard.php' : 'index.php'; ?>" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                    </form>
                </div>
            </div>
            
            <div class="danger-zone">
                <h5>Danger Zone</h5>
                <p>Reset all user passwords to the default password. This action cannot be undone.</p>
                
                <form method="post" onsubmit="return confirm('Are you ABSOLUTELY sure you want to reset ALL passwords? This cannot be undone!');">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirm_reset_all" name="confirm_reset_all" required>
                        <label class="form-check-label" for="confirm_reset_all">
                            I confirm that I want to reset ALL user passwords to the default
                        </label>
                    </div>
                    
                    <button type="submit" name="reset_all_passwords" class="btn btn-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Reset All Passwords
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('new_password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
    </script>
</body>
</html>