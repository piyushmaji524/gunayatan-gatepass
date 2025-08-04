<?php
require_once '../includes/config.php';

// Check if user is logged in and has user role
if (!isLoggedIn() || $_SESSION['role'] != 'user') {
    header("Location: ../index.php");
    exit();
}

// Set page title
$page_title = "Dashboard";

// Include header
require_once '../includes/header.php';

// Get user's gatepass statistics
$conn = connectDB();

// Count total gatepasses
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM gatepasses WHERE created_by = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$total_result = $stmt->get_result();
$total_gatepasses = $total_result->fetch_assoc()['total'];

// Count pending gatepasses
$stmt = $conn->prepare("SELECT COUNT(*) as pending FROM gatepasses WHERE created_by = ? AND status = 'pending'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$pending_result = $stmt->get_result();
$pending_gatepasses = $pending_result->fetch_assoc()['pending'];

// Count approved gatepasses (by admin)
$stmt = $conn->prepare("SELECT COUNT(*) as approved FROM gatepasses WHERE created_by = ? AND status = 'approved_by_admin'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$approved_result = $stmt->get_result();
$approved_gatepasses = $approved_result->fetch_assoc()['approved'];

// Count fully approved gatepasses (by security)
$stmt = $conn->prepare("SELECT COUNT(*) as fully_approved FROM gatepasses WHERE created_by = ? AND status = 'approved_by_security'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$fully_approved_result = $stmt->get_result();
$fully_approved_gatepasses = $fully_approved_result->fetch_assoc()['fully_approved'];

// Count declined gatepasses
$stmt = $conn->prepare("SELECT COUNT(*) as declined FROM gatepasses WHERE created_by = ? AND status = 'declined'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$declined_result = $stmt->get_result();
$declined_gatepasses = $declined_result->fetch_assoc()['declined'];

// Get recent gatepasses
$stmt = $conn->prepare("
    SELECT g.*, 
           admin.name as admin_name, 
           security.name as security_name
    FROM gatepasses g
    LEFT JOIN users admin ON g.admin_approved_by = admin.id
    LEFT JOIN users security ON g.security_approved_by = security.id
    WHERE g.created_by = ?
    ORDER BY g.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recent_gatepasses = $stmt->get_result();

$conn->close();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-tachometer-alt me-2"></i>User Dashboard</h2> 
        <p class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! Here's an overview of your gatepass activity.</p>
    </div>
</div>

<div class="col-md-4">
                        <a href="new_gatepass.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus-circle me-2"></i>Create New Gatepass
                        </a>
                    </div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card dashboard-card h-100">
            <div class="card-body text-center">
                <div class="dashboard-icon text-primary">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h5 class="card-title">Total Gatepasses</h5>
                <h3 class="mb-0"><?php echo $total_gatepasses; ?></h3>
                <p class="card-text">
                    <a href="my_gatepasses.php" class="text-decoration-none">View All</a>
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card dashboard-card h-100">
            <div class="card-body text-center">
                <div class="dashboard-icon text-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <h5 class="card-title">Pending Approval</h5>
                <h3 class="mb-0"><?php echo $pending_gatepasses; ?></h3>
                <p class="card-text">
                    <a href="my_gatepasses.php?status=pending" class="text-decoration-none">View Pending</a>
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card dashboard-card h-100">
            <div class="card-body text-center">
                <div class="dashboard-icon text-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h5 class="card-title">Approved Gatepasses</h5>
                <h3 class="mb-0"><?php echo $approved_gatepasses + $fully_approved_gatepasses; ?></h3>
                <p class="card-text">
                    <a href="my_gatepasses.php?status=approved" class="text-decoration-none">View Approved</a>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Gatepasses</h5>
            </div>
            <div class="card-body">
                <?php if ($recent_gatepasses->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-custom">
                        <thead>
                            <tr>
                                <th>Gatepass #</th>
                                <th>From → To</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($gatepass = $recent_gatepasses->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($gatepass['gatepass_number']); ?></td>
                                <td><?php echo htmlspecialchars($gatepass['from_location']); ?> → <?php echo htmlspecialchars($gatepass['to_location']); ?></td>
                                <td><?php echo date('d M Y', strtotime($gatepass['requested_date'])); ?></td>
                                <td>
                                    <?php 
                                    switch ($gatepass['status']) {
                                        case 'pending':
                                            echo '<span class="badge bg-warning">Pending Admin Approval</span>';
                                            break;
                                        case 'approved_by_admin':
                                            echo '<span class="badge bg-info">Approved by Admin</span>';
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
                                    <a href="view_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($gatepass['status'] == 'pending'): ?>
                                    <a href="edit_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($gatepass['status'] == 'approved_by_admin' || $gatepass['status'] == 'approved_by_security'): ?>
                                    <a href="download_pdf.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-download"></i> PDF
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted text-center">No gatepasses found. <a href="new_gatepass.php" class="btn btn-sm btn-primary ms-2">Create your first gatepass</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <a href="new_gatepass.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus-circle me-2"></i>Create New Gatepass
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="my_gatepasses.php" class="btn btn-secondary w-100">
                            <i class="fas fa-list-alt me-2"></i>View All Gatepasses
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="../profile.php" class="btn btn-info text-white w-100">
                            <i class="fas fa-user-cog me-2"></i>Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?>
