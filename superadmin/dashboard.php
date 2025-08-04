<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Set page title
$page_title = "Super Admin Dashboard";

// Connect to database to get stats
$conn = connectDB();

// Get users statistics
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
$admin_count = $result->fetch_assoc()['admin_count'];

$result = $conn->query("SELECT COUNT(*) as security_count FROM users WHERE role = 'security'");
$security_count = $result->fetch_assoc()['security_count'];

$result = $conn->query("SELECT COUNT(*) as user_count FROM users WHERE role = 'user'");
$user_count = $result->fetch_assoc()['user_count'];

// Get pending users count
$result = $conn->query("SELECT COUNT(*) as pending FROM users WHERE status = 'pending'");
$pending_users = $result->fetch_assoc()['pending'];

// Get active users count
$result = $conn->query("SELECT COUNT(*) as active FROM users WHERE status = 'active'");
$active_users = $result->fetch_assoc()['active'];

// Get gatepasses statistics
$result = $conn->query("SELECT COUNT(*) as total FROM gatepasses");
$total_gatepasses = $result->fetch_assoc()['total'];

// Get pending gatepasses count
$result = $conn->query("SELECT COUNT(*) as pending FROM gatepasses WHERE status = 'pending'");
$pending_gatepasses = $result->fetch_assoc()['pending'];

// Get approved gatepasses count
$result = $conn->query("SELECT COUNT(*) as approved FROM gatepasses WHERE status = 'approved_by_admin'");
$admin_approved_gatepasses = $result->fetch_assoc()['approved'];

// Get verified gatepasses count
$result = $conn->query("SELECT COUNT(*) as verified FROM gatepasses WHERE status = 'approved_by_security'");
$security_approved_gatepasses = $result->fetch_assoc()['verified'];

// Get declined gatepasses count
$result = $conn->query("SELECT COUNT(*) as declined FROM gatepasses WHERE status = 'declined'");
$declined_gatepasses = $result->fetch_assoc()['declined'];

// Get recent gatepasses (5 most recent)
$stmt = $conn->prepare("
    SELECT g.*, 
           u.name as creator_name,
           admin.name as admin_name,
           security.name as security_name
    FROM gatepasses g
    JOIN users u ON g.created_by = u.id
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

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1><i class="fas fa-crown me-2 text-warning"></i>Super Admin Dashboard</h1>
            <p class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! You have access to all system functions and data.</p>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i> As Super Admin, you have complete control over the system. Please use your powers responsibly.
            </div>
        </div>
    </div>

    <!-- Access Control Section -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Role-Based Access</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Super Admin Panel</h5>
                            <p class="card-text">You are here</p>
                            <div class="d-grid">
                                <button class="btn btn-dark disabled">Current</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Admin Panel</h5>
                            <p class="card-text">Manage approvals & users</p>
                            <div class="d-grid">
                                <a href="../admin/dashboard.php" class="btn btn-danger">Access</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Security Panel</h5>
                            <p class="card-text">Verify gatepasses</p>
                            <div class="d-grid">
                                <a href="../security/dashboard.php" class="btn btn-warning">Access</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">User Panel</h5>
                            <p class="card-text">Create requests</p>
                            <div class="d-grid">
                                <a href="../user/dashboard.php" class="btn btn-info">Access</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Overview Stats -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-chart-line me-2"></i>System Overview</h2>
        </div>

        <!-- User Stats Section -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>User Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3 text-center">
                            <h5 class="mb-1">Total</h5>
                            <h2 class="mb-0"><?php echo $total_users; ?></h2>
                        </div>
                        <div class="col-md-3 text-center">
                            <h5 class="mb-1">Admin</h5>
                            <h2 class="mb-0"><?php echo $admin_count; ?></h2>
                        </div>
                        <div class="col-md-3 text-center">
                            <h5 class="mb-1">Security</h5>
                            <h2 class="mb-0"><?php echo $security_count; ?></h2>
                        </div>
                        <div class="col-md-3 text-center">
                            <h5 class="mb-1">Users</h5>
                            <h2 class="mb-0"><?php echo $user_count; ?></h2>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center align-items-center mb-3">
                        <div class="progress w-75">
                            <div class="progress-bar bg-success" style="width: <?php echo ($active_users/$total_users) * 100; ?>%">
                                Active: <?php echo $active_users; ?>
                            </div>
                            <div class="progress-bar bg-warning" style="width: <?php echo ($pending_users/$total_users) * 100; ?>%">
                                Pending: <?php echo $pending_users; ?>
                            </div>
                            <div class="progress-bar bg-secondary" style="width: <?php echo (($total_users-$active_users-$pending_users)/$total_users) * 100; ?>%">
                                Inactive: <?php echo $total_users-$active_users-$pending_users; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <a href="manage_all_users.php" class="btn btn-primary">
                            <i class="fas fa-user-cog me-2"></i>Manage All Users
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gatepass Stats Section -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Gatepass Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3 text-center">
                            <h5 class="mb-1">Total</h5>
                            <h2 class="mb-0"><?php echo $total_gatepasses; ?></h2>
                        </div>
                        <div class="col-md-3 text-center">
                            <h5 class="mb-1">Pending</h5>
                            <h2 class="mb-0"><?php echo $pending_gatepasses; ?></h2>
                        </div>
                        <div class="col-md-3 text-center">
                            <h5 class="mb-1">Approved</h5>
                            <h2 class="mb-0"><?php echo $admin_approved_gatepasses; ?></h2>
                        </div>
                        <div class="col-md-3 text-center">
                            <h5 class="mb-1">Verified</h5>
                            <h2 class="mb-0"><?php echo $security_approved_gatepasses; ?></h2>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center align-items-center mb-3">
                        <div class="progress w-75">
                            <div class="progress-bar bg-warning" style="width: <?php echo ($pending_gatepasses/$total_gatepasses) * 100; ?>%">
                                Pending: <?php echo $pending_gatepasses; ?>
                            </div>
                            <div class="progress-bar bg-info" style="width: <?php echo ($admin_approved_gatepasses/$total_gatepasses) * 100; ?>%">
                                Admin Approved: <?php echo $admin_approved_gatepasses; ?>
                            </div>
                            <div class="progress-bar bg-success" style="width: <?php echo ($security_approved_gatepasses/$total_gatepasses) * 100; ?>%">
                                Verified: <?php echo $security_approved_gatepasses; ?>
                            </div>
                            <div class="progress-bar bg-danger" style="width: <?php echo ($declined_gatepasses/$total_gatepasses) * 100; ?>%">
                                Declined: <?php echo $declined_gatepasses; ?>
                            </div>
                        </div>
                    </div>                    <div class="d-grid">
                        <a href="manage_all_gatepasses.php" class="btn btn-success">
                            <i class="fas fa-search me-2"></i>Manage All Gatepasses
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- System Management Section -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-tools me-2"></i>System Management</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt fa-3x mb-3 text-primary"></i>
                                    <h5 class="card-title">Advanced Reports</h5>
                                    <p class="card-text">Generate comprehensive reports</p>
                                    <div class="d-grid">
                                        <a href="generate_reports.php" class="btn btn-primary btn-sm">Access</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-history fa-3x mb-3 text-info"></i>
                                    <h5 class="card-title">System Logs</h5>
                                    <p class="card-text">View complete activity logs</p>
                                    <div class="d-grid">
                                        <a href="system_logs.php" class="btn btn-info btn-sm">Access</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-cogs fa-3x mb-3 text-secondary"></i>
                                    <h5 class="card-title">System Settings</h5>
                                    <p class="card-text">Configure application settings</p>
                                    <div class="d-grid">
                                        <a href="system_settings.php" class="btn btn-secondary btn-sm">Access</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-database fa-3x mb-3 text-danger"></i>
                                    <h5 class="card-title">Database Management</h5>
                                    <p class="card-text">Backup and optimize database</p>
                                    <div class="d-grid">
                                        <a href="database_management.php" class="btn btn-danger btn-sm">Access</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-envelope fa-3x mb-3 text-success"></i>
                                    <h5 class="card-title">Email Settings</h5>
                                    <p class="card-text">Configure email notifications</p>
                                    <div class="d-grid">
                                        <a href="email_settings.php" class="btn btn-success btn-sm">Access</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-lock fa-3x mb-3 text-warning"></i>
                                    <h5 class="card-title">Security Settings</h5>
                                    <p class="card-text">Manage security configurations</p>
                                    <div class="d-grid">
                                        <a href="security_settings.php" class="btn btn-warning btn-sm">Access</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">User Management</h6>
                            <div class="d-grid gap-2">
                                <a href="add_user.php" class="btn btn-danger">
                                    <i class="fas fa-user-plus me-2"></i>Create New User
                                </a>
                                <a href="manage_all_users.php?status=pending" class="btn btn-warning">
                                    <i class="fas fa-user-clock me-2"></i>Pending User Approvals
                                </a>
                                <a href="manage_all_users.php" class="btn btn-primary">
                                    <i class="fas fa-users-cog me-2"></i>Manage All Users
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="mb-3">Gatepass Management</h6>
                            <div class="d-grid gap-2">
                                <a href="create_gatepass.php" class="btn btn-success">
                                    <i class="fas fa-plus-circle me-2"></i>Create New Gatepass
                                </a>
                                <a href="manage_all_gatepasses.php" class="btn btn-info">
                                    <i class="fas fa-clipboard-list me-2"></i>Manage All Gatepasses
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">System Administration</h6>
                            <div class="d-grid gap-2">
                                <a href="system_settings.php" class="btn btn-dark">
                                    <i class="fas fa-cogs me-2"></i>System Settings
                                </a>
                                <a href="backup_system.php" class="btn btn-secondary">
                                    <i class="fas fa-download me-2"></i>Backup System
                                </a>
                                <a href="database_management.php" class="btn btn-danger">
                                    <i class="fas fa-database me-2"></i>Database Management
                                </a>
                            </div>
                        </div>
                          <div class="col-md-6">
                            <h6 class="mb-3">Configuration & Logs</h6>
                            <div class="d-grid gap-2">
                                <a href="email_settings.php" class="btn btn-primary">
                                    <i class="fas fa-envelope me-2"></i>Email Settings
                                </a>
                                <a href="security_settings.php" class="btn btn-warning">
                                    <i class="fas fa-shield-alt me-2"></i>Security Settings
                                </a>
                                <a href="system_logs.php" class="btn btn-info">
                                    <i class="fas fa-history me-2"></i>View System Logs
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-12">
                            <h6 class="mb-3">Gatepass Configurations</h6>
                            <div class="d-grid gap-2">
                                <a href="manage_units.php" class="btn btn-success">
                                    <i class="fas fa-ruler-combined me-2"></i>Manage Measurement Units
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Recent Gatepasses</h5>
                    <a href="manage_all_gatepasses.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($recent_gatepasses->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Created By</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($gatepass = $recent_gatepasses->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($gatepass['gatepass_number']); ?></td>
                                            <td><?php echo htmlspecialchars($gatepass['creator_name']); ?></td>
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
                                            <td><?php echo formatDateTime($gatepass['created_at'], 'd M Y'); ?></td>                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-primary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-info" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <p class="mb-0">No recent gatepasses found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>System Activity</h5>
                    <a href="system_logs.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($recent_logs->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($log = $recent_logs->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                            <td>
                                                <?php 
                                                    switch ($log['user_role']) {
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
                                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                                            <td><?php echo formatDateTime($log['created_at']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <p class="mb-0">No recent activity found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i>System Health</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h6>Database Size</h6>
                            <h4 class="text-primary">5.2 MB</h4>
                            <div class="progress mt-2">
                                <div class="progress-bar bg-primary" style="width: 12%"></div>
                            </div>
                            <small class="text-muted">12% of allocated space</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h6>File Storage</h6>
                            <h4 class="text-success">18.7 MB</h4>
                            <div class="progress mt-2">
                                <div class="progress-bar bg-success" style="width: 8%"></div>
                            </div>
                            <small class="text-muted">8% of allocated space</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h6>System Load</h6>
                            <h4 class="text-info">Low</h4>
                            <div class="progress mt-2">
                                <div class="progress-bar bg-info" style="width: 15%"></div>
                            </div>
                            <small class="text-muted">15% of maximum capacity</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h6>Last Backup</h6>
                            <h4 class="text-danger">None</h4>
                            <div class="d-grid mt-2">
                                <a href="system_backup.php" class="btn btn-sm btn-outline-danger">Backup Now</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
