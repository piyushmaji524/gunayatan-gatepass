<?php
// PDF Template for Gatepass

class GatepassPDF extends FPDF {    // Page header
    function Header() {
        // Logo - try different paths based on where the script is running from
        $logo_paths = [
            '../assets/img/logo.png', 
            'assets/img/logo.png',
            dirname(__FILE__) . '/../assets/img/logo.png'
        ];
        
        $logo_loaded = false;
        foreach ($logo_paths as $path) {
            if (file_exists($path)) {
                $this->Image($path, 10, 10, 30);
                $logo_loaded = true;
                break;
            }
        }
        
        // If logo couldn't be loaded, just continue without it
        
        // Title
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'GUNAYATAN GATEPASS', 0, 1, 'C');
        
        // Subtitle
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 5, 'Official Material Movement Authorization', 0, 1, 'C');
        
        // Line break
        $this->Ln(10);
    }

    // Page footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
        
        // Disclaimer
        $this->SetY(-10);
        $this->Cell(0, 10, 'This gatepass is valid only when presented with proper identification.', 0, 0, 'C');
    }
    
    // Gatepass Details
    function GatepassDetails($gatepass) {
        // Set colors
        $this->SetFillColor(240, 240, 240);
        
        // Gatepass Number and Date
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'GATEPASS #: ' . $gatepass['gatepass_number'], 0, 1);
        
        // Status
        $this->SetFont('Arial', 'B', 14);
        $status = strtoupper($gatepass['status']);
        
        // Set color based on status
        if ($status == 'APPROVED_BY_SECURITY') {
            $this->SetTextColor(0, 128, 0); // Green
            $status = 'VERIFIED';
        } elseif ($status == 'APPROVED_BY_ADMIN') {
            $this->SetTextColor(0, 0, 255); // Blue
            $status = 'ADMIN APPROVED - AWAITING VERIFICATION';
        } elseif ($status == 'PENDING') {
            $this->SetTextColor(255, 165, 0); // Orange
            $status = 'PENDING APPROVAL';
        } else {
            $this->SetTextColor(255, 0, 0); // Red
            $status = 'DECLINED';
        }
        
        $this->Cell(0, 10, 'STATUS: ' . $status, 0, 1);
        $this->SetTextColor(0, 0, 0); // Reset to black
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(95, 8, 'Requested Date: ' . date('d M Y', strtotime($gatepass['requested_date'])), 1, 0, 'L', true);
        $this->Cell(95, 8, 'Requested Time: ' . date('h:i A', strtotime($gatepass['requested_time'])), 1, 1, 'L', true);
        
        $this->Ln(5);
        
        // Locations
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'MOVEMENT DETAILS', 0, 1);
        $this->SetFont('Arial', '', 10);
        
        $this->Cell(95, 8, 'From Location: ' . $gatepass['from_location'], 1, 0, 'L', true);
        $this->Cell(95, 8, 'To Location: ' . $gatepass['to_location'], 1, 1, 'L', true);
        
        $this->Cell(190, 8, 'Material Type: ' . $gatepass['material_type'], 1, 1, 'L', true);
        
        // Purpose
        if (!empty($gatepass['purpose'])) {
            $this->Cell(190, 8, 'Purpose:', 1, 1, 'L', true);
            $this->SetFont('Arial', '', 10);
            $this->MultiCell(190, 8, $gatepass['purpose'], 1, 'L');
        }
        
        $this->Ln(5);
    }
    
    // Items Table
    function ItemsTable($items) {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'ITEMS', 0, 1);
        
        // Table header
        $this->SetFillColor(200, 220, 255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(100, 8, 'Item Name', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Quantity', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Unit', 1, 1, 'C', true);
        
        // Table data
        $this->SetFont('Arial', '', 10);
        $this->SetFillColor(255, 255, 255);
        
        $count = 1;
        while ($item = $items->fetch_assoc()) {
            $this->Cell(10, 8, $count, 1, 0, 'C');
            $this->Cell(100, 8, $item['item_name'], 1, 0, 'L');
            $this->Cell(40, 8, $item['quantity'], 1, 0, 'R');
            $this->Cell(40, 8, $item['unit'], 1, 1, 'C');
            $count++;
        }
        
        $this->Ln(5);
    }
    
    // Approval Information
    function ApprovalInfo($gatepass) {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'APPROVAL INFORMATION', 0, 1);
        
        $this->SetFont('Arial', '', 10);
        $this->SetFillColor(240, 240, 240);
        
        // Created by
        $this->Cell(190, 8, 'Created By: ' . $gatepass['creator_name'], 1, 1, 'L', true);
        $this->Cell(190, 8, 'Created At: ' . formatDateTime($gatepass['created_at']), 1, 1, 'L', true);
        
        // Admin approval
        if (!empty($gatepass['admin_approved_by'])) {
            $this->Cell(190, 8, 'Admin Approved By: ' . $gatepass['admin_name'], 1, 1, 'L', true);
            $this->Cell(190, 8, 'Admin Approved At: ' . formatDateTime($gatepass['admin_approved_at']), 1, 1, 'L', true);
        }
        
        // Security approval
        if (!empty($gatepass['security_approved_by'])) {
            $this->Cell(190, 8, 'Verified By: ' . $gatepass['security_name'], 1, 1, 'L', true);
            $this->Cell(190, 8, 'Verified At: ' . formatDateTime($gatepass['security_approved_at']), 1, 1, 'L', true);
        }
        
        // Decline information
        if (!empty($gatepass['declined_by'])) {
            $this->Cell(190, 8, 'Declined By: ' . $gatepass['declined_by_name'], 1, 1, 'L', true);
            $this->Cell(190, 8, 'Declined At: ' . formatDateTime($gatepass['declined_at']), 1, 1, 'L', true);
            
            if (!empty($gatepass['decline_reason'])) {
                $this->Cell(190, 8, 'Decline Reason:', 1, 1, 'L', true);
                $this->MultiCell(190, 8, $gatepass['decline_reason'], 1, 'L');
            }
        }
        
        $this->Ln(10);
    }
    
    // Signature block
    function SignatureBlock() {
        $this->SetFont('Arial', '', 10);
        
        $this->Cell(60, 8, 'Created By', 'T', 0, 'C');
        $this->Cell(10, 8, '', 0, 0);
        $this->Cell(60, 8, 'Admin Approval', 'T', 0, 'C');
        $this->Cell(10, 8, '', 0, 0);
        $this->Cell(60, 8, 'Security Verification', 'T', 1, 'C');
        
        $this->Ln(15);
    }
      // Barcode
    function Barcode($gatepass_number) {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 10, 'VERIFICATION BARCODE', 0, 1, 'C');
        
        // Generate barcode (simplified representation)
        $this->SetFillColor(0, 0, 0);
        
        $code = $gatepass_number;
        $x = 70; // X position
        $y = $this->GetY(); // Y position
        
        try {
            // Draw simple barcode
            $this->SetFont('Arial', '', 12);
            $this->SimpleBarcode($x, $y, $code, 70, 15);
            
            $this->Ln(20);
            
            // Text version of the code
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 10, $code, 0, 1, 'C');
        } catch (Exception $e) {
            // If there's an error drawing the barcode, just show the code as text
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 10, $code, 0, 1, 'C');
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 5, '(Barcode generation unavailable)', 0, 1, 'C');
        }
    }
    
    // Simple barcode implementation that won't cause errors
    function SimpleBarcode($x, $y, $code, $w, $h) {
        // Draw placeholder for barcode
        $this->SetFillColor(0, 0, 0);
        $this->Rect($x, $y, $w, $h, 'DF');
        
        // Write text in white over black rectangle
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 12);
        $this->SetXY($x, $y + $h/2 - 3);
        $this->Cell($w, 6, $code, 0, 0, 'C');
        $this->SetTextColor(0, 0, 0); // Reset text color
    }
}
?>
