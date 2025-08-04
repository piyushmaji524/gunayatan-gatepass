/**
 * Push Notification Manager for Gunayatan Gatepass System
 * Handles subscription, permission requests, and push notifications
 */

class PushNotificationManager {
    constructor() {
        this.registration = null;
        this.subscription = null;
        this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        this.vapidPublicKey = 'BNwxRQojNWd7lq2_-9v0y_SLJz9F8LpPgdUj5VdWErGfXXZqPhl2FUXuDsltVYz7jlu4Z-CUhwFFMQt-x5xt1Vo'; // Updated VAPID key
        
        this.init();
    }
    
    async init() {
        if (!this.isSupported) {
            console.log('Push notifications not supported');
            return;
        }
        
        try {
            // Register service worker
            this.registration = await navigator.serviceWorker.register('/sw.js');
            console.log('Push: Service Worker registered');
            
            // Check for existing subscription
            await this.checkExistingSubscription();
            
            // Auto-request permission after app install or on first visit
            setTimeout(() => {
                this.checkAndRequestPermission();
            }, 5000);
            
        } catch (error) {
            console.error('Push: Service Worker registration failed:', error);
        }
    }
    
    async checkExistingSubscription() {
        if (!this.registration) return;
        
        this.subscription = await this.registration.pushManager.getSubscription();
        if (this.subscription) {
            console.log('Push: Existing subscription found');
            this.updateUI(true);
            // Send subscription to server to ensure it's registered
            await this.sendSubscriptionToServer(this.subscription);
        } else {
            this.updateUI(false);
        }
    }
    
    async checkAndRequestPermission() {
        if (Notification.permission === 'granted') {
            await this.subscribe();
        } else if (Notification.permission === 'default') {
            this.showPermissionPrompt();
        }
    }
    
    showPermissionPrompt() {
        // Create a user-friendly notification permission request
        const promptHtml = `
            <div class="push-permission-prompt" id="pushPermissionPrompt">
                <div class="push-prompt-content">
                    <div class="push-prompt-header">
                        <i class="fas fa-bell text-primary"></i>
                        <h5>Stay Updated</h5>
                    </div>
                    <p>Get instant notifications for:</p>
                    <ul class="push-benefits">
                        <li><i class="fas fa-check text-success"></i> Gatepass approvals</li>
                        <li><i class="fas fa-check text-success"></i> Status updates</li>
                        <li><i class="fas fa-check text-success"></i> Important alerts</li>
                    </ul>
                    <div class="push-prompt-actions">
                        <button class="btn btn-primary btn-sm" onclick="window.pushManager.requestPermissionAndSubscribe()">
                            <i class="fas fa-bell me-1"></i>Enable Notifications
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="window.pushManager.dismissPrompt()">
                            Maybe Later
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Add styles
        this.addPromptStyles();
        
        // Insert prompt
        document.body.insertAdjacentHTML('beforeend', promptHtml);
        
        // Animate appearance
        setTimeout(() => {
            const prompt = document.getElementById('pushPermissionPrompt');
            if (prompt) prompt.classList.add('visible');
        }, 100);
        
        // Auto-hide after 15 seconds
        setTimeout(() => {
            this.dismissPrompt();
        }, 15000);
    }
    
    addPromptStyles() {
        if (document.getElementById('pushPromptStyles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'pushPromptStyles';
        styles.textContent = `
            .push-permission-prompt {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.2);
                border: 1px solid #e9ecef;
                padding: 20px;
                max-width: 320px;
                z-index: 10000;
                transform: translateX(100%) translateY(100px);
                opacity: 0;
                transition: all 0.4s cubic-bezier(0.2, 0, 0.2, 1);
                font-family: 'Segoe UI', sans-serif;
            }
            
            .push-permission-prompt.visible {
                transform: translateX(0) translateY(0);
                opacity: 1;
            }
            
            .push-prompt-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 12px;
            }
            
            .push-prompt-header i {
                font-size: 1.5em;
            }
            
            .push-prompt-header h5 {
                margin: 0;
                color: #333;
            }
            
            .push-benefits {
                list-style: none;
                padding: 0;
                margin: 12px 0;
            }
            
            .push-benefits li {
                padding: 4px 0;
                font-size: 0.9em;
                color: #666;
            }
            
            .push-benefits i {
                margin-right: 8px;
                width: 16px;
            }
            
            .push-prompt-actions {
                display: flex;
                gap: 8px;
                margin-top: 15px;
            }
            
            .push-prompt-actions .btn {
                flex: 1;
                padding: 8px 12px;
                font-size: 0.85em;
                border-radius: 6px;
            }
            
            @media (max-width: 768px) {
                .push-permission-prompt {
                    bottom: 10px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }
    
    async requestPermissionAndSubscribe() {
        try {
            const permission = await Notification.requestPermission();
            this.dismissPrompt();
            
            if (permission === 'granted') {
                await this.subscribe();
                this.showToast('✅ Notifications enabled successfully!', 'success');
            } else {
                this.showToast('❌ Notifications permission denied', 'warning');
            }
        } catch (error) {
            console.error('Push: Permission request failed:', error);
            this.showToast('❌ Failed to enable notifications', 'error');
        }
    }
    
    dismissPrompt() {
        const prompt = document.getElementById('pushPermissionPrompt');
        if (prompt) {
            prompt.classList.remove('visible');
            setTimeout(() => {
                prompt.remove();
            }, 400);
        }
    }
    
    async subscribe() {
        if (!this.registration) return;
        
        try {
            // Convert VAPID key
            const convertedVapidKey = this.urlBase64ToUint8Array(this.vapidPublicKey);
            
            this.subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedVapidKey
            });
            
            console.log('Push: Subscription successful');
            
            // Send subscription to server
            await this.sendSubscriptionToServer(this.subscription);
            
            this.updateUI(true);
            
        } catch (error) {
            console.error('Push: Subscription failed:', error);
            this.showToast('Failed to subscribe to notifications', 'error');
        }
    }
    
    async unsubscribe() {
        if (!this.subscription) return;
        
        try {
            await this.subscription.unsubscribe();
            await this.removeSubscriptionFromServer();
            this.subscription = null;
            this.updateUI(false);
            this.showToast('Notifications disabled', 'info');
        } catch (error) {
            console.error('Push: Unsubscribe failed:', error);
        }
    }
    
    async sendSubscriptionToServer(subscription) {
        try {
            const response = await fetch('/api/push-subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    subscription: subscription.toJSON(),
                    userId: this.getCurrentUserId()
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to send subscription to server');
            }
            
            console.log('Push: Subscription sent to server');
        } catch (error) {
            console.error('Push: Failed to send subscription:', error);
        }
    }
    
    async removeSubscriptionFromServer() {
        try {
            await fetch('/api/push-unsubscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    userId: this.getCurrentUserId()
                })
            });
        } catch (error) {
            console.error('Push: Failed to remove subscription:', error);
        }
    }
    
    getCurrentUserId() {
        // Get current user ID from session or local storage
        const userMeta = document.querySelector('meta[name="user-id"]');
        return userMeta ? userMeta.getAttribute('content') : null;
    }
    
    updateUI(isSubscribed) {
        // Update any UI elements to show notification status
        const notificationBells = document.querySelectorAll('.notification-bell');
        notificationBells.forEach(bell => {
            if (isSubscribed) {
                bell.classList.add('active');
                bell.setAttribute('title', 'Notifications enabled');
            } else {
                bell.classList.remove('active');
                bell.setAttribute('title', 'Enable notifications');
            }
        });
        
        // Add notification status to navbar if exists
        this.addNotificationStatusToNavbar(isSubscribed);
    }
    
    addNotificationStatusToNavbar(isSubscribed) {
        const navbar = document.querySelector('.navbar-nav');
        if (!navbar) return;
        
        // Remove existing notification button
        const existingBtn = document.getElementById('notificationToggle');
        if (existingBtn) existingBtn.remove();
        
        // Create notification toggle button
        const notificationBtn = document.createElement('li');
        notificationBtn.className = 'nav-item';
        notificationBtn.innerHTML = `
            <button class="nav-link btn btn-link notification-toggle ${isSubscribed ? 'active' : ''}" 
                    id="notificationToggle" 
                    onclick="window.pushManager.toggleNotifications()"
                    title="${isSubscribed ? 'Disable notifications' : 'Enable notifications'}">
                <i class="fas fa-bell${isSubscribed ? '' : '-slash'}"></i>
                <span class="d-none d-lg-inline ms-1">${isSubscribed ? 'Notifications' : 'Enable Alerts'}</span>
            </button>
        `;
        
        navbar.appendChild(notificationBtn);
    }
    
    async toggleNotifications() {
        if (this.subscription) {
            await this.unsubscribe();
        } else {
            await this.requestPermissionAndSubscribe();
        }
    }
    
    // Test notification function
    async sendTestNotification() {
        if (!this.subscription) {
            this.showToast('Please enable notifications first', 'warning');
            return;
        }
        
        try {
            const response = await fetch('/api/send-test-notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    userId: this.getCurrentUserId()
                })
            });
            
            if (response.ok) {
                this.showToast('Test notification sent!', 'success');
            } else {
                this.showToast('Failed to send test notification', 'error');
            }
        } catch (error) {
            console.error('Push: Test notification failed:', error);
        }
    }
    
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
    
    showToast(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `push-toast push-toast-${type}`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('visible');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('visible');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 4000);
        
        // Add toast styles if not already added
        this.addToastStyles();
    }
    
    addToastStyles() {
        if (document.getElementById('pushToastStyles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'pushToastStyles';
        styles.textContent = `
            .push-toast {
                position: fixed;
                bottom: 80px;
                right: 20px;
                background: #333;
                color: white;
                padding: 12px 16px;
                border-radius: 8px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.3);
                z-index: 10001;
                transform: translateX(100%);
                opacity: 0;
                transition: all 0.3s ease;
                font-size: 0.9em;
                max-width: 300px;
            }
            
            .push-toast.visible {
                transform: translateX(0);
                opacity: 1;
            }
            
            .push-toast-success {
                background: #28a745;
            }
            
            .push-toast-warning {
                background: #ffc107;
                color: #333;
            }
            
            .push-toast-error {
                background: #dc3545;
            }
            
            .push-toast-info {
                background: #17a2b8;
            }
        `;
        
        document.head.appendChild(styles);
    }
}

// Initialize Push Notification Manager
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.pushManager = new PushNotificationManager();
        console.log('Push Notification Manager initialized');
    });
} else {
    window.pushManager = new PushNotificationManager();
    console.log('Push Notification Manager initialized immediately');
}

// Global functions for easy access
window.enableNotifications = function() {
    if (window.pushManager) {
        window.pushManager.requestPermissionAndSubscribe();
    }
};

window.sendTestNotification = function() {
    if (window.pushManager) {
        window.pushManager.sendTestNotification();
    }
};

console.log('Push Notification Manager loaded successfully');
