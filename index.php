<?php
require_once 'includes/config.php';

// Check if already logged in
if (isLoggedIn()) {
    // Redirect based on role
    if ($_SESSION['role'] == 'superadmin') {
        header("Location: superadmin/dashboard.php");
    } elseif ($_SESSION['role'] == 'admin') {
        header("Location: admin/dashboard.php");
    } elseif ($_SESSION['role'] == 'security') {
        header("Location: security/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
}

$error = '';

// Login Process
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitizeInput($_POST["username"]);
    $password = $_POST["password"];
    
    if (empty($username) || empty($password)) {
        $error = "Both username and password are required";
    } else {
        $conn = connectDB();
        
        $stmt = $conn->prepare("SELECT id, username, password, name, role, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Check if user is active
            if ($user['status'] != 'active') {
                $error = "Your account is not active. Please contact administrator.";
            } else if (password_verify($password, $user['password']) || $password == $user['password']) { 
                // For demo, also allow plain password
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                
                // Log the login activity
                logActivity($user['id'], 'USER_LOGIN', 'User logged in successfully');
                  // Redirect based on role
                if ($user['role'] == 'superadmin') {
                    header("Location: superadmin/dashboard.php");
                } elseif ($user['role'] == 'admin') {
                    header("Location: admin/dashboard.php");
                } elseif ($user['role'] == 'security') {
                    header("Location: security/dashboard.php");
                } else {
                    header("Location: user/dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "Username not found";
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- User ID for push notifications (if logged in) -->
    <?php if (isLoggedIn()): ?>
    <meta name="user-id" content="<?php echo $_SESSION['user_id']; ?>">
    <?php endif; ?>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2c3e50">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <link rel="apple-touch-icon" href="assets/img/logo.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background */
        .area {
            background: transparent;
            width: 100%;
            height: 100vh;
            position: absolute;
            z-index: -1;
        }
        
        .circles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .circles li {
            position: absolute;
            display: block;
            list-style: none;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.2);
            animation: animate 25s linear infinite;
            bottom: -150px;
            border-radius: 50%;
        }
        
        .circles li:nth-child(1) {
            left: 25%;
            width: 80px;
            height: 80px;
            animation-delay: 0s;
        }
        
        .circles li:nth-child(2) {
            left: 10%;
            width: 20px;
            height: 20px;
            animation-delay: 2s;
            animation-duration: 12s;
        }
        
        .circles li:nth-child(3) {
            left: 70%;
            width: 20px;
            height: 20px;
            animation-delay: 4s;
        }
        
        .circles li:nth-child(4) {
            left: 40%;
            width: 60px;
            height: 60px;
            animation-delay: 0s;
            animation-duration: 18s;
        }
        
        .circles li:nth-child(5) {
            left: 65%;
            width: 20px;
            height: 20px;
            animation-delay: 0s;
        }
        
        .circles li:nth-child(6) {
            left: 75%;
            width: 110px;
            height: 110px;
            animation-delay: 3s;
        }
        
        .circles li:nth-child(7) {
            left: 35%;
            width: 150px;
            height: 150px;
            animation-delay: 7s;
        }
        
        .circles li:nth-child(8) {
            left: 50%;
            width: 25px;
            height: 25px;
            animation-delay: 15s;
            animation-duration: 45s;
        }
        
        .circles li:nth-child(9) {
            left: 20%;
            width: 15px;
            height: 15px;
            animation-delay: 2s;
            animation-duration: 35s;
        }
        
        .circles li:nth-child(10) {
            left: 85%;
            width: 150px;
            height: 150px;
            animation-delay: 0s;
            animation-duration: 11s;
        }
        
        @keyframes animate {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
                border-radius: 0;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
                border-radius: 50%;
            }
        }
        
        .login-container {
            max-width: 420px;
            width: 100%;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            position: relative;
            z-index: 10;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo img {
            max-width: 120px;
            height: auto;
            filter: drop-shadow(0px 4px 6px rgba(0, 0, 0, 0.1));
            transition: all 0.3s ease;
        }
        
        .logo img:hover {
            transform: scale(1.05);
        }
        
        .login-heading {
            text-align: center;
            margin-bottom: 25px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 1.8rem;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
            border-color: #3498db;
            background-color: #fff;
        }
        
        .input-group-text {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-right: none;
            color: #777;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            background: linear-gradient(135deg, #2980b9, #2c3e50);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .login-footer {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        /* Social icons */
        .social-icons {
            display: flex;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .social-icons a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            margin: 0 8px;
            border-radius: 50%;
            background: #f1f1f1;
            color: #333;
            transition: all 0.3s;
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.1);
        }
        
        .social-icons a:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 8px rgba(0, 0, 0, 0.15);
        }
        
        .social-icons .fb:hover {
            background: #3b5998;
            color: white;
        }
        
        .social-icons .tw:hover {
            background: #1da1f2;
            color: white;
        }
        
        .social-icons .ig:hover {
            background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="area">
        <ul class="circles">
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
        </ul>
    </div>
    
    <div class="login-container">
        <div class="logo">
            <img src="assets/img/logo.png" alt="Gunayatan Logo" class="img-fluid">
        </div>
        <h3 class="login-heading">Gatepass Management System</h3>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="mb-4">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                    <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </span>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i> Login
                </button>
            </div>
        </form>
        
 <div class="login-footer mt-4 text-center">
    <a href="https://purchase.gunayatangatepass.com/login.php" 
       class="btn btn-danger fw-bold rounded-pill px-4 py-2">
        Purchase Login Direct link
    </a>
</div>

        
            
            <hr class="my-3">
            
          
                </div>
            </div>
        </div>
    </div>    <!-- PWA Install Script -->
    <script src="assets/js/pwa-install.js"></script>
    <!-- Push Notification Script -->
    <script src="assets/js/push-notifications.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Initialize PWA install prompt when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Show PWA install prompt after 3 seconds for better user experience
            setTimeout(function() {
                if (window.pwaInstallManager && !window.pwaInstallManager.isInstalled) {
                    window.pwaInstallManager.checkAndShowPrompt();
                }
            }, 3000);
        });
    </script>
</body>
</html>
