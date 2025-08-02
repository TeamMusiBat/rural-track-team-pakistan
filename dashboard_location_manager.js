// Dashboard Location Manager - Handles 1-minute location updates and background sync
let locationUpdateInterval;
let lastLocationUpdateTime = 0;
let isLocationEnabled = false;
let watchId = null;

// Light color themes for visual feedback when location is updated
const dashboardThemes = [
    { bg: '#E8F5E8', name: 'Light Green' },
    { bg: '#E3F2FD', name: 'Light Blue' },
    { bg: '#FFF3E0', name: 'Light Orange' },
    { bg: '#F3E5F5', name: 'Light Purple' },
    { bg: '#E0F2F1', name: 'Light Teal' },
    { bg: '#FFF8E1', name: 'Light Amber' },
    { bg: '#FCE4EC', name: 'Light Pink' },
    { bg: '#F1F8E9', name: 'Light Lime' }
];

// Initialize dashboard location manager
function initializeDashboardLocationManager() {
    // Check if user is checked in
    if (document.querySelector('#check-out-btn')) {
        // User is checked in, start location tracking
        startLocationTracking();
        console.log('Dashboard location manager initialized - user is checked in');
    } else {
        console.log('Dashboard location manager - user not checked in');
    }
}

// Start location tracking with 1-minute intervals
function startLocationTracking() {
    // Clear any existing intervals
    if (locationUpdateInterval) {
        clearInterval(locationUpdateInterval);
    }
    
    // Start location watching
    if (navigator.geolocation) {
        // Initial location update
        getCurrentLocationAndUpdate();
        
        // Set up 1-minute interval for location updates
        locationUpdateInterval = setInterval(() => {
            getCurrentLocationAndUpdate();
        }, 60000); // 60 seconds = 1 minute
        
        // Watch position for real-time tracking (but limit updates to 1 per minute)
        watchId = navigator.geolocation.watchPosition(
            handleLocationUpdate,
            handleLocationError,
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 30000
            }
        );
        
        console.log('Location tracking started - updates every 1 minute');
    } else {
        showLocationError('Geolocation is not supported by this browser');
    }
}

// Get current location and update if 1 minute has passed
function getCurrentLocationAndUpdate() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            handleLocationUpdate,
            handleLocationError,
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 30000
            }
        );
    }
}

// Handle location update with 1-minute rate limiting
function handleLocationUpdate(position) {
    const currentTime = Date.now();
    const timeSinceLastUpdate = currentTime - lastLocationUpdateTime;
    
    // Only update if 1 minute (60000ms) has passed since last update
    if (timeSinceLastUpdate < 60000 && lastLocationUpdateTime > 0) {
        console.log(`Location update skipped - ${Math.round((60000 - timeSinceLastUpdate) / 1000)} seconds remaining`);
        return;
    }
    
    const latitude = position.coords.latitude;
    const longitude = position.coords.longitude;
    
    // Update location via FastAPI
    updateLocationToFastAPI(latitude, longitude);
    
    // Update last update time
    lastLocationUpdateTime = currentTime;
    
    // Mark location as enabled
    isLocationEnabled = true;
    
    console.log(`Location updated: ${latitude}, ${longitude} at ${new Date().toLocaleTimeString()}`);
}

// Handle location errors
function handleLocationError(error) {
    let errorMessage = '';
    
    switch(error.code) {
        case error.PERMISSION_DENIED:
            errorMessage = "Location access denied by user";
            isLocationEnabled = false;
            showLocationPermissionWarning();
            break;
        case error.POSITION_UNAVAILABLE:
            errorMessage = "Location information is unavailable";
            break;
        case error.TIMEOUT:
            errorMessage = "Location request timed out";
            break;
        default:
            errorMessage = "An unknown error occurred";
            break;
    }
    
    console.error('Location error:', errorMessage);
    showLocationError(errorMessage);
}

// Update location to FastAPI endpoint
async function updateLocationToFastAPI(latitude, longitude) {
    try {
        const formData = new FormData();
        formData.append('latitude', latitude);
        formData.append('longitude', longitude);
        
        const response = await fetch('update_location.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Apply color theme change to dashboard
            applyDashboardColorTheme(data.color_theme);
            
            // Update location display if element exists
            const locationElement = document.querySelector('#current-location');
            if (locationElement) {
                locationElement.textContent = data.address || 'Location updated';
                locationElement.style.color = '#4CAF50';
            }
            
            // Update timestamp if element exists
            const timeElement = document.querySelector('#location-time');
            if (timeElement) {
                timeElement.textContent = `Last updated: ${data.timestamp}`;
            }
            
            console.log('Location successfully updated to FastAPI:', data.address);
        } else {
            console.error('Failed to update location:', data.message);
            showLocationError(data.message);
        }
    } catch (error) {
        console.error('Error updating location to FastAPI:', error);
        showLocationError('Failed to update location');
    }
}

// Apply dashboard color theme change
function applyDashboardColorTheme(colorTheme) {
    const dashboard = document.body;
    
    if (dashboard && colorTheme) {
        // Apply the color theme from server response
        dashboard.style.transition = 'background-color 0.8s ease';
        dashboard.style.backgroundColor = colorTheme.bg;
        
        // Reset after 3 seconds
        setTimeout(() => {
            dashboard.style.backgroundColor = '';
        }, 3000);
        
        console.log('Dashboard color theme applied for location update feedback');
    } else {
        // Apply random theme if no server theme
        const randomTheme = dashboardThemes[Math.floor(Math.random() * dashboardThemes.length)];
        
        dashboard.style.transition = 'background-color 0.8s ease';
        dashboard.style.backgroundColor = randomTheme.bg;
        
        setTimeout(() => {
            dashboard.style.backgroundColor = '';
        }, 3000);
        
        console.log(`Applied ${randomTheme.name} dashboard theme`);
    }
}

// Show location permission warning
function showLocationPermissionWarning() {
    // Create or update warning element
    let warningElement = document.querySelector('#location-warning');
    
    if (!warningElement) {
        warningElement = document.createElement('div');
        warningElement.id = 'location-warning';
        warningElement.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ff5722;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(255, 87, 34, 0.3);
            z-index: 9999;
            max-width: 300px;
            font-family: Inter, sans-serif;
            font-size: 14px;
            font-weight: 500;
        `;
        document.body.appendChild(warningElement);
    }
    
    warningElement.innerHTML = `
        <div style="display: flex; align-items: center;">
            <i class="fas fa-exclamation-triangle" style="margin-right: 10px; font-size: 16px;"></i>
            <div>
                <strong>Location Required!</strong><br>
                Your location must be enabled to stay checked in.
            </div>
        </div>
    `;
    
    // Auto-hide after 10 seconds but keep showing until location is enabled
    setTimeout(() => {
        if (!isLocationEnabled && warningElement) {
            warningElement.style.opacity = '0.7';
        }
    }, 10000);
}

// Show location error
function showLocationError(message) {
    const errorElement = document.createElement('div');
    errorElement.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #f44336;
        color: white;
        padding: 12px 16px;
        border-radius: 6px;
        box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);
        z-index: 9999;
        font-family: Inter, sans-serif;
        font-size: 13px;
        max-width: 280px;
    `;
    
    errorElement.innerHTML = `
        <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
        ${message}
    `;
    
    document.body.appendChild(errorElement);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (errorElement.parentNode) {
            errorElement.parentNode.removeChild(errorElement);
        }
    }, 5000);
}

// Stop location tracking
function stopLocationTracking() {
    if (locationUpdateInterval) {
        clearInterval(locationUpdateInterval);
        locationUpdateInterval = null;
    }
    
    if (watchId) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
    
    // Remove warning if exists
    const warningElement = document.querySelector('#location-warning');
    if (warningElement) {
        warningElement.remove();
    }
    
    console.log('Location tracking stopped');
}

// Handle page visibility change for background updates
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        console.log('Page hidden - location tracking continues in background');
    } else {
        console.log('Page visible - resuming normal location tracking');
        // Force an immediate location check when page becomes visible
        if (document.querySelector('#check-out-btn')) {
            setTimeout(() => {
                getCurrentLocationAndUpdate();
            }, 1000);
        }
    }
});

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Small delay to ensure other scripts are loaded
    setTimeout(() => {
        initializeDashboardLocationManager();
    }, 1000);
});

// Handle page unload
window.addEventListener('beforeunload', function() {
    console.log('Page unloading - location tracking will continue in background if possible');
});

// Export functions for global use
window.startLocationTracking = startLocationTracking;
window.stopLocationTracking = stopLocationTracking;
window.getCurrentLocationAndUpdate = getCurrentLocationAndUpdate;
