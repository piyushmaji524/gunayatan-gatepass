<?php
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = ($page - 1) * $limit;

// Initialize variables for filtering
$action_filter = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Connect to database
$conn = connectDB();

// Build query based on filters
$query = "
    SELECT l.*, u.username, u.name 
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE 1=1
";

$count_query = "SELECT COUNT(*) as total FROM logs l WHERE 1=1";

// Add filter conditions
$params = array();
$types = '';

if (!empty($action_filter)) {
    $query .= " AND l.action = ?";
    $count_query .= " AND l.action = ?";
    $params[] = $action_filter;
    $types .= 's';
}

if (!empty($user_filter)) {
    $query .= " AND l.user_id = ?";
    $count_query .= " AND l.user_id = ?";
    $params[] = $user_filter;
    $types .= 'i';
}

if (!empty($date_from)) {
    $query .= " AND DATE(l.created_at) >= ?";
    $count_query .= " AND DATE(l.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $query .= " AND DATE(l.created_at) <= ?";
    $count_query .= " AND DATE(l.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// Add ordering and limit
$query .= " ORDER BY l.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= 'ii';

// Get log entries
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// Get total count for pagination
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    // Remove the last two parameters (offset and limit) for count query
    array_pop($params);
    array_pop($params);
    $count_types = substr($types, 0, -2);
    if (!empty($count_types)) {
        $count_stmt->bind_param($count_types, ...$params);
    }
}
$count_stmt->execute();
$total_logs = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $limit);

// Get unique action types for filter dropdown
$action_types = $conn->query("SELECT DISTINCT action FROM logs ORDER BY action");

// Get users for filter dropdown
$users = $conn->query("SELECT id, username, name FROM users ORDER BY name");

// Set page title
$page_title = "System Logs";

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-history me-2"></i>System Logs</h1>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Logs</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="action" class="form-label">Action Type</label>
                    <select class="form-select" id="action" name="action">
                        <option value="">All Actions</option>
                        <?php while ($action = $action_types->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                <?php if($action_filter === $action['action']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($action['action']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="user_id" class="form-label">User</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="0">All Users</option>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                <?php if($user_filter === $user['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-6">
                    <label for="limit" class="form-label">Results per page</label>
                    <select class="form-select" id="limit" name="limit">
                        <option value="50" <?php if($limit === 50) echo 'selected'; ?>>50</option>
                        <option value="100" <?php if($limit === 100) echo 'selected'; ?>>100</option>
                        <option value="200" <?php if($limit === 200) echo 'selected'; ?>>200</option>
                        <option value="500" <?php if($limit === 500) echo 'selected'; ?>>500</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="d-grid gap-2 d-md-flex w-100">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter me-2"></i>Apply Filter
                        </button>
                        <a href="system_logs.php" class="btn btn-secondary flex-grow-1">
                            <i class="fas fa-sync me-2"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Logs Table -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i>System Activity Logs</h5>
                <span class="badge bg-primary rounded-pill"><?php echo $total_logs; ?> logs found</span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if ($logs->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo formatDateTime($log['created_at']); ?></td>
                                    <td>
                                        <?php if ($log['user_id']): ?>
                                            <?php echo htmlspecialchars($log['name'] ? $log['name'] : $log['username']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = 'primary';
                                        if (strpos($log['action'], 'LOGIN') !== false) {
                                            $badge_class = 'success';
                                        } elseif (strpos($log['action'], 'LOGOUT') !== false) {
                                            $badge_class = 'secondary';
                                        } elseif (strpos($log['action'], 'APPROVED') !== false) {
                                            $badge_class = 'success';
                                        } elseif (strpos($log['action'], 'DECLINED') !== false) {
                                            $badge_class = 'danger';
                                        } elseif (strpos($log['action'], 'CREATED') !== false) {
                                            $badge_class = 'info';
                                        } elseif (strpos($log['action'], 'UPDATED') !== false) {
                                            $badge_class = 'warning text-dark';
                                        } elseif (strpos($log['action'], 'DELETED') !== false) {
                                            $badge_class = 'danger';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                                    <!--<td><?php echo htmlspecialchars($log['ip_address']); ?></td>-->
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1&limit=<?php echo $limit; ?>&action=<?php echo urlencode($action_filter); ?>&user_id=<?php echo $user_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        First
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page-1); ?>&limit=<?php echo $limit; ?>&action=<?php echo urlencode($action_filter); ?>&user_id=<?php echo $user_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&action=<?php echo urlencode($action_filter); ?>&user_id=<?php echo $user_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page+1); ?>&limit=<?php echo $limit; ?>&action=<?php echo urlencode($action_filter); ?>&user_id=<?php echo $user_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        Next
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&limit=<?php echo $limit; ?>&action=<?php echo urlencode($action_filter); ?>&user_id=<?php echo $user_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        Last
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center p-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5>No logs found</h5>
                    <p class="text-muted">Try adjusting your search criteria</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
