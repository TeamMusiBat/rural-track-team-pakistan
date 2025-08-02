let userLatitude = null;
let userLongitude = null;
let isLocationUpdateInProgress = false;
let lastLocationUpdateTime = 0;
let backgroundLocationInterval = null;
let locationPermissionStatus = 'checking';
let isPageVisible = true;

// Light theme colors for dashboard feedback
const lightThemeColors = [
    { bg: '#E8F5E8', border: '#4CAF50', name: 'Green' },
    { bg: '#E3F2FD', border: '#2196F3', name: 'Blue' },
    { bg: '#FFF3E0', border: '#FF9800', name: 'Orange' },
    { bg: '#F3E5F5', border: '#9C27B0', name: 'Purple' },
    { bg: '#E0F2F1', border: '#009688', name: 'Teal' },
    { bg: '#FFF8E1', border: '#FFC107', name: 'Amber' },
    { bg: '#FCE4EC', border: '#E91E63', name: 'Pink' },
    { bg: '#E8F5E8', border: '#8BC34A', name: 'Lime' }
];

// Check if location permission is enabled
function checkLocationPermission() {
    if (!navigator.geolocation) {
        locationPermissionStatus = 'unsupported';
        showLocationWarning('Your browser does not support geolocation. Please use a modern browser.');
        return false;
    }

    // Check permission status
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' })
            .then(status => {
                locationPermissionStatus = status.state;
                if (status.state === 'denied') {
                    showLocationWarning('Location access is denied. Please enable location permission to continue using the app.');
                    return false;
                } else if (status.state === 'granted') {
                    hideLocationWarning();
                    return true;
                }
                
                // Listen for permission changes
                status.onchange = () => {
                    locationPermissionStatus = status.state;
                    if (status.state === 'denied') {
                        showLocationWarning('Location access has been denied. Please enable location permission.');
                    } else if (status.state === 'granted') {
                        hideLocationWarning();
                    }
                };
            })
            .catch(() => {
                // Fallback: try to get location directly
                checkLocationByGettingPosition();
            });
    } else {
        // Fallback for browsers without Permissions API
        checkLocationByGettingPosition();
    }
    return true;
}

// Fallback method to check permission
function checkLocationByGettingPosition() {
    navigator.geolocation.getCurrentPosition(
        () => {
            locationPermissionStatus = 'granted';
            hideLocationWarning();
        },
        (error) => {
            if (error.code === error.PERMISSION_DENIED) {
                locationPermissionStatus = 'denied';
                showLocationWarning('Location access is denied. Please enable location permission in your browser settings.');
            } else {
                locationPermissionStatus = 'prompt';
            }
        },
        { enableHighAccuracy: true, timeout: 5000 }
    );
}

// Show location warning
function showLocationWarning(message) {
    let warningDiv = document.getElementById('location-warning');
    if (!warningDiv) {
        warningDiv = document.createElement('div');
        warningDiv.id = 'location-warning';
        warningDiv.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #ffebee;
            color: #c62828;
            padding: 12px 20px;
            border-radius: 8px;
            border: 2px solid #ef5350;
            font-weight: 500;
            font-size: 14px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 90%;
            text-align: center;
        `;
        document.body.appendChild(warningDiv);
    }
    warningDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
    warningDiv.style.display = 'block';
}

// Hide location warning
function hideLocationWarning() {
    const warningDiv = document.getElementById('location-warning');
    if (warningDiv) {
        warningDiv.style.display = 'none';
    }
}

// Apply random color theme to page
function applyRandomColorTheme() {
    const randomTheme = lightThemeColors[Math.floor(Math.random() * lightThemeColors.length)];
    const body = document.body;
    const originalBg = body.style.backgroundColor;
    
    // Apply theme with smooth transition
    body.style.transition = 'background-color 0.5s ease';
    body.style.backgroundColor = randomTheme.bg;
    
    // Revert back after 2 seconds
    setTimeout(() => {
        body.style.backgroundColor = originalBg;
    }, 2000);
    
    console.log(`Applied ${randomTheme.name} theme for location update feedback`);
}

// Rate limiting: only allow 1 request per minute (60 seconds)
function canMakeLocationRequest() {
    const now = Date.now();
    const timeSinceLastUpdate = now - lastLocationUpdateTime;
    const minInterval = 60000; // 60 seconds in milliseconds
    
    if (timeSinceLastUpdate < minInterval) {
        const remainingTime = Math.ceil((minInterval - timeSinceLastUpdate) / 1000);
        console.log(`Rate limited: ${remainingTime} seconds remaining`);
        return false;
    }
    
    return true;
}

function getCurrentLocation(callback) {
    if (!navigator.geolocation) {
        showLocationWarning("Geolocation is not supported by your browser.");
        return;
    }

    // Check location permission first
    if (locationPermissionStatus === 'denied') {
        showLocationWarning("Location access is denied. Please enable location permission to check in.");
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function (position) {
            userLatitude = position.coords.latitude;
            userLongitude = position.coords.longitude;
            console.log("Location fetched", userLatitude, userLongitude);
            hideLocationWarning();
            if (typeof callback === 'function') callback();
        },
        function (error) {
            if (error.code === error.PERMISSION_DENIED) {
                locationPermissionStatus = 'denied';
                showLocationWarning("Location access denied. You must enable location permission to check in.");
            } else {
                showLocationWarning("Error getting location: " + error.message);
            }
            console.error("Location error:", error);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
    );
}

document.addEventListener("DOMContentLoaded", function () {
    const checkinBtn = document.getElementById("checkin-btn");
    const checkoutBtn = document.getElementById("checkout-btn");

    if (checkinBtn) {
        checkinBtn.addEventListener("click", function () {
            console.log("Check-in button clicked");
            getCurrentLocation(function () {
                sendLocation("checkin");
            });
        });
    }

    if (checkoutBtn) {
        checkoutBtn.addEventListener("click", function () {
            console.log("Check-out button clicked");
            getCurrentLocation(function () {
                sendLocation("checkout");
            });
        });
    }

    function sendLocation(action) {
        if (userLatitude === null || userLongitude === null) {
            showLocationWarning("Location not available. Please try again.");
            return;
        }

        console.log("Sending location for action:", action);
        
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "dashboard.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                console.log("Server response status:", xhr.status);
                console.log("Server response text:", xhr.responseText);
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    console.log("Parsed response:", response);
                    
                    if (response.success) {
                        // Force page refresh immediately without alert popup
                        console.log("Action successful, refreshing page...");
                        window.location.reload(true);
                    } else {
                        alert("Failed: " + response.message);
                        
                        // Show debug info if available
                        if (response.debug) {
                            console.log("Debug info:", response.debug);
                        }
                    }
                } catch (err) {
                    console.error("JSON parse error:", err);
                    console.log("Raw response:", xhr.responseText);
                    alert("Failed to parse server response. Check console for details.");
                }
            }
        };

        const params = `latitude=${userLatitude}&longitude=${userLongitude}&action=${action}&ajax=1`;
        console.log("Sending params:", params);
        xhr.send(params);
    }

    // Background location update function
    function updateLocationInBackground() {
        // Only update if user is checked in and location permission is granted
        if (locationPermissionStatus === 'denied') {
            console.log('Location permission denied, skipping background update');
            return;
        }

        // Check rate limiting
        if (!canMakeLocationRequest()) {
            console.log('Rate limited, skipping background update');
            return;
        }

        // Prevent multiple simultaneous requests
        if (isLocationUpdateInProgress) {
            console.log('Location update already in progress, skipping');
            return;
        }

        // Check if user is checked in by looking at the DOM
        const checkinBtn = document.getElementById("checkin-btn");
        const checkoutBtn = document.getElementById("checkout-btn");
        
        if (!checkoutBtn || checkoutBtn.style.display === 'none' || checkinBtn.style.display !== 'none') {
            console.log('User not checked in, skipping background location update');
            return;
        }

        isLocationUpdateInProgress = true;
        console.log('Starting background location update...');

        navigator.geolocation.getCurrentPosition(
            function(position) {
                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;
                
                // Send to update_location.php endpoint
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "update_location.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === XMLHttpRequest.DONE) {
                        isLocationUpdateInProgress = false;
                        
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                lastLocationUpdateTime = Date.now();
                                console.log(`Background location updated successfully at ${new Date().toLocaleTimeString()}`);
                                
                                // Apply color theme feedback
                                applyRandomColorTheme();
                                
                                // Update location display if elements exist
                                updateLocationDisplay(response);
                            } else {
                                console.log('Background location update failed:', response.message);
                                if (response.rate_limited) {
                                    console.log(`Rate limited: ${response.remaining_seconds} seconds remaining`);
                                }
                            }
                        } catch (err) {
                            console.error('Background location update JSON parse error:', err);
                        }
                    }
                };
                
                const params = `latitude=${latitude}&longitude=${longitude}`;
                xhr.send(params);
            },
            function(error) {
                isLocationUpdateInProgress = false;
                if (error.code === error.PERMISSION_DENIED) {
                    locationPermissionStatus = 'denied';
                    showLocationWarning('Location access denied. Background tracking disabled.');
                    stopBackgroundLocationUpdates();
                } else {
                    console.error('Background location error:', error.message);
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
        );
    }

    // Update location display in UI
    function updateLocationDisplay(response) {
        const locationDisplay = document.getElementById('location-display');
        const lastUpdatedDisplay = document.getElementById('last-updated');
        
        if (locationDisplay && response.address) {
            locationDisplay.innerHTML = `<i class="fas fa-map-marker-alt"></i> ${response.address}`;
        }
        
        if (lastUpdatedDisplay && response.timestamp) {
            lastUpdatedDisplay.textContent = `Last updated: ${response.timestamp}`;
        }
    }

    // Start background location updates
    function startBackgroundLocationUpdates() {
        if (backgroundLocationInterval) {
            clearInterval(backgroundLocationInterval);
        }
        
        // Update every 60 seconds (1 minute as requested)
        backgroundLocationInterval = setInterval(updateLocationInBackground, 60000);
        console.log('Background location updates started (60 second interval)');
        
        // Do an immediate update after a short delay
        setTimeout(updateLocationInBackground, 5000);
    }

    // Stop background location updates
    function stopBackgroundLocationUpdates() {
        if (backgroundLocationInterval) {
            clearInterval(backgroundLocationInterval);
            backgroundLocationInterval = null;
            console.log('Background location updates stopped');
        }
    }

    // Handle page visibility changes
    function handleVisibilityChange() {
        if (document.hidden) {
            isPageVisible = false;
            console.log('Page hidden - background updates continue');
        } else {
            isPageVisible = true;
            console.log('Page visible - resuming normal operation');
        }
    }

    // Initialize everything
    function initializeLocationManager() {
        // Check location permission on startup
        checkLocationPermission();
        
        // Set up page visibility listener
        document.addEventListener('visibilitychange', handleVisibilityChange);
        
        // Start background updates if user is checked in
        const checkoutBtn = document.getElementById("checkout-btn");
        if (checkoutBtn && checkoutBtn.style.display !== 'none') {
            startBackgroundLocationUpdates();
        }
        
        console.log('Location manager initialized');
    }

    // Initialize on page load
    initializeLocationManager();

    // Expose functions globally for use by other scripts
    window.startBackgroundLocationUpdates = startBackgroundLocationUpdates;
    window.stopBackgroundLocationUpdates = stopBackgroundLocationUpdates;
    window.updateLocationInBackground = updateLocationInBackground;
    window.checkLocationPermission = checkLocationPermission;
});

