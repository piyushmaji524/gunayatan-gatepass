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
$page_title = "User Activity: " . $user['name'];

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Prepare SQL for counting total logs
$count_sql = "SELECT COUNT(*) AS total FROM logs WHERE user_id = ?";
$params = [$user_id];
$types = "i";

// Prepare SQL for getting logs
$logs_sql = "SELECT * FROM logs WHERE user_id = ?";

// Apply date range filters if set
if (!empty($start_date) && !empty($end_date)) {
    $count_sql .= " AND created_at BETWEEN ? AND ?";
    $logs_sql .= " AND created_at BETWEEN ? AND ?";
    $params[] = $start_date . " 00:00:00";
    $params[] = $end_date . " 23:59:59";
    $types .= "ss";
}

// Finalize logs SQL with sorting and pagination
$logs_sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Get total count of logs
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$total_logs = $result->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $limit);

// Get logs with pagination
$stmt = $conn->prepare($logs_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result();

// Get user statistics
// Count total gatepasses created by this user
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM gatepasses WHERE created_by = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_gatepasses = $result->fetch_assoc()['total'];

// Count gatepasses by status
$stmt = $conn->prepare("SELECT status, COUNT(*) AS count FROM gatepasses WHERE created_by = ? GROUP BY status");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$gatepass_status_result = $stmt->get_result();
$gatepass_status = [];
while ($row = $gatepass_status_result->fetch_assoc()) {
    $gatepass_status[$row['status']] = $row['count'];
}

// Count total logins
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM logs WHERE user_id = ? AND action LIKE '%logged in%'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_logins = $result->fetch_assoc()['total'];

// Get last login time
$stmt = $conn->prepare("SELECT created_at FROM logs WHERE user_id = ? AND action LIKE '%logged in%' ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$last_login = $result->num_rows > 0 ? $result->fetch_assoc()['created_at'] : 'Never';

// Include header
require_once '../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="manage_all_users.php">Manage Users</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($user['name']); ?>'s Activity</li>
        </ol>
    </nav>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user-clock me-2"></i>User Activity: <?php echo htmlspecialchars($user['name']); ?></h5>
                    <div>
                        <a href="edit_user.php?id=<?php echo $user_id; ?>" class="btn btn-sm btn-light me-1">
                            <i class="fas fa-edit me-1"></i>Edit User
                        </a>
                        <a href="impersonate_user.php?id=<?php echo $user_id; ?>" class="btn btn-sm btn-warning me-1" onclick="return confirm('You will be logged in as this user. Continue?');">
                            <i class="fas fa-user-secret me-1"></i>Impersonate
                        </a>
                        <a href="export_logs.php?user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-download me-1"></i>Export Logs
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <!-- User Info -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title border-bottom pb-2">User Information</h5>
                                    <table class="table table-sm">
                                        <tr>
                                            <th width="30%">Username:</th>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Name:</th>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Role:</th>
                                            <td>
                                                <?php
                                                switch ($user['role']) {
                                                    case 'superadmin':
                                                        echo '<span class="badge bg-dark">Super Admin</span>';
                                                        break;
                                                    case 'admin':
                                                        echo '<span class="badge bg-danger">Admin</span>';
                                                        break;
                                                    case 'security':
                                                        echo '<span class="badge bg-warning text-dark">Security</span>';
                                                        break;
                                                    case 'user':
                                                        echo '<span class="badge bg-info">User</span>';
                                                        break;
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Status:</th>
                                            <td>
                                                <?php
                                                switch ($user['status']) {
                                                    case 'active':
                                                        echo '<span class="badge bg-success">Active</span>';
                                                        break;
                                                    case 'pending':
                                                        echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                        break;
                                                    case 'inactive':
                                                        echo '<span class="badge bg-secondary">Inactive</span>';
                                                        break;
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Registered On:</th>
                                            <td><?php echo formatDateTime($user['created_at']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Last Login:</th>
                                            <td><?php echo $last_login != 'Never' ? formatDateTime($last_login) : 'Never'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- User Stats -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title border-bottom pb-2">Activity Statistics</h5>
                                    <div class="row mb-3">
                                        <div class="col-md-6 text-center mb-3">
                                            <h6 class="text-muted">Total Logins</h6>
                                            <h2 class="mb-0 text-primary"><?php echo $total_logins; ?></h2>
                                        </div>
                                        <div class="col-md-6 text-center mb-3">
                                            <h6 class="text-muted">Total Gatepasses</h6>
                                            <h2 class="mb-0 text-success"><?php echo $total_gatepasses; ?></h2>
                                        </div>
                                    </div>

                                    <h6>Gatepass Status Distribution</h6>
                                    <div class="d-flex justify-content-center align-items-center mb-3">
                                        <div class="progress w-100" style="height: 30px;">
                                            <?php
                                            $pending_count = isset($gatepass_status['pending']) ? $gatepass_status['pending'] : 0;
                                            $admin_approved_count = isset($gatepass_status['approved_by_admin']) ? $gatepass_status['approved_by_admin'] : 0;
                                            $security_approved_count = isset($gatepass_status['approved_by_security']) ? $gatepass_status['approved_by_security'] : 0;
                                            $declined_count = isset($gatepass_status['declined']) ? $gatepass_status['declined'] : 0;

                                            $total_non_zero = max(1, $total_gatepasses);  // Avoid division by zero

                                            $pending_percentage = ($pending_count / $total_non_zero) * 100;
                                            $admin_approved_percentage = ($admin_approved_count / $total_non_zero) * 100;
                                            $security_approved_percentage = ($security_approved_count / $total_non_zero) * 100;
                                            $declined_percentage = ($declined_count / $total_non_zero) * 100;
                                            ?>

                                            <?php if ($pending_count > 0): ?>
                                                <div class="progress-bar bg-warning" style="width: <?php echo $pending_percentage; ?>%" title="Pending: <?php echo $pending_count; ?>">
                                                    <?php echo $pending_count; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($admin_approved_count > 0): ?>
                                                <div class="progress-bar bg-info" style="width: <?php echo $admin_approved_percentage; ?>%" title="Admin Approved: <?php echo $admin_approved_count; ?>">
                                                    <?php echo $admin_approved_count; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($security_approved_count > 0): ?>
                                                <div class="progress-bar bg-success" style="width: <?php echo $security_approved_percentage; ?>%" title="Verified: <?php echo $security_approved_count; ?>">
                                                    <?php echo $security_approved_count; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($declined_count > 0): ?>
                                                <div class="progress-bar bg-danger" style="width: <?php echo $declined_percentage; ?>%" title="Declined: <?php echo $declined_count; ?>">
                                                    <?php echo $declined_count; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="row text-center small">
                                        <div class="col-md-3">
                                            <span class="badge bg-warning text-dark">Pending</span>
                                            <?php echo $pending_count; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="badge bg-info">Admin Approved</span>
                                            <?php echo $admin_approved_count; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="badge bg-success">Verified</span>
                                            <?php echo $security_approved_count; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="badge bg-danger">Declined</span>
                                            <?php echo $declined_count; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Log Filter -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <input type="hidden" name="id" value="<?php echo $user_id; ?>">
                                <div class="col-md-4">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-filter me-1"></i>Filter
                                    </button>
                                    <a href="view_user_activity.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">
                                        <i class="fas fa-sync-alt me-1"></i>Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Activity Logs -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Activity Log</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($logs->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Action</th>
                                                <th>IP Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($log = $logs->fetch_assoc()): ?>
                                                <tr>
                                                    <td width="20%"><?php echo formatDateTime($log['created_at']); ?></td>
                                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                                    <td width="15%"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <div class="d-flex justify-content-center align-items-center pt-3 pb-2">
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?id=<?php echo $user_id; ?>&page=1<?php echo !empty($start_date) ? '&start_date='.$start_date : ''; ?><?php echo !empty($end_date) ? '&end_date='.$end_date : ''; ?>" aria-label="First">
                                                            <i class="fas fa-angle-double-left"></i>
                                                        </a>
                                                    </li>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?id=<?php echo $user_id; ?>&page=<?php echo $page - 1; ?><?php echo !empty($start_date) ? '&start_date='.$start_date : ''; ?><?php echo !empty($end_date) ? '&end_date='.$end_date : ''; ?>" aria-label="Previous">
                                                            <i class="fas fa-angle-left"></i>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>

                                                <?php
                                                $start_page = max(1, $page - 2);
                                                $end_page = min($total_pages, $page + 2);

                                                for ($i = $start_page; $i <= $end_page; $i++):
                                                ?>
                                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?id=<?php echo $user_id; ?>&page=<?php echo $i; ?><?php echo !empty($start_date) ? '&start_date='.$start_date : ''; ?><?php echo !empty($end_date) ? '&end_date='.$end_date : ''; ?>">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>

                                                <?php if ($page < $total_pages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?id=<?php echo $user_id; ?>&page=<?php echo $page + 1; ?><?php echo !empty($start_date) ? '&start_date='.$start_date : ''; ?><?php echo !empty($end_date) ? '&end_date='.$end_date : ''; ?>" aria-label="Next">
                                                            <i class="fas fa-angle-right"></i>
                                                        </a>
                                                    </li>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?id=<?php echo $user_id; ?>&page=<?php echo $total_pages; ?><?php echo !empty($start_date) ? '&start_date='.$start_date : ''; ?><?php echo !empty($end_date) ? '&end_date='.$end_date : ''; ?>" aria-label="Last">
                                                            <i class="fas fa-angle-double-right"></i>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-info-circle text-info fa-2x mb-3"></i>
                                    <p>No activity logs found for this user<?php echo (!empty($start_date) && !empty($end_date)) ? ' in the selected date range.' : '.'; ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <a href="manage_all_users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to All Users
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
