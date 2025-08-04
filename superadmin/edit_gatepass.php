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

// Get all active units from the database
$stmt = $conn->prepare("SELECT unit_name, unit_symbol FROM measurement_units WHERE is_active = 1 ORDER BY unit_type, unit_name");
$stmt->execute();
$units_result = $stmt->get_result();
$units = array();

// Store units for JavaScript use
while ($unit = $units_result->fetch_assoc()) {
    $units[] = array(
        'name' => $unit['unit_name'],
        'symbol' => $unit['unit_symbol']
    );
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_gatepass'])) {
    // Validate input
    $from_location = sanitizeInput($_POST['from_location']);
    $to_location = sanitizeInput($_POST['to_location']);
    $material_type = sanitizeInput($_POST['material_type']);
    $requested_date = sanitizeInput($_POST['requested_date']);
    $requested_time = sanitizeInput($_POST['requested_time']);
    $purpose = sanitizeInput($_POST['purpose']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update gatepass details
        $stmt = $conn->prepare("
            UPDATE gatepasses SET 
            from_location = ?,
            to_location = ?,
            material_type = ?,
            requested_date = ?,
            requested_time = ?,
            purpose = ?,
            updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ssssssi", $from_location, $to_location, $material_type, $requested_date, $requested_time, $purpose, $gatepass_id);
        $stmt->execute();
        
        // Update items if any
        if (isset($_POST['item_id']) && is_array($_POST['item_id'])) {
            foreach ($_POST['item_id'] as $index => $item_id) {
                $item_name = sanitizeInput($_POST['item_name'][$index]);
                $item_quantity = (float)$_POST['item_quantity'][$index];
                $item_unit = sanitizeInput($_POST['item_unit'][$index]);
                
                // Update if the item exists
                if (!empty($item_id) && is_numeric($item_id)) {
                    $stmt = $conn->prepare("
                        UPDATE gatepass_items SET 
                        item_name = ?,
                        quantity = ?,
                        unit = ?
                        WHERE id = ? AND gatepass_id = ?
                    ");
                    $stmt->bind_param("sdsii", $item_name, $item_quantity, $item_unit, $item_id, $gatepass_id);
                    $stmt->execute();
                }
                // Insert if it's a new item
                else if (!empty($item_name)) {
                    $stmt = $conn->prepare("
                        INSERT INTO gatepass_items (gatepass_id, item_name, quantity, unit)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->bind_param("isds", $gatepass_id, $item_name, $item_quantity, $item_unit);
                    $stmt->execute();
                }
            }
        }
        
        // Delete items if requested
        if (isset($_POST['delete_item_id']) && is_array($_POST['delete_item_id'])) {
            foreach ($_POST['delete_item_id'] as $delete_id) {
                if (is_numeric($delete_id)) {
                    $stmt = $conn->prepare("DELETE FROM gatepass_items WHERE id = ? AND gatepass_id = ?");
                    $stmt->bind_param("ii", $delete_id, $gatepass_id);
                    $stmt->execute();
                }
            }
        }
        
        // Log the action
        logAction($_SESSION['user_id'], 'GATEPASS_UPDATED', "Updated gatepass ID: $gatepass_id");
        
        $conn->commit();
        
        $_SESSION['flash_message'] = "Gatepass successfully updated";
        $_SESSION['flash_type'] = "success";
        
        // Redirect to view page
        header("Location: view_gatepass.php?id=$gatepass_id");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = "Failed to update gatepass: " . $e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
}

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

// Set page title
$page_title = "Edit Gatepass #" . $gatepass['gatepass_number'];

// Include header
include '../includes/header.php';

// Determine status class for styling
$status_class = '';
switch ($gatepass['status']) {
    case 'pending':
        $status_class = 'warning text-dark';
        break;
    case 'approved_by_admin':
        $status_class = 'primary';
        break;
    case 'approved_by_security':
        $status_class = 'success';
        break;
    case 'declined':
        $status_class = 'danger';
        break;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-edit me-2"></i>Edit Gatepass</h1>
        <div>
            <a href="view_gatepass.php?id=<?php echo $gatepass_id; ?>" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to View
            </a>
            <a href="manage_all_gatepasses.php" class="btn btn-outline-dark">
                <i class="fas fa-list me-2"></i>All Gatepasses
            </a>
        </div>
    </div>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Superadmin Mode:</strong> You are editing this gatepass with full privileges. All changes will be logged in the system.
    </div>
    
    <div class="card">
        <div class="card-header bg-<?php echo $status_class; ?> <?php if ($status_class !== 'warning') echo 'text-white'; ?>">
            <h5 class="mb-0">Gatepass #<?php echo htmlspecialchars($gatepass['gatepass_number']); ?></h5>
        </div>
        <div class="card-body">
            <form method="post" action="" id="editGatepassForm">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="gatepass_number" class="form-label">Gatepass Number</label>
                        <input type="text" class="form-control" id="gatepass_number" value="<?php echo htmlspecialchars($gatepass['gatepass_number']); ?>" readonly>
                        <div class="form-text">Gatepass number cannot be changed</div>
                    </div>
                    <div class="col-md-6">
                        <label for="creator_name" class="form-label">Created By</label>
                        <input type="text" class="form-control" id="creator_name" value="<?php echo htmlspecialchars($gatepass['creator_name']); ?>" readonly>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="from_location" class="form-label">From Location</label>
                        <input type="text" class="form-control" id="from_location" name="from_location" value="<?php echo htmlspecialchars($gatepass['from_location']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="to_location" class="form-label">To Location</label>
                        <input type="text" class="form-control" id="to_location" name="to_location" value="<?php echo htmlspecialchars($gatepass['to_location']); ?>" required>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label for="material_type" class="form-label">Material Type</label>
                        <select class="form-select" id="material_type" name="material_type" required>
                            <option value="">Select material type</option>
                            <option value="Returnable" <?php if ($gatepass['material_type'] == 'Returnable') echo 'selected'; ?>>Returnable</option>
                            <option value="Non-returnable" <?php if ($gatepass['material_type'] == 'Non-returnable') echo 'selected'; ?>>Non-returnable</option>
                            <option value="Raw Material" <?php if ($gatepass['material_type'] == 'Raw Material') echo 'selected'; ?>>Raw Material</option>
                            <option value="Equipment" <?php if ($gatepass['material_type'] == 'Equipment') echo 'selected'; ?>>Equipment</option>
                            <option value="Spare Parts" <?php if ($gatepass['material_type'] == 'Spare Parts') echo 'selected'; ?>>Spare Parts</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="requested_date" class="form-label">Requested Date</label>
                        <input type="date" class="form-control" id="requested_date" name="requested_date" value="<?php echo htmlspecialchars($gatepass['requested_date']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="requested_time" class="form-label">Requested Time</label>
                        <input type="time" class="form-control" id="requested_time" name="requested_time" value="<?php echo htmlspecialchars($gatepass['requested_time']); ?>" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="purpose" class="form-label">Purpose</label>
                    <textarea class="form-control" id="purpose" name="purpose" rows="3" required><?php echo htmlspecialchars($gatepass['purpose']); ?></textarea>
                </div>
                
                <!-- Gatepass Items -->
                <h5 class="mb-3 mt-5">Items</h5>
                <div id="itemsContainer">
                    <?php 
                    $item_counter = 0;
                    while ($item = $items->fetch_assoc()): 
                        $item_counter++;
                    ?>
                        <div class="item-row row mb-3 align-items-center">
                            <div class="col-md-5">
                                <label class="form-label">Item Name</label>
                                <input type="hidden" name="item_id[]" value="<?php echo $item['id']; ?>">
                                <input type="text" class="form-control" name="item_name[]" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="item_quantity[]" value="<?php echo htmlspecialchars($item['quantity']); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Unit</label>                                <select class="form-select" name="item_unit[]" required>
                                    <option value="">Select unit</option>
                                    <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo htmlspecialchars($unit['name']); ?>" <?php if ($item['unit'] == $unit['name']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($unit['name']); ?> (<?php echo htmlspecialchars($unit['symbol']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                    <option value="Other" <?php if (!in_array($item['unit'], array_column($units, 'name'))) echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="d-block">&nbsp;</label>
                                <button type="button" class="btn btn-danger btn-remove-item" data-item-id="<?php echo $item['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="mb-4">
                    <button type="button" id="btnAddItem" class="btn btn-outline-success">
                        <i class="fas fa-plus me-2"></i>Add Item
                    </button>
                </div>
                
                <div id="deletedItemsContainer"></div>
                
                <div class="mt-5">
                    <button type="submit" name="update_gatepass" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Gatepass
                    </button>
                    <a href="view_gatepass.php?id=<?php echo $gatepass_id; ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const itemsContainer = document.getElementById('itemsContainer');
        const btnAddItem = document.getElementById('btnAddItem');
        const deletedItemsContainer = document.getElementById('deletedItemsContainer');
        let itemCounter = <?php echo $item_counter; ?>;
          // Prepare unit options HTML
        let unitOptionsHtml = '';
        if (window.availableUnits && Array.isArray(window.availableUnits)) {
            window.availableUnits.forEach(function(unit) {
                unitOptionsHtml += `<option value="${unit.name}">${unit.name} (${unit.symbol})</option>`;
            });
        }
        
        // Add new item
        btnAddItem.addEventListener('click', function() {
            const newItem = document.createElement('div');
            newItem.className = 'item-row row mb-3 align-items-center';
            newItem.innerHTML = `
                <div class="col-md-5">
                    <label class="form-label">Item Name</label>
                    <input type="hidden" name="item_id[]" value="">
                    <input type="text" class="form-control" name="item_name[]" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantity</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="item_quantity[]" value="1" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit</label>                    
                    <select class="form-select" name="item_unit[]" required>
                        <option value="">Select unit</option>
                        ${unitOptionsHtml}
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="d-block">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-remove-item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            itemsContainer.appendChild(newItem);
            
            // Add event listener to new remove button
            const newRemoveButton = newItem.querySelector('.btn-remove-item');
            newRemoveButton.addEventListener('click', function() {
                newItem.remove();
            });
            
            // Increment counter
            itemCounter++;
        });
        
        // Remove item (for existing items)
        document.querySelectorAll('.btn-remove-item').forEach(button => {
            button.addEventListener('click', function() {
                const itemId = this.getAttribute('data-item-id');
                if (itemId) {
                    // Add hidden input to track deleted items
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'delete_item_id[]';
                    hiddenInput.value = itemId;
                    deletedItemsContainer.appendChild(hiddenInput);
                }
                
                // Remove the row
                this.closest('.item-row').remove();
            });
        });
          // Form validation
        document.getElementById('editGatepassForm').addEventListener('submit', function(e) {
            const itemRows = document.querySelectorAll('.item-row');
            if (itemRows.length === 0) {
                e.preventDefault();
                alert('At least one item must be added to the gatepass.');
            }
        });
        
        // Pass units data to JavaScript
        window.availableUnits = <?php echo json_encode($units); ?>;
    });
</script>

<?php 
// Close database connection
$conn->close();

include '../includes/footer.php'; 
?>
