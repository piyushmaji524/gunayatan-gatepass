<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Handle status change
if (isset($_POST['change_status']) && isset($_POST['user_id']) && isset($_POST['new_status'])) {
    $user_id = (int)$_POST['user_id'];
    $new_status = sanitizeInput($_POST['new_status']);
    
    if (in_array($new_status, ['active', 'inactive', 'pending'])) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        if ($stmt->execute()) {
            // Log the action
            $user_details = "User ID: $user_id, New Status: $new_status";
            logAction($_SESSION['user_id'], 'USER_STATUS_CHANGED', $user_details);
            
            $_SESSION['flash_message'] = "User status successfully updated";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Failed to update user status";
            $_SESSION['flash_type'] = "danger";
        }
    }
}

// Handle role change
if (isset($_POST['change_role']) && isset($_POST['user_id']) && isset($_POST['new_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = sanitizeInput($_POST['new_role']);
    
    if (in_array($new_role, ['superadmin', 'admin', 'security', 'user'])) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $user_id);
        if ($stmt->execute()) {
            // Log the action
            $user_details = "User ID: $user_id, New Role: $new_role";
            logAction($_SESSION['user_id'], 'USER_ROLE_CHANGED', $user_details);
            
            $_SESSION['flash_message'] = "User role successfully updated";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Failed to update user role";
            $_SESSION['flash_type'] = "danger";
        }
    }
}

// Handle delete user
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    
    // Check if it's not the current user
    if ($user_id != $_SESSION['user_id']) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update gatepasses to remove references
            $stmt = $conn->prepare("UPDATE gatepasses SET admin_approved_by = NULL WHERE admin_approved_by = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            $stmt = $conn->prepare("UPDATE gatepasses SET security_approved_by = NULL WHERE security_approved_by = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            $stmt = $conn->prepare("UPDATE gatepasses SET declined_by = NULL WHERE declined_by = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Delete user's logs
            $stmt = $conn->prepare("DELETE FROM logs WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Delete user's gatepasses
            $stmt = $conn->prepare("SELECT id FROM gatepasses WHERE created_by = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $gatepass_id = $row['id'];
                
                // Delete gatepass items
                $stmt2 = $conn->prepare("DELETE FROM gatepass_items WHERE gatepass_id = ?");
                $stmt2->bind_param("i", $gatepass_id);
                $stmt2->execute();
                
                // Delete gatepass
                $stmt3 = $conn->prepare("DELETE FROM gatepasses WHERE id = ?");
                $stmt3->bind_param("i", $gatepass_id);
                $stmt3->execute();
            }
            
            // Finally delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Log the action
            logAction($_SESSION['user_id'], 'USER_DELETED', "User ID: $user_id deleted from system");
            
            $_SESSION['flash_message'] = "User and all associated data successfully deleted";
            $_SESSION['flash_type'] = "success";
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['flash_message'] = "Failed to delete user: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
    } else {
        $_SESSION['flash_message'] = "You cannot delete your own account";
        $_SESSION['flash_type'] = "danger";
    }
}

// Search functionality
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitizeInput($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Prepare base query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = "";

// Add search term if provided
if (!empty($search_term)) {
    $query .= " AND (username LIKE ? OR name LIKE ? OR email LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Add role filter if provided
if (!empty($role_filter)) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

// Add status filter if provided
if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add sorting
$query .= " ORDER BY created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Set page title
$page_title = "Manage All Users";

// Include header
include '../includes/header.php';
?>

<div class="container py-4">    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-users-cog me-2"></i>Manage All Users</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <a href="add_user.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i>Create New User
            </a>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search and Filters</h5>
        </div>
        <div class="card-body">
            <form method="get" class="row">
                <div class="col-md-4 mb-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by username, name or email" 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All Roles</option>
                        <option value="superadmin" <?php echo $role_filter === 'superadmin' ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="security" <?php echo $role_filter === 'security' ? 'selected' : ''; ?>>Security</option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Regular User</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>All Users</h5>
        </div>
        <div class="card-body p-0">
            <?php if ($users->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr class="<?php echo $user['id'] == $_SESSION['user_id'] ? 'table-info' : ''; ?>">
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            switch($user['role']) {
                                                case 'superadmin': echo 'bg-dark'; break;
                                                case 'admin': echo 'bg-danger'; break;
                                                case 'security': echo 'bg-warning text-dark'; break;
                                                case 'user': echo 'bg-info text-dark'; break;
                                                default: echo 'bg-secondary';
                                            }
                                        ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            switch($user['status']) {
                                                case 'active': echo 'bg-success'; break;
                                                case 'inactive': echo 'bg-danger'; break;
                                                case 'pending': echo 'bg-warning text-dark'; break;
                                                default: echo 'bg-secondary';
                                            }
                                        ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDateTime($user['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-edit me-2"></i>Edit
                                                    </a>
                                                </li>                                                <li>
                                                    <a href="view_user_activity.php?id=<?php echo $user['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-history me-2"></i>View Activity
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="reset_user_password.php?id=<?php echo $user['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-key me-2"></i>Reset Password
                                                    </a>
                                                </li>
                                                <?php if ($user['role'] == 'user'): ?>
                                                <li>
                                                    <a href="user_panel_access.php?id=<?php echo $user['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-user-secret me-2"></i>Access User Panel
                                                    </a>
                                                </li>
                                                <?php elseif ($user['role'] == 'admin'): ?>
                                                <li>
                                                    <a href="admin_panel_access.php?id=<?php echo $user['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-user-secret me-2"></i>Access Admin Panel
                                                    </a>
                                                </li>
                                                <?php elseif ($user['role'] == 'security'): ?>
                                                <li>
                                                    <a href="security_panel_access.php?id=<?php echo $user['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-user-secret me-2"></i>Access Security Panel
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li class="dropdown-item">
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to change the user\'s role?');">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="change_role" value="1">
                                                        <div class="input-group input-group-sm">
                                                            <select name="new_role" class="form-select form-select-sm">
                                                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                                <option value="security" <?php echo $user['role'] === 'security' ? 'selected' : ''; ?>>Security</option>
                                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                                <option value="superadmin" <?php echo $user['role'] === 'superadmin' ? 'selected' : ''; ?>>Super Admin</option>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Change Role</button>
                                                        </div>
                                                    </form>
                                                </li>
                                                <li class="dropdown-item">
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to change the user\'s status?');">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="change_status" value="1">
                                                        <div class="input-group input-group-sm">
                                                            <select name="new_status" class="form-select form-select-sm">
                                                                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                                <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                                <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Change Status</button>
                                                        </div>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <li>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This will remove ALL their data and cannot be undone!');">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="delete_user" value="1">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-trash-alt me-2"></i>Delete User
                                                        </button>
                                                    </form>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center p-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h5>No Users Found</h5>
                    <p class="text-muted">Try adjusting your search or filter criteria</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Close database connection
$conn->close();

// Include footer
include '../includes/footer.php';
?>
