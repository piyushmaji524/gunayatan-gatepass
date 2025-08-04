<?php
require_once '../includes/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Set page title
$page_title = "All Gatepasses";

// Connect to database
$conn = connectDB();

// Handle filter parameters
$status_filter = '';
$status_param = '';
$date_filter = '';
$user_filter = '';
$location_filter = '';

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $_GET['status'];
    
    if ($status == 'pending') {
        $status_filter = " AND g.status = 'pending' ";
        $status_param = "pending";
    } elseif ($status == 'approved_by_admin') {
        $status_filter = " AND g.status = 'approved_by_admin' ";
        $status_param = "approved_by_admin";
    } elseif ($status == 'approved_by_security') {
        $status_filter = " AND g.status = 'approved_by_security' ";
        $status_param = "approved_by_security";
    } elseif ($status == 'declined') {
        $status_filter = " AND g.status = 'declined' ";
        $status_param = "declined";
    }
}

if (isset($_GET['date']) && !empty($_GET['date'])) {
    $date = $conn->real_escape_string($_GET['date']);
    $date_filter = " AND DATE(g.requested_date) = '$date' ";
}

if (isset($_GET['user']) && !empty($_GET['user'])) {
    $user = (int)$_GET['user'];
    $user_filter = " AND g.created_by = $user ";
}

if (isset($_GET['location']) && !empty($_GET['location'])) {
    $location = $conn->real_escape_string($_GET['location']);
    $location_filter = " AND (g.from_location LIKE '%$location%' OR g.to_location LIKE '%$location%') ";
}

// Get all gatepasses with filter
$query = "
    SELECT g.*, 
           u.name as creator_name,
           admin.name as admin_name, 
           security.name as security_name
    FROM gatepasses g
    LEFT JOIN users u ON g.created_by = u.id
    LEFT JOIN users admin ON g.admin_approved_by = admin.id
    LEFT JOIN users security ON g.security_approved_by = security.id
    WHERE 1=1 $status_filter $date_filter $user_filter $location_filter
    ORDER BY g.created_at DESC
";

$result = $conn->query($query);
$gatepasses = $result;

// Get all users for filter dropdown
$users_result = $conn->query("SELECT id, name FROM users ORDER BY name");
$users = $users_result;

// Include header
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>
        <i class="fas fa-clipboard-list me-2"></i>All Gatepasses
        <?php if (!empty($status_param)): ?>
            <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $status_param)); ?></span>
        <?php endif; ?>
    </h2>
</div>

<div class="card mb-4">
    <div class="card-header bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Filters</h5>
            <a href="all_gatepasses.php" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
        </div>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select filter-select" id="status" name="status" data-filter="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo ($status_param == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved_by_admin" <?php echo ($status_param == 'approved_by_admin') ? 'selected' : ''; ?>>Admin Approved</option>
                    <option value="approved_by_security" <?php echo ($status_param == 'approved_by_security') ? 'selected' : ''; ?>>Fully Approved</option>
                    <option value="declined" <?php echo ($status_param == 'declined') ? 'selected' : ''; ?>>Declined</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date" class="form-label">Date</label>
                <input type="date" class="form-control filter-select" id="date" name="date" data-filter="date" 
                       value="<?php echo isset($_GET['date']) ? htmlspecialchars($_GET['date']) : ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="user" class="form-label">Created By</label>
                <select class="form-select filter-select" id="user" name="user" data-filter="user">
                    <option value="">All Users</option>
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo (isset($_GET['user']) && $_GET['user'] == $user['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="location" class="form-label">Location</label>
                <input type="text" class="form-control filter-select" id="location" name="location" data-filter="location" 
                       placeholder="From/To location"
                       value="<?php echo isset($_GET['location']) ? htmlspecialchars($_GET['location']) : ''; ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i>Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0">Gatepass List</h5>
            </div>
            <div class="col-md-6 d-flex justify-content-md-end mt-3 mt-md-0">
                <div class="input-group">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search gatepasses...">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div class="dropdown ms-2">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        Export
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="exportTable('pdf')"><i class="fas fa-file-pdf me-2"></i>Export PDF</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="exportTable('excel')"><i class="fas fa-file-excel me-2"></i>Export Excel</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="exportTable('csv')"><i class="fas fa-file-csv me-2"></i>Export CSV</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped table-export">
                <thead class="table-light">
                    <tr>
                        <th>Gatepass #</th>
                        <th>From → To</th>
                        <th>Material</th>
                        <th>Created By</th>
                        <th>Date & Time</th>
                        <th>Status</th>
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
                        <td><?php echo htmlspecialchars($gatepass['material_type']); ?></td>
                        <td><?php echo htmlspecialchars($gatepass['creator_name']); ?></td>
                        <td>
                            <?php 
                            echo date('d M Y', strtotime($gatepass['requested_date'])); 
                            echo '<br><small class="text-muted">';
                            echo date('h:i A', strtotime($gatepass['requested_time']));
                            echo '</small>';
                            ?>
                        </td>
                        <td data-status="<?php echo htmlspecialchars($gatepass['status']); ?>">
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
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if ($gatepass['status'] == 'pending'): ?>
                                <a href="edit_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-info text-white">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="approve_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-check"></i>
                                </a>
                                <a href="decline_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-danger">
                                    <i class="fas fa-times"></i>
                                </a>
                                
                                <?php elseif ($gatepass['status'] == 'approved_by_admin'): ?>
                                <!-- For approved-by-admin, check if security approved within last hour -->
                                <?php
                                $can_decline = false;
                                if ($gatepass['security_approved_at'] !== null) {
                                    $security_time = strtotime($gatepass['security_approved_at']);
                                    $one_hour_ago = time() - 3600; // 1 hour in seconds
                                    $can_decline = ($security_time > $one_hour_ago);
                                }
                                ?>
                                
                                <?php if ($can_decline): ?>
                                <a href="decline_gatepass.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-danger">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php endif; ?>
                                
                                <?php if ($gatepass['status'] == 'approved_by_admin' || $gatepass['status'] == 'approved_by_security'): ?>
                                <a href="download_pdf.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-secondary" target="_blank">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-3">
                            No gatepasses found
                            <?php if (!empty($status_param)): ?>
                            with status: <?php echo ucfirst(str_replace('_', ' ', $status_param)); ?>
                            <?php endif; ?>
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