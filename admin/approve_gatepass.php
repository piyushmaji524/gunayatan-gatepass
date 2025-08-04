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

// Handle form submission (when admin approves the gatepass)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {    // Update gatepass status using MySQL's NOW() function for consistent server timezone
    $stmt = $conn->prepare("
        UPDATE gatepasses 
        SET status = 'approved_by_admin', 
            admin_approved_by = ?,
            admin_approved_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $_SESSION['user_id'], $gatepass_id);
    $stmt->execute();
        // Check if update was successful
        if ($stmt->affected_rows > 0) {
            // Log the action
            logActivity($_SESSION['user_id'], 'GATEPASS_APPROVED', "Admin approved gatepass " . $gatepass['gatepass_number']);
            
            // Send notifications
            try {
                require_once '../includes/simple_notification_system.php';
                sendInstantNotification('gatepass_approved', $gatepass_id, $gatepass['gatepass_number'], $gatepass['created_by'], $_SESSION['user_id']);
            } catch (Exception $e) {
                // Log the error but don't stop the process
                error_log("Notification error: " . $e->getMessage());
            }
            
            // Get user email who created the gatepass
        $user_stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $gatepass['created_by']);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows == 1) {
            $user = $user_result->fetch_assoc();
            
            // Prepare gatepass data for email
            $gatepass_data = array(
                'gatepass_number' => $gatepass['gatepass_number'],
                'from_location' => $gatepass['from_location'],
                'to_location' => $gatepass['to_location'],
                'material_type' => $gatepass['material_type'],
                'status' => 'APPROVED BY ADMIN'
            );
            
            // Send email notification to the user
            $subject = "Gatepass #" . $gatepass['gatepass_number'] . " Approved by Admin";
            $message = "Your gatepass request has been approved by the administrator and is now pending security approval. You can view the gatepass details by clicking the button below.";
            $action_url = APP_URL . "/user/view_gatepass.php?id=" . $gatepass_id;
            $action_text = "View Gatepass";
            
            // Send the email notification
            sendEmailNotification(
                $user['email'],
                $user['name'],
                $subject,
                $message,
                $gatepass_data,
                $action_url,
                $action_text
            );
            
            // Also notify security personnel
            $security_stmt = $conn->prepare("SELECT name, email FROM users WHERE role = 'security' AND status = 'active' LIMIT 1");
            $security_stmt->execute();
            $security_result = $security_stmt->get_result();
            
            if ($security_result->num_rows == 1) {
                $security = $security_result->fetch_assoc();
                
                // Send notification to security
                $security_subject = "New Gatepass #" . $gatepass['gatepass_number'] . " Ready for Verification";
                $security_message = "A new gatepass has been approved by admin and requires your verification. Please review the details and verify the gatepass.";
                $security_url = APP_URL . "/security/view_gatepass.php?id=" . $gatepass_id;
                
                sendEmailNotification(
                    $security['email'],
                    $security['name'],
                    $security_subject,
                    $security_message,
                    $gatepass_data,
                    $security_url,
                    "Verify Gatepass"
                );
            }
        }
        
        // Redirect with success message
        redirectWithMessage("view_gatepass.php?id=$gatepass_id", "Gatepass #" . $gatepass['gatepass_number'] . " approved successfully", "success");
    } else {
        // If update failed
        redirectWithMessage("view_gatepass.php?id=$gatepass_id", "Failed to approve gatepass. It may have been processed already.", "danger");
    }
}

// Set page title
$page_title = "Approve Gatepass";

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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-check-circle me-2"></i>Approve Gatepass</h2>
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
                        <h6 class="fw-bold">Request Information</h6>
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
                                <th>Status:</th>
                                <td><span class="badge bg-warning">Pending Approval</span></td>
                            </tr>
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
                            
                            <?php if (mysqli_num_rows($items) == 0): ?>
                            <tr>
                                <td colspan="4" class="text-center">No items found</td>
                            </tr>
                            <?php endif; ?>
                            
                            <!-- Reset for second read -->
                            <?php 
                            mysqli_data_seek($items, 0);
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Approve Gatepass</h5>
            </div>
            <div class="card-body">
                <p>You are about to approve this gatepass which will:</p>
                <ul>
                    <li>Generate a PDF document with barcode</li>
                    <li>Allow security to verify and approve it</li>
                    <li>Send notification to the requesting user</li>
                    <li>Record your approval in the system logs</li>
                </ul>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=$gatepass_id"); ?>" class="mt-4">
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes (Optional)</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" placeholder="Add any notes or remarks here"></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check-circle me-2"></i>Approve Gatepass
                        </button>
                        <a href="decline_gatepass.php?id=<?php echo $gatepass_id; ?>" class="btn btn-outline-danger">
                            <i class="fas fa-times-circle me-2"></i>Decline Instead
                        </a>
                        <a href="view_gatepass.php?id=<?php echo $gatepass_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-eye me-2"></i>View Details
                        </a>
                    </div>
                </form>
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