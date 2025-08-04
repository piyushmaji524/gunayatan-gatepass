<?php
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWithMessage("all_gatepasses.php", "Invalid gatepass ID", "danger");
}

$gatepass_id = (int)$_GET['id'];

// Check if gatepass exists and is pending
$stmt = $conn->prepare("SELECT * FROM gatepasses WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $gatepass_id);
$stmt->execute();
$result = $stmt->get_result();

// If no gatepass found or not in pending status
if ($result->num_rows !== 1) {
    $conn->close();
    redirectWithMessage("all_gatepasses.php", "Gatepass not found or already processed", "danger");
}

$gatepass = $result->fetch_assoc();

// Handle form submission (when admin declines the gatepass)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get decline reason
    $decline_reason = sanitizeInput($_POST['decline_reason']);
    
    if (empty($decline_reason)) {
        redirectWithMessage("decline_gatepass.php?id=$gatepass_id", "Please provide a reason for declining", "warning");
    }
      // Update gatepass status using MySQL's NOW() function for consistent server timezone
    $stmt = $conn->prepare("
        UPDATE gatepasses 
        SET status = 'declined', 
            declined_by = ?,
            declined_at = NOW(),
            decline_reason = ?
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->bind_param("isi", $_SESSION['user_id'], $decline_reason, $gatepass_id);
    $stmt->execute();
    
    // Check if update was successful
    if ($stmt->affected_rows > 0) {
        // Log the action
        logActivity($_SESSION['user_id'], 'GATEPASS_DECLINED', "Admin declined gatepass " . $gatepass['gatepass_number'] . ": $decline_reason");
        
        // Redirect with success message
        redirectWithMessage("view_gatepass.php?id=$gatepass_id", "Gatepass #" . $gatepass['gatepass_number'] . " declined successfully", "success");
    } else {
        // If update failed
        redirectWithMessage("view_gatepass.php?id=$gatepass_id", "Failed to decline gatepass. It may have been processed already.", "danger");
    }
}

// Set page title
$page_title = "Decline Gatepass";

// Get gatepass details
$stmt = $conn->prepare("
    SELECT g.*, 
           u.name as creator_name
    FROM gatepasses g
    JOIN users u ON g.created_by = u.id
    WHERE g.id = ?
");
$stmt->bind_param("i", $gatepass_id);
$stmt->execute();
$result = $stmt->get_result();
$gatepass = $result->fetch_assoc();

// Get gatepass items
$stmt = $conn->prepare("SELECT * FROM gatepass_items WHERE gatepass_id = ?");
$stmt->bind_param("i", $gatepass_id);
$stmt->execute();
$items = $stmt->get_result();

// Include header
require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-times-circle me-2"></i>Decline Gatepass</h2>
        <a href="all_gatepasses.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Gatepasses
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Gatepass #<?php echo htmlspecialchars($gatepass['gatepass_number']); ?> Details</h5>
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
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Created By:</strong></p>
                            <p class="border-bottom pb-2"><?php echo htmlspecialchars($gatepass['creator_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Created At:</strong></p>
                            <p class="border-bottom pb-2"><?php echo formatDateTime($gatepass['created_at']); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <p class="mb-1"><strong>Purpose:</strong></p>
                            <p class="border-bottom pb-2"><?php echo htmlspecialchars($gatepass['purpose']); ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5 class="mb-3">Gatepass Items</h5>
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
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4 border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-times-circle me-2"></i>Decline Gatepass</h5>
                </div>
                <div class="card-body">
                    <form action="" method="post">
                        <div class="alert alert-warning mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This action cannot be undone. The gatepass will be permanently marked as declined.
                        </div>
                        
                        <div class="mb-4">
                            <label for="decline_reason" class="form-label">Reason for Declining</label>
                            <textarea class="form-control" id="decline_reason" name="decline_reason" rows="5" required></textarea>
                            <div class="form-text">Please provide a clear reason why this gatepass is being declined.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="fas fa-times me-2"></i>Confirm Decline
                            </button>
                            <a href="view_gatepass.php?id=<?php echo $gatepass_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>