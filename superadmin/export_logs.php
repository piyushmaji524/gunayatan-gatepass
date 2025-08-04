<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Initialize database connection
$conn = connectDB();

// Check if request is for specific user logs or all system logs
$export_all = true;
$user_id = null;
$user_info = '';

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    $export_all = false;
    
    // Get user details for the filename
    $stmt = $conn->prepare("SELECT username, name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    if ($user_result->num_rows > 0) {
        $user = $user_result->fetch_assoc();
        $user_info = $user['username'] . '_' . str_replace(' ', '_', $user['name']);
    } else {
        // If user doesn't exist, export all logs
        $export_all = true;
    }
}

// Date range filter
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : '';

$date_filter = '';
$filename_date_part = '';

// Build date filter if dates provided
if (!empty($start_date) && !empty($end_date)) {
    $date_filter = "AND created_at BETWEEN ? AND ?";
    $filename_date_part = '_from_' . $start_date . '_to_' . $end_date;
}

// Determine export format (default to CSV)
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';

// Build base query with proper JOINs and WHERE clauses
if ($export_all) {
    $sql = "SELECT l.id, l.user_id, u.username, u.name, u.role, l.action, l.ip_address, l.created_at 
            FROM logs l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE 1=1 " . $date_filter . "
            ORDER BY l.created_at DESC";
    
    $filename = 'system_logs' . $filename_date_part;
} else {
    $sql = "SELECT l.id, l.user_id, u.username, u.name, u.role, l.action, l.ip_address, l.created_at 
            FROM logs l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.user_id = ? " . $date_filter . "
            ORDER BY l.created_at DESC";
    
    $filename = 'user_logs_' . $user_info . $filename_date_part;
}

// Prepare and execute the query
$types = '';
$params = [];

if (!$export_all) {
    $params[] = $user_id;
    $types .= 'i';
}

if (!empty($start_date) && !empty($end_date)) {
    $params[] = $start_date . ' 00:00:00';
    $params[] = $end_date . ' 23:59:59';
    $types .= 'ss';
}

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Handle different export formats
switch ($format) {
    case 'excel':
        // Set headers for Excel (XLSX) download using CSV with Excel-compatible encoding
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output Excel file header
        echo '<table border="1">';
        echo '<tr><th>ID</th><th>User ID</th><th>Username</th><th>Name</th><th>Role</th><th>Action</th><th>IP Address</th><th>Timestamp</th></tr>';
        
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . $row['user_id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['username']) . '</td>';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['role']) . '</td>';
            echo '<td>' . htmlspecialchars($row['action']) . '</td>';
            echo '<td>' . htmlspecialchars($row['ip_address']) . '</td>';
            echo '<td>' . $row['created_at'] . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        break;
    
    case 'pdf':
        // Include FPDF library
        require_once('../fpdf/fpdf.php');
        
        class PDF extends FPDF {
            function Header() {
                // Set font
                $this->SetFont('Arial', 'B', 12);
                
                // Title
                $this->Cell(0, 10, 'System Logs Report', 0, 1, 'C');
                $this->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
                $this->Ln(10);
                
                // Column headers
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(15, 7, 'ID', 1);
                $this->Cell(25, 7, 'Username', 1);
                $this->Cell(40, 7, 'Name', 1);
                $this->Cell(20, 7, 'Role', 1);
                $this->Cell(80, 7, 'Action', 1);
                $this->Cell(25, 7, 'IP Address', 1);
                $this->Cell(40, 7, 'Timestamp', 1);
                $this->Ln();
            }
            
            function Footer() {
                // Position at 1.5 cm from bottom
                $this->SetY(-15);
                
                // Set font
                $this->SetFont('Arial', 'I', 8);
                
                // Page number
                $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
            }
        }
        
        // Create PDF
        $pdf = new PDF('L', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 9);
        
        // Data rows
        while ($row = $result->fetch_assoc()) {
            $pdf->Cell(15, 6, $row['id'], 1);
            $pdf->Cell(25, 6, substr($row['username'], 0, 20), 1);
            $pdf->Cell(40, 6, substr($row['name'], 0, 25), 1);
            $pdf->Cell(20, 6, $row['role'], 1);
            $pdf->Cell(80, 6, substr($row['action'], 0, 60), 1);
            $pdf->Cell(25, 6, $row['ip_address'], 1);
            $pdf->Cell(40, 6, $row['created_at'], 1);
            $pdf->Ln();
            
            // Check if we need a new page
            if ($pdf->GetY() > 180) {
                $pdf->AddPage();
            }
        }
        
        // Output PDF
        $pdf->Output('D', $filename . '.pdf');
        break;
    
    case 'csv':
    default:
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create file handle for output
        $output = fopen('php://output', 'w');
        
        // Output CSV header row
        fputcsv($output, ['ID', 'User ID', 'Username', 'Name', 'Role', 'Action', 'IP Address', 'Timestamp']);
        
        // Output each data row
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        
        // Close file handle
        fclose($output);
        break;
}

// Close the database connection
$stmt->close();
$conn->close();
exit;
?>
