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

// Get gatepass details
$stmt = $conn->prepare("
    SELECT g.*, u.name as creator_name
    FROM gatepasses g
    JOIN users u ON g.created_by = u.id
    WHERE g.id = ?
");
$stmt->bind_param("i", $gatepass_id);
$stmt->execute();
$result = $stmt->get_result();

// If no gatepass found
if ($result->num_rows !== 1) {
    $conn->close();
    redirectWithMessage("all_gatepasses.php", "Gatepass not found", "danger");
}

$gatepass = $result->fetch_assoc();

// Get gatepass items
$stmt = $conn->prepare("SELECT * FROM gatepass_items WHERE gatepass_id = ?");
$stmt->bind_param("i", $gatepass_id);
$stmt->execute();
$items = $stmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and validate form data
    $from_location = sanitizeInput($_POST['from_location']);
    $to_location = sanitizeInput($_POST['to_location']);
    $material_type = sanitizeInput($_POST['material_type']);
    $purpose = sanitizeInput($_POST['purpose']);
    $requested_date = sanitizeInput($_POST['requested_date']);
    $requested_time = sanitizeInput($_POST['requested_time']);
    
    // Validate inputs
    $errors = array();
    
    if (empty($from_location)) {
        $errors[] = "From location is required";
    }
    
    if (empty($to_location)) {
        $errors[] = "To location is required";
    }
    
    if (empty($material_type)) {
        $errors[] = "Material type is required";
    }
    
    if (empty($requested_date)) {
        $errors[] = "Requested date is required";
    }
    
    if (empty($requested_time)) {
        $errors[] = "Requested time is required";
    }
    
    // Process items
    $item_names = $_POST['item_name'] ?? array();
    $item_quantities = $_POST['item_quantity'] ?? array();
    $item_units = $_POST['item_unit'] ?? array();
    $item_ids = $_POST['item_id'] ?? array();
    
    // Validate items
    if (empty($item_names) || count($item_names) === 0) {
        $errors[] = "At least one item is required";
    } else {
        for ($i = 0; $i < count($item_names); $i++) {
            if (empty($item_names[$i])) {
                $errors[] = "Item name cannot be empty";
                break;
            }
            if (empty($item_quantities[$i]) || !is_numeric($item_quantities[$i]) || $item_quantities[$i] <= 0) {
                $errors[] = "Item quantity must be a positive number";
                break;
            }
            if (empty($item_units[$i])) {
                $errors[] = "Item unit cannot be empty";
                break;
            }
        }
    }
    
    // If no errors, update the gatepass
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Update gatepass
            $stmt = $conn->prepare("
                UPDATE gatepasses 
                SET from_location = ?, 
                    to_location = ?, 
                    material_type = ?, 
                    purpose = ?, 
                    requested_date = ?, 
                    requested_time = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssssi", $from_location, $to_location, $material_type, $purpose, $requested_date, $requested_time, $gatepass_id);
            $stmt->execute();
            
            // Delete all existing items for this gatepass
            $stmt = $conn->prepare("DELETE FROM gatepass_items WHERE gatepass_id = ?");
            $stmt->bind_param("i", $gatepass_id);
            $stmt->execute();
            
            // Insert new items
            $stmt = $conn->prepare("
                INSERT INTO gatepass_items (gatepass_id, item_name, quantity, unit) 
                VALUES (?, ?, ?, ?)
            ");
            
            for ($i = 0; $i < count($item_names); $i++) {
                $stmt->bind_param("isds", $gatepass_id, $item_names[$i], $item_quantities[$i], $item_units[$i]);
                $stmt->execute();
            }
            
            // Log the activity
            logActivity($_SESSION['user_id'], 'GATEPASS_UPDATED', "Admin updated gatepass " . $gatepass['gatepass_number']);
            
            // Commit transaction
            $conn->commit();
            
            // Redirect with success message
            redirectWithMessage("view_gatepass.php?id=$gatepass_id", "Gatepass updated successfully", "success");
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Set page title
$page_title = "Edit Gatepass #" . $gatepass['gatepass_number'];

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-edit me-2"></i>Edit Gatepass</h1>
        <div>
            <a href="all_gatepasses.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to All Gatepasses
            </a>
        </div>
    </div>
    
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Gatepass #<?php echo htmlspecialchars($gatepass['gatepass_number']); ?></h5>
        </div>
        <div class="card-body">
            <form action="" method="post" id="edit-gatepass-form">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="from_location" class="form-label">From Location</label>
                            <input type="text" class="form-control" id="from_location" name="from_location" 
                                   value="<?php echo htmlspecialchars($gatepass['from_location']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="to_location" class="form-label">To Location</label>
                            <input type="text" class="form-control" id="to_location" name="to_location" 
                                   value="<?php echo htmlspecialchars($gatepass['to_location']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="material_type" class="form-label">Material Type</label>
                            <input type="text" class="form-control" id="material_type" name="material_type" 
                                   value="<?php echo htmlspecialchars($gatepass['material_type']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="requested_date" class="form-label">Requested Date</label>
                            <input type="date" class="form-control" id="requested_date" name="requested_date" 
                                   value="<?php echo htmlspecialchars($gatepass['requested_date']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="requested_time" class="form-label">Requested Time</label>
                            <input type="time" class="form-control" id="requested_time" name="requested_time" 
                                   value="<?php echo htmlspecialchars($gatepass['requested_time']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="purpose" class="form-label">Purpose</label>
                    <textarea class="form-control" id="purpose" name="purpose" rows="3"><?php echo htmlspecialchars($gatepass['purpose']); ?></textarea>
                </div>
                
                <!-- Items Section -->
                <h4 class="mb-3">Items</h4>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered" id="items-table">
                        <thead class="table-light">
                            <tr>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $item_count = 0;
                            while ($item = $items->fetch_assoc()): 
                                $item_count++;
                            ?>
                                <tr class="item-row">
                                    <td>
                                        <input type="hidden" name="item_id[]" value="<?php echo $item['id']; ?>">
                                        <input type="text" class="form-control" name="item_name[]" 
                                               value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control" name="item_quantity[]" 
                                               value="<?php echo htmlspecialchars($item['quantity']); ?>" min="0.01" step="0.01" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="item_unit[]" 
                                               value="<?php echo htmlspecialchars($item['unit']); ?>" required>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm remove-item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mb-4">
                    <button type="button" class="btn btn-outline-primary" id="add-item">
                        <i class="fas fa-plus me-2"></i>Add Another Item
                    </button>
                </div>
                
                <hr class="my-4">
                
                <div class="d-flex justify-content-end">
                    <a href="view_gatepass.php?id=<?php echo $gatepass_id; ?>" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsTable = document.getElementById('items-table').getElementsByTagName('tbody')[0];
    const addItemButton = document.getElementById('add-item');
    
    // Initialize row count
    let itemCount = <?php echo $item_count; ?>;
    
    // Add new item row
    addItemButton.addEventListener('click', function() {
        const newRow = document.createElement('tr');
        newRow.className = 'item-row';
        
        newRow.innerHTML = `
            <td>
                <input type="hidden" name="item_id[]" value="0">
                <input type="text" class="form-control" name="item_name[]" required>
            </td>
            <td>
                <input type="number" class="form-control" name="item_quantity[]" min="0.01" step="0.01" required>
            </td>
            <td>
                <input type="text" class="form-control" name="item_unit[]" required>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-item">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        
        itemsTable.appendChild(newRow);
        
        // Add event listener to the new remove button
        newRow.querySelector('.remove-item').addEventListener('click', function() {
            removeItem(this);
        });
        
        itemCount++;
    });
    
    // Remove item row
    function removeItem(button) {
        const row = button.closest('tr');
        
        // Only allow removal if more than one item exists
        if (itemCount > 1) {
            row.remove();
            itemCount--;
        } else {
            alert('At least one item is required');
        }
    }
    
    // Add event listeners to existing remove buttons
    document.querySelectorAll('.remove-item').forEach(function(button) {
        button.addEventListener('click', function() {
            removeItem(this);
        });
    });
    
    // Form validation
    document.getElementById('edit-gatepass-form').addEventListener('submit', function(event) {
        if (itemCount === 0) {
            event.preventDefault();
            alert('At least one item is required');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>