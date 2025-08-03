// Dashboard Location Manager with reliable background location updates

let userLatitude = null;
let userLongitude = null;
let isLocationUpdateInProgress = false;
let lastLocationUpdateTime = 0;
let backgroundLocationInterval = null;
let locationPermissionStatus = 'checking';
let isPageVisible = true;
let lastBackgroundLogTime = 0;

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

// Apply random color theme to page for feedback
function applyRandomColorTheme() {
    const randomTheme = lightThemeColors[Math.floor(Math.random() * lightThemeColors.length)];
    const body = document.body;
    const originalBg = body.style.backgroundColor;

    body.style.transition = 'background-color 0.5s ease';
    body.style.backgroundColor = randomTheme.bg;
    setTimeout(() => {
        body.style.backgroundColor = originalBg;
    }, 2000);

    const now = Date.now();
    if (now - lastBackgroundLogTime > 600000) {
        console.log(`Applied ${randomTheme.name} theme for location update feedback`);
        lastBackgroundLogTime = now;
    }
}

// Rate limiting: only allow 1 request per minute (60 seconds)
function canMakeLocationRequest() {
    const now = Date.now();
    return (now - lastLocationUpdateTime) >= 60000;
}

// Check and monitor location permission
function checkLocationPermission() {
    if (!navigator.geolocation) {
        locationPermissionStatus = 'unsupported';
        showLocationWarning('Your browser does not support geolocation. Please use a modern browser.');
        return false;
    }

    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' })
            .then(status => {
                locationPermissionStatus = status.state;
                if (status.state === 'denied') {
                    showLocationWarning('Location access is denied. Please enable location permission to continue using the app.');
                } else if (status.state === 'granted') {
                    hideLocationWarning();
                }
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
                checkLocationByGettingPosition();
            });
    } else {
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

// Get current location (for check-in/out)
function getCurrentLocation(callback) {
    if (!navigator.geolocation) {
        showLocationWarning("Geolocation is not supported by your browser.");
        return;
    }

    if (locationPermissionStatus === 'denied') {
        showLocationWarning("Location access is denied. Please enable location permission to check in.");
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function (position) {
            userLatitude = position.coords.latitude;
            userLongitude = position.coords.longitude;
            console.log("Location fetched for check-in/out", userLatitude, userLongitude);
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

// Check if user is currently checked in by examining button states
function isUserCheckedIn() {
    const checkinBtn = document.getElementById("checkin-btn");
    const checkoutBtn = document.getElementById("checkout-btn");
    return (checkinBtn && checkinBtn.style.display === 'none' &&
            checkoutBtn && checkoutBtn.style.display !== 'none');
}

// Background location update function using FastAPI with SW bypass
function updateLocationInBackground() {
    if (locationPermissionStatus === 'denied') {
        stopBackgroundLocationUpdates();
        return;
    }
    if (!canMakeLocationRequest()) return;
    if (isLocationUpdateInProgress) return;
    if (!isUserCheckedIn()) {
        stopBackgroundLocationUpdates();
        return;
    }

    isLocationUpdateInProgress = true;
    navigator.geolocation.getCurrentPosition(
        function (position) {
            const latitude = position.coords.latitude;
            const longitude = position.coords.longitude;
            const usernameElement = document.querySelector('[data-username]');
            let username = usernameElement ? usernameElement.getAttribute('data-username') : null;
            if (!username) {
                const userInfoElement = document.querySelector('.user-info');
                if (userInfoElement) {
                    const text = userInfoElement.textContent;
                    const match = text.match(/@([a-zA-Z0-9_]+)/);
                    if (match) username = match[1];
                }
            }
            if (!username) {
                console.error('Username not found for background location update');
                isLocationUpdateInProgress = false;
                return;
            }
            const xhr = new XMLHttpRequest();
            const fastApiUrl = `http://54.250.198.0:8000/update_location/${username}/${longitude}_${latitude}`;
            xhr.open('POST', fastApiUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
            xhr.setRequestHeader('Pragma', 'no-cache');
            xhr.setRequestHeader('Expires', '0');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-Bypass-Service-Worker', 'true');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    isLocationUpdateInProgress = false;
                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            if (data.message && data.message.includes('Location added')) {
                                lastLocationUpdateTime = Date.now();
                                const now = Date.now();
                                if (now - lastBackgroundLogTime > 600000) {
                                    console.log(`Background location updated successfully via FastAPI at ${new Date().toLocaleTimeString()}`);
                                    lastBackgroundLogTime = now;
                                }
                                applyRandomColorTheme();
                                updateLocationDisplay(data);
                            }
                        } catch (err) {
                            // Reduce console spam
                        }
                    }
                }
            };
            xhr.onerror = function () {
                isLocationUpdateInProgress = false;
            };
            xhr.send(JSON.stringify({}));
        },
        function (error) {
            isLocationUpdateInProgress = false;
            if (error.code === error.PERMISSION_DENIED) {
                locationPermissionStatus = 'denied';
                showLocationWarning('Location access denied. Background tracking disabled.');
                stopBackgroundLocationUpdates();
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
    if (lastUpdatedDisplay) {
        const now = new Date();
        lastUpdatedDisplay.textContent = `Last updated: ${now.toLocaleTimeString()}`;
    }
}

// Start background location updates every 60 seconds (works minimized)
function startBackgroundLocationUpdates() {
    if (backgroundLocationInterval) clearInterval(backgroundLocationInterval);
    backgroundLocationInterval = setInterval(updateLocationInBackground, 60000);
    console.log('Background location updates started (60s interval)');
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

// Visibility change: DO NOT stop interval when hidden
function handleVisibilityChange() {
    if (document.hidden) {
        isPageVisible = false;
        console.log('Page hidden - background updates continue running');
    } else {
        isPageVisible = true;
        console.log('Page visible - background updates still running');
        if (isUserCheckedIn() && !backgroundLocationInterval) {
            startBackgroundLocationUpdates();
        }
    }
}

// Listen for check-in/out and trigger background location tracking
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
        xhr.setRequestHeader('Cache-Control', 'no-cache');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                console.log("Server response status:", xhr.status);
                try {
                    const response = JSON.parse(xhr.responseText);
                    console.log("Parsed response:", response);
                    if (response.success) {
                        console.log("Action successful, updating UI...");
                        if (action === "checkin") {
                            checkinBtn.style.display = 'none';
                            checkoutBtn.style.display = 'inline-block';
                            startBackgroundLocationUpdates();
                        } else if (action === "checkout") {
                            checkinBtn.style.display = 'inline-block';
                            checkoutBtn.style.display = 'none';
                            stopBackgroundLocationUpdates();
                        }
                        applyRandomColorTheme();
                        setTimeout(() => {
                            window.location.reload(true);
                        }, 1500);
                    } else {
                        alert("Failed: " + response.message);
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

    // Initialize everything
    function initializeLocationManager() {
        checkLocationPermission();
        document.addEventListener('visibilitychange', handleVisibilityChange);
        setTimeout(() => {
            if (isUserCheckedIn()) {
                console.log('User is checked in on page load, starting background location updates');
                startBackgroundLocationUpdates();
            } else {
                console.log('User is not checked in on page load');
            }
        }, 1000);
        console.log('Location manager initialized');
    }

    initializeLocationManager();

    // Expose functions globally for other scripts
    window.startBackgroundLocationUpdates = startBackgroundLocationUpdates;
    window.stopBackgroundLocationUpdates = stopBackgroundLocationUpdates;
    window.updateLocationInBackground = updateLocationInBackground;
    window.checkLocationPermission = checkLocationPermission;
});

// Clean up on page unload
window.addEventListener('beforeunload', function () {
    stopBackgroundLocationUpdates();
});
