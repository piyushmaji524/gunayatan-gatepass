<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$page_title = "Test Push Notifications";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- User ID for push notifications -->
    <meta name="user-id" content="<?php echo $_SESSION['user_id']; ?>">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2c3e50">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- Push Notification Script -->
    <script src="assets/js/push-notifications.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-id-card-alt me-2"></i>
                <?php echo APP_NAME; ?>
            </a>
            <div class="ms-auto">
                <a href="<?php 
                    if ($_SESSION['role'] == 'admin') echo 'admin/dashboard.php';
                    elseif ($_SESSION['role'] == 'security') echo 'security/dashboard.php';
                    else echo 'user/dashboard.php';
                ?>" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-bell me-2"></i>Push Notification Test</h4>
                    </div>
                    <div class="card-body">
                        <!-- Current Status -->
                        <div class="alert alert-info" id="statusAlert">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="statusText">Checking notification support...</span>
                        </div>

                        <!-- Permission Status -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card border-secondary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-shield-alt fa-3x text-secondary mb-3"></i>
                                        <h5>Permission Status</h5>
                                        <p id="permissionStatus" class="badge bg-secondary">Checking...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <i class="fas fa-satellite-dish fa-3x text-info mb-3"></i>
                                        <h5>Subscription Status</h5>
                                        <p id="subscriptionStatus" class="badge bg-info">Checking...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row mb-4">
                            <div class="col-12 text-center">
                                <button id="enableBtn" class="btn btn-success btn-lg me-2" onclick="enableNotifications()">
                                    <i class="fas fa-bell me-2"></i>Enable Notifications
                                </button>
                                <button id="testBtn" class="btn btn-primary btn-lg me-2" onclick="sendTestNotification()">
                                    <i class="fas fa-paper-plane me-2"></i>Send Test Notification
                                </button>
                                <button id="disableBtn" class="btn btn-danger btn-lg" onclick="disableNotifications()">
                                    <i class="fas fa-bell-slash me-2"></i>Disable Notifications
                                </button>
                            </div>
                        </div>

                        <!-- Browser Info -->
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5><i class="fas fa-browser me-2"></i>Browser Information</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Browser:</strong> <span id="browserInfo">-</span></p>
                                        <p><strong>Service Worker:</strong> <span id="swSupport">-</span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Push Manager:</strong> <span id="pushSupport">-</span></p>
                                        <p><strong>Notifications:</strong> <span id="notificationSupport">-</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Test Results -->
                        <div id="testResults" class="mt-4" style="display: none;">
                            <h5><i class="fas fa-clipboard-list me-2"></i>Test Results</h5>
                            <div id="resultsList" class="list-group">
                                <!-- Results will be added here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global functions for testing
        let testResults = [];
        
        function addTestResult(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            testResults.push({
                message: message,
                type: type,
                time: timestamp
            });
            
            updateTestResults();
        }
        
        function updateTestResults() {
            const resultsDiv = document.getElementById('testResults');
            const resultsList = document.getElementById('resultsList');
            
            if (testResults.length > 0) {
                resultsDiv.style.display = 'block';
                resultsList.innerHTML = '';
                
                testResults.forEach((result, index) => {
                    const item = document.createElement('div');
                    item.className = `list-group-item list-group-item-${result.type}`;
                    item.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <span>${result.message}</span>
                            <small class="text-muted">${result.time}</small>
                        </div>
                    `;
                    resultsList.appendChild(item);
                });
            }
        }
        
        function updateBrowserInfo() {
            document.getElementById('browserInfo').textContent = navigator.userAgent.split(' ').pop();
            document.getElementById('swSupport').textContent = 'serviceWorker' in navigator ? '✅ Supported' : '❌ Not Supported';
            document.getElementById('pushSupport').textContent = 'PushManager' in window ? '✅ Supported' : '❌ Not Supported';
            document.getElementById('notificationSupport').textContent = 'Notification' in window ? '✅ Supported' : '❌ Not Supported';
        }
        
        function updateStatus() {
            if (window.pushManager) {
                // Update permission status
                const permission = Notification.permission;
                const permissionElement = document.getElementById('permissionStatus');
                
                switch(permission) {
                    case 'granted':
                        permissionElement.textContent = '✅ Granted';
                        permissionElement.className = 'badge bg-success';
                        break;
                    case 'denied':
                        permissionElement.textContent = '❌ Denied';
                        permissionElement.className = 'badge bg-danger';
                        break;
                    default:
                        permissionElement.textContent = '⏳ Default';
                        permissionElement.className = 'badge bg-warning';
                }
                
                // Update subscription status
                const subscriptionElement = document.getElementById('subscriptionStatus');
                if (window.pushManager.subscription) {
                    subscriptionElement.textContent = '✅ Subscribed';
                    subscriptionElement.className = 'badge bg-success';
                } else {
                    subscriptionElement.textContent = '❌ Not Subscribed';
                    subscriptionElement.className = 'badge bg-secondary';
                }
                
                // Update main status
                const statusText = document.getElementById('statusText');
                const statusAlert = document.getElementById('statusAlert');
                
                if (permission === 'granted' && window.pushManager.subscription) {
                    statusText.textContent = '✅ Push notifications are enabled and working!';
                    statusAlert.className = 'alert alert-success';
                } else if (permission === 'denied') {
                    statusText.textContent = '❌ Push notifications are blocked. Please enable them in browser settings.';
                    statusAlert.className = 'alert alert-danger';
                } else {
                    statusText.textContent = '⏳ Push notifications need to be enabled.';
                    statusAlert.className = 'alert alert-warning';
                }
            }
        }
        
        async function enableNotifications() {
            addTestResult('Attempting to enable notifications...', 'info');
            
            try {
                if (window.pushManager) {
                    await window.pushManager.requestPermissionAndSubscribe();
                    addTestResult('✅ Notifications enabled successfully!', 'success');
                    updateStatus();
                } else {
                    addTestResult('❌ Push notification manager not available', 'danger');
                }
            } catch (error) {
                addTestResult(`❌ Failed to enable notifications: ${error.message}`, 'danger');
            }
        }
        
        async function sendTestNotification() {
            addTestResult('Sending test notification...', 'info');
            
            try {
                if (window.pushManager) {
                    await window.pushManager.sendTestNotification();
                    addTestResult('✅ Test notification sent successfully!', 'success');
                } else {
                    addTestResult('❌ Push notification manager not available', 'danger');
                }
            } catch (error) {
                addTestResult(`❌ Failed to send test notification: ${error.message}`, 'danger');
            }
        }
        
        async function disableNotifications() {
            addTestResult('Disabling notifications...', 'info');
            
            try {
                if (window.pushManager) {
                    await window.pushManager.unsubscribe();
                    addTestResult('✅ Notifications disabled successfully!', 'success');
                    updateStatus();
                } else {
                    addTestResult('❌ Push notification manager not available', 'danger');
                }
            } catch (error) {
                addTestResult(`❌ Failed to disable notifications: ${error.message}`, 'danger');
            }
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateBrowserInfo();
            
            // Wait for push manager to initialize
            setTimeout(() => {
                updateStatus();
                addTestResult('🚀 Push notification test page loaded', 'info');
            }, 1000);
            
            // Update status every 5 seconds
            setInterval(updateStatus, 5000);
        });
    </script>
</body>
</html>
