<?php
require_once 'includes/config.php';

// Check the source of navigation
$from = isset($_GET['from']) ? $_GET['from'] : '';

// Set page title
$page_title = "Copyright & Legal Notice";

// Store the return path information
$return_path = 'index.php';
$dashboard_link = 'index.php';
$section_name = '';

switch ($from) {
    case 'admin':
        $return_path = 'admin/dashboard.php';
        $dashboard_link = 'admin/dashboard.php';
        $section_name = 'Admin';
        break;
    case 'security':
        $return_path = 'security/dashboard.php';
        $dashboard_link = 'security/dashboard.php';
        $section_name = 'Security';
        break;
    case 'user':
        $return_path = 'user/dashboard.php';
        $dashboard_link = 'user/dashboard.php';
        $section_name = 'User';
        break;
}

// Include header
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-copyright me-2"></i>Copyright & Legal Notice</h2>
                <a href="<?php echo $return_path; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to <?php echo !empty($section_name) ? $section_name . ' ' : ''; ?>Dashboard
                </a>
            </div>
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="mb-0 fs-4"><i class="fas fa-copyright me-2"></i>Copyright & Legal Notice</h1>
                </div><div class="card-body copyright-notice">
                    <div class="alert alert-warning">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                            <div>
                                <h5>Important Notice</h5>
                                <p class="mb-0">This software is protected by copyright law and international treaties. Unauthorized reproduction or distribution of this software, or any portion of it, may result in severe civil and criminal penalties, and will be prosecuted to the maximum extent possible under the law.</p>
                            </div>
                        </div>
                    </div>

                    <h5 class="mt-4 border-bottom pb-2">Proprietary Software Notice</h5>
                    <p>The Gunayatan Gatepass System is proprietary software developed by <strong>Piyush Maji</strong>. All rights to the software, including but not limited to copyright, trade secrets, and intellectual property rights, are owned exclusively by the developer.</p>

                    <h5 class="mt-4 border-bottom pb-2">Restrictions</h5>
                    <p>Unless explicitly authorized in writing by the developer, you may NOT:</p>
                    <ul>
                        <li>Modify, adapt, or alter the software or source code in any way</li>
                        <li>Decompile, reverse engineer, disassemble, or otherwise attempt to derive the source code</li>
                        <li>Remove or modify any copyright notices or other proprietary markings</li>
                        <li>Create derivative works based on any part of the software</li>
                        <li>Redistribute, sell, rent, lease, sublicense, or otherwise transfer rights to the software</li>
                        <li>Use the software for any purpose other than its intended use at Gunayatan</li>
                    </ul>

                    <h5 class="mt-4 border-bottom pb-2">Security Notice</h5>
                    <p>This software contains security features and monitoring systems that detect unauthorized access or tampering attempts. Unauthorized access or attempts to circumvent security measures are strictly prohibited and may result in:</p>
                    <ul>
                        <li>Immediate termination of access rights</li>
                        <li>Legal action for damages</li>
                        <li>Criminal prosecution under applicable computer crime laws</li>
                    </ul>

                    <h5 class="mt-4 border-bottom pb-2">Ownership Statement</h5>
                    <p>The following components of the Gunayatan Gatepass System are protected:</p>
                    <ul>
                        <li>Source code and compiled code</li>
                        <li>Database structure and organization</li>
                        <li>User interface design and elements</li>
                        <li>Documentation and training materials</li>
                        <li>Business logic and workflows</li>
                        <li>Graphics, logos, and visual elements</li>
                    </ul>

                    <h5 class="mt-4 border-bottom pb-2">Contact Information</h5>
                    <p>For licensing inquiries, permissions, or to report security issues, contact the developer:</p>
                    <div class="card bg-light p-3 mb-3">
                        <p class="mb-1"><strong>Developer:</strong> Piyush Maji</p>
                        <p class="mb-1"><strong>Email:</strong> <a href="mailto:contact@piyushmaji.com">piyush.maji@your_domain_name</a></p>
                        <p class="mb-0"><strong>Website:</strong> <a href="https://www.piyushmaji.com" target="_blank">www.piyushmaji.com</a></p>
                    </div>

                    <h5 class="mt-4 border-bottom pb-2">License Verification</h5>
                    <p>The Gunayatan Gatepass System includes an automated license verification system that periodically validates the authenticity of the software installation. Attempts to bypass this verification may result in the software becoming non-functional and may constitute a violation of applicable copyright laws.</p>

                    <div class="alert alert-danger mt-4">
                        <h5><i class="fas fa-shield-alt me-2"></i>Security Warning</h5>
                        <p class="mb-0">This software is protected with multiple layers of security. Any attempt to tamper with, modify, or reverse-engineer the code may trigger security alerts and automatic defensive measures, including but not limited to system lockdown, data protection protocols, and notification to authorized administrators.</p>
                    </div>

                    <div class="text-center mt-5">
                        <p class="mb-0"><strong>&copy; <?php echo date('Y'); ?> Piyush Maji. All Rights Reserved.</strong></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Add the copyright protection script
$additional_js = '<script src="assets/js/copyright.js"></script>';

// Add a hidden debug div to help with troubleshooting (visible only to admins)
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    echo '<div class="container mt-5 small text-muted">
        <details>
            <summary>Debug Information (Admin Only)</summary>
            <p>Return Path: ' . htmlspecialchars($return_path) . '<br>
            Dashboard Link: ' . htmlspecialchars($dashboard_link) . '<br>
            From Parameter: ' . htmlspecialchars($from) . '<br>
            Current Page: ' . htmlspecialchars($_SERVER['PHP_SELF']) . '<br>
            Current Section: ' . htmlspecialchars($section_name) . '</p>
        </details>
    </div>';
}

// Include footer
include 'includes/footer.php';
?>
