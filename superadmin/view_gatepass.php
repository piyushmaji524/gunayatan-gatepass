<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWithMessage("manage_all_gatepasses.php", "Invalid gatepass ID", "danger");
}

$gatepass_id = (int)$_GET['id'];

// Connect to database
$conn = connectDB();

// Handle status change
if (isset($_POST['change_status']) && isset($_POST['new_status'])) {
    $new_status = sanitizeInput($_POST['new_status']);
    
    if (in_array($new_status, ['pending', 'approved_by_admin', 'approved_by_security', 'declined'])) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update the status
            $stmt = $conn->prepare("UPDATE gatepasses SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $gatepass_id);
            $stmt->execute();
            
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
                $decline_reason = isset($_POST['decline_reason']) ? sanitizeInput($_POST['decline_reason']) : "Status change by Super Admin";
                $stmt = $conn->prepare("UPDATE gatepasses SET declined_by = ?, declined_at = NOW(), decline_reason = ? WHERE id = ?");
                $stmt->bind_param("isi", $_SESSION['user_id'], $decline_reason, $gatepass_id);
                $stmt->execute();
            }
            
            // Log the action
            $action_details = "Changed gatepass ID: $gatepass_id to status: $new_status";
            logAction($_SESSION['user_id'], 'GATEPASS_STATUS_CHANGED', $action_details);
            
            $conn->commit();
            
            $_SESSION['flash_message'] = "Gatepass status successfully updated";
            $_SESSION['flash_type'] = "success";
            
            // Redirect to reload the page with updated data
            header("Location: view_gatepass.php?id=" . $gatepass_id);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = "Failed to update gatepass status: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
    }
}

// Get gatepass details with more detailed information
$stmt = $conn->prepare("
    SELECT g.*, 
           admin.name as admin_name, 
           admin.email as admin_email,
           security.name as security_name,
           security.email as security_email,
           creator.name as creator_name,
           creator.email as creator_email,
           creator.department as creator_department,
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
    redirectWithMessage("manage_all_gatepasses.php", "Gatepass not found", "danger");
}

$gatepass = $result->fetch_assoc();

// Get gatepass items
$stmt = $conn->prepare("SELECT * FROM gatepass_items WHERE gatepass_id = ?");
$stmt->bind_param("i", $gatepass_id);
$stmt->execute();
$items = $stmt->get_result();

// Get activity log for this gatepass
$stmt = $conn->prepare("
    SELECT a.*, u.name as user_name, u.role as user_role
    FROM activity_log a
    JOIN users u ON a.user_id = u.id
    WHERE a.action_details LIKE ?
    ORDER BY a.timestamp DESC
    LIMIT 10
");
$search_term = "%gatepass ID: $gatepass_id%";
$stmt->bind_param("s", $search_term);
$stmt->execute();
$activity_logs = $stmt->get_result();

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

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-clipboard-list me-2"></i>View Gatepass (Superadmin)</h1>
        <div>
            <a href="manage_all_gatepasses.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to All Gatepasses
            </a>
            <a href="edit_gatepass.php?id=<?php echo $gatepass_id; ?>" class="btn btn-info me-2">
                <i class="fas fa-edit me-2"></i>Edit Gatepass
            </a>
            <a href="../superadmin/export_report_pdf.php?id=<?php echo $gatepass_id; ?>" class="btn btn-primary me-2">
                <i class="fas fa-download me-2"></i>Download PDF
            </a>
            <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#changeStatusModal">
                <i class="fas fa-exchange-alt me-2"></i>Change Status
            </button>
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
                            <p class="mb-1"><strong>Requested Date/Time:</strong></p>
                            <p class="border-bottom pb-2">
                                <?php echo formatDateTime($gatepass['requested_date'] . ' ' . $gatepass['requested_time']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Created At:</strong></p>
                            <p class="border-bottom pb-2">
                                <?php echo formatDateTime($gatepass['created_at']); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Gatepass ID:</strong></p>
                            <p class="border-bottom pb-2"><?php echo $gatepass_id; ?></p>
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
            
            <!-- Activity Log for this Gatepass -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Activity History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Date/Time</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($activity_logs->num_rows > 0): ?>
                                    <?php while ($log = $activity_logs->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo formatDateTime($log['timestamp']); ?></td>
                                            <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo ucfirst(htmlspecialchars($log['user_role'])); ?></span></td>
                                            <td><?php echo str_replace('_', ' ', htmlspecialchars($log['action_type'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['action_details']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No activity logs found for this gatepass</td>
                                    </tr>
                                <?php endif; ?>
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
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($gatepass['creator_email']); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($gatepass['creator_department']); ?></p>
                    <p><strong>Date:</strong> <?php echo formatDateTime($gatepass['created_at']); ?></p>
                    <a href="../superadmin/edit_user.php?id=<?php echo $gatepass['created_by']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-user-edit me-1"></i>View User Profile
                    </a>
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
                                    <a href="../superadmin/edit_user.php?id=<?php echo $gatepass['admin_approved_by']; ?>" class="ms-2 text-info">
                                        <i class="fas fa-info-circle"></i>
                                    </a>
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
                                    <a href="../superadmin/edit_user.php?id=<?php echo $gatepass['security_approved_by']; ?>" class="ms-2 text-info">
                                        <i class="fas fa-info-circle"></i>
                                    </a>
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
            
            <!-- Technical Details -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Technical Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted">System ID:</small>
                        <p class="mb-1"><?php echo $gatepass['id']; ?></p>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Gatepass Number:</small>
                        <p class="mb-1"><?php echo htmlspecialchars($gatepass['gatepass_number']); ?></p>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Created by User ID:</small>
                        <p class="mb-1"><?php echo $gatepass['created_by']; ?></p>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Last Updated:</small>
                        <p class="mb-1"><?php echo formatDateTime($gatepass['updated_at'] ?? $gatepass['created_at']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Status Modal -->
<div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeStatusModalLabel">Change Gatepass Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_status" class="form-label">New Status</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="" disabled>Select status</option>
                            <option value="pending" <?php if ($gatepass['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                            <option value="approved_by_admin" <?php if ($gatepass['status'] == 'approved_by_admin') echo 'selected'; ?>>Approved by Admin</option>
                            <option value="approved_by_security" <?php if ($gatepass['status'] == 'approved_by_security') echo 'selected'; ?>>Verified by Security</option>
                            <option value="declined" <?php if ($gatepass['status'] == 'declined') echo 'selected'; ?>>Declined</option>
                        </select>
                    </div>
                    
                    <div id="decline_reason_container" class="mb-3" style="display: none;">
                        <label for="decline_reason" class="form-label">Decline Reason</label>
                        <textarea class="form-control" id="decline_reason" name="decline_reason" rows="3"><?php echo $gatepass['decline_reason'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Warning: Changing the status will update all related timestamps and user assignments. This action is logged.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="change_status" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Show/hide decline reason based on status selection
    document.addEventListener('DOMContentLoaded', function() {
        const newStatusSelect = document.getElementById('new_status');
        const declineReasonContainer = document.getElementById('decline_reason_container');
        
        // Initial check
        if (newStatusSelect.value === 'declined') {
            declineReasonContainer.style.display = 'block';
        } else {
            declineReasonContainer.style.display = 'none';
        }
        
        // Event listener
        newStatusSelect.addEventListener('change', function() {
            if (this.value === 'declined') {
                declineReasonContainer.style.display = 'block';
            } else {
                declineReasonContainer.style.display = 'none';
            }
        });    });
</script>

<?php 
// Close database connection
$conn->close();

include '../includes/footer.php'; 
?>
