<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Create units table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS measurement_units (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unit_name VARCHAR(50) NOT NULL UNIQUE,
        unit_symbol VARCHAR(20),
        unit_type ENUM('length', 'weight', 'volume', 'quantity', 'other') DEFAULT 'other',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Check if table is empty and populate with default values
$result = $conn->query("SELECT COUNT(*) as count FROM measurement_units");
$count = $result->fetch_assoc()['count'];

if ($count == 0) {
    // Insert default units
    $default_units = [
        ['Pieces', 'pcs', 'quantity', 1],
        ['Kilograms', 'kg', 'weight', 1],
        ['Grams', 'g', 'weight', 1],
        ['Liters', 'L', 'volume', 1],
        ['Milliliters', 'ml', 'volume', 1],
        ['Meters', 'm', 'length', 1],
        ['Centimeters', 'cm', 'length', 1],
        ['Feet', 'ft', 'length', 1],
        ['Inches', 'in', 'length', 1],
        ['Boxes', 'box', 'quantity', 1],
        ['Pairs', 'pair', 'quantity', 1],
        ['Units', 'unit', 'quantity', 1],
        ['Sets', 'set', 'quantity', 1],
        ['Tons', 'ton', 'weight', 1],
        ['Square Meters', 'm²', 'other', 1],
        ['Cubic Meters', 'm³', 'volume', 1]
    ];
    
    $stmt = $conn->prepare("INSERT INTO measurement_units (unit_name, unit_symbol, unit_type, is_active) VALUES (?, ?, ?, ?)");
    
    foreach ($default_units as $unit) {
        $stmt->bind_param("sssi", $unit[0], $unit[1], $unit[2], $unit[3]);
        $stmt->execute();
    }
}

// Process form submission for adding new unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_unit'])) {
    $unit_name = sanitizeInput($_POST['unit_name']);
    $unit_symbol = sanitizeInput($_POST['unit_symbol']);
    $unit_type = sanitizeInput($_POST['unit_type']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Check if unit name already exists
    $stmt = $conn->prepare("SELECT id FROM measurement_units WHERE unit_name = ?");
    $stmt->bind_param("s", $unit_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['flash_message'] = "Unit with this name already exists";
        $_SESSION['flash_type'] = "danger";
    } else {
        // Insert new unit
        $stmt = $conn->prepare("INSERT INTO measurement_units (unit_name, unit_symbol, unit_type, is_active) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $unit_name, $unit_symbol, $unit_type, $is_active);
        
        if ($stmt->execute()) {
            logAction($_SESSION['user_id'], 'UNIT_ADDED', "Added new measurement unit: $unit_name");
            $_SESSION['flash_message'] = "Unit added successfully";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Failed to add unit";
            $_SESSION['flash_type'] = "danger";
        }
    }
}

// Process form submission for updating unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_unit'])) {
    $unit_id = (int)$_POST['unit_id'];
    $unit_name = sanitizeInput($_POST['unit_name']);
    $unit_symbol = sanitizeInput($_POST['unit_symbol']);
    $unit_type = sanitizeInput($_POST['unit_type']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Check if unit name already exists for a different unit
    $stmt = $conn->prepare("SELECT id FROM measurement_units WHERE unit_name = ? AND id != ?");
    $stmt->bind_param("si", $unit_name, $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['flash_message'] = "Unit with this name already exists";
        $_SESSION['flash_type'] = "danger";
    } else {
        // Update unit
        $stmt = $conn->prepare("UPDATE measurement_units SET unit_name = ?, unit_symbol = ?, unit_type = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("sssii", $unit_name, $unit_symbol, $unit_type, $is_active, $unit_id);
        
        if ($stmt->execute()) {
            logAction($_SESSION['user_id'], 'UNIT_UPDATED', "Updated measurement unit: $unit_name (ID: $unit_id)");
            $_SESSION['flash_message'] = "Unit updated successfully";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Failed to update unit";
            $_SESSION['flash_type'] = "danger";
        }
    }
}

// Process form submission for deleting unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_unit'])) {
    $unit_id = (int)$_POST['unit_id'];
    
    // Get unit details for logging
    $stmt = $conn->prepare("SELECT unit_name FROM measurement_units WHERE id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $unit = $result->fetch_assoc();
    
    if ($unit) {
        // Delete unit
        $stmt = $conn->prepare("DELETE FROM measurement_units WHERE id = ?");
        $stmt->bind_param("i", $unit_id);
        
        if ($stmt->execute()) {
            logAction($_SESSION['user_id'], 'UNIT_DELETED', "Deleted measurement unit: {$unit['unit_name']} (ID: $unit_id)");
            $_SESSION['flash_message'] = "Unit deleted successfully";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Failed to delete unit";
            $_SESSION['flash_type'] = "danger";
        }
    } else {
        $_SESSION['flash_message'] = "Unit not found";
        $_SESSION['flash_type'] = "danger";
    }
}

// Set page title
$page_title = "Manage Measurement Units";

// Get all units
$stmt = $conn->prepare("SELECT * FROM measurement_units ORDER BY unit_type, unit_name");
$stmt->execute();
$units = $stmt->get_result();

// Include header
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-ruler-combined me-2"></i>Manage Measurement Units</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUnitModal">
            <i class="fas fa-plus me-2"></i>Add New Unit
        </button>
    </div>
    
    <div class="card">
        <div class="card-body">
            <?php if ($units->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Unit Name</th>
                                <th>Symbol</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($unit = $units->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $unit['id']; ?></td>
                                    <td><?php echo htmlspecialchars($unit['unit_name']); ?></td>
                                    <td><?php echo htmlspecialchars($unit['unit_symbol']); ?></td>
                                    <td>
                                        <?php 
                                        $badge_class = '';
                                        switch ($unit['unit_type']) {
                                            case 'length':
                                                $badge_class = 'primary';
                                                break;
                                            case 'weight':
                                                $badge_class = 'success';
                                                break;
                                            case 'volume':
                                                $badge_class = 'info';
                                                break;
                                            case 'quantity':
                                                $badge_class = 'warning';
                                                break;
                                            default:
                                                $badge_class = 'secondary';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo ucfirst($unit['unit_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($unit['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-unit-btn" 
                                                data-id="<?php echo $unit['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($unit['unit_name']); ?>"
                                                data-symbol="<?php echo htmlspecialchars($unit['unit_symbol']); ?>"
                                                data-type="<?php echo $unit['unit_type']; ?>"
                                                data-active="<?php echo $unit['is_active']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editUnitModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-unit-btn"
                                                data-id="<?php echo $unit['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($unit['unit_name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#deleteUnitModal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center p-5">
                    <i class="fas fa-ruler-combined fa-4x text-muted mb-3"></i>
                    <h5>No Units Found</h5>
                    <p class="text-muted">Add a new unit to get started</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Unit Modal -->
<div class="modal fade" id="addUnitModal" tabindex="-1" aria-labelledby="addUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUnitModalLabel">Add New Measurement Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="unit_name" class="form-label">Unit Name</label>
                        <input type="text" class="form-control" id="unit_name" name="unit_name" required>
                        <div class="form-text">Enter the full name of the unit (e.g., Kilograms)</div>
                    </div>
                    <div class="mb-3">
                        <label for="unit_symbol" class="form-label">Unit Symbol</label>
                        <input type="text" class="form-control" id="unit_symbol" name="unit_symbol" required>
                        <div class="form-text">Enter the symbol or abbreviation (e.g., kg)</div>
                    </div>
                    <div class="mb-3">
                        <label for="unit_type" class="form-label">Unit Type</label>
                        <select class="form-select" id="unit_type" name="unit_type" required>
                            <option value="length">Length</option>
                            <option value="weight">Weight</option>
                            <option value="volume">Volume</option>
                            <option value="quantity" selected>Quantity</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_unit" class="btn btn-primary">Save Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Unit Modal -->
<div class="modal fade" id="editUnitModal" tabindex="-1" aria-labelledby="editUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUnitModalLabel">Edit Measurement Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_unit_id" name="unit_id">
                    <div class="mb-3">
                        <label for="edit_unit_name" class="form-label">Unit Name</label>
                        <input type="text" class="form-control" id="edit_unit_name" name="unit_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_unit_symbol" class="form-label">Unit Symbol</label>
                        <input type="text" class="form-control" id="edit_unit_symbol" name="unit_symbol" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_unit_type" class="form-label">Unit Type</label>
                        <select class="form-select" id="edit_unit_type" name="unit_type" required>
                            <option value="length">Length</option>
                            <option value="weight">Weight</option>
                            <option value="volume">Volume</option>
                            <option value="quantity">Quantity</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_unit" class="btn btn-primary">Update Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Unit Modal -->
<div class="modal fade" id="deleteUnitModal" tabindex="-1" aria-labelledby="deleteUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUnitModalLabel">Delete Measurement Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="delete_unit_id" name="unit_id">
                    <p>Are you sure you want to delete the unit <strong id="delete_unit_name"></strong>?</p>
                    <p class="text-danger">This action cannot be undone. It may affect gatepasses that use this unit.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_unit" class="btn btn-danger">Delete Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        if (typeof $.fn.DataTable !== 'undefined') {
            $('table').DataTable({
                "order": [[0, "asc"]],
                "pageLength": 25
            });
        }
        
        // Set up edit unit modal
        document.querySelectorAll('.edit-unit-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                document.getElementById('edit_unit_id').value = this.getAttribute('data-id');
                document.getElementById('edit_unit_name').value = this.getAttribute('data-name');
                document.getElementById('edit_unit_symbol').value = this.getAttribute('data-symbol');
                document.getElementById('edit_unit_type').value = this.getAttribute('data-type');
                document.getElementById('edit_is_active').checked = this.getAttribute('data-active') === '1';
            });
        });
        
        // Set up delete unit modal
        document.querySelectorAll('.delete-unit-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                document.getElementById('delete_unit_id').value = this.getAttribute('data-id');
                document.getElementById('delete_unit_name').textContent = this.getAttribute('data-name');
            });
        });
    });
</script>

<?php
// Close database connection
$conn->close();

// Include footer
include '../includes/footer.php';
?>
