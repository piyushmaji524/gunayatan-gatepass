<?php
/**
 * User Credentials Display Script for Gunayatan Gatepass System
 * Shows all user credentials with copy functionality
 * Only accessible to superadmins for system administration
 */

require_once 'includes/config.php';

// Security check - only allow superadmin access
if (!isLoggedIn() || $_SESSION['role'] !== 'superadmin') {
    // Allow localhost access for emergency recovery
    $localhost_ips = array('127.0.0.1', '::1', 'localhost');
    $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!in_array($remote_addr, $localhost_ips)) {
        header("Location: index.php");
        exit();
    }
}

// Connect to database
$conn = connectDB();

// Get all users with their details
$stmt = $conn->prepare("
    SELECT id, username, name, email, role, status, created_at 
    FROM users 
    ORDER BY 
        CASE role 
            WHEN 'superadmin' THEN 1
            WHEN 'admin' THEN 2
            WHEN 'security' THEN 3
            WHEN 'user' THEN 4
            ELSE 5
        END,
        name ASC
");
$stmt->execute();
$users = $stmt->get_result();

// Default passwords based on role (as typically set in the system)
$default_passwords = [
    'superadmin' => 'admin123',
    'admin' => 'admin123',
    'security' => 'security123',
    'user' => 'user123'
];

// Role-specific instructions
$role_instructions = [
    'superadmin' => [
        'name' => 'Super Admin',
        'instructions' => 'You have full system access. Manage all users, system settings, database, and security. Create gatepasses, manage all departments, and oversee the entire system.',
        'access_areas' => ['Dashboard', 'User Management', 'System Settings', 'Database Management', 'Security Settings', 'Reports', 'All Modules']
    ],
    'admin' => [
        'name' => 'Admin',
        'instructions' => 'You can approve gatepasses submitted by users, manage regular users, view reports, and handle day-to-day administrative tasks.',
        'access_areas' => ['Admin Dashboard', 'Approve Gatepasses', 'User Management', 'Reports', 'Profile Settings']
    ],
    'security' => [
        'name' => 'Security Officer',
        'instructions' => 'You can verify approved gatepasses, search for gatepasses, and manage the final verification process before items leave the premises.',
        'access_areas' => ['Security Dashboard', 'Verify Gatepasses', 'Search Gatepasses', 'View Reports', 'Profile Settings']
    ],
    'user' => [
        'name' => 'Regular User',
        'instructions' => 'You can create new gatepass requests, view your gatepass history, and track the status of your submissions.',
        'access_areas' => ['User Dashboard', 'Create Gatepass', 'My Gatepasses', 'Profile Settings']
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Credentials - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .main-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 20px auto;
            max-width: 1200px;
        }
        
        .header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        
        .user-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 25px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }
        
        .user-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        
        .role-header {
            padding: 15px 20px;
            font-weight: 600;
            color: white;
        }
        
        .role-superadmin { background: linear-gradient(45deg, #2c3e50, #34495e); }
        .role-admin { background: linear-gradient(45deg, #e74c3c, #c0392b); }
        .role-security { background: linear-gradient(45deg, #f39c12, #d68910); }
        .role-user { background: linear-gradient(45deg, #3498db, #2980b9); }
        
        .user-details {
            padding: 20px;
        }
        
        .credential-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            position: relative;
        }
        
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            font-size: 12px;
            border: none;
            border-radius: 5px;
            background: #667eea;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .copy-btn:hover {
            background: #5a6fd8;
            transform: scale(1.05);
        }
        
        .copy-btn.copied {
            background: #28a745;
        }
        
        .welcome-message {
            background: #e8f4fd;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            white-space: pre-line;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            background: #28a745;
            color: white;
            border: none;
        }
        
        .website-url {
            background: #e7f3ff;
            border: 2px solid #0066cc;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin: 20px 0;
            font-size: 18px;
            font-weight: 600;
            color: #0066cc;
        }
        
        .stats-row {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        
        .instruction-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin: 20px 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            font-family: 'Poppins', sans-serif;
            position: relative;
            overflow: hidden;
        }
        
        .instruction-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .instruction-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        
        .instruction-title {
            font-size: 2.5em;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 10px;
        }
        
        .instruction-subtitle {
            font-size: 1.2em;
            opacity: 0.9;
            font-weight: 300;
        }
        
        .credential-section {
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            z-index: 1;
        }
        
        .credential-item {
            display: flex;
            align-items: center;
            margin: 15px 0;
            font-size: 1.1em;
        }
        
        .credential-label {
            font-weight: 600;
            min-width: 140px;
            color: #ffd700;
        }
        
        .credential-value {
            font-weight: 500;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 5px;
            flex: 1;
            margin-left: 10px;
        }
        
        .instruction-content {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
            position: relative;
            z-index: 1;
        }
        
        .instruction-content h4 {
            color: #ffd700;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .access-areas {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .access-tag {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .website-section {
            text-align: center;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            position: relative;
            z-index: 1;
        }
        
        .website-url-large {
            font-size: 1.5em;
            font-weight: 600;
            color: #ffd700;
            text-decoration: none;
            border: 2px solid #ffd700;
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .footer-section {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.3);
            position: relative;
            z-index: 1;
        }
        
        .copy-image-btn {
            background: #28a745;
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 12px;
            margin-left: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .copy-image-btn:hover {
            background: #218838;
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <?php if (isLoggedIn()): ?>
    <a href="<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-light back-btn">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
    <?php endif; ?>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <div class="container">
        <div class="main-container">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-users-cog me-3"></i>GUNAYATAN GATEPASS</h1>
                <h2>User Credentials & Access Information</h2>
                <p class="mb-0">Complete user account details with login credentials</p>
            </div>

            <!-- Website URL -->
            <div class="website-url">
                <i class="fas fa-globe me-2"></i>
                Login Portal: <strong>gunayatangatepass.com</strong>
            </div>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="row text-center">
                    <div class="col-md-3 stat-item">
                        <div class="stat-number"><?php echo $users->num_rows; ?></div>
                        <div>Total Users</div>
                    </div>
                    <?php
                    // Reset pointer to count roles
                    $users->data_seek(0);
                    $role_counts = ['superadmin' => 0, 'admin' => 0, 'security' => 0, 'user' => 0];
                    while ($user = $users->fetch_assoc()) {
                        if (isset($role_counts[$user['role']])) {
                            $role_counts[$user['role']]++;
                        }
                    }
                    $users->data_seek(0); // Reset again for main display
                    ?>
                    <div class="col-md-3 stat-item">
                        <div class="stat-number"><?php echo $role_counts['admin']; ?></div>
                        <div>Admins</div>
                    </div>
                    <div class="col-md-3 stat-item">
                        <div class="stat-number"><?php echo $role_counts['security']; ?></div>
                        <div>Security</div>
                    </div>
                    <div class="col-md-3 stat-item">
                        <div class="stat-number"><?php echo $role_counts['user']; ?></div>
                        <div>Users</div>
                    </div>
                </div>
            </div>

            <!-- User Cards -->
            <div class="row p-4">
                <?php while ($user = $users->fetch_assoc()): ?>
                    <?php 
                    $role_info = $role_instructions[$user['role']] ?? $role_instructions['user'];
                    $default_password = $default_passwords[$user['role']] ?? 'user123';
                    ?>
                    
                    <div class="col-md-6 mb-4">
                        <div class="user-card">
                            <!-- Role Header -->
                            <div class="role-header role-<?php echo $user['role']; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="fas <?php 
                                            echo $user['role'] === 'superadmin' ? 'fa-crown' : 
                                                ($user['role'] === 'admin' ? 'fa-user-shield' : 
                                                ($user['role'] === 'security' ? 'fa-shield-alt' : 'fa-user')); 
                                        ?> me-2"></i>
                                        <?php echo strtoupper($role_info['name']); ?>
                                    </span>
                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- User Details -->
                            <div class="user-details">
                                <h5 class="mb-3"><?php echo htmlspecialchars($user['name']); ?></h5>
                                
                                <!-- Credentials Box -->
                                <div class="credential-box">
                                    <button class="copy-btn" onclick="copyCredentials(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-copy me-1"></i>Copy
                                    </button>
                                    <button class="copy-image-btn" onclick="copyUserInstructionAsImage(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-image me-1"></i>Copy as Image
                                    </button>
                                    <strong>User ID:</strong> <?php echo htmlspecialchars($user['username']); ?><br>
                                    <strong>Password:</strong> <?php echo $default_password; ?><br>
                                    <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                                </div>

                                <!-- User Instruction Card (for image capture) -->
                                <div class="instruction-card" id="instruction-card-<?php echo $user['id']; ?>" style="display: none;">
                                    <div class="instruction-header">
                                        <div class="instruction-title">GUNAYATAN GATEPASS</div>
                                        <div class="instruction-subtitle">User Access Information</div>
                                    </div>
                                    
                                    <div class="credential-section">
                                        <div class="credential-item">
                                            <div class="credential-label">YOUR ROLE:</div>
                                            <div class="credential-value"><?php echo strtoupper($role_info['name']); ?></div>
                                        </div>
                                        <div class="credential-item">
                                            <div class="credential-label">USER ID:</div>
                                            <div class="credential-value"><?php echo htmlspecialchars($user['username']); ?></div>
                                        </div>
                                        <div class="credential-item">
                                            <div class="credential-label">PASSWORD:</div>
                                            <div class="credential-value"><?php echo $default_password; ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="website-section">
                                        <div style="margin-bottom: 15px; font-size: 1.2em; font-weight: 600;">PLEASE LOGIN FROM HERE:</div>
                                        <div class="website-url-large">gunayatangatepass.com</div>
                                    </div>
                                    
                                    <div class="instruction-content">
                                        <h4><i class="fas fa-info-circle me-2"></i>HOW TO USE:</h4>
                                        <p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 20px;">
                                            <?php echo $role_info['instructions']; ?>
                                        </p>
                                        
                                        <h4><i class="fas fa-key me-2"></i>ACCESS AREAS:</h4>
                                        <div class="access-areas">
                                            <?php foreach ($role_info['access_areas'] as $area): ?>
                                                <span class="access-tag"><?php echo $area; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="footer-section">
                                        <div style="font-size: 1.3em; font-weight: 600; color: #ffd700;">THANK YOU</div>
                                        <div style="margin-top: 10px; font-size: 0.9em; opacity: 0.8;">
                                            Generated on <?php echo date('d M Y, h:i A'); ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Welcome Message -->
                                <div class="welcome-message" id="welcome-<?php echo $user['id']; ?>">GUNAYATAN GATEPASS


YOUR USER ID - <?php echo htmlspecialchars($user['username']); ?>

YOUR PASSWORD - <?php echo $default_password; ?>

PLEASE LOGIN FROM HERE - gunayatangatepass.com

MUST CHANGE PASSWORD AFTER FIRST LOGIN FROM PROFILE SECTION

</div>


                                <!-- Action Buttons -->
                                <div class="mt-3">
                                    <button class="btn btn-primary btn-sm" onclick="copyWelcomeMessage(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-copy me-1"></i>Copy Welcome Message
                                    </button>
                                    <button class="btn btn-info btn-sm" onclick="copyLoginDetails(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-key me-1"></i>Copy Login Only
                                    </button>
                                    <button class="btn btn-success btn-sm" onclick="toggleInstructionCard(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>Preview Card
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="copyUserInstructionAsImage(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-camera me-1"></i>Copy as Image
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Important Notes -->
            <div class="alert alert-warning mx-4 mb-4">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Important Security Notes:</h5>
                <ul class="mb-0">
                    <li>These are default passwords. Users should change them after first login.</li>
                    <li>This page should only be accessible to system administrators.</li>
                    <li>Always verify user identity before sharing credentials.</li>
                    <li>Consider implementing password reset functionality for enhanced security.</li>
                    <li>Monitor user access and update passwords regularly.</li>
                </ul>
            </div>

            <!-- Keyboard Shortcuts -->
            <div class="alert alert-info mx-4 mb-4">
                <h5><i class="fas fa-keyboard me-2"></i>Keyboard Shortcuts & Bulk Actions:</h5>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="mb-0">
                            <li><kbd>Ctrl + Shift + C</kbd> - Copy all credentials as text</li>
                            <li><kbd>Ctrl + Shift + I</kbd> - Copy all instruction cards as single image</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <button class="btn btn-primary btn-sm me-2" onclick="copyAllInstructionsAsImage()">
                            <i class="fas fa-images me-1"></i>Copy All as Image
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="toggleAllInstructionCards()">
                            <i class="fas fa-eye me-1"></i>Toggle All Cards
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center p-4 bg-light border-top">
                <p class="mb-0">
                    <strong>Gunayatan Gatepass System</strong> - User Management Portal<br>
                    <small class="text-muted">Generated on <?php echo date('d M Y, h:i A'); ?></small>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toast notification function
        function showToast(message, type = 'success') {
            const toastHtml = `
                <div class="toast" role="alert">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                        ${message}
                    </div>
                </div>
            `;
            
            const toastContainer = document.querySelector('.toast-container');
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            const toastElement = toastContainer.lastElementChild;
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            // Remove toast after it's hidden
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }

        // Copy credentials function
        function copyCredentials(userId) {
            const userCard = document.querySelector(`#welcome-${userId}`).closest('.user-card');
            const username = userCard.querySelector('.credential-box').textContent.match(/User ID: (.+?)(?=Password:)/)[1].trim();
            const password = userCard.querySelector('.credential-box').textContent.match(/Password: (.+?)(?=Email:)/)[1].trim();
            
            const credentials = `Username: ${username}\nPassword: ${password}`;
            
            navigator.clipboard.writeText(credentials).then(() => {
                const btn = userCard.querySelector('.copy-btn');
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
                btn.classList.add('copied');
                
                showToast('Credentials copied to clipboard!');
                
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-copy me-1"></i>Copy';
                    btn.classList.remove('copied');
                }, 2000);
            });
        }

        // Copy welcome message function
        function copyWelcomeMessage(userId) {
            const welcomeText = document.querySelector(`#welcome-${userId}`).textContent;
            
            navigator.clipboard.writeText(welcomeText).then(() => {
                showToast('Welcome message copied to clipboard!');
            });
        }

        // Copy login details only
        function copyLoginDetails(userId) {
            const userCard = document.querySelector(`#welcome-${userId}`).closest('.user-card');
            const username = userCard.querySelector('.credential-box').textContent.match(/User ID: (.+?)(?=Password:)/)[1].trim();
            const password = userCard.querySelector('.credential-box').textContent.match(/Password: (.+?)(?=Email:)/)[1].trim();
            
            const loginDetails = `User ID: ${username}\nPassword: ${password}\nLogin: gunayatangatepass.com`;
            
            navigator.clipboard.writeText(loginDetails).then(() => {
                showToast('Login details copied to clipboard!');
            });
        }

        // Toggle instruction card visibility
        function toggleInstructionCard(userId) {
            const instructionCard = document.querySelector(`#instruction-card-${userId}`);
            const button = event.target.closest('button');
            
            if (instructionCard.style.display === 'none' || instructionCard.style.display === '') {
                instructionCard.style.display = 'block';
                button.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Hide Card';
                button.classList.remove('btn-success');
                button.classList.add('btn-secondary');
                
                // Scroll to the instruction card
                instructionCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                instructionCard.style.display = 'none';
                button.innerHTML = '<i class="fas fa-eye me-1"></i>Preview Card';
                button.classList.remove('btn-secondary');
                button.classList.add('btn-success');
            }
        }

        // Copy user instruction as image
        function copyUserInstructionAsImage(userId) {
            const instructionCard = document.querySelector(`#instruction-card-${userId}`);
            const button = event.target.closest('button');
            
            // Show the instruction card temporarily if hidden
            const wasHidden = instructionCard.style.display === 'none' || instructionCard.style.display === '';
            if (wasHidden) {
                instructionCard.style.display = 'block';
            }
            
            // Update button state
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Capturing...';
            button.disabled = true;
            
            // Use html2canvas to capture the instruction card
            html2canvas(instructionCard, {
                backgroundColor: null,
                scale: 2, // Higher quality
                useCORS: true,
                allowTaint: true,
                logging: false,
                width: instructionCard.offsetWidth,
                height: instructionCard.offsetHeight
            }).then(canvas => {
                // Convert canvas to blob
                canvas.toBlob(async (blob) => {
                    try {
                        // Copy image to clipboard
                        await navigator.clipboard.write([
                            new ClipboardItem({
                                'image/png': blob
                            })
                        ]);
                        
                        showToast('User instruction image copied to clipboard!', 'success');
                        
                        // Also create a download link as fallback
                        const downloadLink = document.createElement('a');
                        downloadLink.href = canvas.toDataURL();
                        downloadLink.download = `user-instruction-${userId}-${Date.now()}.png`;
                        
                        // Automatically trigger download as backup
                        // downloadLink.click();
                        
                    } catch (err) {
                        console.error('Failed to copy image to clipboard:', err);
                        
                        // Fallback: Download the image
                        const downloadLink = document.createElement('a');
                        downloadLink.href = canvas.toDataURL();
                        downloadLink.download = `user-instruction-${userId}-${Date.now()}.png`;
                        downloadLink.click();
                        
                        showToast('Image downloaded (clipboard not supported)', 'info');
                    }
                    
                    // Hide the instruction card if it was hidden before
                    if (wasHidden) {
                        instructionCard.style.display = 'none';
                    }
                    
                    // Restore button state
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                    
                }, 'image/png');
            }).catch(error => {
                console.error('Error capturing image:', error);
                showToast('Failed to capture image', 'error');
                
                // Hide the instruction card if it was hidden before
                if (wasHidden) {
                    instructionCard.style.display = 'none';
                }
                
                // Restore button state
                button.innerHTML = originalHtml;
                button.disabled = false;
            });
        }

        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.user-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'C') {
                // Copy all credentials
                let allCredentials = '';
                document.querySelectorAll('.welcome-message').forEach(msg => {
                    allCredentials += msg.textContent + '\n\n-------------------\n\n';
                });
                
                navigator.clipboard.writeText(allCredentials).then(() => {
                    showToast('All user credentials copied to clipboard!');
                });
            }
            
            if (e.ctrlKey && e.shiftKey && e.key === 'I') {
                // Copy all instruction cards as single image
                copyAllInstructionsAsImage();
            }
        });

        // Copy all instruction cards as a single image
        function copyAllInstructionsAsImage() {
            const allCards = document.querySelectorAll('.instruction-card');
            
            // Show all instruction cards temporarily
            const hiddenCards = [];
            allCards.forEach((card, index) => {
                if (card.style.display === 'none' || card.style.display === '') {
                    card.style.display = 'block';
                    hiddenCards.push(index);
                }
            });
            
            // Create a container for all cards
            const tempContainer = document.createElement('div');
            tempContainer.style.cssText = `
                position: absolute;
                top: -9999px;
                left: -9999px;
                background: #f8f9fa;
                padding: 30px;
                font-family: 'Poppins', sans-serif;
            `;
            
            // Clone all instruction cards into the container
            allCards.forEach(card => {
                const clone = card.cloneNode(true);
                clone.style.marginBottom = '30px';
                tempContainer.appendChild(clone);
            });
            
            document.body.appendChild(tempContainer);
            
            // Capture the combined image
            html2canvas(tempContainer, {
                backgroundColor: '#f8f9fa',
                scale: 1.5,
                useCORS: true,
                allowTaint: true,
                logging: false
            }).then(canvas => {
                // Remove temporary container
                document.body.removeChild(tempContainer);
                
                // Hide cards that were hidden before
                hiddenCards.forEach(index => {
                    allCards[index].style.display = 'none';
                });
                
                // Copy to clipboard or download
                canvas.toBlob(async (blob) => {
                    try {
                        await navigator.clipboard.write([
                            new ClipboardItem({
                                'image/png': blob
                            })
                        ]);
                        showToast('All user instructions copied as single image!', 'success');
                    } catch (err) {
                        // Fallback: Download the image
                        const downloadLink = document.createElement('a');
                        downloadLink.href = canvas.toDataURL();
                        downloadLink.download = `all-user-instructions-${Date.now()}.png`;
                        downloadLink.click();
                        showToast('All instructions downloaded as image!', 'info');
                    }
                }, 'image/png');
            }).catch(error => {
                console.error('Error capturing combined image:', error);
                document.body.removeChild(tempContainer);
                
                // Hide cards that were hidden before
                hiddenCards.forEach(index => {
                    allCards[index].style.display = 'none';
                });
                
                showToast('Failed to capture combined image', 'error');
            });
        }

        // Toggle all instruction cards
        function toggleAllInstructionCards() {
            const allCards = document.querySelectorAll('.instruction-card');
            const button = event.target.closest('button');
            
            // Check if any card is visible
            const anyVisible = Array.from(allCards).some(card => 
                card.style.display === 'block'
            );
            
            if (anyVisible) {
                // Hide all cards
                allCards.forEach(card => {
                    card.style.display = 'none';
                });
                button.innerHTML = '<i class="fas fa-eye me-1"></i>Show All Cards';
                button.classList.remove('btn-secondary');
                button.classList.add('btn-success');
                
                // Update individual toggle buttons
                document.querySelectorAll('button[onclick^="toggleInstructionCard"]').forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-eye me-1"></i>Preview Card';
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-success');
                });
            } else {
                // Show all cards
                allCards.forEach(card => {
                    card.style.display = 'block';
                });
                button.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Hide All Cards';
                button.classList.remove('btn-success');
                button.classList.add('btn-secondary');
                
                // Update individual toggle buttons
                document.querySelectorAll('button[onclick^="toggleInstructionCard"]').forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Hide Card';
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-secondary');
                });
            }
        }
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>
