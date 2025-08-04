<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Search and filter functionality
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action_filter = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$from_date = isset($_GET['from_date']) ? sanitizeInput($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? sanitizeInput($_GET['to_date']) : '';

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 50;
$offset = ($page - 1) * $records_per_page;

// Prepare base query for count
$count_query = "
    SELECT COUNT(*) as total
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE 1=1
";

// Prepare base query for data
$query = "
    SELECT l.*, 
           u.name as user_name,
           u.username as username,
           u.role as user_role
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE 1=1
";

$params = [];
$types = "";

// Add search term if provided
if (!empty($search_term)) {
    $search_condition = " AND (l.action LIKE ? OR l.details LIKE ? OR u.name LIKE ? OR u.username LIKE ?)";
    $count_query .= $search_condition;
    $query .= $search_condition;
    
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Add user filter if provided
if ($user_filter > 0) {
    $user_condition = " AND l.user_id = ?";
    $count_query .= $user_condition;
    $query .= $user_condition;
    
    $params[] = $user_filter;
    $types .= "i";
}

// Add action filter if provided
if (!empty($action_filter)) {
    $action_condition = " AND l.action = ?";
    $count_query .= $action_condition;
    $query .= $action_condition;
    
    $params[] = $action_filter;
    $types .= "s";
}

// Add date range filter if provided
if (!empty($from_date)) {
    $from_condition = " AND l.created_at >= ?";
    $count_query .= $from_condition;
    $query .= $from_condition;
    
    $params[] = $from_date . " 00:00:00";
    $types .= "s";
}

if (!empty($to_date)) {
    $to_condition = " AND l.created_at <= ?";
    $count_query .= $to_condition;
    $query .= $to_condition;
    
    $params[] = $to_date . " 23:59:59";
    $types .= "s";
}

// Add sorting and pagination
$query .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Get total records count
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    // Clone the params array without pagination parameters
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Prepare and execute the main query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// Get all users for the filter dropdown
$users_stmt = $conn->prepare("SELECT id, name, username FROM users ORDER BY name ASC");
$users_stmt->execute();
$users_result = $users_stmt->get_result();
$all_users = [];
while ($user = $users_result->fetch_assoc()) {
    $all_users[$user['id']] = $user['name'] . ' (' . $user['username'] . ')';
}

// Get all unique action types for the filter dropdown
$actions_stmt = $conn->prepare("SELECT DISTINCT action FROM logs ORDER BY action ASC");
$actions_stmt->execute();
$actions_result = $actions_stmt->get_result();
$all_actions = [];
while ($action = $actions_result->fetch_assoc()) {
    $all_actions[] = $action['action'];
}

// Set page title
$page_title = "System Logs";

// Include header
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-history me-2"></i>System Logs</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <a href="export_logs.php" class="btn btn-success">
                <i class="fas fa-file-export me-2"></i>Export Logs
            </a>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Search and Filters</h5>
        </div>
        <div class="card-body">
            <form method="get" class="row">
                <div class="col-md-3 mb-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search in actions, details, or user" 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="user_id" class="form-label">User</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($all_users as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $user_filter === $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="action" class="form-label">Action Type</label>
                    <select class="form-select" id="action" name="action">
                        <option value="">All Actions</option>
                        <?php foreach ($all_actions as $action): ?>
                            <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($action); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="from_date" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="from_date" name="from_date" 
                           value="<?php echo htmlspecialchars($from_date); ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="to_date" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="to_date" name="to_date" 
                           value="<?php echo htmlspecialchars($to_date); ?>">
                </div>
                <div class="col-md-12 mb-3 d-flex justify-content-end">
                    <a href="system_logs.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-sync-alt me-2"></i>Reset Filters
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>System Activity Logs</h5>
            <span class="badge bg-light text-dark">
                <?php echo number_format($total_records); ?> log entries found
            </span>
        </div>
        <div class="card-body p-0">
            <?php if ($logs->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td><?php echo formatDateTime($log['created_at']); ?></td>
                                    <td>
                                        <?php if ($log['user_id']): ?>
                                            <a href="view_user_activity.php?id=<?php echo $log['user_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($log['user_name'] ?? 'Unknown'); ?>
                                                <small class="text-muted">(<?php echo htmlspecialchars($log['username'] ?? ''); ?>)</small>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            switch($log['user_role']) {
                                                case 'superadmin': echo 'bg-dark'; break;
                                                case 'admin': echo 'bg-danger'; break;
                                                case 'security': echo 'bg-warning text-dark'; break;
                                                case 'user': echo 'bg-info text-dark'; break;
                                                default: echo 'bg-secondary';
                                            }
                                        ?>">
                                            <?php echo ucfirst($log['user_role'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            if (strpos($log['action'], 'LOGIN') !== false) echo 'bg-success';
                                            elseif (strpos($log['action'], 'LOGOUT') !== false) echo 'bg-secondary';
                                            elseif (strpos($log['action'], 'CREATED') !== false) echo 'bg-primary';
                                            elseif (strpos($log['action'], 'UPDATED') !== false) echo 'bg-info text-dark';
                                            elseif (strpos($log['action'], 'DELETED') !== false) echo 'bg-danger';
                                            elseif (strpos($log['action'], 'APPROVED') !== false || strpos($log['action'], 'VERIFIED') !== false) echo 'bg-success';
                                            elseif (strpos($log['action'], 'DECLINED') !== false) echo 'bg-danger';
                                            else echo 'bg-secondary';
                                        ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($log['details']); ?>">
                                        <?php echo htmlspecialchars($log['details']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Logs pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=1<?php echo !empty($_GET) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>">First</a>
                                </li>
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>">Previous</a>
                                </li>
                                
                                <?php 
                                $start_page = max(1, $page - 3);
                                $end_page = min($total_pages, $page + 3);
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>">Next</a>
                                </li>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>">Last</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center p-5">
                    <i class="fas fa-history fa-4x text-muted mb-3"></i>
                    <h5>No Logs Found</h5>
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
