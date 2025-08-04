<?php
// profile.php - Security profile management
require_once '../includes/config.php';

// Check if the user is logged in and has security role
if (!isLoggedIn() || $_SESSION['role'] !== 'security') {
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
            logActivity($user_id, 'PROFILE_UPDATED', "Security user updated their profile information");
            
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
            logActivity($user_id, 'PASSWORD_CHANGED', "Security user changed their password");
            
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
$page_title = "Security Profile";

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <h1><i class="fas fa-shield-alt me-2"></i>Security Profile</h1>
    
    <div class="row mt-4">
        <!-- Profile Information Card -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
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
                            <input type="text" class="form-control bg-warning text-dark" id="role" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" readonly disabled>
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
                            <button type="submit" class="btn btn-warning">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="col-md-6">
            <!-- Change Password Card -->
            <div class="card">
                <div class="card-header bg-danger text-white">
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
                            <button type="submit" class="btn btn-danger">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Activity Card -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get recent activity for this user
                    $stmt = $conn->prepare("
                        SELECT action, details, created_at 
                        FROM logs 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $activities = $stmt->get_result();
                    
                    if ($activities->num_rows > 0):
                    ?>
                    <ul class="list-group">
                        <?php while ($activity = $activities->fetch_assoc()): ?>
                        <li class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                <small><?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?></small>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars($activity['details']); ?></p>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                    <?php else: ?>
                    <p class="mb-0 text-muted">No recent activity found.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Security Tips Card -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Guidelines</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>As security personnel, you have critical responsibilities for verifying gatepasses and ensuring organizational security.</li>
                        <li>Always verify the identity of individuals presenting gatepasses.</li>
                        <li>Check that gatepass details match the items being transported.</li>
                        <li>Report any suspicious activity to your supervisor immediately.</li>
                        <li>Use a strong password and change it regularly.</li>
                        <li>Always log out when leaving your workstation.</li>
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
