<?php
require_once "../includes/config.php";
require_once "../fpdf/fpdf.php";
require_once "../templates/pdf_template.php";

// Check if user is logged in and has admin role
if (!isLoggedIn() || $_SESSION["role"] != "admin") {
    header("Location: ../index.php");
    exit();
}

// Check if ID is provided (for downloading specific gatepass PDF)
if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
    $gatepass_id = (int)$_GET["id"];
    
    // Connect to database
    $conn = connectDB();
    
    // Get gatepass details
    $stmt = $conn->prepare("
        SELECT g.*, 
               admin.name as admin_name, 
               security.name as security_name,
               creator.name as creator_name,
               decliner.name as declined_by_name
        FROM gatepasses g
        LEFT JOIN users admin ON g.admin_approved_by = admin.id
        LEFT JOIN users security ON g.security_approved_by = security.id
        LEFT JOIN users creator ON g.created_by = creator.id
        LEFT JOIN users decliner ON g.declined_by = decliner.id
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
    
    // Check if template class exists
    if (!class_exists('GatepassPDF')) {
        redirectWithMessage("all_gatepasses.php", "PDF template class not found. Please check template file.", "danger");
        exit();
    }
    
    // Create PDF document using our template
    $pdf = new GatepassPDF();
    $pdf->AliasNbPages(); // Initialize page numbers
    $pdf->AddPage();
    
    // Add admin note at top
    $pdf->SetFont("Arial", "B", 10);
    $pdf->SetTextColor(220, 53, 69);  // Red color
    $pdf->Cell(0, 10, "ADMIN GENERATED DOCUMENT - FOR VERIFICATION ONLY", 0, 1, "C");
    $pdf->SetTextColor(0);  // Reset to black
    
    // Add gatepass details using our template methods
    $pdf->GatepassDetails($gatepass);
    
    // Add items table
    $pdf->ItemsTable($items);
    
    // Reset items result pointer for counting
    $items->data_seek(0);
    
    // Add approval information
    $pdf->ApprovalInfo($gatepass);
    
    // Add signature block
    $pdf->SignatureBlock();
    
    // Add barcode
    $pdf->Barcode($gatepass["gatepass_number"]);
    
    // Check if this is a preview request
    $is_preview = isset($_GET['preview']) && $_GET['preview'] == 'true';
    
    // Log the PDF generation
    if ($is_preview) {
        logActivity($_SESSION["user_id"], "GATEPASS_PDF_PREVIEWED", "Admin previewed PDF for gatepass " . $gatepass["gatepass_number"]);
    } else {
        logActivity($_SESSION["user_id"], "GATEPASS_PDF_GENERATED", "Admin generated PDF for gatepass " . $gatepass["gatepass_number"]);
    }
    
    // Generate filename
    $filename = "Gatepass_" . $gatepass["gatepass_number"] . ".pdf";
    
    // Output PDF - use inline mode for preview, download mode for actual download
    if ($is_preview) {
        $pdf->Output("I", $filename); // Inline view
    } else {
        $pdf->Output("D", $filename); // Download
    }
    
    // Close the database connection
    $conn->close();
    exit();
} else {
    // If no ID provided, redirect back
    redirectWithMessage("all_gatepasses.php", "Invalid gatepass ID", "danger");
}
?>
