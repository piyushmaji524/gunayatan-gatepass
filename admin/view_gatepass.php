<?php
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWithMessage("all_gatepasses.php", "Invalid gatepass ID", "danger");
}

$gatepass_id = (int)$_GET['id'];

// Connect to database
$conn = connectDB();

// Get gatepass details
$stmt = $conn->prepare("
    SELECT g.*, 
           admin.name as admin_name, 
           security.name as security_name,
           creator.name as creator_name,
           decliner.name as declined_by_name
    FROM gatepasses g
    LEFT JOIN users admin ON g.admin_approved_by = admin.id
    LEFT JOIN users security ON g.security_approved_by = security.id
    LEFT JOIN users creator ON g.created_by = creator.id
    LEFT JOIN users decliner ON g.declined_by = decliner.id
    WHERE g.id = ?
");
$stmt->bind_param("i", $gatepass_id);
$stmt->execute();
$result = $stmt->get_result();

// If no gatepass found
if ($result->num_rows !== 1) {
    $conn->close();
    redirectWithMessage("all_gatepasses.php", "Gatepass not found", "danger");
}

$gatepass = $result->fetch_assoc();

// Get gatepass items
$stmt = $conn->prepare("SELECT * FROM gatepass_items WHERE gatepass_id = ?");
$stmt->bind_param("i", $gatepass_id);
$stmt->execute();
$items = $stmt->get_result();

// Set page title
$page_title = "Gatepass #" . $gatepass['gatepass_number'];

// Include header
include '../includes/header.php';

// Determine status class for styling
$status_class = '';
$status_icon = '';
$status_text = '';

switch ($gatepass['status']) {
    case 'pending':
        $status_class = 'warning text-dark';
        $status_icon = 'clock';
        $status_text = 'Pending';
        break;
    case 'approved_by_admin':
        $status_class = 'primary';
        $status_icon = 'check';
        $status_text = 'Approved by Admin';
        break;
    case 'approved_by_security':
        $status_class = 'success';
        $status_icon = 'check-double';
        $status_text = 'Verified by Security';
        break;
    case 'declined':
        $status_class = 'danger';
        $status_icon = 'times';
        $status_text = 'Declined';
        break;
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-clipboard-list me-2"></i>View Gatepass</h1>
        <div>
            <a href="all_gatepasses.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to All Gatepasses
            </a>
            <?php if ($gatepass['status'] === 'pending'): ?>
                <a href="approve_gatepass.php?id=<?php echo $gatepass_id; ?>" class="btn btn-success me-2">
                    <i class="fas fa-check me-2"></i>Approve
                </a>
                <a href="decline_gatepass.php?id=<?php echo $gatepass_id; ?>" class="btn btn-danger me-2">
                    <i class="fas fa-times me-2"></i>Decline
                </a>
            <?php endif; ?>
            <a href="download_pdf.php?id=<?php echo $gatepass_id; ?>" class="btn btn-primary">
                <i class="fas fa-download me-2"></i>Download PDF
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Gatepass Details -->
            <div class="card mb-4">
                <div class="card-header bg-<?php echo $status_class; ?> <?php if ($status_class !== 'warning') echo 'text-white'; ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $status_icon; ?> me-2"></i>
                            Gatepass #<?php echo htmlspecialchars($gatepass['gatepass_number']); ?>
                        </h5>
                        <span class="badge bg-white text-<?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>From Location:</strong></p>
                            <p class="border-bottom pb-2"><?php echo htmlspecialchars($gatepass['from_location']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>To Location:</strong></p>
                            <p class="border-bottom pb-2"><?php echo htmlspecialchars($gatepass['to_location']); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Material Type:</strong></p>
                            <p class="border-bottom pb-2"><?php echo htmlspecialchars($gatepass['material_type']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Requested Date:</strong></p>
                            <p class="border-bottom pb-2">
                                <?php echo formatDateTime($gatepass['requested_date'] . ' ' . $gatepass['requested_time']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <p class="mb-1"><strong>Purpose:</strong></p>
                            <p class="border-bottom pb-2"><?php echo htmlspecialchars($gatepass['purpose']); ?></p>
                        </div>
                    </div>
                    
                    <!-- Gatepass Items -->
                    <h5 class="mb-3">Items in this Gatepass</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Item Name</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $count = 1; while ($item = $items->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $count++; ?></td>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Creator Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Created By</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($gatepass['creator_name']); ?></p>
                    <p><strong>Date:</strong> <?php echo formatDateTime($gatepass['created_at']); ?></p>
                </div>
            </div>
            
            <!-- Workflow Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Workflow Status</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge rounded-pill bg-success me-2">1</span>
                                    <span>Creation</span>
                                </div>
                                <span class="text-muted small">
                                    <?php echo formatDateTime($gatepass['created_at']); ?>
                                </span>
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($gatepass['creator_name']); ?>
                            </div>
                        </li>
                        
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge rounded-pill <?php echo $gatepass['admin_approved_by'] ? 'bg-success' : 'bg-light text-dark border'; ?> me-2">2</span>
                                    <span>Admin Approval</span>
                                </div>
                                <?php if ($gatepass['admin_approved_by']): ?>
                                    <span class="text-muted small">
                                        <?php echo formatDateTime($gatepass['admin_approved_at']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($gatepass['admin_approved_by']): ?>
                                <div class="text-muted small mt-1">
                                    <i class="fas fa-user-shield me-1"></i> <?php echo htmlspecialchars($gatepass['admin_name']); ?>
                                </div>
                            <?php endif; ?>
                        </li>
                        
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge rounded-pill <?php echo $gatepass['security_approved_by'] ? 'bg-success' : 'bg-light text-dark border'; ?> me-2">3</span>
                                    <span>Security Verification</span>
                                </div>
                                <?php if ($gatepass['security_approved_by']): ?>
                                    <span class="text-muted small">
                                        <?php echo formatDateTime($gatepass['security_approved_at']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($gatepass['security_approved_by']): ?>
                                <div class="text-muted small mt-1">
                                    <i class="fas fa-shield-alt me-1"></i> <?php echo htmlspecialchars($gatepass['security_name']); ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Declined Information (if applicable) -->
            <?php if ($gatepass['status'] === 'declined'): ?>
                <div class="card mb-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-times-circle me-2"></i>Declined</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Declined By:</h6>
                            <p><?php echo htmlspecialchars($gatepass['declined_by_name']); ?></p>
                        </div>
                        <div class="mb-3">
                            <h6>Declined At:</h6>
                            <p><?php echo formatDateTime($gatepass['declined_at']); ?></p>
                        </div>
                        <div class="mb-3">
                            <h6>Reason:</h6>
                            <p class="border p-2 rounded"><?php echo htmlspecialchars($gatepass['decline_reason']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>