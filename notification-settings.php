<?php
require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$page_title = "Notification Settings";
require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Settings</h4>
                </div>
                <div class="card-body">
                    <!-- Push Notification Status -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <h5><i class="fas fa-mobile-alt me-2"></i>Push Notifications</h5>
                            <p class="text-muted mb-2">Get instant alerts on your device for gatepass updates</p>
                            <div id="notificationStatus" class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Checking notification status...
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <button id="toggleNotifications" class="btn btn-primary btn-lg" onclick="toggleNotifications()">
                                <i class="fas fa-bell me-2"></i>Enable Notifications
                            </button>
                        </div>
                    </div>

                    <!-- Benefits Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5><i class="fas fa-star me-2"></i>Benefits of Push Notifications</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Instant gatepass approval alerts</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Security verification updates</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Status change notifications</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Works even when browser is closed</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>No need to constantly check for updates</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Real-time communication</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Test Notification -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5><i class="fas fa-test-tube me-2"></i>Test Notifications</h5>
                            <p class="text-muted">Send yourself a test notification to see how they work</p>
                            <button id="testNotification" class="btn btn-outline-primary" onclick="sendTestNotification()">
                                <i class="fas fa-paper-plane me-2"></i>Send Test Notification
                            </button>
                        </div>
                    </div>

                    <!-- Browser Support -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5><i class="fas fa-browser me-2"></i>Browser Support</h5>
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <i class="fab fa-chrome fa-2x text-warning mb-2"></i>
                                    <p class="small">Chrome 42+</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <i class="fab fa-firefox fa-2x text-danger mb-2"></i>
                                    <p class="small">Firefox 44+</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <i class="fab fa-edge fa-2x text-info mb-2"></i>
                                    <p class="small">Edge 17+</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <i class="fab fa-safari fa-2x text-primary mb-2"></i>
                                    <p class="small">Safari 16+</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Troubleshooting -->
                    <div class="row">
                        <div class="col-12">
                            <h5><i class="fas fa-question-circle me-2"></i>Troubleshooting</h5>
                            <div class="accordion" id="troubleshootingAccordion">
                                <div class="accordion-item">
                                    <h6 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                            I'm not receiving notifications
                                        </button>
                                    </h6>
                                    <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                        <div class="accordion-body">
                                            <ul>
                                                <li>Check if notifications are enabled in your browser settings</li>
                                                <li>Make sure the website is allowed to send notifications</li>
                                                <li>Try refreshing the page and enabling notifications again</li>
                                                <li>Check if your device's "Do Not Disturb" mode is off</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h6 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                            How to enable notifications manually
                                        </button>
                                    </h6>
                                    <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                        <div class="accordion-body">
                                            <p><strong>Chrome:</strong> Click the lock icon in the address bar → Notifications → Allow</p>
                                            <p><strong>Firefox:</strong> Click the shield icon → Permissions → Enable notifications</p>
                                            <p><strong>Safari:</strong> Safari menu → Preferences → Websites → Notifications</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update notification status on page load
document.addEventListener('DOMContentLoaded', function() {
    updateNotificationStatus();
});

function updateNotificationStatus() {
    const statusDiv = document.getElementById('notificationStatus');
    const toggleBtn = document.getElementById('toggleNotifications');
    const testBtn = document.getElementById('testNotification');
    
    if (!('Notification' in window)) {
        statusDiv.className = 'alert alert-warning';
        statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Push notifications are not supported by your browser';
        toggleBtn.disabled = true;
        testBtn.disabled = true;
        return;
    }
    
    const permission = Notification.permission;
    
    if (permission === 'granted') {
        statusDiv.className = 'alert alert-success';
        statusDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i>Push notifications are enabled';
        toggleBtn.innerHTML = '<i class="fas fa-bell-slash me-2"></i>Disable Notifications';
        toggleBtn.className = 'btn btn-outline-danger btn-lg';
        testBtn.disabled = false;
    } else if (permission === 'denied') {
        statusDiv.className = 'alert alert-danger';
        statusDiv.innerHTML = '<i class="fas fa-times-circle me-2"></i>Push notifications are blocked. Please enable them in your browser settings.';
        toggleBtn.disabled = true;
        testBtn.disabled = true;
    } else {
        statusDiv.className = 'alert alert-info';
        statusDiv.innerHTML = '<i class="fas fa-info-circle me-2"></i>Push notifications are not enabled';
        toggleBtn.innerHTML = '<i class="fas fa-bell me-2"></i>Enable Notifications';
        toggleBtn.className = 'btn btn-primary btn-lg';
        testBtn.disabled = true;
    }
}

function toggleNotifications() {
    if (window.pushManager) {
        window.pushManager.toggleNotifications().then(() => {
            setTimeout(updateNotificationStatus, 1000);
        });
    }
}

function sendTestNotification() {
    if (window.sendTestNotification) {
        window.sendTestNotification();
    }
}

// Listen for permission changes
if ('Notification' in window) {
    // Some browsers support permission change events
    navigator.permissions?.query?.({name: 'notifications'}).then(function(permissionStatus) {
        permissionStatus.onchange = function() {
            updateNotificationStatus();
        };
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
