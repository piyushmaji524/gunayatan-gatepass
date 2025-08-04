<?php
require_once '../includes/config.php';

// Check if user is logged in and has security role
if (!isLoggedIn() || $_SESSION['role'] != 'security') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables
$search_term = '';
$status_filter = '';
$date_from = '';
$date_to = '';
$gatepasses = null;
$search_performed = false;

// Connect to database
$conn = connectDB();

// If search is performed
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['search']) || isset($_GET['status']))) {
    $search_performed = true;
    
    // Get search parameters
    $search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';
    
    // Build the query based on search parameters
    $query = "
        SELECT g.*,
               creator.name as creator_name,
               admin.name as admin_name,
               security.name as security_name
        FROM gatepasses g
        LEFT JOIN users creator ON g.created_by = creator.id
        LEFT JOIN users admin ON g.admin_approved_by = admin.id
        LEFT JOIN users security ON g.security_approved_by = security.id
        WHERE 1=1
    ";
    
    // Add search conditions
    $params = array();
    $types = '';
    
    // Search term (gatepass_number or from_location or to_location)
    if (!empty($search_term)) {
        $query .= " AND (g.gatepass_number LIKE ? OR g.from_location LIKE ? OR g.to_location LIKE ? OR g.material_type LIKE ?)";
        $search_param = '%' . $search_term . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ssss';
    }
    
    // Status filter
    if (!empty($status_filter)) {
        $query .= " AND g.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    // Date range filter
    if (!empty($date_from)) {
        $query .= " AND g.requested_date >= ?";
        $params[] = $date_from;
        $types .= 's';
    }
    
    if (!empty($date_to)) {
        $query .= " AND g.requested_date <= ?";
        $params[] = $date_to;
        $types .= 's';
    }
    
    // Order by most recent first
    $query .= " ORDER BY g.created_at DESC";
    
    // Prepare and execute the statement
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $gatepasses = $stmt->get_result();
}

// Set page title
$page_title = "Search Gatepass";

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-search me-2"></i>Search Gatepass</h1>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Search Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search Term</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Gatepass #, From, To, Material Type" 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="" <?php if($status_filter == '') echo 'selected'; ?>>All Statuses</option>
                        <option value="pending" <?php if($status_filter == 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="approved_by_admin" <?php if($status_filter == 'approved_by_admin') echo 'selected'; ?>>Approved by Admin</option>
                        <option value="approved_by_security" <?php if($status_filter == 'approved_by_security') echo 'selected'; ?>>Approved by Security</option>
                        <option value="declined" <?php if($status_filter == 'declined') echo 'selected'; ?>>Declined</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-4">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                    <a href="search_gatepass.php" class="btn btn-secondary">
                        <i class="fas fa-sync me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Manual Input for Gatepass Number -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-keyboard me-2"></i>Quick Search by Gatepass Number</h5>
        </div>
        <div class="card-body">
            <form action="verify_gatepass.php" method="get" class="row g-3">                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                        <div class="input-group-text bg-light">GP</div>
                        <input type="text" class="form-control form-control-lg" name="gatepass_number" 
                               placeholder="Enter Last 3 Digits" required
                               maxlength="3" pattern="\d{3}" title="Please enter the 3-digit gatepass code">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search me-2"></i>Find
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <a href="#" class="btn btn-outline-dark btn-lg" data-bs-toggle="modal" data-bs-target="#scanModal">
                        <i class="fas fa-camera me-2"></i>Scan Barcode
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Search Results -->
    <?php if ($search_performed): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Search Results</h5>
            </div>
            <div class="card-body p-0">
                <?php if ($gatepasses && $gatepasses->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Gatepass #</th>
                                    <th>Requested By</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($gatepass = $gatepasses->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($gatepass['gatepass_number']); ?></td>
                                        <td><?php echo htmlspecialchars($gatepass['creator_name']); ?></td>
                                        <td><?php echo htmlspecialchars($gatepass['from_location']); ?></td>
                                        <td><?php echo htmlspecialchars($gatepass['to_location']); ?></td>
                                        <td><?php echo formatDateTime($gatepass['requested_date'] . ' ' . $gatepass['requested_time'], 'd M Y'); ?></td>
                                        <td>
                                            <?php if ($gatepass['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php elseif ($gatepass['status'] === 'approved_by_admin'): ?>
                                                <span class="badge bg-primary">Approved by Admin</span>
                                            <?php elseif ($gatepass['status'] === 'approved_by_security'): ?>
                                                <span class="badge bg-success">Verified</span>
                                            <?php elseif ($gatepass['status'] === 'declined'): ?>
                                                <span class="badge bg-danger">Declined</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="view_gatepass.php?id=<?php echo $gatepass['id']; ?>" 
                                                   class="btn btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($gatepass['status'] === 'approved_by_admin'): ?>
                                                    <a href="verify_gatepass.php?id=<?php echo $gatepass['id']; ?>" 
                                                       class="btn btn-success" title="Verify">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($gatepass['status'] === 'approved_by_security'): ?>
                                                    <a href="download_pdf.php?id=<?php echo $gatepass['id']; ?>" 
                                                       class="btn btn-secondary" title="Download PDF">
                                                        <i class="fas fa-download"></i>
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
                    <div class="text-center p-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5>No matching gatepasses found</h5>
                        <p class="text-muted">Try adjusting your search criteria</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
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
                        <div class="input-group-text bg-light">GP</div>
                        <input type="text" class="form-control" id="barcode-result" name="gatepass_number" 
                               placeholder="Last 3 Digits" maxlength="3" pattern="\d{3}" 
                               title="Please enter the 3-digit gatepass code">
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

<script>
    // Auto-focus on the gatepass number input field when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        const gatepassInput = document.querySelector('input[name="gatepass_number"]');
        if (gatepassInput) {
            gatepassInput.focus();
        }
    });
    
    // Auto-focus on the barcode result input when modal is shown
    document.getElementById('scanModal').addEventListener('shown.bs.modal', function () {
        document.getElementById('barcode-result').focus();
    });
</script>

<?php include '../includes/footer.php'; ?>