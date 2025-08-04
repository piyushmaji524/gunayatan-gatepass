<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Set page title
$page_title = "Add New User";

// Initialize variables
$username = '';
$name = '';
$email = '';
$role = '';
$status = '';
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = $_POST['role'];
    $status = $_POST['status'];

    // Validate input
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    if (empty($name)) {
        $errors[] = "Full name is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    if (empty($role)) {
        $errors[] = "Role is required";
    }
    if (empty($status)) {
        $errors[] = "Status is required";
    }

    // Check if username or email already exists
    if (empty($errors)) {
        $conn = connectDB();
        
        // Check username
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Username already exists";
        }
        
        // Check email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists";
        }
    }

    // Create user if no errors
    if (empty($errors)) {
        $conn = connectDB();
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user into database
        $stmt = $conn->prepare("INSERT INTO users (username, password, name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $hashed_password, $name, $email, $role, $status);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            // Log the action
            logAction($_SESSION['user_id'], "Created new user: " . $name . " (ID: " . $user_id . ") with role: " . $role);
            
            $success = true;
            
            // Clear form data on success
            $username = '';
            $name = '';
            $email = '';
            $role = '';
            $status = '';
        } else {
            $errors[] = "Error creating user: " . $conn->error;
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
            <li class="breadcrumb-item active" aria-current="page">Add User</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>User created successfully!
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
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="" disabled <?php echo empty($role) ? 'selected' : ''; ?>>Select Role</option>
                                    <option value="superadmin" <?php echo $role === 'superadmin' ? 'selected' : ''; ?>>Super Admin</option>
                                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="security" <?php echo $role === 'security' ? 'selected' : ''; ?>>Security</option>
                                    <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>User</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">Password must be at least 6 characters long.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="" disabled <?php echo empty($status) ? 'selected' : ''; ?>>Select Status</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex mt-4">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-save me-2"></i>Create User
                            </button>
                            <a href="manage_all_users.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to User List
                            </a>                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close database connection
if (isset($conn) && $conn) {
    $conn->close();
}

// Include footer
include '../includes/footer.php';
?>
