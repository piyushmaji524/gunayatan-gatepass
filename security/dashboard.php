<?php
require_once '../includes/config.php';

// Check if user is logged in and has security role
if (!isLoggedIn() || $_SESSION['role'] != 'security') {
    header("Location: ../index.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Count pending gatepasses (approved by admin or self-approved, waiting security approval)
$stmt = $conn->prepare("SELECT COUNT(*) AS pending_count FROM gatepasses WHERE status = 'approved_by_admin' OR status = 'self_approved'");
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['pending_count'];

// Count verified gatepasses (approved by security)
$stmt = $conn->prepare("SELECT COUNT(*) AS verified_count FROM gatepasses WHERE status = 'approved_by_security'");
$stmt->execute();
$verified_count = $stmt->get_result()->fetch_assoc()['verified_count'];

// Count rejected gatepasses (declined by security)
$stmt = $conn->prepare("SELECT COUNT(*) AS declined_count FROM gatepasses WHERE status = 'declined' AND declined_by IN (SELECT id FROM users WHERE role = 'security')");
$stmt->execute();
$declined_count = $stmt->get_result()->fetch_assoc()['declined_count'];

// Get recent gatepasses (5 most recent approved by admin or self-approved)
$stmt = $conn->prepare("
    SELECT g.*, u.name as requested_by_name
    FROM gatepasses g
    JOIN users u ON g.created_by = u.id
    WHERE g.status = 'approved_by_admin' OR g.status = 'self_approved'
    ORDER BY g.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_gatepasses = $stmt->get_result();

// Close database connection
$conn->close();

// Set page title
$page_title = "Security Dashboard";

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-shield-alt me-2"></i>Security Dashboard</h1>
        <div>
            <a href="search_gatepass.php" class="btn btn-primary">
                <i class="fas fa-search me-2"></i>Search Gatepass
            </a>
        </div>
    </div>
    
            <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Pending Verification</h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($recent_gatepasses->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Gatepass #</th>
                                        <th>Requested By</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($gatepass = $recent_gatepasses->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($gatepass['gatepass_number']); ?></td>
                                            <td><?php echo htmlspecialchars($gatepass['requested_by_name']); ?></td>
                                            <td><?php echo formatDateTime($gatepass['created_at'], 'd M Y'); ?></td>                                            <td>

                                                <a href="verify_gatepass.php?id=<?php echo $gatepass['id']; ?>&direct_verify=1" class="btn btn-sm btn-success" title="Verify Immediately" onclick="return confirm('क्या आप यह गेटपास देखना चाहते हैं??');">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No pending gatepasses for verification.</p>
                        </div>
                    <?php endif; ?>
                </div>                <div class="card-footer bg-white">
                    <a href="search_gatepass.php?status=pending_verification" class="text-decoration-none">View all pending gatepasses <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-primary h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="display-4 me-3">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <h5 class="card-title">Pending Verification</h5>
                        <h3 class="mb-0"><?php echo $pending_count; ?></h3>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="search_gatepass.php?status=approved_by_admin" class="text-white">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="display-4 me-3">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <h5 class="card-title">Verified Gatepasses</h5>
                        <h3 class="mb-0"><?php echo $verified_count; ?></h3>
                    </div>                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="all_verified_gatepasses.php" class="text-white">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-danger h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="display-4 me-3">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <h5 class="card-title">Declined Gatepasses</h5>
                        <h3 class="mb-0"><?php echo $declined_count; ?></h3>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="search_gatepass.php?status=declined" class="text-white">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">                        <a href="search_gatepass.php" class="btn btn-lg btn-outline-primary">
                            <i class="fas fa-search me-2"></i>Search Gatepass
                        </a>
                        <a href="all_verified_gatepasses.php" class="btn btn-lg btn-outline-success">
                            <i class="fas fa-clipboard-check me-2"></i>View Verified Gatepasses
                        </a>
                        <a href="translation_demo.php" class="btn btn-lg btn-outline-warning">
                            <i class="fas fa-language me-2"></i>Hindi Translation Demo
                        </a>
                        <a href="#" class="btn btn-lg btn-outline-dark" data-bs-toggle="modal" data-bs-target="#scanModal">
                            <i class="fas fa-barcode me-2"></i>Scan Barcode
                        </a>
                    </div>
                </div>
            </div>
        </div>
        


<!-- Barcode Scanner Modal -->
<div class="modal fade" id="scanModal" tabindex="-1" aria-labelledby="scanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scanModalLabel">Scan Gatepass Barcode</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="d-flex justify-content-center">
                    <div id="scanner-container" class="mb-3">
                        <div class="d-flex justify-content-center align-items-center bg-light" style="width: 300px; height: 200px;">
                            <i class="fas fa-camera fa-4x text-muted"></i>
                        </div>
                    </div>
                </div>
                <p class="text-muted mb-4">Position the barcode in front of your camera</p>
                
                <form action="verify_gatepass.php" method="get" class="mt-3">
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                        <input type="text" class="form-control" id="barcode-result" name="gatepass_number" placeholder="Gatepass Number">
                        <button class="btn btn-primary" type="submit">Verify</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php
// Add this at the end of your dashboard.php file, before including the footer
if (isset($_SESSION['show_success_popup']) && $_SESSION['show_success_popup']) {
    // Clear the flag
    unset($_SESSION['show_success_popup']);
    // Add the success popup HTML
    echo '
    <div class="modal fade" id="successPopup" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 80px;"></i>
                    </div>
                    <h3 class="mb-4">Verification Successful!</h3>
                    <p>The gatepass has been verified successfully.</p>
                    <button type="button" class="btn btn-success btn-lg px-4" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var successModal = new bootstrap.Modal(document.getElementById("successPopup"));
            successModal.show();
        });
    </script>
    ';
}
?>

<?php include '../includes/footer.php'; ?>