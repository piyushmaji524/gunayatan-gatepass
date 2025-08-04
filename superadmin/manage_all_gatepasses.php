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
if (isset($_POST['change_status']) && isset($_POST['gatepass_id']) && isset($_POST['new_status'])) {
    $gatepass_id = (int)$_POST['gatepass_id'];
    $new_status = sanitizeInput($_POST['new_status']);
    
    if (in_array($new_status, ['pending', 'approved_by_admin', 'approved_by_security', 'declined'])) {
        $stmt = $conn->prepare("UPDATE gatepasses SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $gatepass_id);
        
        if ($stmt->execute()) {
            // Update additional fields based on status
            if ($new_status == 'approved_by_admin') {
                $stmt = $conn->prepare("UPDATE gatepasses SET admin_approved_by = ?, admin_approved_at = NOW() WHERE id = ?");
                $stmt->bind_param("ii", $_SESSION['user_id'], $gatepass_id);
                $stmt->execute();
            } elseif ($new_status == 'approved_by_security') {
                $stmt = $conn->prepare("UPDATE gatepasses SET security_approved_by = ?, security_approved_at = NOW() WHERE id = ?");
                $stmt->bind_param("ii", $_SESSION['user_id'], $gatepass_id);
                $stmt->execute();
            } elseif ($new_status == 'declined') {
                $decline_reason = "Status change by Super Admin";
                $stmt = $conn->prepare("UPDATE gatepasses SET declined_by = ?, declined_at = NOW(), decline_reason = ? WHERE id = ?");
                $stmt->bind_param("isi", $_SESSION['user_id'], $decline_reason, $gatepass_id);
                $stmt->execute();
            }
            
            // Log the action
            $action_details = "Changed gatepass ID: $gatepass_id to status: $new_status";
            logAction($_SESSION['user_id'], 'GATEPASS_STATUS_CHANGED', $action_details);
            
            $_SESSION['flash_message'] = "Gatepass status successfully updated";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Failed to update gatepass status";
            $_SESSION['flash_type'] = "danger";
        }
    }
}

// Handle delete gatepass
if (isset($_POST['delete_gatepass']) && isset($_POST['gatepass_id'])) {
    $gatepass_id = (int)$_POST['gatepass_id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete gatepass items first
        $stmt = $conn->prepare("DELETE FROM gatepass_items WHERE gatepass_id = ?");
        $stmt->bind_param("i", $gatepass_id);
        $stmt->execute();
        
        // Delete the gatepass
        $stmt = $conn->prepare("DELETE FROM gatepasses WHERE id = ?");
        $stmt->bind_param("i", $gatepass_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Log the action
        logAction($_SESSION['user_id'], 'GATEPASS_DELETED', "Deleted gatepass ID: $gatepass_id");
        
        $_SESSION['flash_message'] = "Gatepass successfully deleted";
        $_SESSION['flash_type'] = "success";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['flash_message'] = "Failed to delete gatepass: " . $e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
}

// Search and filter functionality
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$from_date = isset($_GET['from_date']) ? sanitizeInput($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? sanitizeInput($_GET['to_date']) : '';
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Prepare base query
$query = "
    SELECT g.*, 
           creator.name as creator_name,
           admin.name as admin_name,
           security.name as security_name,
           decliner.name as decliner_name
    FROM gatepasses g
    LEFT JOIN users creator ON g.created_by = creator.id
    LEFT JOIN users admin ON g.admin_approved_by = admin.id
    LEFT JOIN users security ON g.security_approved_by = security.id
    LEFT JOIN users decliner ON g.declined_by = decliner.id
    WHERE 1=1
";
$params = [];
$types = "";

// Add search term if provided
if (!empty($search_term)) {
    $query .= " AND (g.gatepass_number LIKE ? OR g.from_location LIKE ? OR g.to_location LIKE ? OR g.material_type LIKE ? OR g.purpose LIKE ? OR creator.name LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssssss";
}

// Add status filter if provided
if (!empty($status_filter)) {
    $query .= " AND g.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add date range filter if provided
if (!empty($from_date)) {
    $query .= " AND g.created_at >= ?";
    $params[] = $from_date . " 00:00:00";
    $types .= "s";
}

if (!empty($to_date)) {
    $query .= " AND g.created_at <= ?";
    $params[] = $to_date . " 23:59:59";
    $types .= "s";
}

// Add user filter if provided
if ($user_filter > 0) {
    $query .= " AND g.created_by = ?";
    $params[] = $user_filter;
    $types .= "i";
}

// Add sorting
$query .= " ORDER BY g.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$gatepasses = $stmt->get_result();

// Get all users for the user filter dropdown
$users_stmt = $conn->prepare("SELECT id, name, username FROM users ORDER BY name ASC");
$users_stmt->execute();
$users_result = $users_stmt->get_result();
$all_users = [];
while ($user = $users_result->fetch_assoc()) {
    $all_users[$user['id']] = $user['name'] . ' (' . $user['username'] . ')';
}

// Set page title
$page_title = "Manage All Gatepasses";

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-clipboard-list me-2"></i>Manage All Gatepasses</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <a href="create_gatepass.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create New Gatepass
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
                           placeholder="Search by gatepass number, location, or purpose" 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved_by_admin" <?php echo $status_filter === 'approved_by_admin' ? 'selected' : ''; ?>>Approved by Admin</option>
                        <option value="approved_by_security" <?php echo $status_filter === 'approved_by_security' ? 'selected' : ''; ?>>Approved by Security</option>
                        <option value="declined" <?php echo $status_filter === 'declined' ? 'selected' : ''; ?>>Declined</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="user_id" class="form-label">Created By</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($all_users as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $user_filter === $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="from_date" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="from_date" name="from_date" 
                           value="<?php echo htmlspecialchars($from_date); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="to_date" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="to_date" name="to_date" 
                           value="<?php echo htmlspecialchars($to_date); ?>">
                </div>
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2 flex-grow-1">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                    <a href="manage_all_gatepasses.php" class="btn btn-outline-secondary flex-grow-1">
                        <i class="fas fa-sync-alt me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Gatepasses Table -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>All Gatepasses</h5>
        </div>
        <div class="card-body p-0">
            <?php if ($gatepasses->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Gatepass #</th>
                                <th>Created By</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($gatepass = $gatepasses->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $gatepass['id']; ?></td>
                                    <td><?php echo htmlspecialchars($gatepass['gatepass_number']); ?></td>
                                    <td><?php echo htmlspecialchars($gatepass['creator_name']); ?></td>
                                    <td><?php echo htmlspecialchars($gatepass['from_location']); ?></td>
                                    <td><?php echo htmlspecialchars($gatepass['to_location']); ?></td>
                                    <td><?php echo formatDateTime($gatepass['created_at']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            switch($gatepass['status']) {
                                                case 'pending': echo 'bg-warning text-dark'; break;
                                                case 'approved_by_admin': echo 'bg-primary'; break;
                                                case 'approved_by_security': echo 'bg-success'; break;
                                                case 'declined': echo 'bg-danger'; break;
                                                default: echo 'bg-secondary';
                                            }
                                        ?>">
                                            <?php 
                                            switch($gatepass['status']) {
                                                case 'pending': echo 'Pending'; break;
                                                case 'approved_by_admin': echo 'Admin Approved'; break;
                                                case 'approved_by_security': echo 'Verified'; break;
                                                case 'declined': echo 'Declined'; break;
                                                default: echo ucfirst($gatepass['status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a href="view_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-eye me-2"></i>View Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="edit_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-edit me-2"></i>Edit Gatepass
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="download_pdf.php?id=<?php echo $gatepass['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-download me-2"></i>Download PDF
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li class="dropdown-item">
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to change the gatepass status?');">
                                                        <input type="hidden" name="gatepass_id" value="<?php echo $gatepass['id']; ?>">
                                                        <input type="hidden" name="change_status" value="1">
                                                        <div class="input-group input-group-sm">
                                                            <select name="new_status" class="form-select form-select-sm">
                                                                <option value="pending" <?php echo $gatepass['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="approved_by_admin" <?php echo $gatepass['status'] === 'approved_by_admin' ? 'selected' : ''; ?>>Admin Approved</option>
                                                                <option value="approved_by_security" <?php echo $gatepass['status'] === 'approved_by_security' ? 'selected' : ''; ?>>Verified</option>
                                                                <option value="declined" <?php echo $gatepass['status'] === 'declined' ? 'selected' : ''; ?>>Declined</option>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Change Status</button>
                                                        </div>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this gatepass? This action cannot be undone!');">
                                                        <input type="hidden" name="gatepass_id" value="<?php echo $gatepass['id']; ?>">
                                                        <input type="hidden" name="delete_gatepass" value="1">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-trash-alt me-2"></i>Delete Gatepass
                                                        </button>
                                                    </form>
                                                </li>
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
                    <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                    <h5>No Gatepasses Found</h5>
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
