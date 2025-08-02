// Dashboard Location Manager - Handles 1-minute location updates and background sync
let locationUpdateInterval;
let lastLocationUpdateTime = 0;
let isLocationEnabled = false;
let watchId = null;
let pendingLocationUpdates = JSON.parse(localStorage.getItem('pendingLocationUpdates') || '[]');

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
    if (locationUpdateInterval) {
        clearInterval(locationUpdateInterval);
    }

    if (navigator.geolocation) {
        getCurrentLocationAndUpdate();

        locationUpdateInterval = setInterval(() => {
            getCurrentLocationAndUpdate();
        }, 60000);

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

    if (timeSinceLastUpdate < 60000 && lastLocationUpdateTime > 0) {
        console.log(`Location update skipped - ${Math.round((60000 - timeSinceLastUpdate) / 1000)} seconds remaining`);
        return;
    }

    const latitude = position.coords.latitude;
    const longitude = position.coords.longitude;

    updateLocationToFastAPI(latitude, longitude);

    lastLocationUpdateTime = currentTime;
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

// Update location to FastAPI endpoint, with offline/background sync support
async function updateLocationToFastAPI(latitude, longitude) {
    const formData = new FormData();
    formData.append('latitude', latitude);
    formData.append('longitude', longitude);

    try {
        const response = await fetch('update_location.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();

        if (data.success) {
            applyDashboardColorTheme(data.color_theme);

            const locationElement = document.querySelector('#current-location');
            if (locationElement) {
                locationElement.textContent = data.address || 'Location updated';
                locationElement.style.color = '#4CAF50';
            }

            const timeElement = document.querySelector('#location-time');
            if (timeElement) {
                timeElement.textContent = `Last updated: ${data.timestamp}`;
            }

            console.log('Location successfully updated to FastAPI:', data.address);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        // Save failed update for background sync
        pendingLocationUpdates.push({
            latitude, longitude, timestamp: new Date().toISOString()
        });
        localStorage.setItem('pendingLocationUpdates', JSON.stringify(pendingLocationUpdates));

        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            navigator.serviceWorker.ready.then(function(registration) {
                registration.sync.register('location-sync');
            });
        }

        console.error('Error updating location to FastAPI:', error);
        showLocationError('Failed to update location, will retry when online');
    }
}

// Handle background sync from Service Worker
window.addEventListener('online', function() {
    if (pendingLocationUpdates.length > 0) {
        pendingLocationUpdates.forEach(update => {
            updateLocationToFastAPI(update.latitude, update.longitude);
        });
        pendingLocationUpdates = [];
        localStorage.removeItem('pendingLocationUpdates');
    }
});

// Apply dashboard color theme change
function applyDashboardColorTheme(colorTheme) {
    const dashboard = document.body;
    if (dashboard && colorTheme) {
        dashboard.style.transition = 'background-color 0.8s ease';
        dashboard.style.backgroundColor = colorTheme.bg;
        setTimeout(() => {
            dashboard.style.backgroundColor = '';
        }, 3000);
        console.log('Dashboard color theme applied for location update feedback');
    } else {
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
        if (document.querySelector('#check-out-btn')) {
            setTimeout(() => {
                getCurrentLocationAndUpdate();
            }, 1000);
        }
    }
});

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        initializeDashboardLocationManager();
    }, 1000);
});

document.addEventListener('DOMContentLoaded', function() {
    const checkinBtn = document.getElementById('checkin-btn');
    if (checkinBtn) {
        checkinBtn.addEventListener('click', function() {
            // Disable button to prevent double-clicks
            checkinBtn.disabled = true;

            // Optional: visually indicate loading
            checkinBtn.textContent = 'Checking in...';

            // Prepare AJAX request for check-in
            const formData = new FormData();
            formData.append('action', 'checkin');
            formData.append('ajax', '1');

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to show new checked-in status
                    window.location.reload();
                } else {
                    // Show error and re-enable button
                    alert('Check-in failed: ' + (data.message || 'Unknown error'));
                    checkinBtn.disabled = false;
                    checkinBtn.textContent = 'Check In';
                }
            })
            .catch(error => {
                alert('Check-in failed due to network error. Please try again.');
                checkinBtn.disabled = false;
                checkinBtn.textContent = 'Check In';
            });
        });
    }
});

// Handle page unload
window.addEventListener('beforeunload', function() {
    console.log('Page unloading - location tracking will continue in background if possible');
});

// Export functions for global use
window.startLocationTracking = startLocationTracking;
window.stopLocationTracking = stopLocationTracking;
window.getCurrentLocationAndUpdate = getCurrentLocationAndUpdate;
