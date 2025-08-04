/**
 * Real-time Notification System - Frontend JavaScript
 * Handles live notifications, push notifications, and notification UI
 */

class NotificationManager {
    constructor() {
        this.notificationEndpoint = '/api/get_notifications.php';
        this.markReadEndpoint = '/api/mark_notification_read.php';
        this.pollInterval = 30000; // 30 seconds
        this.isPolling = false;
        this.unreadCount = 0;
        
        this.init();
    }
    
    init() {
        this.setupNotificationUI();
        this.requestNotificationPermission();
        this.startPolling();
        this.setupServiceWorker();
        this.bindEvents();
    }
    
    setupNotificationUI() {
        // Create notification bell icon in header
        const header = document.querySelector('.navbar-nav');
        if (header) {
            const notificationHtml = `
                <li class="nav-item dropdown" id="notification-dropdown">
                    <a class="nav-link position-relative" href="#" id="notification-bell" 
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
                              id="notification-badge" style="display: none;">
                            0
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" 
                        style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><div id="notification-list">Loading...</div></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="/notifications.php">View All</a></li>
                    </ul>
                </li>
            `;
            header.insertAdjacentHTML('beforeend', notificationHtml);
        }
    }
    
    async requestNotificationPermission() {
        if ('Notification' in window && 'serviceWorker' in navigator) {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                console.log('Notification permission granted');
                this.setupPushSubscription();
            }
        }
    }
    
    async setupServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js');
                console.log('Service Worker registered:', registration);
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        }
    }
    
    async setupPushSubscription() {
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array('YOUR_VAPID_PUBLIC_KEY') // Replace with actual key
                });
                
                // Send subscription to server
                await this.sendSubscriptionToServer(subscription);
            } catch (error) {
                console.error('Push subscription failed:', error);
            }
        }
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
    
    async sendSubscriptionToServer(subscription) {
        try {
            await fetch('/api/save_push_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subscription)
            });
        } catch (error) {
            console.error('Failed to send subscription to server:', error);
        }
    }
    
    startPolling() {
        if (this.isPolling) return;
        
        this.isPolling = true;
        this.pollNotifications();
        
        setInterval(() => {
            this.pollNotifications();
        }, this.pollInterval);
    }
    
    async pollNotifications() {
        try {
            const response = await fetch(this.notificationEndpoint);
            const data = await response.json();
            
            if (data.success) {
                this.updateNotificationUI(data.notifications);
                this.updateUnreadCount(data.unread_count);
                
                // Show browser notifications for new urgent notifications
                data.notifications.forEach(notification => {
                    if (notification.urgency === 'high' && !notification.shown_as_push) {
                        this.showBrowserNotification(notification);
                    }
                });
            }
        } catch (error) {
            console.error('Failed to poll notifications:', error);
        }
    }
    
    updateNotificationUI(notifications) {
        const notificationList = document.getElementById('notification-list');
        if (!notificationList) return;
        
        if (notifications.length === 0) {
            notificationList.innerHTML = '<div class="text-center text-muted p-3">No new notifications</div>';
            return;
        }
        
        const notificationHtml = notifications.map(notification => {
            const timeAgo = this.timeAgo(new Date(notification.created_at));
            const urgencyClass = notification.urgency === 'high' ? 'border-start border-danger border-3' : '';
            const readClass = notification.read_at ? 'text-muted' : '';
            
            return `
                <li class="notification-item ${urgencyClass} ${readClass}" data-id="${notification.id}">
                    <a class="dropdown-item py-2" href="#" onclick="notificationManager.handleNotificationClick(${notification.id}, '${notification.data?.action_url || '#'}')">
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${this.escapeHtml(notification.title)}</h6>
                                <p class="mb-1 small">${this.escapeHtml(notification.message)}</p>
                                <small class="text-muted">${timeAgo}</small>
                            </div>
                            ${notification.urgency === 'high' ? '<i class="fas fa-exclamation-circle text-danger"></i>' : ''}
                        </div>
                    </a>
                </li>
            `;
        }).join('');
        
        notificationList.innerHTML = notificationHtml;
    }
    
    updateUnreadCount(count) {
        this.unreadCount = count;
        const badge = document.getElementById('notification-badge');
        
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
        
        // Update page title
        if (count > 0) {
            document.title = `(${count}) ${document.title.replace(/^\(\d+\) /, '')}`;
        } else {
            document.title = document.title.replace(/^\(\d+\) /, '');
        }
    }
    
    showBrowserNotification(notification) {
        if (Notification.permission === 'granted') {
            const browserNotification = new Notification(notification.title, {
                body: notification.message,
                icon: '/assets/img/logo.png',
                badge: '/assets/img/logo.png',
                tag: `notification-${notification.id}`,
                requireInteraction: notification.urgency === 'high',
                actions: [
                    {
                        action: 'view',
                        title: 'View'
                    }
                ]
            });
            
            browserNotification.onclick = () => {
                window.focus();
                if (notification.data?.action_url) {
                    window.location.href = notification.data.action_url;
                }
                browserNotification.close();
            };
            
            // Auto close after 10 seconds for non-urgent notifications
            if (notification.urgency !== 'high') {
                setTimeout(() => browserNotification.close(), 10000);
            }
        }
    }
    
    async handleNotificationClick(notificationId, actionUrl) {
        // Mark as read
        await this.markNotificationAsRead(notificationId);
        
        // Navigate to action URL
        if (actionUrl && actionUrl !== '#') {
            window.location.href = actionUrl;
        }
    }
    
    async markNotificationAsRead(notificationId) {
        try {
            await fetch(this.markReadEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            });
            
            // Update UI immediately
            const notificationItem = document.querySelector(`[data-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.classList.add('text-muted');
            }
            
            // Decrease unread count
            this.updateUnreadCount(Math.max(0, this.unreadCount - 1));
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }
    
    bindEvents() {
        // Handle page visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Page became visible, check for new notifications
                this.pollNotifications();
            }
        });
        
        // Handle online/offline status
        window.addEventListener('online', () => {
            this.pollNotifications();
        });
        
        // Handle notification permission change
        if ('permissions' in navigator) {
            navigator.permissions.query({name: 'notifications'}).then((permission) => {
                permission.onchange = () => {
                    if (permission.state === 'granted') {
                        this.setupPushSubscription();
                    }
                };
            });
        }
    }
    
    // Utility functions
    timeAgo(date) {
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} min ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hr ago`;
        return `${Math.floor(diffInSeconds / 86400)} days ago`;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Sound notification
    playNotificationSound() {
        const audio = new Audio('/assets/sounds/notification.mp3');
        audio.volume = 0.3;
        audio.play().catch(e => console.log('Could not play notification sound:', e));
    }
    
    // Show toast notification
    showToast(message, type = 'info') {
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'primary'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${this.escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = toastContainer.lastElementChild;
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        
        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }
}

// CSS for notification styling
const notificationCSS = `
    .notification-dropdown {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .notification-item:hover {
        background-color: #f8f9fa;
    }
    
    .notification-item.border-start {
        padding-left: 1rem;
    }
    
    #notification-badge {
        font-size: 0.7rem;
        min-width: 18px;
        height: 18px;
        line-height: 18px;
    }
    
    @keyframes notification-pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .notification-new {
        animation: notification-pulse 2s ease-in-out;
    }
`;

// Add CSS to page
const style = document.createElement('style');
style.textContent = notificationCSS;
document.head.appendChild(style);

// Initialize notification manager when DOM is ready
let notificationManager;
document.addEventListener('DOMContentLoaded', () => {
    notificationManager = new NotificationManager();
});

// Export for global access
window.notificationManager = notificationManager;
