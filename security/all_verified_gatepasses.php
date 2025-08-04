<?php
// Disable output buffering and enable error display
ob_end_clean();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';

// Check if user is logged in and has security role
if (!isLoggedIn() || $_SESSION['role'] != 'security') {
    header("Location: ../index.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Set default pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Add error handling for SQL errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Count total verified gatepasses
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM gatepasses 
        WHERE status = 'approved_by_security'
    ");
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $items_per_page);

    // Get verified gatepasses with pagination
    $stmt = $conn->prepare("
        SELECT g.*, 
               creator.name as creator_name,
               security.name as security_name
        FROM gatepasses g
        LEFT JOIN users creator ON g.created_by = creator.id
        LEFT JOIN users security ON g.security_approved_by = security.id
        WHERE g.status = 'approved_by_security'
        ORDER BY g.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $items_per_page, $offset);
    $stmt->execute();
    $verified_gatepasses = $stmt->get_result();
} catch (Exception $e) {
    // Log error and continue with empty result set
    error_log("Database error in all_verified_gatepasses.php: " . $e->getMessage());
    $verified_gatepasses = new mysqli_result();
    $total_records = 0;
    $total_pages = 1;
}

// Set page title
$page_title = "Verified Gatepasses";

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-check-circle text-success me-2"></i>Verified Gatepasses</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <a href="search_gatepass.php" class="btn btn-primary">
                <i class="fas fa-search me-2"></i>Search Gatepass
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Verified Gatepasses</h5>
        </div>
        <div class="card-body p-0">
            <?php if ($verified_gatepasses->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Gatepass #</th>
                                <th>Requested By</th>
                                <th>Verified By</th>
                                <th>Verified Date</th>
                                <th>Type</th>
                                <th>Valid Until</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($gatepass = $verified_gatepasses->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($gatepass['gatepass_number']); ?></td>
                                    <td><?php echo htmlspecialchars($gatepass['creator_name']); ?></td>
                                    <td><?php echo htmlspecialchars($gatepass['security_name']); ?></td>                                    <td>
                                        <?php 
                                        try {
                                            echo !empty($gatepass['security_approved_at']) ? date('d M Y H:i', strtotime($gatepass['security_approved_at'])) : 'N/A';
                                        } catch (Exception $e) {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $gatepass['type'] ?? 'unknown')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($gatepass['valid_until'])): ?>
                                            <?php 
                                            try {
                                                echo date('d M Y H:i', strtotime($gatepass['valid_until'])); 
                                            } catch (Exception $e) {
                                                echo 'N/A';
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="verified_gatepasses.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-sm btn-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="download_pdf.php?id=<?php echo $gatepass['id']; ?>" class="btn btn-sm btn-success" title="Download PDF">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="p-3">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1">First</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">First</span>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link">Previous</span>
                                    </li>
                                <?php endif; ?>

                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?>">Last</a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Next</span>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link">Last</span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center p-5">
                    <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                    <h5>No Verified Gatepasses Found</h5>
                    <p class="text-muted">There are currently no gatepasses that have been verified.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Close database connection
$conn->close();

// Include footer
include '../includes/footer.php'; 
?>