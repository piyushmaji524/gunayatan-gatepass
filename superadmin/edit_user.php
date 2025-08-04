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

// Connect to database
$conn = connectDB();

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows == 0) {
    header("Location: manage_all_users.php");
    exit();
}

$user = $user_result->fetch_assoc();

// Set page title
$page_title = "Edit User: " . $user['name'];

// Initialize variables for form processing
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    $change_password = isset($_POST['change_password']) ? true : false;
    $password = $change_password ? trim($_POST['password']) : '';
    $confirm_password = $change_password ? trim($_POST['confirm_password']) : '';

    // Validate input
    if (empty($name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if the email already exists for other users
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists for another user";
        }
    }
    
    if (empty($role)) {
        $errors[] = "Role is required";
    }
    
    if (empty($status)) {
        $errors[] = "Status is required";
    }
    
    // Validate password if changing
    if ($change_password) {
        if (empty($password)) {
            $errors[] = "Password is required when changing password";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }

    // Update user if no errors
    if (empty($errors)) {
        // Prepare SQL for updating user
        if ($change_password) {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user with new password
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssssi", $name, $email, $role, $status, $hashed_password, $user_id);
        } else {
            // Update user without changing password
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $email, $role, $status, $user_id);
        }
        
        if ($stmt->execute()) {
            // Log the action
            logAction($_SESSION['user_id'], "Updated user: " . $name . " (ID: " . $user_id . ") with role: " . $role);
            
            $success = true;
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user_result = $stmt->get_result();
            $user = $user_result->fetch_assoc();
        } else {
            $errors[] = "Error updating user: " . $conn->error;
        }
    }
}

// Include header
require_once '../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="manage_all_users.php">Manage Users</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit User</li>
        </ol>
    </nav>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit User: <?php echo htmlspecialchars($user['name']); ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>User updated successfully!
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                                <div class="form-text text-muted">Username cannot be changed.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="superadmin" <?php echo $user['role'] === 'superadmin' ? 'selected' : ''; ?>>Super Admin</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="security" <?php echo $user['role'] === 'security' ? 'selected' : ''; ?>>Security</option>
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Created At</label>
                                <input type="text" class="form-control" value="<?php echo formatDateTime($user['created_at']); ?>" readonly disabled>
                            </div>
                        </div>

                        <div class="card mt-4 mb-4">
                            <div class="card-header bg-secondary text-white">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="change_password" id="change_password">
                                    <label class="form-check-label" for="change_password">Change Password</label>
                                </div>
                            </div>
                            <div class="card-body password-fields" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="password" name="password">
                                        <div class="form-text">Password must be at least 6 characters long.</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex mt-4">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-save me-2"></i>Update User
                            </button>
                            <a href="view_user_activity.php?id=<?php echo $user_id; ?>" class="btn btn-info me-2">
                                <i class="fas fa-history me-2"></i>View Activity
                            </a>
                            <a href="manage_all_users.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to User List
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="card border-danger mb-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5>Delete User Account</h5>
                    <p class="text-muted mb-0">Once deleted, all of this user's data will be permanently removed. This action cannot be undone.</p>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                        <i class="fas fa-trash-alt me-2"></i>Delete User
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the user account for <strong><?php echo htmlspecialchars($user['name']); ?></strong>?</p>
                <p class="mb-0 text-danger"><strong>Warning:</strong> This action cannot be undone and will delete all associated data.</p>
            </div>
            <div class="modal-footer">
                <form action="delete_user.php" method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle password fields visibility
    document.addEventListener('DOMContentLoaded', function() {
        const changePasswordCheckbox = document.getElementById('change_password');
        const passwordFieldsContainer = document.querySelector('.password-fields');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        changePasswordCheckbox.addEventListener('change', function() {
            if (this.checked) {
                passwordFieldsContainer.style.display = 'block';
                passwordInput.setAttribute('required', '');
                confirmPasswordInput.setAttribute('required', '');
            } else {
                passwordFieldsContainer.style.display = 'none';
                passwordInput.removeAttribute('required');
                confirmPasswordInput.removeAttribute('required');
                
                // Clear password fields when unchecked
                passwordInput.value = '';
                confirmPasswordInput.value = '';
            }
        });    });
</script>

<?php
// Close database connection
$conn->close();

// Include footer
include '../includes/footer.php';
?>
