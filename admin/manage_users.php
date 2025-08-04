<?php
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Handle form submission for new user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $username = sanitizeInput($_POST['username']);
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $role = sanitizeInput($_POST['role']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    $errors = array();
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($role) || !in_array($role, ['admin', 'security', 'user'])) {
        $errors[] = "Valid role is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password != $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Username already taken";
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already registered";
    }
    
    // If no errors, insert the new user
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $conn->prepare("
            INSERT INTO users (username, password, name, email, role, status) 
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param("sssss", $username, $hashed_password, $name, $email, $role);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            logActivity($_SESSION['user_id'], 'USER_CREATED', "Created new $role user: $username");
            redirectWithMessage("manage_users.php", "User added successfully", "success");
        } else {
            $errors[] = "Error creating user: " . $conn->error;
        }
    }
}

// Get all users except superadmins
$stmt = $conn->prepare("
    SELECT id, username, name, email, role, status, created_at 
    FROM users 
    WHERE role != 'superadmin'
    ORDER BY created_at DESC
");
$stmt->execute();
$users = $stmt->get_result();

// Set page title
$page_title = "Manage Users";

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-users me-2"></i>Manage Users</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-2"></i>Add New User
        </button>
    </div>
    
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>User Accounts</h5>
                <span class="badge bg-primary rounded-pill"><?php echo $users->num_rows; ?> Users</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions (not authorised )</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if ($user['role'] == 'admin'): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php elseif ($user['role'] == 'security'): ?>
                                        <span class="badge bg-warning text-dark">Security</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($user['status'] == 'inactive'): ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDateTime($user['created_at'], 'd M Y'); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <?php if ($user['status'] == 'active'): ?>
                                                <a href="#?id=<?php echo $user['id']; ?>&status=inactive" 
                                                   class="btn btn-outline-secondary" title="You are not authorized to click this button">
                                                    <i class="fas fa-user-slash"></i>
                                                </a>
                                            <?php elseif ($user['status'] == 'inactive'): ?>
                                                <a href="change_user_status.php?id=<?php echo $user['id']; ?>&status=active" 
                                                   class="btn btn-outline-success" title="Activate">
                                                    <i class="fas fa-user-check"></i>
                                                </a>
                                            <?php elseif ($user['status'] == 'pending'): ?>
                                                <a href="approve_user.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-outline-success" title="Approve">
                                                    <i class="fas fa-user-check"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-primary reset-password-btn" 
                                                data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                                data-user-id="<?php echo $user['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                            <i class="fas fa-key"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="post">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="security">Security</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">At least 8 characters long</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="reset_password.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    
                    <p>For change password go to profile . You are not authorised to change other users password <strong id="reset_username"></strong>.</p>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text">At least 8 characters long</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <!--<button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle reset password button click
    const resetButtons = document.querySelectorAll('.reset-password-btn');
    resetButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.getAttribute('data-username');
            
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_username').textContent = username;
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>