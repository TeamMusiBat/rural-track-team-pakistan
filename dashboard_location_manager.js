
// Dashboard Location Manager with mobile-optimized background location updates
import { mobileLocationService } from './src/services/MobileLocationService.js';

let userLatitude = null;
let userLongitude = null;
let isLocationUpdateInProgress = false;
let lastLocationUpdateTime = 0;
let locationPermissionStatus = 'checking';

// Check if user is currently checked in by examining button states
function isUserCheckedIn() {
    const checkinBtn = document.getElementById("checkin-btn");
    const checkoutBtn = document.getElementById("checkout-btn");
    return (checkinBtn && checkinBtn.style.display === 'none' &&
            checkoutBtn && checkoutBtn.style.display !== 'none');
}

// Get current location for check-in/out
function getCurrentLocation(callback) {
    if (!navigator.geolocation) {
        alert("Geolocation is not supported by your browser.");
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function (position) {
            userLatitude = position.coords.latitude;
            userLongitude = position.coords.longitude;
            console.log("Location fetched for check-in/out", userLatitude, userLongitude);
            if (typeof callback === 'function') callback();
        },
        function (error) {
            console.error("Location error:", error);
            alert("Error getting location: " + error.message);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
    );
}

// Enhanced button click handlers
document.addEventListener("DOMContentLoaded", function () {
    const checkinBtn = document.getElementById("checkin-btn");
    const checkoutBtn = document.getElementById("checkout-btn");

    if (checkinBtn) {
        checkinBtn.addEventListener("click", function (e) {
            e.preventDefault();
            console.log("Check-in button clicked");
            
            // Disable button to prevent double-click
            checkinBtn.disabled = true;
            checkinBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking in...';
            
            getCurrentLocation(function () {
                sendLocation("checkin");
            });
        });
    }

    if (checkoutBtn) {
        checkoutBtn.addEventListener("click", function (e) {
            e.preventDefault();
            console.log("Check-out button clicked");
            
            // Disable button to prevent double-click
            checkoutBtn.disabled = true;
            checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking out...';
            
            getCurrentLocation(function () {
                sendLocation("checkout");
            });
        });
    }

    function sendLocation(action) {
        if (userLatitude === null || userLongitude === null) {
            alert("Location not available. Please try again.");
            resetButton(action);
            return;
        }
        
        console.log("Sending location for action:", action);
        
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "dashboard.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.setRequestHeader('Cache-Control', 'no-cache');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-Bypass-Service-Worker', 'true');
        
        xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        console.log("Action successful, updating UI...");
                        
                        // Get username for mobile location service
                        const usernameElement = document.querySelector('[data-username]');
                        const username = usernameElement ? usernameElement.getAttribute('data-username') : null;
                        
                        if (action === "checkin") {
                            checkinBtn.style.display = 'none';
                            checkoutBtn.style.display = 'inline-block';
                            
                            // Start mobile location tracking
                            if (username) {
                                mobileLocationService.startTracking(username);
                            }
                            
                            // Show success message
                            showSuccessMessage("Successfully checked in! Location tracking started.");
                        } else if (action === "checkout") {
                            checkinBtn.style.display = 'inline-block';
                            checkoutBtn.style.display = 'none';
                            
                            // Stop mobile location tracking
                            mobileLocationService.stopTracking();
                            
                            // Show success message
                            showSuccessMessage("Successfully checked out! Location tracking stopped.");
                        }
                        
                        // Don't reload page, just update UI
                        resetButton(action);
                    } else {
                        alert("Failed: " + response.message);
                        resetButton(action);
                    }
                } catch (err) {
                    console.error("JSON parse error:", err);
                    alert("Failed to process response. Please try again.");
                    resetButton(action);
                }
            }
        };
        
        xhr.onerror = function() {
            alert("Network error. Please check your connection.");
            resetButton(action);
        };
        
        const params = `latitude=${userLatitude}&longitude=${userLongitude}&action=${action}&ajax=1`;
        xhr.send(params);
    }
    
    function resetButton(action) {
        if (action === "checkin") {
            checkinBtn.disabled = false;
            checkinBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Check In';
        } else {
            checkoutBtn.disabled = false;
            checkoutBtn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Check Out';
        }
    }
    
    function showSuccessMessage(message) {
        const messageDiv = document.createElement('div');
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #4CAF50;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        `;
        messageDiv.textContent = message;
        document.body.appendChild(messageDiv);
        
        setTimeout(() => {
            messageDiv.remove();
        }, 3000);
    }

    // Initialize tracking if user is already checked in
    setTimeout(() => {
        if (isUserCheckedIn()) {
            const usernameElement = document.querySelector('[data-username]');
            const username = usernameElement ? usernameElement.getAttribute('data-username') : null;
            
            if (username) {
                console.log('User is checked in on page load, starting mobile location tracking');
                mobileLocationService.startTracking(username);
            }
        }
    }, 1000);

    console.log('Enhanced dashboard location manager initialized');
});

// Clean up on page unload
window.addEventListener('beforeunload', function () {
    mobileLocationService.stopTracking();
});
