<?php
require_once '../includes/config.php';

// Check if user is logged in and has user role
if (!isLoggedIn() || $_SESSION['role'] != 'user') {
    header("Location: ../index.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Filter by status if provided
$status_filter = '';
$status_param = '';
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $_GET['status'];
      if ($status == 'pending') {
        $status_filter = " AND g.status = 'pending' ";
        $status_param = "pending";
    } elseif ($status == 'approved') {
        $status_filter = " AND (g.status = 'approved_by_admin' OR g.status = 'approved_by_security') ";
        $status_param = "approved";
    } elseif ($status == 'declined') {
        $status_filter = " AND g.status = 'declined' ";
        $status_param = "declined";
    }
}

// Get all gatepasses for this user
$stmt = $conn->prepare("
    SELECT g.*, 
           admin.name as admin_name, 
           security.name as security_name
    FROM gatepasses g
    LEFT JOIN users admin ON g.admin_approved_by = admin.id
    LEFT JOIN users security ON g.security_approved_by = security.id
    WHERE g.created_by = ? $status_filter
    ORDER BY g.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$gatepasses = $stmt->get_result();

// Set page title
$page_title = "My Gatepasses";

// Include header
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>
        <i class="fas fa-list-alt me-2"></i>My Gatepasses
        <?php if (!empty($status_param)): ?>
            <span class="badge bg-secondary"><?php echo ucfirst($status_param); ?></span>
        <?php endif; ?>
    </h2>
    <a href="new_gatepass.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i>Create New Gatepass
    </a>
</div>

<div class="card mb-4">
    <div class="card-header bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">All Gatepasses</h5>
            </div>
            <div class="btn-group" role="group">
                <a href="my_gatepasses.php" class="btn btn-outline-secondary <?php echo empty($status_param) ? 'active' : ''; ?>">All</a>
                <a href="my_gatepasses.php?status=pending" class="btn btn-outline-warning <?php echo $status_param == 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="my_gatepasses.php?status=approved" class="btn btn-outline-success <?php echo $status_param == 'approved' ? 'active' : ''; ?>">Approved</a>
                <a href="my_gatepasses.php?status=declined" class="btn btn-outline-danger <?php echo $status_param == 'declined' ? 'active' : ''; ?>">Declined</a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Gatepass #</th>
                        <th>From → To</th>
                        <th>Date & Time</th>
                        <th>Material Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($gatepasses->num_rows > 0): ?>
                    <?php while ($gatepass = $gatepasses->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($gatepass['gatepass_number']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($gatepass['from_location']); ?> → 
                            <?php echo htmlspecialchars($gatepass['to_location']); ?>
                        </td>
                        <td>
                            <?php 
                            echo date('d M Y', strtotime($gatepass['requested_date'])); 
                            echo '<br><small class="text-muted">';
                            echo date('h:i A', strtotime($gatepass['requested_time']));
                            echo '</small>';
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($gatepass['material_type']); ?></td>
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
                            <small><?php echo date('d M Y, h:i A', strtotime($gatepass['created_at'])); ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>                                <?php if ($gatepass['status'] == 'pending'): ?>
                                <a href="edit_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php endif; ?>
                                
                                <!-- Always show PDF download button with appropriate styling based on status -->
                                <a href="download_pdf.php?id=<?php echo $gatepass['id']; ?>" class="btn
                                <?php
                                switch ($gatepass['status']) {
                                    case 'approved_by_security':
                                        echo 'btn-success';
                                        break;
                                    case 'approved_by_admin':
                                        echo 'btn-primary';
                                        break;
                                    case 'pending':
                                        echo 'btn-warning';
                                        break;
                                    case 'declined':
                                        echo 'btn-danger';
                                        break;
                                    default:
                                        echo 'btn-secondary';
                                }
                                ?>" target="_blank">
                                    <i class="fas fa-download"></i> PDF
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-3">
                            No gatepasses found
                            <?php if (!empty($status_param)): ?>
                            with status: <?php echo ucfirst($status_param); ?>
                            <?php endif; ?>
                            <br><br>
                            <a href="new_gatepass.php" class="btn btn-sm btn-primary">Create New Gatepass</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Close connection
$conn->close();

// Include footer
require_once '../includes/footer.php';
?>
