/**
 * Real-Time Notification System - Frontend
 * Handles browser notifications, push notifications, and real-time updates
 */

class NotificationManager {
    constructor() {
        this.websocket = null;
        this.notificationPermission = false;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.checkInterval = null;
        
        this.init();
    }

    async init() {
        // Request notification permission
        await this.requestNotificationPermission();
        
        // Start checking for new notifications
        this.startNotificationPolling();
        
        // Initialize service worker for push notifications
        this.initServiceWorker();
        
        // Show unread notification count
        this.updateNotificationBadge();
        
        // Initialize real-time features
        this.initRealTimeFeatures();
    }

    /**
     * Request browser notification permission
     */
    async requestNotificationPermission() {
        if (!("Notification" in window)) {
            console.log("This browser does not support notifications");
            return false;
        }

        if (Notification.permission === "granted") {
            this.notificationPermission = true;
            return true;
        }

        if (Notification.permission !== "denied") {
            const permission = await Notification.requestPermission();
            this.notificationPermission = (permission === "granted");
            return this.notificationPermission;
        }

        return false;
    }

    /**
     * Start polling for new notifications
     */
    startNotificationPolling() {
        // Check for new notifications every 30 seconds
        this.checkInterval = setInterval(() => {
            this.checkForNewNotifications();
        }, 30000);

        // Initial check
        this.checkForNewNotifications();
    }

    /**
     * Check for new notifications from server
     */
    async checkForNewNotifications() {
        try {
            const response = await fetch('/api/get_new_notifications.php', {
                method: 'GET',
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                
                if (data.success && data.notifications) {
                    data.notifications.forEach(notification => {
                        this.showNotification(notification);
                        this.markNotificationAsDelivered(notification.id);
                    });
                }

                // Update badge count
                if (data.unread_count !== undefined) {
                    this.updateNotificationBadge(data.unread_count);
                }
            }
        } catch (error) {
            console.error('Error checking for notifications:', error);
        }
    }

    /**
     * Show browser notification
     */
    showNotification(notification) {
        if (!this.notificationPermission) return;

        // Show browser notification
        const browserNotification = new Notification(notification.title, {
            body: notification.message,
            icon: '/assets/img/logo.png',
            badge: '/assets/img/logo.png',
            tag: notification.type + '_' + notification.id,
            requireInteraction: notification.urgency === 'high',
            actions: notification.action_url ? [
                {
                    action: 'view',
                    title: 'View Details',
                    icon: '/assets/img/view-icon.png'
                }
            ] : []
        });

        // Handle notification click
        browserNotification.onclick = (event) => {
            event.preventDefault();
            window.focus();
            
            if (notification.action_url) {
                window.location.href = notification.action_url;
            }
            
            browserNotification.close();
            this.markNotificationAsRead(notification.id);
        };

        // Auto-close after 10 seconds for low priority notifications
        if (notification.urgency !== 'high') {
            setTimeout(() => {
                browserNotification.close();
            }, 10000);
        }

        // Show in-app notification as well
        this.showInAppNotification(notification);
    }

    /**
     * Show in-app notification (toast)
     */
    showInAppNotification(notification) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${this.getBootstrapClass(notification.urgency)} alert-dismissible notification-toast`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideInRight 0.3s ease-out;
        `;

        const urgencyIcons = {
            'low': 'fas fa-info-circle',
            'medium': 'fas fa-bell',
            'high': 'fas fa-exclamation-triangle',
            'urgent': 'fas fa-fire'
        };

        toast.innerHTML = `
            <div class="d-flex align-items-start">
                <i class="${urgencyIcons[notification.urgency] || 'fas fa-bell'} me-2 mt-1"></i>
                <div class="flex-grow-1">
                    <strong>${notification.title}</strong>
                    <div class="small">${notification.message}</div>
                    ${notification.action_url ? `
                        <a href="${notification.action_url}" class="btn btn-sm btn-outline-primary mt-2">
                            View Details
                        </a>
                    ` : ''}
                </div>
                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;

        document.body.appendChild(toast);

        // Auto-remove after 8 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 300);
            }
        }, 8000);

        // Mark as read when clicked
        toast.addEventListener('click', () => {
            this.markNotificationAsRead(notification.id);
        });
    }

    /**
     * Update notification badge count
     */
    updateNotificationBadge(count = null) {
        if (count === null) {
            // Fetch current count
            fetch('/api/get_notification_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.displayBadgeCount(data.count);
                    }
                })
                .catch(error => console.error('Error fetching notification count:', error));
        } else {
            this.displayBadgeCount(count);
        }
    }

    /**
     * Display badge count in UI
     */
    displayBadgeCount(count) {
        const badges = document.querySelectorAll('.notification-badge');
        badges.forEach(badge => {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        });

        // Update page title
        if (count > 0) {
            document.title = `(${count}) ${document.title.replace(/^\(\d+\)\s*/, '')}`;
        } else {
            document.title = document.title.replace(/^\(\d+\)\s*/, '');
        }
    }

    /**
     * Mark notification as delivered
     */
    async markNotificationAsDelivered(notificationId) {
        try {
            await fetch('/api/mark_notification_delivered.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId }),
                credentials: 'same-origin'
            });
        } catch (error) {
            console.error('Error marking notification as delivered:', error);
        }
    }

    /**
     * Mark notification as read
     */
    async markNotificationAsRead(notificationId) {
        try {
            await fetch('/api/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId }),
                credentials: 'same-origin'
            });
            
            // Update badge count
            this.updateNotificationBadge();
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    /**
     * Initialize service worker for push notifications
     */
    async initServiceWorker() {
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js');
                console.log('Service Worker registered:', registration);
                
                // Subscribe to push notifications
                await this.subscribeToPushNotifications(registration);
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        }
    }

    /**
     * Subscribe to push notifications
     */
    async subscribeToPushNotifications(registration) {
        try {
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array('YOUR_VAPID_PUBLIC_KEY') // Set your VAPID key
            });

            // Send subscription to server
            await fetch('/api/save_push_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subscription),
                credentials: 'same-origin'
            });

            console.log('Push subscription saved');
        } catch (error) {
            console.error('Failed to subscribe to push notifications:', error);
        }
    }

    /**
     * Initialize real-time features
     */
    initRealTimeFeatures() {
        // Real-time dashboard updates
        if (window.location.pathname.includes('dashboard.php')) {
            this.startDashboardUpdates();
        }

        // Real-time gatepass status updates
        if (window.location.pathname.includes('view_gatepass.php')) {
            this.startGatepassStatusUpdates();
        }

        // Add notification sound
        this.loadNotificationSound();
    }

    /**
     * Start dashboard real-time updates
     */
    startDashboardUpdates() {
        setInterval(async () => {
            try {
                const response = await fetch('/api/get_dashboard_stats.php');
                const data = await response.json();
                
                if (data.success) {
                    this.updateDashboardStats(data.stats);
                }
            } catch (error) {
                console.error('Error updating dashboard stats:', error);
            }
        }, 60000); // Update every minute
    }

    /**
     * Update dashboard statistics
     */
    updateDashboardStats(stats) {
        // Update pending count
        const pendingElement = document.querySelector('[data-stat="pending"]');
        if (pendingElement && stats.pending !== undefined) {
            pendingElement.textContent = stats.pending;
            
            // Add animation if count changed
            if (pendingElement.dataset.lastValue !== stats.pending.toString()) {
                pendingElement.classList.add('stat-updated');
                setTimeout(() => {
                    pendingElement.classList.remove('stat-updated');
                }, 1000);
                pendingElement.dataset.lastValue = stats.pending.toString();
            }
        }

        // Update other stats similarly...
        const statElements = document.querySelectorAll('[data-stat]');
        statElements.forEach(element => {
            const statName = element.dataset.stat;
            if (stats[statName] !== undefined) {
                element.textContent = stats[statName];
            }
        });
    }

    /**
     * Load notification sound
     */
    loadNotificationSound() {
        this.notificationSound = new Audio('/assets/sounds/notification.mp3');
        this.notificationSound.volume = 0.5;
    }

    /**
     * Play notification sound
     */
    playNotificationSound() {
        if (this.notificationSound) {
            this.notificationSound.play().catch(error => {
                console.log('Could not play notification sound:', error);
            });
        }
    }

    /**
     * Helper methods
     */
    getBootstrapClass(urgency) {
        const classMap = {
            'low': 'info',
            'medium': 'primary',
            'high': 'warning',
            'urgent': 'danger'
        };
        return classMap[urgency] || 'info';
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    /**
     * Cleanup
     */
    destroy() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
        
        if (this.websocket) {
            this.websocket.close();
        }
    }
}

// CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    .notification-toast {
        animation: slideInRight 0.3s ease-out;
    }

    .stat-updated {
        animation: pulse 1s ease-in-out;
        color: #28a745 !important;
        font-weight: bold;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    .notification-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #dc3545;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        min-width: 20px;
    }
`;
document.head.appendChild(style);

// Initialize notification manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationManager = new NotificationManager();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.notificationManager) {
        window.notificationManager.destroy();
    }
});
