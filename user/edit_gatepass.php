<?php
require_once '../includes/config.php';

// Check if user is logged in and has user role
if (!isLoggedIn() || $_SESSION['role'] != 'user') {
    header("Location: ../index.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWithMessage("my_gatepasses.php", "Invalid gatepass ID", "danger");
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

// Get gatepass details and check if it can be edited
$stmt = $conn->prepare("
    SELECT * FROM gatepasses
    WHERE id = ? AND created_by = ? AND status = 'pending'
");
$stmt->bind_param("ii", $gatepass_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

// If no gatepass found, not owned by this user, or not editable
if ($result->num_rows !== 1) {
    $conn->close();
    redirectWithMessage("my_gatepasses.php", "Gatepass not found, cannot be edited, or you don't have permission", "danger");
}

$gatepass = $result->fetch_assoc();

// Get gatepass items
$stmt = $conn->prepare("SELECT * FROM gatepass_items WHERE gatepass_id = ?");
$stmt->bind_param("i", $gatepass_id);
$stmt->execute();
$items_result = $stmt->get_result();

// Store items in array
$items = array();
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize inputs
    $from_location = sanitizeInput($_POST['from_location']);
    $to_location = sanitizeInput($_POST['to_location']);
    $material_type = sanitizeInput($_POST['material_type']);
    $purpose = sanitizeInput($_POST['purpose']);
    $requested_date = sanitizeInput($_POST['requested_date']);
    $requested_time = sanitizeInput($_POST['requested_time']);
    
    // Validate item arrays
    $item_names = $_POST['item_name'];
    $item_quantities = $_POST['item_quantity'];
    $item_units = $_POST['item_unit'];
    $item_ids = isset($_POST['item_id']) ? $_POST['item_id'] : array();
    
    $errors = array();
    
    // Basic validation
    if (empty($from_location)) $errors[] = "From location is required";
    if (empty($to_location)) $errors[] = "To location is required";
    if (empty($material_type)) $errors[] = "Material type is required";
    
    // Validate that from and to locations are different
    if (!empty($from_location) && !empty($to_location) && trim($from_location) === trim($to_location)) {
        $errors[] = "From location and To location cannot be the same";
    }
    
    if (empty($requested_date)) $errors[] = "Date is required";
    if (empty($requested_time)) $errors[] = "Time is required";
    if (empty($item_names) || count($item_names) < 1) $errors[] = "At least one item is required";
    
    // Date validation
    $current_date = date('Y-m-d');
    if ($requested_date < $current_date) {
        $errors[] = "Requested date cannot be in the past";
    }
    
    // If no errors, proceed with update
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update gatepasses table
            $stmt = $conn->prepare("
                UPDATE gatepasses 
                SET from_location = ?, to_location = ?, material_type = ?, purpose = ?,
                    requested_date = ?, requested_time = ?
                WHERE id = ? AND created_by = ? AND status = 'pending'
            ");
            $stmt->bind_param(
                "ssssssii", 
                $from_location, $to_location, $material_type, $purpose,
                $requested_date, $requested_time, $gatepass_id, $_SESSION['user_id']
            );
            $stmt->execute();
            
            // If no rows affected, something went wrong
            if ($stmt->affected_rows <= 0 && $stmt->error) {
                throw new Exception("Failed to update gatepass: " . $stmt->error);
            }
            
            // Delete all existing items
            $stmt = $conn->prepare("DELETE FROM gatepass_items WHERE gatepass_id = ?");
            $stmt->bind_param("i", $gatepass_id);
            $stmt->execute();
            
            // Insert new/updated items
            $stmt = $conn->prepare("
                INSERT INTO gatepass_items 
                (gatepass_id, item_name, quantity, unit)
                VALUES (?, ?, ?, ?)
            ");
            
            for ($i = 0; $i < count($item_names); $i++) {
                // Skip empty entries
                if (empty($item_names[$i])) continue;
                
                $item_name = sanitizeInput($item_names[$i]);
                $item_quantity = floatval($item_quantities[$i]);
                $item_unit = sanitizeInput($item_units[$i]);
                
                $stmt->bind_param("isds", $gatepass_id, $item_name, $item_quantity, $item_unit);
                $stmt->execute();
            }
            
            // Log the action
            logActivity($_SESSION['user_id'], 'GATEPASS_EDITED', "Edited gatepass " . $gatepass['gatepass_number']);
            
            // Commit transaction
            $conn->commit();
            
            // Redirect with success message
            redirectWithMessage("view_gatepass.php?id=$gatepass_id", "Gatepass #" . $gatepass['gatepass_number'] . " updated successfully");
            
        } catch (Exception $e) {
            // Rollback if error
            $conn->rollback();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Set page title
$page_title = "Edit Gatepass #" . $gatepass['gatepass_number'];

// Include header
require_once '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-edit me-2"></i>Edit Gatepass #<?php echo htmlspecialchars($gatepass['gatepass_number']); ?></h2>
        <p class="text-muted">Update your gatepass request details below.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $gatepass_id); ?>" class="needs-validation" novalidate>
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <label for="from_location" class="form-label">From Location <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="from_location" name="from_location" required
                           list="from_location_suggestions" autocomplete="off"
                           value="<?php echo htmlspecialchars($gatepass['from_location']); ?>">
                    <datalist id="from_location_suggestions">
                        <option value="STORE">
                        <option value="MANDIR">
                        <option value="THEME PARK">
                        <option value="GOWSHALA">
                        <option value="SADHNA VASTIKA">
                        <option value="ANNA CHETRA">
                        <option value="MODI BHAWAN">
                        <option value="PAWANDHAM">
                        <option value="SIKHAR SHREE">

                    </datalist>
                    <div class="invalid-feedback">
                        Please enter the source location
                    </div>
                    <small class="form-text text-muted">Select from suggestions or type your own location</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="to_location" class="form-label">To Location <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="to_location" name="to_location" required
                           list="to_location_suggestions" autocomplete="off"
                           value="<?php echo htmlspecialchars($gatepass['to_location']); ?>">
                    <datalist id="to_location_suggestions">
                        <option value="STORE">
                        <option value="MANDIR">
                        <option value="THEME PARK">
                        <option value="GOWSHALA">
                        <option value="SADHNA VASTIKA">
                        <option value="ANNA CHETRA">
                        <option value="MODI BHAWAN">
                        <option value="PAWANDHAM">
                        <option value="SIKHAR SHREE">

                    </datalist>
                    <div class="invalid-feedback">
                        Please enter the destination location
                    </div>
                    <small class="form-text text-muted">Select from suggestions or type your own location</small>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <label for="material_type" class="form-label">Material Type <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="material_type" name="material_type" required
                           value="<?php echo htmlspecialchars($gatepass['material_type']); ?>">
                    <div class="invalid-feedback">
                        Please enter the type of material
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="requested_date" class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="requested_date" name="requested_date" required
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo htmlspecialchars($gatepass['requested_date']); ?>">
                    <div class="invalid-feedback">
                        Please select a valid date
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="requested_time" class="form-label">Time <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" id="requested_time" name="requested_time" required
                           value="<?php echo htmlspecialchars($gatepass['requested_time']); ?>">
                    <div class="invalid-feedback">
                        Please enter a valid time
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12 mb-3">
                    <label for="purpose" class="form-label">Purpose/Remarks</label>
                    <textarea class="form-control" id="purpose" name="purpose" rows="3"><?php echo htmlspecialchars($gatepass['purpose']); ?></textarea>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label">Items <span class="text-danger">*</span></label>
                    <div id="itemsContainer">
                        <?php foreach ($items as $index => $item): ?>
                        <div class="item-entry" id="item-<?php echo $index + 1; ?>">
                            <span class="remove-item"><i class="fas fa-times"></i></span>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="item_name_<?php echo $index + 1; ?>" class="form-label">Item Name</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control item-name-input" id="item_name_<?php echo $index + 1; ?>" name="item_name[]" required autocomplete="off" value="<?php echo htmlspecialchars($item['item_name']); ?>">
                                        <div class="autocomplete-suggestions" id="suggestions_<?php echo $index + 1; ?>"></div>
                                    </div>
                                    <small class="form-text text-muted">Start typing to see suggestions from previous entries</small>
                                    <input type="hidden" name="item_id[]" value="<?php echo $item['id']; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="item_quantity_<?php echo $index + 1; ?>" class="form-label">Quantity</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control" id="item_quantity_<?php echo $index + 1; ?>" name="item_quantity[]" required value="<?php echo htmlspecialchars($item['quantity']); ?>">
                                </div>                                <div class="col-md-4 mb-3">
                                    <label for="item_unit_<?php echo $index + 1; ?>" class="form-label">Unit</label>
                                    <select class="form-select" id="item_unit_<?php echo $index + 1; ?>" name="item_unit[]" required>
                                        <option value="">Select unit</option>
                                        <?php foreach ($units as $unit): ?>
                                            <option value="<?php echo htmlspecialchars($unit['name']); ?>" <?php if ($item['unit'] == $unit['name']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($unit['name']); ?> (<?php echo htmlspecialchars($unit['symbol']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="Other" <?php if (!in_array($item['unit'], array_column($units, 'name'))) echo 'selected'; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" id="addItemBtn">
                        <i class="fas fa-plus-circle me-1"></i> Add Another Item
                    </button>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-between">
                <a href="view_gatepass.php?id=<?php echo $gatepass_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Update Gatepass
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Additional JavaScript for the form
$additional_js = '
<script>
// Pass units data to JavaScript
window.availableUnits = ' . json_encode($units) . ';

document.addEventListener("DOMContentLoaded", function() {
    // Event listener for add item button already set in script.js
    // Add location validation
    setupLocationValidation();
});

// Setup location validation
function setupLocationValidation() {
    const fromLocationInput = document.getElementById("from_location");
    const toLocationInput = document.getElementById("to_location");
    const form = document.querySelector("form");
    
    // Real-time validation while typing
    function validateLocations() {
        const fromLocation = fromLocationInput.value.trim();
        const toLocation = toLocationInput.value.trim();
        
        // Remove any existing custom validation messages
        fromLocationInput.setCustomValidity("");
        toLocationInput.setCustomValidity("");
        
        if (fromLocation && toLocation && fromLocation.toLowerCase() === toLocation.toLowerCase()) {
            const errorMessage = "From location and To location cannot be the same";
            fromLocationInput.setCustomValidity(errorMessage);
            toLocationInput.setCustomValidity(errorMessage);
            
            // Add visual feedback
            fromLocationInput.classList.add("is-invalid");
            toLocationInput.classList.add("is-invalid");
            
            // Update or create error message display
            updateLocationErrorMessage(errorMessage);
        } else {
            // Remove visual feedback if validation passes
            fromLocationInput.classList.remove("is-invalid");
            toLocationInput.classList.remove("is-invalid");
            removeLocationErrorMessage();
        }
    }
    
    // Update error message display
    function updateLocationErrorMessage(message) {
        let errorDiv = document.getElementById("location-validation-error");
        if (!errorDiv) {
            errorDiv = document.createElement("div");
            errorDiv.id = "location-validation-error";
            errorDiv.className = "alert alert-danger mt-2";
            errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>${message}`;
            toLocationInput.parentNode.appendChild(errorDiv);
        } else {
            errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>${message}`;
        }
    }
    
    // Remove error message display
    function removeLocationErrorMessage() {
        const errorDiv = document.getElementById("location-validation-error");
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    // Add event listeners
    fromLocationInput.addEventListener("input", validateLocations);
    fromLocationInput.addEventListener("blur", validateLocations);
    toLocationInput.addEventListener("input", validateLocations);
    toLocationInput.addEventListener("blur", validateLocations);
    
    // Form submission validation
    form.addEventListener("submit", function(e) {
        validateLocations();
        
        const fromLocation = fromLocationInput.value.trim();
        const toLocation = toLocationInput.value.trim();
        
        if (fromLocation && toLocation && fromLocation.toLowerCase() === toLocation.toLowerCase()) {
            e.preventDefault();
            e.stopPropagation();
            
            // Focus on the first invalid field
            fromLocationInput.focus();
            
            // Show browser validation message
            fromLocationInput.reportValidity();
            
            return false;
        }
    });
}
</script>
';

// Close connection
$conn->close();

// Include footer
require_once '../includes/footer.php';
?>
