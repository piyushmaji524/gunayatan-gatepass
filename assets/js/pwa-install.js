/**
 * PWA Install Prompt Manager for Gunayatan Gatepass System
 * Handles the "Add to Home Screen" functionality with persistent prompting
 */

class PWAInstallManager {
    constructor() {
        this.installPromptEvent = null;
        this.isInstalled = false;
        this.installBanner = null;
        this.floatingButton = null;
        this.dismissCount = 0;
        this.maxDismissals = 10; // Increased to show prompt more times
        this.installCheckInterval = null;
        this.lastPromptTime = 0;
        this.minTimeBetweenPrompts = 30000; // 30 seconds minimum between prompts
        
        this.init();
    }
    
    init() {
        // Check if already installed
        this.checkInstallStatus();
        
        // Listen for install prompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('PWA: Before install prompt triggered');
            e.preventDefault();
            this.installPromptEvent = e;
            this.showInstallBanner();
        });
        
        // Listen for app installed event
        window.addEventListener('appinstalled', () => {
            console.log('PWA: App installed successfully');
            this.isInstalled = true;
            this.hideInstallBanner();
            this.hideFloatingInstallButton();
            this.saveInstallStatus(true);
            this.showInstalledNotification();
        });
        
        // Register service worker
        this.registerServiceWorker();
        
        // Periodic check for installation status
        this.startInstallCheck();
        
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !this.isInstalled) {
                setTimeout(() => {
                    this.checkAndShowPrompt();
                }, 5000); // Show after 5 seconds when page becomes visible
            }
        });
        
        // Show prompt on every page load if not installed
        if (!this.isInstalled) {
            setTimeout(() => {
                this.checkAndShowPrompt();
            }, 3000); // Show after 3 seconds on page load
        }
    }
    
    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/'
                });
                console.log('PWA: Service Worker registered successfully:', registration);
                
                // Handle service worker updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateNotification();
                        }
                    });
                });
                
            } catch (error) {
                console.error('PWA: Service Worker registration failed:', error);
            }
        }
    }
    
    checkInstallStatus() {
        // Check if running in standalone mode (installed)
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
            this.isInstalled = true;
            console.log('PWA: App is running in standalone mode (installed)');
            return;
        }
        
        // Check localStorage for install status
        const installStatus = localStorage.getItem('pwa-installed');
        if (installStatus === 'true') {
            this.isInstalled = true;
            return;
        }
        
        // Check dismiss count
        this.dismissCount = parseInt(localStorage.getItem('pwa-dismiss-count') || '0');
        
        // Show prompt if not dismissed too many times
        if (this.dismissCount < this.maxDismissals) {
            setTimeout(() => this.checkAndShowPrompt(), 2000);
        }
    }
    
    showFloatingInstallButton() {
        if (this.floatingButton || this.isInstalled) return;
        
        this.floatingButton = document.createElement('div');
        this.floatingButton.className = 'pwa-floating-install-btn';
        this.floatingButton.innerHTML = `
            <div class="floating-btn-content">
                <i class="fas fa-mobile-alt"></i>
                <span>Install App</span>
            </div>
        `;
        
        this.floatingButton.addEventListener('click', () => {
            this.triggerInstall();
        });
        
        document.body.appendChild(this.floatingButton);
        
        // Animate appearance
        setTimeout(() => {
            this.floatingButton.classList.add('floating-btn-visible');
        }, 500);
        
        console.log('PWA: Floating install button displayed');
    }
    
    hideFloatingInstallButton() {
        if (this.floatingButton) {
            this.floatingButton.classList.remove('floating-btn-visible');
            setTimeout(() => {
                if (this.floatingButton && this.floatingButton.parentNode) {
                    this.floatingButton.parentNode.removeChild(this.floatingButton);
                }
                this.floatingButton = null;
            }, 300);
        }
    }

    checkAndShowPrompt() {
        const now = Date.now();
        
        // Check if enough time has passed since last prompt
        if (now - this.lastPromptTime < this.minTimeBetweenPrompts) {
            return;
        }
        
        if (!this.isInstalled) {
            this.lastPromptTime = now;
            
            // Show banner first
            if (!this.installBanner) {
                this.showInstallBanner();
            }
            
            // Show floating button after banner is dismissed or after some time
            setTimeout(() => {
                if (!this.isInstalled && !this.floatingButton) {
                    this.showFloatingInstallButton();
                }
            }, 5000); // Show floating button after 5 seconds (reduced from 15)
        }
    }
    
    showInstallBanner() {
        if (this.installBanner || this.isInstalled) return;
        
        // Create install banner
        this.installBanner = document.createElement('div');
        this.installBanner.className = 'pwa-install-banner';
        this.installBanner.innerHTML = `
            <div class="pwa-banner-content">
                <div class="pwa-banner-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div class="pwa-banner-text">
                    <div class="pwa-banner-title">📱 Create App Shortcut</div>
                    <div class="pwa-banner-subtitle">Add Gunayatan Gatepass to your home screen for quick access</div>
                </div>
                <div class="pwa-banner-actions">
                    <button class="pwa-install-btn" id="pwa-install-btn">
                        <i class="fas fa-home"></i> Add to Home Screen
                    </button>
                    <button class="pwa-dismiss-btn" id="pwa-dismiss-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="pwa-banner-progress">
                <div class="pwa-progress-bar"></div>
            </div>
        `;
        
        // Add styles
        this.addInstallStyles();
        
        // Add event listeners
        this.installBanner.querySelector('#pwa-install-btn').addEventListener('click', () => {
            this.triggerInstall();
        });
        
        this.installBanner.querySelector('#pwa-dismiss-btn').addEventListener('click', () => {
            this.dismissInstallPrompt();
        });
        
        // Insert banner at the top of the page
        document.body.insertBefore(this.installBanner, document.body.firstChild);
        
        // Animate banner appearance
        setTimeout(() => {
            this.installBanner.classList.add('pwa-banner-visible');
        }, 100);
        
        // Auto-hide after 10 seconds if not interacted with
        setTimeout(() => {
            if (this.installBanner && !this.installBanner.classList.contains('pwa-banner-interacted')) {
                this.dismissInstallPrompt();
            }
        }, 10000);
        
        console.log('PWA: Install banner displayed');
    }
    
    addInstallStyles() {
        if (document.getElementById('pwa-install-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'pwa-install-styles';
        styles.textContent = `
            .pwa-install-banner {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                z-index: 10000;
                transform: translateY(-100%);
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            .pwa-install-banner.pwa-banner-visible {
                transform: translateY(0);
            }
            
            .pwa-banner-content {
                display: flex;
                align-items: center;
                padding: 15px 20px;
                gap: 15px;
            }
            
            .pwa-banner-icon {
                font-size: 2.5em;
                color: #ffd700;
                animation: pulse 2s infinite;
            }
            
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            
            .pwa-banner-text {
                flex: 1;
            }
            
            .pwa-banner-title {
                font-size: 1.2em;
                font-weight: 600;
                margin-bottom: 4px;
            }
            
            .pwa-banner-subtitle {
                font-size: 0.9em;
                opacity: 0.9;
            }
            
            .pwa-banner-actions {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            
            .pwa-install-btn {
                background: #ffd700;
                color: #333;
                border: none;
                padding: 10px 20px;
                border-radius: 25px;
                font-weight: 600;
                font-size: 0.9em;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .pwa-install-btn:hover {
                background: #ffed4e;
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(255,215,0,0.4);
            }
            
            .pwa-dismiss-btn {
                background: rgba(255,255,255,0.2);
                color: white;
                border: none;
                padding: 8px;
                border-radius: 50%;
                cursor: pointer;
                transition: all 0.3s ease;
                width: 36px;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .pwa-dismiss-btn:hover {
                background: rgba(255,255,255,0.3);
                transform: rotate(90deg);
            }
            
            .pwa-banner-progress {
                height: 3px;
                background: rgba(255,255,255,0.2);
                overflow: hidden;
            }
            
            .pwa-progress-bar {
                height: 100%;
                background: #ffd700;
                width: 0%;
                animation: progressBar 10s linear forwards;
            }
            
            @keyframes progressBar {
                from { width: 0%; }
                to { width: 100%; }
            }
            
            /* Mobile responsive */
            @media (max-width: 768px) {
                .pwa-banner-content {
                    padding: 12px 15px;
                    gap: 10px;
                }
                
                .pwa-banner-icon {
                    font-size: 2em;
                }
                
                .pwa-banner-title {
                    font-size: 1.1em;
                }
                
                .pwa-banner-subtitle {
                    font-size: 0.85em;
                }
                
                .pwa-install-btn {
                    padding: 8px 15px;
                    font-size: 0.85em;
                }
            }
            
            /* iOS specific adjustments */
            @supports (-webkit-touch-callout: none) {
                .pwa-install-banner {
                    top: env(safe-area-inset-top, 0);
                }
            }
            
            /* Toast notification styles */
            .pwa-toast {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 15px 20px;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                z-index: 10001;
                transform: translateX(100%);
                transition: transform 0.4s ease;
                max-width: 300px;
            }
            
            .pwa-toast.pwa-toast-visible {
                transform: translateX(0);
            }
            
            .pwa-toast-success {
                background: #28a745;
            }
            
            .pwa-toast-info {
                background: #17a2b8;
            }
            
            .pwa-toast-warning {
                background: #ffc107;
                color: #333;
            }
            
            /* Floating Install Button */
            .pwa-floating-install-btn {
                position: fixed;
                bottom: 80px;
                right: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 50px;
                padding: 12px 20px;
                box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
                cursor: pointer;
                z-index: 9999;
                transform: translateX(120%);
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                border: none;
                outline: none;
            }
            
            .pwa-floating-install-btn.floating-btn-visible {
                transform: translateX(0);
            }
            
            .pwa-floating-install-btn:hover {
                transform: translateX(0) translateY(-3px);
                box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
                background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            }
            
            .floating-btn-content {
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 600;
                font-size: 0.9em;
            }
            
            .floating-btn-content i {
                font-size: 1.2em;
                animation: pulse 2s infinite;
            }
            
            @media (max-width: 768px) {
                .pwa-floating-install-btn {
                    bottom: 20px;
                    right: 15px;
                    padding: 10px 16px;
                }
                
                .floating-btn-content {
                    gap: 6px;
                    font-size: 0.85em;
                }
                
                .floating-btn-content span {
                    display: none;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }
    
    async triggerInstall() {
        if (this.installBanner) {
            this.installBanner.classList.add('pwa-banner-interacted');
        }
        
        if (this.installPromptEvent) {
            // Native install prompt available
            try {
                const result = await this.installPromptEvent.prompt();
                console.log('PWA: Install prompt result:', result);
                
                if (result.outcome === 'accepted') {
                    console.log('PWA: User accepted the install prompt');
                    this.hideInstallBanner();
                    this.hideFloatingInstallButton();
                    this.saveInstallStatus(true);
                } else {
                    console.log('PWA: User dismissed the install prompt');
                    this.dismissInstallPrompt();
                }
                
                this.installPromptEvent = null;
            } catch (error) {
                console.error('PWA: Install prompt failed:', error);
                this.showManualInstallInstructions();
            }
        } else {
            // Fallback for browsers without native prompt
            this.showManualInstallInstructions();
        }
    }
    
    showManualInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isSafari = /Safari/.test(navigator.userAgent) && !/Chrome/.test(navigator.userAgent);
        const isAndroid = /Android/.test(navigator.userAgent);
        
        let instructions = '';
        
        if (isIOS && isSafari) {
            instructions = `
                <h4>📱 Install on iOS Safari:</h4>
                <ol>
                    <li>Tap the Share button <i class="fas fa-share"></i></li>
                    <li>Scroll down and tap "Add to Home Screen"</li>
                    <li>Tap "Add" to confirm</li>
                </ol>
            `;
        } else if (isAndroid) {
            instructions = `
                <h4>📱 Install on Android:</h4>
                <ol>
                    <li>Tap the menu button (⋮)</li>
                    <li>Select "Add to Home screen" or "Install app"</li>
                    <li>Tap "Add" to confirm</li>
                </ol>
            `;
        } else {
            instructions = `
                <h4>💻 Install on Desktop:</h4>
                <ol>
                    <li>Look for the install icon in the address bar</li>
                    <li>Or check browser menu for "Install app" option</li>
                    <li>Follow the prompts to install</li>
                </ol>
            `;
        }
        
        this.showModal('Install Gunayatan Gatepass App', `
            <div style="text-align: left;">
                <p>To get the best experience, install our app on your device:</p>
                ${instructions}
                <p><strong>Benefits:</strong></p>
                <ul>
                    <li>✅ Quick access from home screen</li>
                    <li>✅ Offline functionality</li>
                    <li>✅ Push notifications</li>
                    <li>✅ App-like experience</li>
                </ul>
            </div>
        `);
        
        this.dismissInstallPrompt();
    }
    
    showModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'pwa-modal-overlay';
        modal.innerHTML = `
            <div class="pwa-modal">
                <div class="pwa-modal-header">
                    <h3>${title}</h3>
                    <button class="pwa-modal-close">&times;</button>
                </div>
                <div class="pwa-modal-body">
                    ${content}
                </div>
                <div class="pwa-modal-footer">
                    <button class="pwa-modal-btn pwa-modal-btn-primary" onclick="this.closest('.pwa-modal-overlay').remove()">Got it!</button>
                </div>
            </div>
        `;
        
        // Add modal styles if not already added
        if (!document.getElementById('pwa-modal-styles')) {
            const modalStyles = document.createElement('style');
            modalStyles.id = 'pwa-modal-styles';
            modalStyles.textContent = `
                .pwa-modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.7);
                    z-index: 10002;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                
                .pwa-modal {
                    background: white;
                    border-radius: 15px;
                    max-width: 500px;
                    width: 100%;
                    max-height: 90vh;
                    overflow-y: auto;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                }
                
                .pwa-modal-header {
                    padding: 20px 20px 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .pwa-modal-header h3 {
                    margin: 0;
                    color: #333;
                }
                
                .pwa-modal-close {
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #999;
                }
                
                .pwa-modal-body {
                    padding: 20px;
                    color: #333;
                }
                
                .pwa-modal-footer {
                    padding: 0 20px 20px;
                    text-align: right;
                }
                
                .pwa-modal-btn {
                    background: #667eea;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 600;
                }
                
                .pwa-modal-btn:hover {
                    background: #5a6fd8;
                }
            `;
            document.head.appendChild(modalStyles);
        }
        
        modal.querySelector('.pwa-modal-close').addEventListener('click', () => {
            modal.remove();
        });
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        document.body.appendChild(modal);
    }
    
    dismissInstallPrompt() {
        if (this.installBanner) {
            this.installBanner.classList.remove('pwa-banner-visible');
            setTimeout(() => {
                if (this.installBanner && this.installBanner.parentNode) {
                    this.installBanner.parentNode.removeChild(this.installBanner);
                }
                this.installBanner = null;
            }, 400);
        }
        
        // Increment dismiss count
        this.dismissCount++;
        localStorage.setItem('pwa-dismiss-count', this.dismissCount.toString());
        
        // Set a delay before showing again (if under max dismissals)
        if (this.dismissCount < this.maxDismissals) {
            // Show again after some time or on next visit
            setTimeout(() => {
                if (!this.isInstalled) {
                    this.checkAndShowPrompt();
                }
            }, 120000); // 2 minutes instead of 5
        } else {
            // Even after max dismissals, show again after a longer delay
            setTimeout(() => {
                if (!this.isInstalled) {
                    this.dismissCount = 0; // Reset dismiss count
                    this.checkAndShowPrompt();
                }
            }, 1800000); // 30 minutes
        }
        
        console.log('PWA: Install prompt dismissed');
    }
    
    hideInstallBanner() {
        if (this.installBanner) {
            this.installBanner.classList.remove('pwa-banner-visible');
            setTimeout(() => {
                if (this.installBanner && this.installBanner.parentNode) {
                    this.installBanner.parentNode.removeChild(this.installBanner);
                }
                this.installBanner = null;
            }, 400);
        }
    }
    
    saveInstallStatus(installed) {
        localStorage.setItem('pwa-installed', installed.toString());
        if (installed) {
            localStorage.removeItem('pwa-dismiss-count');
        }
    }
    
    showInstalledNotification() {
        this.showToast('App installed successfully! 🎉', 'success');
    }
    
    showUpdateNotification() {
        this.showToast('App updated! Refresh to use the latest version.', 'info');
    }
    
    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `pwa-toast pwa-toast-${type}`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('pwa-toast-visible');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('pwa-toast-visible');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 400);
        }, 5000);
    }
    
    startInstallCheck() {
        // Check periodically if the app gets installed through other means
        this.installCheckInterval = setInterval(() => {
            if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches && !this.isInstalled) {
                this.isInstalled = true;
                this.hideInstallBanner();
                this.saveInstallStatus(true);
                console.log('PWA: App detected as installed');
            }
        }, 5000);
    }
    
    // Force show install prompt - useful for testing
    forceShowPrompt() {
        this.lastPromptTime = 0;
        this.dismissCount = 0;
        this.checkAndShowPrompt();
    }
    
    // Method to check if app is installable
    isAppInstallable() {
        // Check if running in standalone mode
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
            return false;
        }
        
        // Check if beforeinstallprompt is supported
        if ('BeforeInstallPromptEvent' in window) {
            return true;
        }
        
        // For iOS Safari
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isSafari = /Safari/.test(navigator.userAgent) && !/Chrome/.test(navigator.userAgent);
        
        return isIOS && isSafari;
    }

    destroy() {
        if (this.installCheckInterval) {
            clearInterval(this.installCheckInterval);
        }
        this.hideInstallBanner();
        this.hideFloatingInstallButton();
    }
}

// Initialize PWA Install Manager when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.pwaInstallManager = new PWAInstallManager();
        console.log('PWA Install Manager initialized on DOMContentLoaded');
    });
} else {
    window.pwaInstallManager = new PWAInstallManager();
    console.log('PWA Install Manager initialized immediately');
}

// Global function to force show install prompt (for testing)
window.showInstallPrompt = function() {
    if (window.pwaInstallManager) {
        window.pwaInstallManager.forceShowPrompt();
        console.log('Install prompt forced to show');
    } else {
        console.log('PWA Install Manager not available');
    }
};

// Global function to check install status
window.checkPWAStatus = function() {
    if (window.pwaInstallManager) {
        console.log('PWA Status:', {
            isInstalled: window.pwaInstallManager.isInstalled,
            dismissCount: window.pwaInstallManager.dismissCount,
            hasInstallPrompt: !!window.pwaInstallManager.installPromptEvent,
            isAppInstallable: window.pwaInstallManager.isAppInstallable()
        });
    }
};

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PWAInstallManager;
}

console.log('PWA Install Manager loaded successfully');
