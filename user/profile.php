<?php
// profile.php - User profile management
require_once '../includes/config.php';

// Check if the user is logged in and has user role
if (!isLoggedIn() || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

// Initialize database connection
$conn = connectDB();

// Get current user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, username, name, email, role, status, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email address is required";
    }
    
    // Check if email already exists (for another user)
    if ($email !== $user['email']) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email address is already in use by another account";
        }
    }
    
    // Update profile if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $email, $user_id);
        
        if ($stmt->execute()) {
            // Update session data
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            
            // Log the activity
            logActivity($user_id, 'PROFILE_UPDATED', "User updated their profile information");
            
            // Set success message
            $_SESSION['flash_message'] = "Profile updated successfully";
            $_SESSION['flash_type'] = "success";
            
            // Redirect to refresh the page
            header("Location: profile.php");
            exit();
        } else {
            $errors[] = "Error updating profile: " . $conn->error;
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    $errors = [];
    
    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        if (!password_verify($current_password, $user_data['password'])) {
            $errors[] = "Current password is incorrect";
        }
    } else {
        $errors[] = "User authentication failed";
    }
    
    // Validate new password
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters long";
    }
    
    // Confirm passwords match
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    // Update password if no errors
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Log the activity
            logActivity($user_id, 'PASSWORD_CHANGED', "User changed their password");
            
            // Set success message
            $_SESSION['flash_message'] = "Password changed successfully";
            $_SESSION['flash_type'] = "success";
            
            // Redirect to refresh the page
            header("Location: profile.php");
            exit();
        } else {
            $errors[] = "Error changing password: " . $conn->error;
        }
    }
}

// Set page title
$page_title = "My Profile";

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <h1><i class="fas fa-user-circle me-2"></i>My Profile</h1>
    
    <div class="row mt-4">
        <!-- Profile Information Card -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Profile Information</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($errors) && !empty($errors) && isset($_POST['action']) && $_POST['action'] === 'update_profile'): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="profile.php">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                            <div class="form-text">Username cannot be changed</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <input type="text" class="form-control bg-info text-white" id="role" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" readonly disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Account Status</label>
                            <input type="text" class="form-control" id="status" value="<?php echo ucfirst(htmlspecialchars($user['status'])); ?>" readonly disabled>
                        </div>
                          <div class="mb-3">
                            <label for="created_at" class="form-label">Account Created</label>
                            <input type="text" class="form-control" id="created_at" value="<?php echo formatDateTime($user['created_at'], 'd M Y H:i'); ?>" readonly disabled>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="col-md-6">
            <!-- Change Password Card -->
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($errors) && !empty($errors) && isset($_POST['action']) && $_POST['action'] === 'change_password'): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="profile.php">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text">Password must be at least 8 characters long</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- My Gatepasses Summary -->
            <div class="card mt-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>My Gatepasses Summary</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get gatepasses summary for this user
                    $stmt = $conn->prepare("
                        SELECT 
                            COUNT(*) as total, 
                            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                            SUM(CASE WHEN status = 'approved_by_admin' OR status = 'approved_by_security' THEN 1 ELSE 0 END) as approved,
                            SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined
                        FROM gatepasses 
                        WHERE created_by = ?
                    ");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $summary = $stmt->get_result()->fetch_assoc();
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-6 col-sm-3 mb-3">
                            <div class="card h-100 border-primary">
                                <div class="card-body p-2">
                                    <h3 class="m-0"><?php echo $summary['total']; ?></h3>
                                    <small>Total</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3 mb-3">
                            <div class="card h-100 border-warning">
                                <div class="card-body p-2">
                                    <h3 class="m-0"><?php echo $summary['pending']; ?></h3>
                                    <small>Pending</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3 mb-3">
                            <div class="card h-100 border-success">
                                <div class="card-body p-2">
                                    <h3 class="m-0"><?php echo $summary['approved']; ?></h3>
                                    <small>Approved</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3 mb-3">
                            <div class="card h-100 border-danger">
                                <div class="card-body p-2">
                                    <h3 class="m-0"><?php echo $summary['declined']; ?></h3>
                                    <small>Declined</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid mt-2">
                        <a href="my_gatepasses.php" class="btn btn-outline-success">View All My Gatepasses</a>
                    </div>
                </div>
            </div>
            
            <!-- Security Tips Card -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Use a strong, unique password that combines letters, numbers, and special characters.</li>
                        <li>Never share your login credentials with others.</li>
                        <li>Change your password periodically for better security.</li>
                        <li>Log out of your account when using shared computers.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
