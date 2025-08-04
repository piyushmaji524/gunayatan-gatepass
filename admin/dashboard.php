<?php
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Set page title
$page_title = "Admin Dashboard";

// Connect to database to get stats
$conn = connectDB();

// Get total users count
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = $result->fetch_assoc()['total'];

// Get pending users count
$result = $conn->query("SELECT COUNT(*) as pending FROM users WHERE status = 'pending'");
$pending_users = $result->fetch_assoc()['pending'];

// Get active users count
$result = $conn->query("SELECT COUNT(*) as active FROM users WHERE status = 'active'");
$active_users = $result->fetch_assoc()['active'];

// Get gatepasses counts
$result = $conn->query("SELECT COUNT(*) as total FROM gatepasses");
$total_gatepasses = $result->fetch_assoc()['total'];

// Get pending gatepasses count
$result = $conn->query("SELECT COUNT(*) as pending FROM gatepasses WHERE status = 'pending'");
$pending_gatepasses = $result->fetch_assoc()['pending'];

// Get admin approved count
$result = $conn->query("SELECT COUNT(*) as approved FROM gatepasses WHERE status = 'approved_by_admin'");
$admin_approved_gatepasses = $result->fetch_assoc()['approved'];

// Get fully approved count
$result = $conn->query("SELECT COUNT(*) as approved FROM gatepasses WHERE status = 'approved_by_security'");
$fully_approved_gatepasses = $result->fetch_assoc()['approved'];

// Get declined count
$result = $conn->query("SELECT COUNT(*) as declined FROM gatepasses WHERE status = 'declined'");
$declined_gatepasses = $result->fetch_assoc()['declined'];

// Get recent gatepasses
$stmt = $conn->prepare("
    SELECT g.*, 
           u.name as creator_name,
           admin.name as admin_name, 
           security.name as security_name
    FROM gatepasses g
    LEFT JOIN users u ON g.created_by = u.id
    LEFT JOIN users admin ON g.admin_approved_by = admin.id
    LEFT JOIN users security ON g.security_approved_by = security.id
    ORDER BY g.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_gatepasses = $stmt->get_result();

// Get recent activity logs
$stmt = $conn->prepare("
    SELECT l.*, u.name as user_name, u.role as user_role
    FROM logs l
    JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_logs = $stmt->get_result();

// Include header
require_once '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</h2>
        <p class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! Here's an overview of the system activities.</p>
    </div>
</div>

<!-- Stats Overview -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card h-100">
            <div class="card-body text-center">
                <div class="dashboard-icon text-primary">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h5 class="card-title">Total Gatepasses</h5>
                <h3 class="mb-0"><?php echo $total_gatepasses; ?></h3>
                <p class="card-text mt-2">
                    <span class="badge bg-warning me-1"><?php echo $pending_gatepasses; ?> Pending</span>
                    <span class="badge bg-success"><?php echo $fully_approved_gatepasses; ?> Approved</span>
                </p>
            </div>
            <div class="card-footer bg-transparent">
                <a href="all_gatepasses.php" class="text-decoration-none">View All Gatepasses</a>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card dashboard-card h-100">
            <div class="card-body text-center">
                <div class="dashboard-icon text-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <h5 class="card-title">Pending Approval</h5>
                <h3 class="mb-0"><?php echo $pending_gatepasses; ?></h3>
                <p class="card-text mt-2">
                    <a href="all_gatepasses.php?status=pending" class="btn btn-sm btn-warning">Review Now</a>
                </p>
            </div>
            <div class="card-footer bg-transparent">
                <a href="all_gatepasses.php?status=pending" class="text-decoration-none">View Pending</a>
            </div>
        </div>
    </div>


<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Gatepasses</h5>
                    <a href="all_gatepasses.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($recent_gatepasses->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Gatepass #</th>
                                <th>From → To</th>
                                <th>Created By</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($gatepass = $recent_gatepasses->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($gatepass['gatepass_number']); ?></td>
                                <td><?php echo htmlspecialchars($gatepass['from_location']); ?> → <?php echo htmlspecialchars($gatepass['to_location']); ?></td>                                <td><?php echo htmlspecialchars($gatepass['creator_name']); ?></td>
                                <td><?php echo formatDateTime($gatepass['created_at'], 'd M Y'); ?></td>
                                <td>
                                    <?php 
                                    switch ($gatepass['status']) {
                                        case 'pending':
                                            echo '<span class="badge bg-warning">Pending</span>';
                                            break;
                                        case 'approved_by_admin':
                                            echo '<span class="badge bg-info">Admin Approved</span>';
                                            break;
                                        case 'approved_by_security':
                                            echo '<span class="badge bg-success">Fully Approved</span>';
                                            break;
                                        case 'declined':
                                            echo '<span class="badge bg-danger">Declined</span>';
                                            break;
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($gatepass['status'] == 'pending'): ?>
                                        <a href="approve_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-success">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted text-center">No recent gatepasses found</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="all_gatepasses.php?status=pending" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-clipboard-check me-2"></i>Review Pending Gatepasses</span>
                        <span class="badge bg-warning rounded-pill"><?php echo $pending_gatepasses; ?></span>
                    </a>
                    <a href="manage_users.php?status=pending" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-user-check me-2"></i>Approve New Users</span>
                        <span class="badge bg-warning rounded-pill"><?php echo $pending_users; ?></span>
                    </a>
                    <a href="reports.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-line me-2"></i>Generate Reports
                    </a>
                    <a href="logs.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-history me-2"></i>View System Logs
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close connection
$conn->close();

// Include footer
require_once '../includes/footer.php';
?>