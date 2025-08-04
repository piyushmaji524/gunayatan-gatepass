<?php
// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

// Get the current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>    
    <!-- User ID for push notifications -->
    <meta name="user-id" content="<?php echo $_SESSION['user_id']; ?>">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#2c3e50">
    
    <!-- Favicon -->
    <?php 
        $current_dir = dirname($_SERVER['PHP_SELF']);
        $favicon_path = '';
        
        if (strpos($current_dir, '/admin') !== false || 
            strpos($current_dir, '/user') !== false || 
            strpos($current_dir, '/security') !== false) {
            $favicon_path = '../assets/img/';
        } else {
            $favicon_path = 'assets/img/';
        }
    ?>
    <!-- Standard Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo $favicon_path; ?>favicon.svg">
    <link rel="icon" type="image/png" href="<?php echo $favicon_path; ?>favicon.png" sizes="32x32">
    <link rel="icon" type="image/x-icon" href="<?php echo $favicon_path; ?>favicon.ico">
    
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" href="<?php echo $favicon_path; ?>favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $favicon_path; ?>apple-touch-icon.png">
    
    <!-- Android Chrome -->
    <link rel="manifest" href="<?php echo $favicon_path; ?>site.webmanifest">
    <meta name="theme-color" content="#2c3e50">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"><!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- Additional CSS if needed -->
    <?php if (isset($additional_css)) echo $additional_css; ?>
    
    <!-- PWA Install Script -->
    <script src="../assets/js/pwa-install.js"></script>
    <!-- Push Notification Script -->
    <script src="../assets/js/push-notifications.js"></script>
    
    <!-- Immediate dark mode application script -->
    <script>
    (function() {
        // Apply dark mode immediately if it's saved in localStorage
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('theme-transition-disabled');
            document.body.classList.add('dark-mode');
            setTimeout(function() {
                document.documentElement.classList.remove('theme-transition-disabled');
            }, 300);
        }
    })();
    </script>
    <style>
    .theme-transition-disabled * {
        transition: none !important;
    }
    </style>
</head>
<body>    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="/#">
                <i class="fas fa-id-card-alt me-2"></i>
                <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">                    <?php 
                    // Adjust dashboard link for copyright page
                    $current_is_copyright = ($current_page == 'copyright.php');
                    $dashboard_href = isset($dashboard_link) && $current_is_copyright ? $dashboard_link : 'dashboard.php';
                    
                    if ($_SESSION['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo $dashboard_href; ?>">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>" href="manage_users.php">
                                <i class="fas fa-users me-1"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'all_gatepasses.php') ? 'active' : ''; ?>" href="all_gatepasses.php">
                                <i class="fas fa-clipboard-list me-1"></i> All Gatepasses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                                <i class="fas fa-chart-bar me-1"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'system_logs.php') ? 'active' : ''; ?>" href="system_logs.php">
                                <i class="fas fa-history me-1"></i> System Logs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'download_pdf.php') ? 'active' : ''; ?>" href="https://purchase.gunayatangatepass.com/login.php">
                                <i class="fas fa-file-pdf me-1"></i> Request Meterial Login
                            </a>
                        </li>                    <?php elseif ($_SESSION['role'] == 'security'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo $dashboard_href; ?>">
                                <i class="fas fa-shield-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'search_gatepass.php') ? 'active' : ''; ?>" href="search_gatepass.php">
                                <i class="fas fa-search me-1"></i> Search Gatepass
                            </a>
                       
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'verified_gatepasses.php') ? 'active' : ''; ?>" href="verified_gatepasses.php">
                                <i class="fas fa-check-circle me-1"></i> Verified Gatepasses
                            </a>
                        </li>                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo $dashboard_href; ?>">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'new_gatepass.php') ? 'active' : ''; ?>" href="new_gatepass.php">
                                <i class="fas fa-plus-circle me-1"></i> New Gatepass
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'my_gatepasses.php') ? 'active' : ''; ?>" href="my_gatepasses.php">
                                <i class="fas fa-list-alt me-1"></i> My Gatepasses
                            </a>
                        </li>
                        
                         <div class="login-footer mt-4 text-center">
    <a href="https://purchase.gunayatangatepass.com/login.php" 
       class="btn btn-danger fw-bold rounded-pill px-4 py-2">
        Purchase Login
    </a>
</div>
                        
                    <?php endif; ?>
                </ul>                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link theme-toggle-btn" href="#" onclick="toggleDarkMode(); return false;">
                            <i id="theme-icon" class="fas fa-moon"></i>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle user-profile-link" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <span class="user-role-badge"><?php echo ucfirst($_SESSION['role']); ?></span>
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-header">
                                <div class="user-header">
                                    <i class="fas fa-user-circle fa-3x"></i>
                                    <p>
                                        <?php echo htmlspecialchars($_SESSION['name']); ?>
                                        <small><?php echo ucfirst($_SESSION['role']); ?></small>                            </p>
                                </div>
                            </li>
                            <?php if ($_SESSION['role'] == 'admin'): ?>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <?php elseif ($_SESSION['role'] == 'security'): ?>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <?php elseif ($_SESSION['role'] == 'user'): ?>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item theme-toggle-menu" href="#" onclick="toggleDarkMode(); return false;"><i class="fas fa-moon me-2" id="menu-theme-icon"></i><span id="themeText">Dark Mode</span></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
        // Clear the message after displaying
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        endif;
        ?>
