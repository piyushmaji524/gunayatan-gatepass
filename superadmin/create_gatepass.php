<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

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

// Get list of all users for the "created by" dropdown
$stmt = $conn->prepare("SELECT id, name, department, role FROM users ORDER BY name");
$stmt->execute();
$users = $stmt->get_result();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_gatepass'])) {
    // Validate input
    $created_by_user = (int)$_POST['created_by_user'];
    $from_location = sanitizeInput($_POST['from_location']);
    $to_location = sanitizeInput($_POST['to_location']);
    $material_type = sanitizeInput($_POST['material_type']);
    $requested_date = sanitizeInput($_POST['requested_date']);
    $requested_time = sanitizeInput($_POST['requested_time']);
    $purpose = sanitizeInput($_POST['purpose']);
    
    // Validate that user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $created_by_user);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    if ($user_result->num_rows !== 1) {
        $_SESSION['flash_message'] = "Invalid user selected";
        $_SESSION['flash_type'] = "danger";
    } else {
        // Generate a unique gatepass number
        $today = date('Ymd');
        $stmt = $conn->prepare("SELECT MAX(gatepass_number) as max_number FROM gatepasses WHERE gatepass_number LIKE ?");
        $number_prefix = "GP-$today-%";
        $stmt->bind_param("s", $number_prefix);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['max_number']) {
            $last_number = (int)substr($row['max_number'], -4);
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        $gatepass_number = "GP-$today-" . str_pad($new_number, 4, "0", STR_PAD_LEFT);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create gatepass
            $stmt = $conn->prepare("
                INSERT INTO gatepasses (
                    gatepass_number, created_by, from_location, to_location, 
                    material_type, requested_date, requested_time, purpose, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("sissssss", 
                $gatepass_number, $created_by_user, $from_location, $to_location,
                $material_type, $requested_date, $requested_time, $purpose
            );
            $stmt->execute();
            
            $gatepass_id = $conn->insert_id;
            
            // Add items
            if (isset($_POST['item_name']) && is_array($_POST['item_name'])) {
                foreach ($_POST['item_name'] as $index => $item_name) {
                    if (!empty($item_name)) {
                        $quantity = (float)$_POST['item_quantity'][$index];
                        $unit = sanitizeInput($_POST['item_unit'][$index]);
                        
                        $stmt = $conn->prepare("
                            INSERT INTO gatepass_items (gatepass_id, item_name, quantity, unit)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->bind_param("isds", $gatepass_id, $item_name, $quantity, $unit);
                        $stmt->execute();
                    }
                }
            }
            
            // Log the action
            $action_details = "Created gatepass ID: $gatepass_id, Number: $gatepass_number for user ID: $created_by_user";
            logAction($_SESSION['user_id'], 'GATEPASS_CREATED', $action_details);
            
            $conn->commit();
            
            $_SESSION['flash_message'] = "Gatepass #$gatepass_number successfully created";
            $_SESSION['flash_type'] = "success";
            
            // Redirect to view page
            header("Location: view_gatepass.php?id=$gatepass_id");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = "Failed to create gatepass: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
    }
}

// Set page title
$page_title = "Create New Gatepass";

// Include header
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-plus-circle me-2"></i>Create New Gatepass</h1>
        <div>
            <a href="manage_all_gatepasses.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to All Gatepasses
            </a>
        </div>
    </div>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Superadmin Mode:</strong> You are creating a new gatepass on behalf of a user. This action will be logged in the system.
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">New Gatepass Details</h5>
        </div>
        <div class="card-body">
            <form method="post" action="" id="createGatepassForm">
                <!-- User Selection -->
                <div class="mb-4">
                    <label for="created_by_user" class="form-label">Create On Behalf Of User</label>
                    <select class="form-select" id="created_by_user" name="created_by_user" required>
                        <option value="">Select user</option>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['name']); ?> 
                                (<?php echo ucfirst($user['role']); ?>) - 
                                <?php echo htmlspecialchars($user['department']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="form-text">The selected user will be registered as the creator of this gatepass</div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="from_location" class="form-label">From Location</label>
                        <input type="text" class="form-control" id="from_location" name="from_location" required>
                    </div>
                    <div class="col-md-6">
                        <label for="to_location" class="form-label">To Location</label>
                        <input type="text" class="form-control" id="to_location" name="to_location" required>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label for="material_type" class="form-label">Material Type</label>
                        <select class="form-select" id="material_type" name="material_type" required>
                            <option value="">Select material type</option>
                            <option value="Returnable">Returnable</option>
                            <option value="Non-returnable">Non-returnable</option>
                            <option value="Raw Material">Raw Material</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Spare Parts">Spare Parts</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="requested_date" class="form-label">Requested Date</label>
                        <input type="date" class="form-control" id="requested_date" name="requested_date" required>
                    </div>
                    <div class="col-md-4">
                        <label for="requested_time" class="form-label">Requested Time</label>
                        <input type="time" class="form-control" id="requested_time" name="requested_time" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="purpose" class="form-label">Purpose</label>
                    <textarea class="form-control" id="purpose" name="purpose" rows="3" required></textarea>
                </div>
                
                <!-- Gatepass Items -->
                <h5 class="mb-3 mt-5">Items</h5>
                <div id="itemsContainer">
                    <div class="item-row row mb-3 align-items-center">
                        <div class="col-md-5">
                            <label class="form-label">Item Name</label>
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
                                <option value="Pieces">Pieces</option>
                                <option value="Kg">Kg</option>
                                <option value="Liters">Liters</option>
                                <option value="Meters">Meters</option>
                                <option value="Boxes">Boxes</option>
                                <option value="Pairs">Pairs</option>
                                <option value="Sets">Sets</option>
                                <option value="Units">Units</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="d-block">&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-remove-item" disabled>
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <button type="button" id="btnAddItem" class="btn btn-outline-success">
                        <i class="fas fa-plus me-2"></i>Add Item
                    </button>
                </div>
                
                <div class="mt-5">
                    <button type="submit" name="create_gatepass" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create Gatepass
                    </button>
                    <a href="manage_all_gatepasses.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const itemsContainer = document.getElementById('itemsContainer');
        const btnAddItem = document.getElementById('btnAddItem');
        
        // Set default date to today
        document.getElementById('requested_date').value = '<?php echo date('Y-m-d'); ?>';
        
        // Add new item
        btnAddItem.addEventListener('click', function() {
            const newItem = document.createElement('div');
            newItem.className = 'item-row row mb-3 align-items-center';
            newItem.innerHTML = `
                <div class="col-md-5">
                    <label class="form-label">Item Name</label>
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
                        <option value="Pieces">Pieces</option>
                        <option value="Kg">Kg</option>
                        <option value="Liters">Liters</option>
                        <option value="Meters">Meters</option>
                        <option value="Boxes">Boxes</option>
                        <option value="Pairs">Pairs</option>
                        <option value="Sets">Sets</option>
                        <option value="Units">Units</option>
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
                updateRemoveButtons();
            });
            
            updateRemoveButtons();
        });
        
        // Function to update remove buttons (disable if only one item)
        function updateRemoveButtons() {
            const removeButtons = document.querySelectorAll('.btn-remove-item');
            const disableButtons = removeButtons.length <= 1;
            
            removeButtons.forEach(button => {
                button.disabled = disableButtons;
            });
        }
        
        // Form validation
        document.getElementById('createGatepassForm').addEventListener('submit', function(e) {
            const itemRows = document.querySelectorAll('.item-row');
            let hasErrors = false;
            
            // Check for at least one item
            if (itemRows.length === 0) {
                e.preventDefault();
                alert('At least one item must be added to the gatepass.');
                hasErrors = true;
            }
            
            // Basic validation for each item
            itemRows.forEach(row => {
                const nameInput = row.querySelector('input[name="item_name[]"]');
                const quantityInput = row.querySelector('input[name="item_quantity[]"]');
                const unitSelect = row.querySelector('select[name="item_unit[]"]');
                
                if (!nameInput.value || !quantityInput.value || !unitSelect.value) {
                    e.preventDefault();
                    if (!hasErrors) {
                        alert('Please complete all item details.');
                        hasErrors = true;
                    }
                }            });        });
        
        // Pass units data to JavaScript
        window.availableUnits = <?php echo json_encode($units); ?>;
    });
</script>

<?php 
// Close database connection
$conn->close();

include '../includes/footer.php'; 
?>
