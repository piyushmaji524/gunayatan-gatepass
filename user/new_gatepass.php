<?php
require_once '../includes/config.php';

// Check if user is logged in and has user role
if (!isLoggedIn() || $_SESSION['role'] != 'user') {
    header("Location: ../index.php");
    exit();
}

// Set page title
$page_title = "New Gatepass";

// Get all active units from the database
$conn = connectDB();
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

// Close the database connection for units query
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Connect to database
    $conn = connectDB();
    
    // Validate and sanitize inputs
    $from_location = sanitizeInput($_POST['from_location']);
    $to_location = sanitizeInput($_POST['to_location']);
    $material_type = sanitizeInput($_POST['material_type']);
    $purpose = sanitizeInput($_POST['purpose']);
    $requested_date = sanitizeInput($_POST['requested_date']);
    $requested_time = sanitizeInput($_POST['requested_time']);
    
    // Validate item arrays
    $item_names = isset($_POST['item_name']) ? $_POST['item_name'] : array();
    $item_quantities = isset($_POST['item_quantity']) ? $_POST['item_quantity'] : array();
    $item_units = isset($_POST['item_unit']) ? $_POST['item_unit'] : array();
    
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
      // If no errors, proceed with insertion
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Generate unique gatepass number (format: GPXXX where XXX is a random 3-digit number)
            $isUnique = false;
            $gatepass_number = '';
            
            while (!$isUnique) {
                // Generate a random 3-digit number (100-999)
                $random = rand(100, 999);
                $gatepass_number = 'GP' . $random;
                
                // Check if this number already exists
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM gatepasses WHERE gatepass_number = ?");
                $stmt->bind_param("s", $gatepass_number);
                $stmt->execute();
                $result = $stmt->get_result();
                
                // If count is 0, we have a unique number
                if ($result->fetch_assoc()['count'] == 0) {
                    $isUnique = true;
                }
            }
            
            // Insert into gatepasses table
            $stmt = $conn->prepare("
                INSERT INTO gatepasses 
                (gatepass_number, from_location, to_location, material_type, purpose, 
                requested_date, requested_time, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssssssi", 
                $gatepass_number, $from_location, $to_location, $material_type, $purpose,
                $requested_date, $requested_time, $_SESSION['user_id']
            );
            $stmt->execute();
            $gatepass_id = $conn->insert_id;
            
            // Insert items
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
            logActivity($_SESSION['user_id'], 'GATEPASS_CREATED', "Created gatepass $gatepass_number");
            
            // Commit transaction
            $conn->commit();
            
            // Send notifications to admins
            try {
                require_once '../includes/simple_notification_system.php';
                sendInstantNotification('new_gatepass', $gatepass_id, $gatepass_number, $_SESSION['user_id']);
            } catch (Exception $e) {
                // Log the error but don't stop the process
                error_log("Notification error: " . $e->getMessage());
            }
            
            // Redirect with success message
            redirectWithMessage("view_gatepass.php?id=$gatepass_id", "Gatepass #$gatepass_number created successfully");
            
        } catch (Exception $e) {
            // Rollback if error
            $conn->rollback();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    $conn->close();
}

// Include header
require_once '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-plus-circle me-2"></i>Create New Gatepass</h2>
        <p class="text-muted">Fill in all the required fields to create a new gatepass request.</p>
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
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="needs-validation" novalidate>
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <label for="from_location" class="form-label">From Location <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="from_location" name="from_location" required
                           list="from_location_suggestions" autocomplete="off"
                           value="<?php echo isset($_POST['from_location']) ? htmlspecialchars($_POST['from_location']) : ''; ?>">
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
                           value="<?php echo isset($_POST['to_location']) ? htmlspecialchars($_POST['to_location']) : ''; ?>">
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
                           value="<?php echo isset($_POST['material_type']) ? htmlspecialchars($_POST['material_type']) : 'NOT APPLY'; ?>">
                    <div class="invalid-feedback">
                        Please enter the type of material
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="requested_date" class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="requested_date" name="requested_date" required
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo isset($_POST['requested_date']) ? htmlspecialchars($_POST['requested_date']) : date('Y-m-d'); ?>">
                    <div class="invalid-feedback">
                        Please select a valid date
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="requested_time" class="form-label">Time <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" id="requested_time" name="requested_time" required
                           value="<?php echo isset($_POST['requested_time']) ? htmlspecialchars($_POST['requested_time']) : date('H:i'); ?>">
                    <div class="invalid-feedback">
                        Please enter a valid time
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12 mb-3">
                    <label for="purpose" class="form-label">Purpose/Remarks</label>
                    <textarea class="form-control" id="purpose" name="purpose" rows="3"><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label">Items <span class="text-danger">*</span></label>
                    <div id="itemsContainer">
                        <?php 
                        // If form was submitted with errors, preserve the submitted items
                        $hasValidItems = false;
                        if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($errors) && isset($_POST['item_name'])): 
                            $item_names = $_POST['item_name'];
                            $item_quantities = isset($_POST['item_quantity']) ? $_POST['item_quantity'] : array();
                            $item_units = isset($_POST['item_unit']) ? $_POST['item_unit'] : array();
                            
                            for ($i = 0; $i < count($item_names); $i++):
                                if (!empty(trim($item_names[$i]))): // Only show non-empty items
                                    $hasValidItems = true;
                        ?>
                        <div class="item-entry" id="item-<?php echo $i + 1; ?>">
                            <span class="remove-item"><i class="fas fa-times"></i></span>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="item_name_<?php echo $i + 1; ?>" class="form-label">Item Name</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control item-name-input" id="item_name_<?php echo $i + 1; ?>" name="item_name[]" required autocomplete="off"
                                               value="<?php echo htmlspecialchars($item_names[$i]); ?>">
                                        <div class="autocomplete-suggestions" id="suggestions_<?php echo $i + 1; ?>"></div>
                                    </div>
                                    <small class="form-text text-muted">Start typing to see suggestions from previous entries</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="item_quantity_<?php echo $i + 1; ?>" class="form-label">Quantity</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control" id="item_quantity_<?php echo $i + 1; ?>" name="item_quantity[]" required 
                                           value="<?php echo isset($item_quantities[$i]) ? htmlspecialchars($item_quantities[$i]) : ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="item_unit_<?php echo $i + 1; ?>" class="form-label">Unit</label>
                                    <select class="form-select" id="item_unit_<?php echo $i + 1; ?>" name="item_unit[]" required>
                                        <option value="">Select unit</option>
                                        <?php foreach ($units as $unit): ?>
                                            <option value="<?php echo htmlspecialchars($unit['name']); ?>" 
                                                    <?php if (isset($item_units[$i]) && $item_units[$i] == $unit['name']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($unit['name']); ?> (<?php echo htmlspecialchars($unit['symbol']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="Other" <?php if (isset($item_units[$i]) && !in_array($item_units[$i], array_column($units, 'name'))) echo 'selected'; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php 
                                endif; 
                            endfor; 
                        endif; 
                        ?>
                        <!-- Item entries will be dynamically added here if no form submission or if items container is empty -->
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" id="addItemBtn">
                        <i class="fas fa-plus-circle me-1"></i> Add Another Item
                    </button>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-between">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Submit Gatepass
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Determine if we have preserved items from form submission
$hasPreservedItemsVar = ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($errors) && isset($hasValidItems) && $hasValidItems) ? 'true' : 'false';

// Additional JavaScript
$additional_js = '
<script>
// Pass units data to JavaScript
window.availableUnits = ' . json_encode($units) . ';

// Pass information about preserved items
window.hasPreservedItems = ' . $hasPreservedItemsVar . ';

document.addEventListener("DOMContentLoaded", function() {
    // Initialize first item only if no items exist (not from form submission with errors)
    const itemsContainer = document.getElementById("itemsContainer");
    const existingItems = itemsContainer.querySelectorAll(".item-entry");
    
    if (window.hasPreservedItems && existingItems.length > 0) {
        // Valid items exist from form submission, set up remove listeners for existing items
        addRemoveItemListeners();
    } else if (existingItems.length === 0) {
        // No items exist, add the first item
        addNewItem();
    } else {
        // Items exist but set up listeners
        addRemoveItemListeners();
    }
    
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

// Close database connection if still open
if (isset($conn) && $conn) {
    $conn->close();
}

// Include footer
require_once '../includes/footer.php';
?>
