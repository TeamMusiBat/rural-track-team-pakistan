
/**
 * Background Location Worker
 * Handles location updates even when page is minimized or in background
 */

class BackgroundLocationWorker {
    constructor() {
        this.isActive = false;
        this.lastUpdateTime = 0;
        this.updateInterval = null;
        this.locationPermissionStatus = 'checking';
        this.isUpdateInProgress = false;
        this.currentUser = null;
        this.isCheckedIn = false;
        this.lastVisibilityLog = 0;
        
        // Light theme colors for feedback
        this.lightThemes = [
            { bg: '#E8F5E8', border: '#4CAF50', name: 'Green' },
            { bg: '#E3F2FD', border: '#2196F3', name: 'Blue' },
            { bg: '#FFF3E0', border: '#FF9800', name: 'Orange' },
            { bg: '#F3E5F5', border: '#9C27B0', name: 'Purple' },
            { bg: '#E0F2F1', border: '#009688', name: 'Teal' },
            { bg: '#FFF8E1', border: '#FFC107', name: 'Amber' },
            { bg: '#FCE4EC', border: '#E91E63', name: 'Pink' },
            { bg: '#E8F5E8', border: '#8BC34A', name: 'Lime' }
        ];
        
        this.init();
    }
    
    init() {
        this.checkLocationPermission();
        this.setupVisibilityListeners();
        this.setupUserStatusCheck();
        
        console.log('Background Location Worker initialized');
    }
    
    // Check and monitor location permission
    checkLocationPermission() {
        if (!navigator.geolocation) {
            this.locationPermissionStatus = 'unsupported';
            this.showLocationWarning('Geolocation is not supported by this browser');
            return;
        }
        
        if (navigator.permissions) {
            navigator.permissions.query({ name: 'geolocation' })
                .then(status => {
                    this.locationPermissionStatus = status.state;
                    this.handlePermissionChange(status.state);
                    
                    // Listen for permission changes
                    status.onchange = () => {
                        this.locationPermissionStatus = status.state;
                        this.handlePermissionChange(status.state);
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
        navigator.geolocation.getCurrentPosition(
            () => {
                this.locationPermissionStatus = 'granted';
                this.hideLocationWarning();
            },
            (error) => {
                if (error.code === error.PERMISSION_DENIED) {
                    this.locationPermissionStatus = 'denied';
                    this.showLocationWarning('Location access denied. Enable location permission for automatic tracking.');
                } else {
                    this.locationPermissionStatus = 'prompt';
                }
            },
            { enableHighAccuracy: true, timeout: 5000 }
        );
    }
    
    handlePermissionChange(state) {
        if (state === 'granted') {
            this.hideLocationWarning();
            if (this.isCheckedIn && !this.isActive) {
                this.startTracking();
            }
        } else if (state === 'denied') {
            this.showLocationWarning('Location access denied. Automatic tracking disabled. Please enable location permission.');
            this.stopTracking();
        }
    }
    
    // Show/hide location warning
    showLocationWarning(message) {
        let warning = document.getElementById('bg-location-warning');
        if (!warning) {
            warning = document.createElement('div');
            warning.id = 'bg-location-warning';
            warning.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #ffebee;
                color: #c62828;
                padding: 12px 16px;
                border-radius: 8px;
                border-left: 4px solid #f44336;
                font-size: 14px;
                font-weight: 500;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);
                max-width: 300px;
                animation: slideIn 0.3s ease-out;
            `;
            
            // Add CSS animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
            
            document.body.appendChild(warning);
        }
        warning.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
        warning.style.display = 'block';
    }
    
    hideLocationWarning() {
        const warning = document.getElementById('bg-location-warning');
        if (warning) {
            warning.style.display = 'none';
        }
    }
    
    // Setup page visibility listeners
    setupVisibilityListeners() {
        document.addEventListener('visibilitychange', () => {
            const now = Date.now();
            // Only log visibility changes every 30 seconds to reduce spam
            if (now - this.lastVisibilityLog > 30000) {
                if (document.hidden) {
                    console.log('Page hidden - background tracking continues');
                } else {
                    console.log('Page visible - resuming normal operation');
                }
                this.lastVisibilityLog = now;
            }
        });
        
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            this.stopTracking();
        });
    }
    
    // Check user status periodically
    setupUserStatusCheck() {
        setInterval(() => {
            this.checkUserCheckinStatus();
        }, 30000); // Check every 30 seconds
        
        // Initial check
        this.checkUserCheckinStatus();
    }
    
    checkUserCheckinStatus() {
        const checkinBtn = document.getElementById('checkin-btn');
        const checkoutBtn = document.getElementById('checkout-btn');
        
        // Determine if user is checked in based on UI state
        const wasCheckedIn = this.isCheckedIn;
        this.isCheckedIn = checkoutBtn && 
                          checkoutBtn.style.display !== 'none' && 
                          checkinBtn && 
                          checkinBtn.style.display === 'none';
        
        // Start/stop tracking based on check-in status - only log changes
        if (this.isCheckedIn && !wasCheckedIn) {
            console.log('User checked in - starting background tracking');
            this.startTracking();
        } else if (!this.isCheckedIn && wasCheckedIn) {
            console.log('User checked out - stopping background tracking');
            this.stopTracking();
        }
    }
    
    // Rate limiting check
    canUpdateLocation() {
        const now = Date.now();
        const timeSinceLastUpdate = now - this.lastUpdateTime;
        const minInterval = 60000; // 60 seconds
        
        if (timeSinceLastUpdate < minInterval) {
            const remainingTime = Math.ceil((minInterval - timeSinceLastUpdate) / 1000);
            return { allowed: false, remainingTime };
        }
        
        return { allowed: true, remainingTime: 0 };
    }
    
    // Start background tracking
    startTracking() {
        if (this.isActive) {
            return;
        }
        
        if (this.locationPermissionStatus === 'denied') {
            this.showLocationWarning('Cannot start tracking: Location permission denied');
            return;
        }
        
        this.isActive = true;
        
        // Clear any existing interval
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        
        // Start regular updates every 60 seconds
        this.updateInterval = setInterval(() => {
            this.updateLocation();
        }, 60000);
        
        console.log('Background location tracking started (60 second interval)');
        
        // Do initial update after 5 seconds
        setTimeout(() => {
            this.updateLocation();
        }, 5000);
    }
    
    // Stop background tracking
    stopTracking() {
        this.isActive = false;
        
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
        
        console.log('Background location tracking stopped');
    }
    
    // Update location in background
    updateLocation() {
        if (!this.isActive || !this.isCheckedIn) {
            return;
        }
        
        if (this.locationPermissionStatus === 'denied') {
            console.log('Location permission denied, stopping tracking');
            this.stopTracking();
            return;
        }
        
        // Check rate limiting
        const rateCheck = this.canUpdateLocation();
        if (!rateCheck.allowed) {
            return;
        }
        
        // Prevent multiple simultaneous requests
        if (this.isUpdateInProgress) {
            return;
        }
        
        this.isUpdateInProgress = true;
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.sendLocationUpdate(position.coords.latitude, position.coords.longitude);
            },
            (error) => {
                this.isUpdateInProgress = false;
                this.handleLocationError(error);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 30000
            }
        );
    }
    
    // Send location update to server
    sendLocationUpdate(latitude, longitude) {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'update_location.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('Cache-Control', 'no-cache');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onreadystatechange = () => {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                this.isUpdateInProgress = false;
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            this.lastUpdateTime = Date.now();
                            console.log(`Background location updated successfully at ${new Date().toLocaleTimeString()}`);
                            
                            // Apply visual feedback
                            this.applyColorTheme();
                            
                            // Update UI if elements exist
                            this.updateLocationDisplay(response);
                            
                            // Hide any location warnings
                            this.hideLocationWarning();
                            
                        } else {
                            if (response.rate_limited) {
                                // Don't log rate limit messages to reduce spam
                            } else {
                                console.log('Background location update failed:', response.message);
                            }
                        }
                    } catch (err) {
                        console.error('Background location update JSON parse error:', err);
                    }
                } else {
                    console.error('Background location update HTTP error:', xhr.status);
                }
            }
        };
        
        const params = `latitude=${latitude}&longitude=${longitude}`;
        xhr.send(params);
    }
    
    // Handle location errors
    handleLocationError(error) {
        switch (error.code) {
            case error.PERMISSION_DENIED:
                this.locationPermissionStatus = 'denied';
                this.showLocationWarning('Location access denied. Background tracking disabled.');
                this.stopTracking();
                break;
            case error.POSITION_UNAVAILABLE:
                // Don't spam console with these errors
                break;
            case error.TIMEOUT:
                // Don't spam console with timeout errors
                break;
            default:
                console.log('Location error:', error.message);
                break;
        }
    }
    
    // Apply random color theme
    applyColorTheme() {
        const randomTheme = this.lightThemes[Math.floor(Math.random() * this.lightThemes.length)];
        const body = document.body;
        const originalBg = body.style.backgroundColor;
        
        // Apply theme with smooth transition
        body.style.transition = 'background-color 0.5s ease';
        body.style.backgroundColor = randomTheme.bg;
        
        // Add subtle border flash
        body.style.borderTop = `3px solid ${randomTheme.border}`;
        
        // Revert after 2 seconds
        setTimeout(() => {
            body.style.backgroundColor = originalBg;
            body.style.borderTop = '';
        }, 2000);
        
        console.log(`Applied ${randomTheme.name} theme for location update feedback`);
    }
    
    // Update location display in UI
    updateLocationDisplay(response) {
        // Update location text if element exists
        const locationDisplay = document.querySelector('#current-location');
        if (locationDisplay && response.address) {
            locationDisplay.innerHTML = `<i class="fas fa-map-marker-alt"></i> ${response.address}`;
        }
        
        // Update last updated time
        const lastUpdated = document.querySelector('#last-updated');
        if (lastUpdated && response.timestamp) {
            lastUpdated.textContent = `Last updated: ${response.timestamp}`;
            lastUpdated.style.color = '#4CAF50';
            
            // Reset color after 2 seconds
            setTimeout(() => {
                lastUpdated.style.color = '';
            }, 2000);
        }
        
        // Update coordinates display if exists
        const coordsDisplay = document.querySelector('#coordinates');
        if (coordsDisplay && response.latitude && response.longitude) {
            coordsDisplay.textContent = `${response.latitude.toFixed(6)}, ${response.longitude.toFixed(6)}`;
        }
    }
    
    // Public methods
    forceUpdate() {
        if (this.isCheckedIn && this.locationPermissionStatus === 'granted') {
            this.updateLocation();
        }
    }
    
    getStatus() {
        return {
            isActive: this.isActive,
            isCheckedIn: this.isCheckedIn,
            locationPermission: this.locationPermissionStatus,
            lastUpdate: this.lastUpdateTime ? new Date(this.lastUpdateTime).toLocaleTimeString() : 'Never',
            canUpdate: this.canUpdateLocation().allowed
        };
    }
}

// Initialize background worker when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Create global instance
    window.backgroundLocationWorker = new BackgroundLocationWorker();
    
    console.log('Background Location Worker ready');
});

// Export for potential module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BackgroundLocationWorker;
}
