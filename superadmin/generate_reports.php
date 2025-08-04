<?php
require_once '../includes/config.php';

// Check if user is logged in and has superadmin role
if (!isLoggedIn() || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Define available report types
$report_types = [
    'gatepass_summary' => 'Gatepass Summary Report',
    'user_activity' => 'User Activity Report',
    'system_usage' => 'System Usage Statistics',
    'verification_time' => 'Gatepass Verification Time Analysis',
    'monthly_stats' => 'Monthly Statistics Report',
    'user_performance' => 'User Performance Metrics'
];

// Get parameters
$report_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$from_date = isset($_GET['from_date']) ? sanitizeInput($_GET['from_date']) : date('Y-m-01'); // First day of current month
$to_date = isset($_GET['to_date']) ? sanitizeInput($_GET['to_date']) : date('Y-m-d'); // Today
$format = isset($_GET['format']) ? sanitizeInput($_GET['format']) : 'html';

// Initialize report data array
$report_data = [];
$chart_data = [];
$report_title = '';

// Generate report if a type is selected
if (!empty($report_type) && array_key_exists($report_type, $report_types)) {
    $report_title = $report_types[$report_type];
    
    switch ($report_type) {
        case 'gatepass_summary':
            // Get gatepass counts by status
            $query = "
                SELECT 
                    status,
                    COUNT(*) as count
                FROM gatepasses
                WHERE created_at BETWEEN ? AND ?
                GROUP BY status
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $from_date_with_time = $from_date . " 00:00:00";
            $to_date_with_time = $to_date . " 23:59:59";
            $stmt->execute();
            $result = $stmt->get_result();
            
            $status_counts = [
                'pending' => 0,
                'approved_by_admin' => 0,
                'approved_by_security' => 0,
                'declined' => 0
            ];
            
            while ($row = $result->fetch_assoc()) {
                $status_counts[$row['status']] = $row['count'];
            }
            
            $report_data['status_counts'] = $status_counts;
            $report_data['total_gatepasses'] = array_sum($status_counts);
            
            // Chart data for status distribution
            $chart_data['status'] = [
                'labels' => ['Pending', 'Admin Approved', 'Security Verified', 'Declined'],
                'data' => [
                    $status_counts['pending'],
                    $status_counts['approved_by_admin'],
                    $status_counts['approved_by_security'],
                    $status_counts['declined']
                ],
                'colors' => ['#ffc107', '#0d6efd', '#198754', '#dc3545']
            ];
            
            // Get daily gatepass creation counts for the period
            $query = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM gatepasses
                WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $daily_counts = [];
            $daily_dates = [];
            
            while ($row = $result->fetch_assoc()) {
                $daily_dates[] = date('M d', strtotime($row['date']));
                $daily_counts[] = $row['count'];
            }
            
            $chart_data['daily'] = [
                'labels' => $daily_dates,
                'data' => $daily_counts
            ];
            
            // Get gatepass counts by type
            $query = "
                SELECT 
                    material_type,
                    COUNT(*) as count
                FROM gatepasses
                WHERE created_at BETWEEN ? AND ?
                GROUP BY material_type
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $type_counts = [];
            $type_labels = [];
            $type_data = [];
            
            while ($row = $result->fetch_assoc()) {
                $type_counts[$row['material_type']] = $row['count'];
                $type_labels[] = $row['material_type'];
                $type_data[] = $row['count'];
            }
            
            $report_data['type_counts'] = $type_counts;
            $chart_data['types'] = [
                'labels' => $type_labels,
                'data' => $type_data
            ];
            
            break;
            
        case 'user_activity':
            // Get user login activity
            $query = "
                SELECT 
                    u.id,
                    u.name,
                    u.username,
                    u.role,
                    COUNT(l.id) as login_count,
                    MAX(l.created_at) as last_login
                FROM users u
                LEFT JOIN logs l ON u.id = l.user_id AND l.action = 'USER_LOGIN' 
                    AND l.created_at BETWEEN ? AND ?
                GROUP BY u.id, u.name, u.username, u.role
                ORDER BY login_count DESC
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $from_date_with_time = $from_date . " 00:00:00";
            $to_date_with_time = $to_date . " 23:59:59";
            $stmt->execute();
            $user_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $report_data['user_activity'] = $user_activity;
            
            // Get action counts by role
            $query = "
                SELECT 
                    u.role,
                    COUNT(l.id) as action_count
                FROM logs l
                JOIN users u ON l.user_id = u.id
                WHERE l.created_at BETWEEN ? AND ?
                GROUP BY u.role
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $role_actions = [
                'superadmin' => 0,
                'admin' => 0,
                'security' => 0,
                'user' => 0
            ];
            
            while ($row = $result->fetch_assoc()) {
                $role_actions[$row['role']] = $row['action_count'];
            }
            
            $report_data['role_actions'] = $role_actions;
            $chart_data['role_activity'] = [
                'labels' => ['Superadmin', 'Admin', 'Security', 'User'],
                'data' => [
                    $role_actions['superadmin'],
                    $role_actions['admin'],
                    $role_actions['security'],
                    $role_actions['user']
                ]
            ];
            
            break;
            
        case 'system_usage':
            // Get hourly system activity
            $query = "
                SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as count
                FROM logs
                WHERE created_at BETWEEN ? AND ?
                GROUP BY HOUR(created_at)
                ORDER BY hour
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $from_date_with_time = $from_date . " 00:00:00";
            $to_date_with_time = $to_date . " 23:59:59";
            $stmt->execute();
            $result = $stmt->get_result();
            
            $hourly_activity = array_fill(0, 24, 0); // Initialize all hours with 0
            
            while ($row = $result->fetch_assoc()) {
                $hourly_activity[(int)$row['hour']] = $row['count'];
            }
            
            $report_data['hourly_activity'] = $hourly_activity;
            $chart_data['hourly'] = [
                'labels' => array_map(function($hour) { return sprintf('%02d:00', $hour); }, range(0, 23)),
                'data' => array_values($hourly_activity)
            ];
            
            // Get top actions
            $query = "
                SELECT 
                    action,
                    COUNT(*) as count
                FROM logs
                WHERE created_at BETWEEN ? AND ?
                GROUP BY action
                ORDER BY count DESC
                LIMIT 10
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $stmt->execute();
            $top_actions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $report_data['top_actions'] = $top_actions;
            
            $action_labels = [];
            $action_data = [];
            foreach ($top_actions as $action) {
                $action_labels[] = $action['action'];
                $action_data[] = $action['count'];
            }
            
            $chart_data['top_actions'] = [
                'labels' => $action_labels,
                'data' => $action_data
            ];
            
            break;
            
        case 'verification_time':
            // Calculate time between admin approval and security verification
            $query = "
                SELECT 
                    id,
                    gatepass_number,
                    admin_approved_at,
                    security_approved_at,
                    TIMESTAMPDIFF(MINUTE, admin_approved_at, security_approved_at) as verification_minutes
                FROM gatepasses
                WHERE 
                    status = 'approved_by_security'
                    AND admin_approved_at IS NOT NULL
                    AND security_approved_at IS NOT NULL
                    AND created_at BETWEEN ? AND ?
                ORDER BY verification_minutes DESC
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $from_date_with_time = $from_date . " 00:00:00";
            $to_date_with_time = $to_date . " 23:59:59";
            $stmt->execute();
            $verification_times = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $report_data['verification_times'] = $verification_times;
            
            // Calculate average, min, and max verification times
            $total_minutes = 0;
            $min_minutes = PHP_INT_MAX;
            $max_minutes = 0;
            $count = count($verification_times);
            
            if ($count > 0) {
                foreach ($verification_times as $time) {
                    $minutes = $time['verification_minutes'];
                    $total_minutes += $minutes;
                    $min_minutes = min($min_minutes, $minutes);
                    $max_minutes = max($max_minutes, $minutes);
                }
                
                $avg_minutes = $total_minutes / $count;
            } else {
                $avg_minutes = 0;
                $min_minutes = 0;
                $max_minutes = 0;
            }
            
            $report_data['avg_verification_time'] = $avg_minutes;
            $report_data['min_verification_time'] = $min_minutes;
            $report_data['max_verification_time'] = $max_minutes;
            $report_data['verification_count'] = $count;
            
            // Get verification time distribution
            $time_ranges = [
                '< 1 hour' => 0,
                '1-2 hours' => 0,
                '2-4 hours' => 0,
                '4-8 hours' => 0,
                '8-24 hours' => 0,
                '> 24 hours' => 0
            ];
            
            foreach ($verification_times as $time) {
                $minutes = $time['verification_minutes'];
                
                if ($minutes < 60) {
                    $time_ranges['< 1 hour']++;
                } elseif ($minutes < 120) {
                    $time_ranges['1-2 hours']++;
                } elseif ($minutes < 240) {
                    $time_ranges['2-4 hours']++;
                } elseif ($minutes < 480) {
                    $time_ranges['4-8 hours']++;
                } elseif ($minutes < 1440) {
                    $time_ranges['8-24 hours']++;
                } else {
                    $time_ranges['> 24 hours']++;
                }
            }
            
            $report_data['time_ranges'] = $time_ranges;
            $chart_data['time_ranges'] = [
                'labels' => array_keys($time_ranges),
                'data' => array_values($time_ranges)
            ];
            
            break;
            
        case 'monthly_stats':
            // Get monthly gatepass counts
            $query = "
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'approved_by_security' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined
                FROM gatepasses
                WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ";
            $stmt = $conn->prepare($query);
            
            // Adjust date range to include full months
            $from_date_adjusted = date('Y-m-01', strtotime($from_date));
            $to_date_adjusted = date('Y-m-t', strtotime($to_date));
            
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $from_date_with_time = $from_date_adjusted . " 00:00:00";
            $to_date_with_time = $to_date_adjusted . " 23:59:59";
            $stmt->execute();
            $monthly_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $report_data['monthly_stats'] = $monthly_stats;
            
            // Format data for charts
            $months = [];
            $totals = [];
            $verified = [];
            $declined = [];
            
            foreach ($monthly_stats as $stat) {
                $months[] = date('M Y', strtotime($stat['month'] . '-01'));
                $totals[] = $stat['total'];
                $verified[] = $stat['verified'];
                $declined[] = $stat['declined'];
            }
            
            $chart_data['monthly'] = [
                'labels' => $months,
                'datasets' => [
                    [
                        'label' => 'Total',
                        'data' => $totals,
                        'borderColor' => '#0d6efd'
                    ],
                    [
                        'label' => 'Verified',
                        'data' => $verified,
                        'borderColor' => '#198754'
                    ],
                    [
                        'label' => 'Declined',
                        'data' => $declined,
                        'borderColor' => '#dc3545'
                    ]
                ]
            ];
            
            // Get user registration trend
            $query = "
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as new_users,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
                    SUM(CASE WHEN role = 'security' THEN 1 ELSE 0 END) as security_users,
                    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as regular_users
                FROM users
                WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $stmt->execute();
            $user_trends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $report_data['user_trends'] = $user_trends;
            
            break;
            
        case 'user_performance':
            // Get users with most gatepasses created
            $query = "
                SELECT 
                    u.id,
                    u.name,
                    u.username,
                    u.role,
                    COUNT(g.id) as gatepass_count
                FROM users u
                LEFT JOIN gatepasses g ON u.id = g.created_by 
                    AND g.created_at BETWEEN ? AND ?
                GROUP BY u.id, u.name, u.username, u.role
                ORDER BY gatepass_count DESC
                LIMIT 10
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $from_date_with_time = $from_date . " 00:00:00";
            $to_date_with_time = $to_date . " 23:59:59";
            $stmt->execute();
            $top_creators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $report_data['top_creators'] = $top_creators;
            
            // Get admins with most approvals
            $query = "
                SELECT 
                    u.id,
                    u.name,
                    u.username,
                    COUNT(g.id) as approval_count,
                    AVG(TIMESTAMPDIFF(MINUTE, g.created_at, g.admin_approved_at)) as avg_approval_time
                FROM users u
                LEFT JOIN gatepasses g ON u.id = g.admin_approved_by 
                    AND g.admin_approved_at BETWEEN ? AND ?
                WHERE u.role = 'admin'
                GROUP BY u.id, u.name, u.username
                ORDER BY approval_count DESC
                LIMIT 10
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $stmt->execute();
            $top_admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $report_data['top_admins'] = $top_admins;
            
            // Get security users with most verifications
            $query = "
                SELECT 
                    u.id,
                    u.name,
                    u.username,
                    COUNT(g.id) as verification_count,
                    AVG(TIMESTAMPDIFF(MINUTE, g.admin_approved_at, g.security_approved_at)) as avg_verification_time
                FROM users u
                LEFT JOIN gatepasses g ON u.id = g.security_approved_by 
                    AND g.security_approved_at BETWEEN ? AND ?
                WHERE u.role = 'security'
                GROUP BY u.id, u.name, u.username
                ORDER BY verification_count DESC
                LIMIT 10
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date_with_time, $to_date_with_time);
            $stmt->execute();
            $top_security = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $report_data['top_security'] = $top_security;
            
            break;
    }
    
    // Handle export formats
    if ($format === 'csv' || $format === 'excel') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $report_type . '_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        
        // CSV header
        fputcsv($output, ['Report: ' . $report_title]);
        fputcsv($output, ['Date Range: ' . date('Y-m-d', strtotime($from_date)) . ' to ' . date('Y-m-d', strtotime($to_date))]);
        fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, ['Generated by: ' . $_SESSION['name']]);
        fputcsv($output, []); // Empty line
        
        // Report-specific CSV data
        switch ($report_type) {
            case 'gatepass_summary':
                fputcsv($output, ['Status Summary']);
                fputcsv($output, ['Status', 'Count', 'Percentage']);
                foreach ($status_counts as $status => $count) {
                    $percentage = ($report_data['total_gatepasses'] > 0) ? 
                                  round(($count / $report_data['total_gatepasses']) * 100, 2) : 0;
                    fputcsv($output, [ucfirst(str_replace('_', ' ', $status)), $count, $percentage . '%']);
                }
                
                fputcsv($output, []); // Empty line
                fputcsv($output, ['Gatepass Types']);
                fputcsv($output, ['Type', 'Count']);
                foreach ($type_counts as $type => $count) {
                    fputcsv($output, [$type, $count]);
                }
                break;
                
            case 'user_activity':
                fputcsv($output, ['User Login Activity']);
                fputcsv($output, ['Name', 'Username', 'Role', 'Login Count', 'Last Login']);
                foreach ($user_activity as $user) {
                    fputcsv($output, [
                        $user['name'], 
                        $user['username'], 
                        ucfirst($user['role']), 
                        $user['login_count'], 
                        $user['last_login']
                    ]);
                }
                break;
                
            case 'verification_time':
                fputcsv($output, ['Verification Time Analysis']);
                fputcsv($output, ['Average Verification Time (minutes)', 'Minimum Time', 'Maximum Time', 'Total Count']);
                fputcsv($output, [
                    round($report_data['avg_verification_time'], 2),
                    $report_data['min_verification_time'],
                    $report_data['max_verification_time'],
                    $report_data['verification_count']
                ]);
                
                fputcsv($output, []); // Empty line
                fputcsv($output, ['Time Range', 'Count']);
                foreach ($time_ranges as $range => $count) {
                    fputcsv($output, [$range, $count]);
                }
                
                fputcsv($output, []); // Empty line
                fputcsv($output, ['Gatepass Details']);
                fputcsv($output, ['Gatepass Number', 'Admin Approved', 'Security Verified', 'Minutes to Verify']);
                foreach ($verification_times as $time) {
                    fputcsv($output, [
                        $time['gatepass_number'],
                        $time['admin_approved_at'],
                        $time['security_approved_at'],
                        $time['verification_minutes']
                    ]);
                }
                break;
                
            case 'monthly_stats':
                fputcsv($output, ['Monthly Gatepass Statistics']);
                fputcsv($output, ['Month', 'Total Gatepasses', 'Verified', 'Declined']);
                foreach ($monthly_stats as $stat) {
                    fputcsv($output, [
                        date('F Y', strtotime($stat['month'] . '-01')),
                        $stat['total'],
                        $stat['verified'],
                        $stat['declined']
                    ]);
                }
                break;
                
            case 'user_performance':
                fputcsv($output, ['Top Gatepass Creators']);
                fputcsv($output, ['Name', 'Username', 'Role', 'Gatepasses Created']);
                foreach ($top_creators as $user) {
                    fputcsv($output, [
                        $user['name'],
                        $user['username'],
                        ucfirst($user['role']),
                        $user['gatepass_count']
                    ]);
                }
                
                fputcsv($output, []); // Empty line
                fputcsv($output, ['Top Admin Approvers']);
                fputcsv($output, ['Name', 'Username', 'Approvals', 'Avg. Approval Time (min)']);
                foreach ($top_admins as $admin) {
                    fputcsv($output, [
                        $admin['name'],
                        $admin['username'],
                        $admin['approval_count'],
                        round($admin['avg_approval_time'], 2)
                    ]);
                }
                
                fputcsv($output, []); // Empty line
                fputcsv($output, ['Top Security Verifiers']);
                fputcsv($output, ['Name', 'Username', 'Verifications', 'Avg. Verification Time (min)']);
                foreach ($top_security as $security) {
                    fputcsv($output, [
                        $security['name'],
                        $security['username'],
                        $security['verification_count'],
                        round($security['avg_verification_time'], 2)
                    ]);
                }
                break;
                
            case 'system_usage':
                fputcsv($output, ['Hourly System Activity']);
                fputcsv($output, ['Hour', 'Activity Count']);
                foreach ($hourly_activity as $hour => $count) {
                    fputcsv($output, [sprintf('%02d:00', $hour), $count]);
                }
                
                fputcsv($output, []); // Empty line
                fputcsv($output, ['Top System Actions']);
                fputcsv($output, ['Action', 'Count']);
                foreach ($top_actions as $action) {
                    fputcsv($output, [$action['action'], $action['count']]);
                }
                break;
        }
        
        exit;
    }    elseif ($format === 'pdf') {
        // Redirect to the PDF export script
        header("Location: export_report_pdf.php?type=$report_type&from_date=$from_date&to_date=$to_date");
        exit();
    }
}

// Set page title
$page_title = "Generate Reports";

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-chart-bar me-2"></i><?php echo empty($report_title) ? 'Generate Reports' : $report_title; ?></h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <?php if (!empty($report_type)): ?>
                <div class="dropdown d-inline">
                    <button class="btn btn-success dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                        <li>
                            <a class="dropdown-item" href="generate_reports.php?type=<?php echo $report_type; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&format=csv">
                                <i class="fas fa-file-csv me-2"></i>CSV
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="generate_reports.php?type=<?php echo $report_type; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&format=pdf">
                                <i class="fas fa-file-pdf me-2"></i>PDF
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Report Selection Form -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Report Parameters</h5>
        </div>
        <div class="card-body">
            <form method="get" class="row">
                <div class="col-md-4 mb-3">
                    <label for="type" class="form-label">Report Type</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="" disabled <?php echo empty($report_type) ? 'selected' : ''; ?>>Select a report type</option>
                        <?php foreach ($report_types as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $report_type === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="from_date" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="from_date" name="from_date" 
                           value="<?php echo htmlspecialchars($from_date); ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="to_date" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="to_date" name="to_date" 
                           value="<?php echo htmlspecialchars($to_date); ?>" required>
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sync-alt me-2"></i>Generate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($report_type)): ?>
        <!-- Report Content -->
        <?php if ($report_type === 'gatepass_summary'): ?>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Gatepass Status Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart"></canvas>
                        </div>
                        <div class="card-footer">
                            <div class="row text-center">
                                <div class="col">
                                    <h5 class="mb-0"><?php echo $report_data['total_gatepasses']; ?></h5>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col">
                                    <h5 class="mb-0"><?php echo $report_data['status_counts']['pending']; ?></h5>
                                    <small class="text-muted">Pending</small>
                                </div>
                                <div class="col">
                                    <h5 class="mb-0"><?php echo $report_data['status_counts']['approved_by_admin']; ?></h5>
                                    <small class="text-muted">Admin Approved</small>
                                </div>
                                <div class="col">
                                    <h5 class="mb-0"><?php echo $report_data['status_counts']['approved_by_security']; ?></h5>
                                    <small class="text-muted">Verified</small>
                                </div>
                                <div class="col">
                                    <h5 class="mb-0"><?php echo $report_data['status_counts']['declined']; ?></h5>
                                    <small class="text-muted">Declined</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Daily Gatepass Creation</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-info text-dark">
                    <h5 class="mb-0">Gatepass Types</h5>
                </div>
                <div class="card-body">
                    <canvas id="typesChart"></canvas>
                </div>
            </div>
            
        <?php elseif ($report_type === 'user_activity'): ?>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Actions by User Role</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="roleActivityChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">User Login Activity</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Logins</th>
                                            <th>Last Login</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['user_activity'] as $user): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($user['name']); ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($user['username']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php 
                                                        switch($user['role']) {
                                                            case 'superadmin': echo 'bg-dark'; break;
                                                            case 'admin': echo 'bg-danger'; break;
                                                            case 'security': echo 'bg-warning text-dark'; break;
                                                            case 'user': echo 'bg-info text-dark'; break;
                                                            default: echo 'bg-secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $user['login_count']; ?></td>
                                                <td><?php echo !empty($user['last_login']) ? formatDateTime($user['last_login']) : 'Never'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($report_type === 'system_usage'): ?>
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Hourly System Activity</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="hourlyChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Top System Actions</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="topActionsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($report_type === 'verification_time'): ?>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Verification Time Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="timeRangesChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Verification Time Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="display-6 mb-2">
                                        <?php echo number_format(round($report_data['avg_verification_time'], 2)); ?>
                                    </div>
                                    <p class="text-muted mb-0">Avg. Minutes</p>
                                </div>
                                <div class="col-md-4">
                                    <div class="display-6 mb-2">
                                        <?php echo number_format($report_data['min_verification_time']); ?>
                                    </div>
                                    <p class="text-muted mb-0">Min. Minutes</p>
                                </div>
                                <div class="col-md-4">
                                    <div class="display-6 mb-2">
                                        <?php echo number_format($report_data['max_verification_time']); ?>
                                    </div>
                                    <p class="text-muted mb-0">Max. Minutes</p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center">
                                <h5><?php echo number_format($report_data['verification_count']); ?> Verified Gatepasses</h5>
                                <p class="text-muted">
                                    <?php 
                                        $hours = floor($report_data['avg_verification_time'] / 60);
                                        $minutes = $report_data['avg_verification_time'] % 60;
                                        echo "Average verification time: $hours hours and " . round($minutes) . " minutes";
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-info text-dark">
                    <h5 class="mb-0">Detailed Verification Times</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Gatepass #</th>
                                    <th>Admin Approved</th>
                                    <th>Security Verified</th>
                                    <th>Minutes to Verify</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['verification_times'] as $time): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($time['gatepass_number']); ?></td>
                                        <td><?php echo formatDateTime($time['admin_approved_at']); ?></td>
                                        <td><?php echo formatDateTime($time['security_approved_at']); ?></td>
                                        <td>
                                            <?php
                                                $minutes = $time['verification_minutes'];
                                                $hours = floor($minutes / 60);
                                                $mins = $minutes % 60;
                                                
                                                if ($hours > 0) {
                                                    echo "<strong>$hours</strong> hrs <strong>$mins</strong> mins";
                                                } else {
                                                    echo "<strong>$mins</strong> minutes";
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($report_type === 'monthly_stats'): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Monthly Gatepass Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" style="height: 300px;"></canvas>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-info text-dark">
                    <h5 class="mb-0">Monthly Breakdown</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Total Gatepasses</th>
                                    <th>Verified</th>
                                    <th>Verification Rate</th>
                                    <th>Declined</th>
                                    <th>Decline Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['monthly_stats'] as $stat): ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($stat['month'] . '-01')); ?></td>
                                        <td><?php echo $stat['total']; ?></td>
                                        <td><?php echo $stat['verified']; ?></td>
                                        <td>
                                            <?php 
                                                $verification_rate = ($stat['total'] > 0) ? 
                                                                    round(($stat['verified'] / $stat['total']) * 100, 1) : 0;
                                                echo "$verification_rate%";
                                            ?>
                                            <div class="progress mt-1" style="height: 5px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo $verification_rate; ?>%"></div>
                                            </div>
                                        </td>
                                        <td><?php echo $stat['declined']; ?></td>
                                        <td>
                                            <?php 
                                                $decline_rate = ($stat['total'] > 0) ? 
                                                              round(($stat['declined'] / $stat['total']) * 100, 1) : 0;
                                                echo "$decline_rate%";
                                            ?>
                                            <div class="progress mt-1" style="height: 5px;">
                                                <div class="progress-bar bg-danger" style="width: <?php echo $decline_rate; ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($report_type === 'user_performance'): ?>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-dark">
                            <h5 class="mb-0">Top Gatepass Creators</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['top_creators'] as $user): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($user['name']); ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($user['username']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php 
                                                        switch($user['role']) {
                                                            case 'superadmin': echo 'bg-dark'; break;
                                                            case 'admin': echo 'bg-danger'; break;
                                                            case 'security': echo 'bg-warning text-dark'; break;
                                                            case 'user': echo 'bg-info text-dark'; break;
                                                            default: echo 'bg-secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $user['gatepass_count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Top Admin Approvers</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Admin</th>
                                            <th>Approvals</th>
                                            <th>Avg. Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['top_admins'] as $admin): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($admin['name']); ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($admin['username']); ?></small>
                                                </td>
                                                <td><?php echo $admin['approval_count']; ?></td>
                                                <td>
                                                    <?php
                                                        $minutes = $admin['avg_approval_time'];
                                                        if (!is_null($minutes)) {
                                                            $hours = floor($minutes / 60);
                                                            $mins = round($minutes % 60);
                                                            
                                                            if ($hours > 0) {
                                                                echo "$hours hrs $mins mins";
                                                            } else {
                                                                echo "$mins mins";
                                                            }
                                                        } else {
                                                            echo "N/A";
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Top Security Verifiers</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Security</th>
                                            <th>Verified</th>
                                            <th>Avg. Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['top_security'] as $security): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($security['name']); ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($security['username']); ?></small>
                                                </td>
                                                <td><?php echo $security['verification_count']; ?></td>
                                                <td>
                                                    <?php
                                                        $minutes = $security['avg_verification_time'];
                                                        if (!is_null($minutes)) {
                                                            $hours = floor($minutes / 60);
                                                            $mins = round($minutes % 60);
                                                            
                                                            if ($hours > 0) {
                                                                echo "$hours hrs $mins mins";
                                                            } else {
                                                                echo "$mins mins";
                                                            }
                                                        } else {
                                                            echo "N/A";
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="card">
            <div class="card-body text-center p-5">
                <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
                <h3>Select a Report Type</h3>
                <p class="text-muted">Choose a report type and date range to generate reports</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($chart_data)): ?>
<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($report_type === 'gatepass_summary'): ?>
        // Status pie chart
        const statusChart = new Chart(
            document.getElementById('statusChart'),
            {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($chart_data['status']['labels']); ?>,
                    datasets: [{
                        data: <?php echo json_encode($chart_data['status']['data']); ?>,
                        backgroundColor: <?php echo json_encode($chart_data['status']['colors']); ?>
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            }
        );
        
        // Daily line chart
        const dailyChart = new Chart(
            document.getElementById('dailyChart'),
            {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_data['daily']['labels']); ?>,
                    datasets: [{
                        label: 'Gatepasses',
                        data: <?php echo json_encode($chart_data['daily']['data']); ?>,
                        fill: false,
                        borderColor: '#198754',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            }
        );
        
        // Types bar chart
        const typesChart = new Chart(
            document.getElementById('typesChart'),
            {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_data['types']['labels']); ?>,
                    datasets: [{
                        label: 'Count',
                        data: <?php echo json_encode($chart_data['types']['data']); ?>,
                        backgroundColor: '#0dcaf0'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            }
        );
    <?php endif; ?>
    
    <?php if ($report_type === 'user_activity'): ?>
        // Role activity chart
        const roleActivityChart = new Chart(
            document.getElementById('roleActivityChart'),
            {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_data['role_activity']['labels']); ?>,
                    datasets: [{
                        label: 'Actions',
                        data: <?php echo json_encode($chart_data['role_activity']['data']); ?>,
                        backgroundColor: ['#212529', '#dc3545', '#ffc107', '#0d6efd']
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            }
        );
    <?php endif; ?>
    
    <?php if ($report_type === 'system_usage'): ?>
        // Hourly activity chart
        const hourlyChart = new Chart(
            document.getElementById('hourlyChart'),
            {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_data['hourly']['labels']); ?>,
                    datasets: [{
                        label: 'Activity',
                        data: <?php echo json_encode($chart_data['hourly']['data']); ?>,
                        backgroundColor: '#0d6efd'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            }
        );
        
        // Top actions chart
        const topActionsChart = new Chart(
            document.getElementById('topActionsChart'),
            {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($chart_data['top_actions']['labels']); ?>,
                    datasets: [{
                        data: <?php echo json_encode($chart_data['top_actions']['data']); ?>,
                        backgroundColor: [
                            '#0d6efd', '#198754', '#dc3545', '#ffc107', 
                            '#0dcaf0', '#6c757d', '#d63384', '#fd7e14',
                            '#20c997', '#6610f2'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            }
        );
    <?php endif; ?>
    
    <?php if ($report_type === 'verification_time'): ?>
        // Time ranges chart
        const timeRangesChart = new Chart(
            document.getElementById('timeRangesChart'),
            {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_data['time_ranges']['labels']); ?>,
                    datasets: [{
                        label: 'Count',
                        data: <?php echo json_encode($chart_data['time_ranges']['data']); ?>,
                        backgroundColor: '#0d6efd'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            }
        );
    <?php endif; ?>
    
    <?php if ($report_type === 'monthly_stats'): ?>
        // Monthly stats chart
        const monthlyChart = new Chart(
            document.getElementById('monthlyChart'),
            {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_data['monthly']['labels']); ?>,
                    datasets: [
                        {
                            label: 'Total',
                            data: <?php echo json_encode($chart_data['monthly']['datasets'][0]['data']); ?>,
                            fill: false,
                            borderColor: <?php echo json_encode($chart_data['monthly']['datasets'][0]['borderColor']); ?>,
                            tension: 0.1
                        },
                        {
                            label: 'Verified',
                            data: <?php echo json_encode($chart_data['monthly']['datasets'][1]['data']); ?>,
                            fill: false,
                            borderColor: <?php echo json_encode($chart_data['monthly']['datasets'][1]['borderColor']); ?>,
                            tension: 0.1
                        },
                        {
                            label: 'Declined',
                            data: <?php echo json_encode($chart_data['monthly']['datasets'][2]['data']); ?>,
                            fill: false,
                            borderColor: <?php echo json_encode($chart_data['monthly']['datasets'][2]['borderColor']); ?>,
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            }
        );
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php
// Close database connection
$conn->close();

// Include footer
include '../includes/footer.php';
?>
