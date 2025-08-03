/**
 * Location Permission Checker
 * Continuously monitors location permission status and shows warnings
 * Prevents checkout if location is disabled
 */

class LocationPermissionChecker {
    constructor() {
        this.permissionStatus = 'checking';
        this.isMonitoring = false;
        this.checkInterval = null;
        this.warningElement = null;
        this.isUserCheckedIn = false;
        
        this.init();
    }
    
    init() {
        this.createWarningElement();
        this.startMonitoring();
        this.setupEventListeners();
        
        console.log('Location Permission Checker initialized');
    }
    
    createWarningElement() {
        // Create warning element but don't show it yet
        this.warningElement = document.createElement('div');
        this.warningElement.id = 'location-permission-warning';
        this.warningElement.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
            padding: 16px 20px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            z-index: 10001;
            box-shadow: 0 4px 20px rgba(244, 67, 54, 0.4);
            transform: translateY(-100%);
            transition: transform 0.4s ease-in-out;
            border-bottom: 3px solid #c62828;
        `;
        
        document.body.appendChild(this.warningElement);
    }
    
    showLocationWarning() {
        if (this.warningElement) {
            this.warningElement.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; gap: 12px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 18px;"></i>
                    <span>Location access is DISABLED! You cannot check out until location permission is enabled.</span>
                    <button onclick="window.locationPermissionChecker.requestPermission()" 
                            style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                        Enable Location
                    </button>
                </div>
            `;
            this.warningElement.style.transform = 'translateY(0)';
            
            // Pulse animation for urgency
            this.warningElement.style.animation = 'pulse 2s infinite';
            
            // Add CSS animation if not already added
            if (!document.getElementById('permission-warning-styles')) {
                const style = document.createElement('style');
                style.id = 'permission-warning-styles';
                style.textContent = `
                    @keyframes pulse {
                        0% { box-shadow: 0 4px 20px rgba(244, 67, 54, 0.4); }
                        50% { box-shadow: 0 6px 30px rgba(244, 67, 54, 0.8); }
                        100% { box-shadow: 0 4px 20px rgba(244, 67, 54, 0.4); }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        // Disable checkout button if user is checked in
        this.disableCheckoutIfLocationDenied();
    }
    
    hideLocationWarning() {
        if (this.warningElement) {
            this.warningElement.style.transform = 'translateY(-100%)';
            this.warningElement.style.animation = '';
        }
        
        // Re-enable checkout button
        this.enableCheckoutIfLocationGranted();
    }
    
    disableCheckoutIfLocationDenied() {
        const checkoutBtn = document.getElementById('checkout-btn');
        if (checkoutBtn && this.isUserCheckedIn) {
            checkoutBtn.disabled = true;
            checkoutBtn.style.opacity = '0.5';
            checkoutBtn.style.cursor = 'not-allowed';
            checkoutBtn.title = 'Location permission required to check out';
            
            // Add click handler to show message
            checkoutBtn.onclick = (e) => {
                e.preventDefault();
                alert('You cannot check out while location access is disabled. Please enable location permission first.');
                return false;
            };
        }
    }
    
    enableCheckoutIfLocationGranted() {
        const checkoutBtn = document.getElementById('checkout-btn');
        if (checkoutBtn && this.isUserCheckedIn) {
            checkoutBtn.disabled = false;
            checkoutBtn.style.opacity = '1';
            checkoutBtn.style.cursor = 'pointer';
            checkoutBtn.title = '';
            checkoutBtn.onclick = null; // Remove the warning click handler
        }
    }
    
    checkPermissionStatus() {
        if (!navigator.geolocation) {
            this.permissionStatus = 'unsupported';
            this.showLocationWarning();
            return;
        }
        
        // Try multiple methods to check permission
        if (navigator.permissions) {
            navigator.permissions.query({ name: 'geolocation' })
                .then(status => {
                    this.handlePermissionStatus(status.state);
                    
                    // Listen for permission changes
                    status.onchange = () => {
                        this.handlePermissionStatus(status.state);
                    };
                })
                .catch(() => {
                    this.fallbackPermissionCheck();
                });
        } else {
            this.fallbackPermissionCheck();
        }
    }
    
    fallbackPermissionCheck() {
        // Test by attempting to get location
        navigator.geolocation.getCurrentPosition(
            () => {
                this.handlePermissionStatus('granted');
            },
            (error) => {
                if (error.code === error.PERMISSION_DENIED) {
                    this.handlePermissionStatus('denied');
                } else {
                    this.handlePermissionStatus('prompt');
                }
            },
            { enableHighAccuracy: false, timeout: 3000, maximumAge: 60000 }
        );
    }
    
    handlePermissionStatus(status) {
        const previousStatus = this.permissionStatus;
        this.permissionStatus = status;
        
        console.log(`Location permission status: ${status}`);
        
        if (status === 'granted') {
            this.hideLocationWarning();
        } else if (status === 'denied') {
            this.showLocationWarning();
        } else if (status === 'prompt') {
            // Don't show warning for prompt state unless user is checked in
            if (this.isUserCheckedIn) {
                this.showLocationWarning();
            }
        }
        
        // Notify other components of permission change
        window.dispatchEvent(new CustomEvent('locationPermissionChanged', {
            detail: { status: status, previousStatus: previousStatus }
        }));
    }
    
    requestPermission() {
        console.log('Requesting location permission...');
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                console.log('Location permission granted');
                this.handlePermissionStatus('granted');
            },
            (error) => {
                console.log('Location permission denied or error:', error);
                if (error.code === error.PERMISSION_DENIED) {
                    this.handlePermissionStatus('denied');
                    
                    // Show instructions for manual permission enable
                    this.showManualInstructions();
                }
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }
    
    showManualInstructions() {
        // Create modal with instructions
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 10002;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        `;
        
        modal.innerHTML = `
            <div style="background: white; border-radius: 12px; padding: 24px; max-width: 500px; width: 100%;">
                <h3 style="margin: 0 0 16px 0; color: #f44336; font-size: 18px;">
                    <i class="fas fa-exclamation-triangle"></i> Location Permission Required
                </h3>
                <p style="margin: 0 0 16px 0; line-height: 1.5;">
                    To enable location access:
                </p>
                <ol style="margin: 0 0 20px 20px; line-height: 1.8;">
                    <li>Click the location icon in your browser's address bar</li>
                    <li>Select "Allow" for location permission</li>
                    <li>Refresh this page</li>
                </ol>
                <p style="margin: 0 0 20px 0; font-size: 14px; color: #666;">
                    <strong>Note:</strong> Location access is required for attendance tracking and checkout.
                </p>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button onclick="this.parentElement.parentElement.parentElement.remove()" 
                            style="background: #f5f5f5; border: 1px solid #ddd; padding: 8px 16px; border-radius: 6px; cursor: pointer;">
                        Close
                    </button>
                    <button onclick="window.location.reload()" 
                            style="background: #4CAF50; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">
                        Refresh Page
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Auto-remove modal after 10 seconds
        setTimeout(() => {
            if (modal.parentElement) {
                modal.remove();
            }
        }, 10000);
    }
    
    checkUserStatus() {
        const checkinBtn = document.getElementById('checkin-btn');
        const checkoutBtn = document.getElementById('checkout-btn');
        
        // Determine if user is checked in based on UI state
        this.isUserCheckedIn = checkoutBtn && 
                              checkoutBtn.style.display !== 'none' && 
                              checkinBtn && 
                              checkinBtn.style.display === 'none';
        
        console.log(`User checked in status: ${this.isUserCheckedIn}`);
        
        // If user just checked in and location is denied, show warning
        if (this.isUserCheckedIn && this.permissionStatus === 'denied') {
            this.showLocationWarning();
        } else if (!this.isUserCheckedIn) {
            // Hide warning when user is not checked in
            this.hideLocationWarning();
        }
    }
    
    startMonitoring() {
        if (this.isMonitoring) return;
        
        this.isMonitoring = true;
        
        // Initial checks
        this.checkPermissionStatus();
        this.checkUserStatus();
        
        // Set up periodic checks
        this.checkInterval = setInterval(() => {
            this.checkPermissionStatus();
            this.checkUserStatus();
        }, 5000); // Check every 5 seconds
        
        console.log('Location permission monitoring started');
    }
    
    stopMonitoring() {
        this.isMonitoring = false;
        
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
        
        this.hideLocationWarning();
        console.log('Location permission monitoring stopped');
    }
    
    setupEventListeners() {
        // Listen for page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Re-check when page becomes visible
                setTimeout(() => {
                    this.checkPermissionStatus();
                    this.checkUserStatus();
                }, 1000);
            }
        });
        
        // Listen for DOM changes that might indicate check-in/out
        const observer = new MutationObserver(() => {
            this.checkUserStatus();
        });
        
        // Observe changes to button visibility
        const checkinBtn = document.getElementById('checkin-btn');
        const checkoutBtn = document.getElementById('checkout-btn');
        
        if (checkinBtn) {
            observer.observe(checkinBtn, { attributes: true, attributeFilter: ['style'] });
        }
        if (checkoutBtn) {
            observer.observe(checkoutBtn, { attributes: true, attributeFilter: ['style'] });
        }
    }
    
    // Public API
    getStatus() {
        return {
            permissionStatus: this.permissionStatus,
            isMonitoring: this.isMonitoring,
            isUserCheckedIn: this.isUserCheckedIn,
            warningVisible: this.warningElement && this.warningElement.style.transform === 'translateY(0px)'
        };
    }
    
    forceCheck() {
        this.checkPermissionStatus();
        this.checkUserStatus();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Create global instance
    window.locationPermissionChecker = new LocationPermissionChecker();
    
    console.log('Location Permission Checker ready');
});

// Export for potential module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LocationPermissionChecker;
}