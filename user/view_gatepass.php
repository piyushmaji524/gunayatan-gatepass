<?php
require_once '../includes/config.php';

// Check if user is logged in and has user role
if (!isLoggedIn() || $_SESSION['role'] != 'user') {
    header("Location: ../index.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWithMessage("my_gatepasses.php", "Invalid gatepass ID", "danger");
}

$gatepass_id = (int)$_GET['id'];

// Connect to database
$conn = connectDB();

// Get gatepass details
$stmt = $conn->prepare("
    SELECT g.*, 
           admin.name as admin_name, 
           security.name as security_name,
           creator.name as creator_name
    FROM gatepasses g
    LEFT JOIN users admin ON g.admin_approved_by = admin.id
    LEFT JOIN users security ON g.security_approved_by = security.id
    LEFT JOIN users creator ON g.created_by = creator.id
    WHERE g.id = ? AND g.created_by = ?
");
$stmt->bind_param("ii", $gatepass_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

// If no gatepass found or not owned by this user
if ($result->num_rows !== 1) {
    $conn->close();
    redirectWithMessage("my_gatepasses.php", "Gatepass not found or you don't have permission to view it", "danger");
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
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>
        <i class="fas fa-clipboard me-2"></i>Gatepass Details
    </h2>
    <div>
        <a href="my_gatepasses.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Gatepasses
        </a>
          <?php if ($gatepass['status'] == 'pending'): ?>
        <a href="edit_gatepass.php?id=<?php echo $gatepass_id; ?>" class="btn btn-primary">
            <i class="fas fa-edit me-2"></i>Edit Gatepass
        </a>
        <?php endif; ?>
        
        <!-- Always show PDF download button with appropriate color based on status -->
        <a href="download_pdf.php?id=<?php echo $gatepass_id; ?>" class="btn 
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
            <i class="fas fa-download me-2"></i>Download PDF
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Gatepass #<?php echo htmlspecialchars($gatepass['gatepass_number']); ?>
                    </h5>
                    <span class="
                        <?php 
                        switch ($gatepass['status']) {
                            case 'pending': echo 'badge bg-warning';
                                break;
                            case 'approved_by_admin': echo 'badge bg-info';
                                break;
                            case 'approved_by_security': echo 'badge bg-success';
                                break;
                            case 'declined': echo 'badge bg-danger';
                                break;
                            default: echo 'badge bg-secondary';
                        }
                        ?>">
                        <?php 
                        switch ($gatepass['status']) {
                            case 'pending': echo 'Pending Admin Approval';
                                break;
                            case 'approved_by_admin': echo 'Approved by Admin';
                                break;
                            case 'approved_by_security': echo 'Fully Approved';
                                break;
                            case 'declined': echo 'Declined';
                                break;
                            default: echo $gatepass['status'];
                        }
                        ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold">General Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="150">From Location:</th>
                                <td><?php echo htmlspecialchars($gatepass['from_location']); ?></td>
                            </tr>
                            <tr>
                                <th>To Location:</th>
                                <td><?php echo htmlspecialchars($gatepass['to_location']); ?></td>
                            </tr>
                            <tr>
                                <th>Material Type:</th>
                                <td><?php echo htmlspecialchars($gatepass['material_type']); ?></td>
                            </tr>
                            <tr>
                                <th>Purpose:</th>
                                <td><?php echo !empty($gatepass['purpose']) ? htmlspecialchars($gatepass['purpose']) : '<em>No purpose provided</em>'; ?></td>
                            </tr>
                            <tr>
                                <th>Date & Time:</th>
                                <td>
                                    <?php 
                                    echo date('d M Y', strtotime($gatepass['requested_date'])); 
                                    echo ' at ';
                                    echo date('h:i A', strtotime($gatepass['requested_time']));
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Approval Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="150">Created By:</th>
                                <td>
                                    <?php echo htmlspecialchars($gatepass['creator_name']); ?><br>
                                    <small class="text-muted">
                                        <?php echo date('d M Y, h:i A', strtotime($gatepass['created_at'])); ?>
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <th>Admin Approval:</th>
                                <td>
                                    <?php if (!empty($gatepass['admin_approved_by'])): ?>
                                    <?php echo htmlspecialchars($gatepass['admin_name']); ?><br>
                                    <small class="text-muted">
                                        <?php echo formatDateTime($gatepass['admin_approved_at']); ?>
                                    </small>
                                    <?php else: ?>
                                    <span class="text-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Security Verification:</th>
                                <td>
                                    <?php if (!empty($gatepass['security_approved_by'])): ?>
                                    <?php echo htmlspecialchars($gatepass['security_name']); ?><br>
                                    <small class="text-muted">
                                        <?php echo formatDateTime($gatepass['security_approved_at']); ?>
                                    </small>
                                    <?php else: ?>
                                    <span class="text-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($gatepass['status'] == 'declined'): ?>
                            <tr>
                                <th>Declined By:</th>
                                <td class="text-danger">
                                    <?php 
                                    // Determine who declined it
                                    if ($gatepass['declined_by'] == $gatepass['admin_approved_by']) {
                                        echo htmlspecialchars($gatepass['admin_name']);
                                    } else {
                                        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                                        $stmt->bind_param("i", $gatepass['declined_by']);
                                        $stmt->execute();
                                        $declinedBy = $stmt->get_result()->fetch_assoc();
                                        echo htmlspecialchars($declinedBy['name']);
                                    }
                                    ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo formatDateTime($gatepass['declined_at']); ?>
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <th>Decline Reason:</th>
                                <td class="text-danger">
                                    <?php echo !empty($gatepass['decline_reason']) ? htmlspecialchars($gatepass['decline_reason']) : '<em>No reason provided</em>'; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <h6 class="fw-bold mt-4">Items</h6>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            while ($item = $items->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($items->num_rows == 0): ?>
                            <tr>
                                <td colspan="4" class="text-center">No items found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($gatepass['status'] == 'approved_by_admin' || $gatepass['status'] == 'approved_by_security'): ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-pdf me-2"></i>Gatepass PDF Preview</h5>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <iframe src="download_pdf.php?id=<?php echo $gatepass_id; ?>&preview=true" width="100%" height="500" style="border: 1px solid #ddd;"></iframe>
                </div>
                <div class="text-center mt-3">
                    <a href="download_pdf.php?id=<?php echo $gatepass_id; ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-download me-2"></i>Download PDF
                    </a>
                    <button class="btn btn-secondary ms-2" onclick="window.print();">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Close connection
$conn->close();

// Include footer
require_once '../includes/footer.php';
?>
