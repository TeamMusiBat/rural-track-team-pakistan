
// Production-ready Dashboard Location Manager
import { mobileLocationService } from './src/services/MobileLocationService.js';

let userLatitude = null;
let userLongitude = null;
let isLocationUpdateInProgress = false;
let lastLocationUpdateTime = 0;

console.log('Dashboard Location Manager loading...');

// Check if user is currently checked in
function isUserCheckedIn() {
    const checkinBtn = document.getElementById("checkin-btn");
    const checkoutBtn = document.getElementById("checkout-btn");
    return (checkinBtn && checkinBtn.style.display === 'none' &&
            checkoutBtn && checkoutBtn.style.display !== 'none');
}

// Get current location with enhanced error handling
function getCurrentLocation(callback, showLoader = true) {
    if (!navigator.geolocation) {
        showErrorMessage("Geolocation is not supported by your browser.");
        return;
    }

    if (showLoader) {
        showLoadingMessage("Getting your location...");
    }

    const options = {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 30000
    };

    navigator.geolocation.getCurrentPosition(
        function (position) {
            userLatitude = position.coords.latitude;
            userLongitude = position.coords.longitude;
            console.log("Location fetched:", userLatitude, userLongitude, "Accuracy:", position.coords.accuracy + "m");
            
            if (showLoader) {
                hideLoadingMessage();
            }
            
            if (typeof callback === 'function') {
                callback();
            }
        },
        function (error) {
            console.error("Location error:", error);
            
            if (showLoader) {
                hideLoadingMessage();
            }
            
            let errorMessage = "Error getting location: ";
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage += "Location access denied. Please enable location permission.";
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage += "Location information unavailable.";
                    break;
                case error.TIMEOUT:
                    errorMessage += "Location request timed out. Please try again.";
                    break;
                default:
                    errorMessage += error.message;
                    break;
            }
            showErrorMessage(errorMessage);
        },
        options
    );
}

// Enhanced button click handlers with proper AJAX
document.addEventListener("DOMContentLoaded", function () {
    console.log("DOM loaded, initializing dashboard...");
    
    const checkinBtn = document.getElementById("checkin-btn");
    const checkoutBtn = document.getElementById("checkout-btn");

    if (checkinBtn) {
        checkinBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log("Check-in button clicked");
            
            if (checkinBtn.disabled) {
                console.log("Check-in button already disabled, ignoring click");
                return;
            }
            
            setButtonLoading(checkinBtn, "checkin");
            getCurrentLocation(() => sendLocation("checkin"));
        });
    }

    if (checkoutBtn) {
        checkoutBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log("Check-out button clicked");
            
            if (checkoutBtn.disabled) {
                console.log("Check-out button already disabled, ignoring click");
                return;
            }
            
            setButtonLoading(checkoutBtn, "checkout");
            getCurrentLocation(() => sendLocation("checkout"));
        });
    }

    function setButtonLoading(button, action) {
        button.disabled = true;
        
        if (action === "checkin") {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking in...';
        } else {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking out...';
        }
    }

    function sendLocation(action) {
        if (userLatitude === null || userLongitude === null) {
            showErrorMessage("Location not available. Please try again.");
            resetButton(action);
            return;
        }
        
        console.log("Sending location for action:", action, "Coords:", userLatitude, userLongitude);
        
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "dashboard.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.setRequestHeader('Cache-Control', 'no-cache');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-Bypass-Service-Worker', 'true');
        
        // Set timeout
        xhr.timeout = 30000;
        
        xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                console.log("XHR Response received:", xhr.status, xhr.responseText);
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log("Parsed response:", response);
                        
                        if (response.success) {
                            handleSuccessfulAction(action, response);
                        } else {
                            showErrorMessage("Failed: " + (response.message || 'Unknown error'));
                            resetButton(action);
                        }
                    } catch (err) {
                        console.error("JSON parse error:", err, "Raw response:", xhr.responseText);
                        showErrorMessage("Invalid response from server. Please try again.");
                        resetButton(action);
                    }
                } else {
                    console.error("HTTP Error:", xhr.status, xhr.statusText);
                    showErrorMessage(`Server error (${xhr.status}). Please try again.`);
                    resetButton(action);
                }
            }
        };
        
        xhr.ontimeout = function() {
            console.error("Request timeout");
            showErrorMessage("Request timed out. Please check your connection and try again.");
            resetButton(action);
        };
        
        xhr.onerror = function() {
            console.error("Network error");
            showErrorMessage("Network error. Please check your connection and try again.");
            resetButton(action);
        };
        
        const params = `latitude=${userLatitude}&longitude=${userLongitude}&action=${action}&ajax=1`;
        console.log("Sending params:", params);
        xhr.send(params);
    }
    
    function handleSuccessfulAction(action, response) {
        console.log("Action successful:", action);
        
        // Get username for mobile location service
        const usernameElement = document.querySelector('[data-username]') || 
                               document.querySelector('#username') ||
                               document.querySelector('.username');
        const username = usernameElement ? 
                        (usernameElement.getAttribute('data-username') || 
                         usernameElement.textContent || 
                         usernameElement.value) : null;
        
        if (action === "checkin") {
            // Hide check-in, show check-out
            document.getElementById("checkin-btn").style.display = 'none';
            document.getElementById("checkout-btn").style.display = 'inline-block';
            
            // Start mobile location tracking
            if (username) {
                console.log("Starting location tracking for:", username);
                mobileLocationService.startTracking(username.trim());
            } else {
                console.warn("Username not found, location tracking may not work properly");
            }
            
            showSuccessMessage("✅ Successfully checked in! Location tracking started.");
            
        } else if (action === "checkout") {
            // Show check-in, hide check-out
            document.getElementById("checkin-btn").style.display = 'inline-block';
            document.getElementById("checkout-btn").style.display = 'none';
            
            // Stop mobile location tracking
            console.log("Stopping location tracking");
            mobileLocationService.stopTracking();
            
            showSuccessMessage("✅ Successfully checked out! Location tracking stopped.");
        }
        
        resetButton(action);
    }
    
    function resetButton(action) {
        const checkinBtn = document.getElementById("checkin-btn");
        const checkoutBtn = document.getElementById("checkout-btn");
        
        if (action === "checkin" && checkinBtn) {
            checkinBtn.disabled = false;
            checkinBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Check In';
        } else if (action === "checkout" && checkoutBtn) {
            checkoutBtn.disabled = false;
            checkoutBtn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Check Out';
        }
    }
    
    // Initialize tracking if user is already checked in
    setTimeout(() => {
        if (isUserCheckedIn()) {
            const usernameElement = document.querySelector('[data-username]') || 
                                   document.querySelector('#username') ||
                                   document.querySelector('.username');
            const username = usernameElement ? 
                            (usernameElement.getAttribute('data-username') || 
                             usernameElement.textContent || 
                             usernameElement.value) : null;
            
            if (username) {
                console.log('User is checked in on page load, starting location tracking for:', username);
                mobileLocationService.startTracking(username.trim());
            }
        }
    }, 2000);

    console.log('Dashboard location manager initialized successfully');
});

// Message display functions
function showSuccessMessage(message) {
    const messageDiv = document.createElement('div');
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #4CAF50, #45a049);
        color: white;
        padding: 15px 25px;
        border-radius: 10px;
        font-weight: 600;
        z-index: 10000;
        box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        font-size: 14px;
        max-width: 90vw;
        text-align: center;
    `;
    messageDiv.innerHTML = message;
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        messageDiv.style.transform = 'translateX(-50%) translateY(-20px)';
        messageDiv.style.transition = 'all 0.5s ease';
        setTimeout(() => messageDiv.remove(), 500);
    }, 4000);
}

function showErrorMessage(message) {
    const messageDiv = document.createElement('div');
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #f44336, #d32f2f);
        color: white;
        padding: 15px 25px;
        border-radius: 10px;
        font-weight: 600;
        z-index: 10000;
        box-shadow: 0 6px 20px rgba(244, 67, 54, 0.4);
        font-size: 14px;
        max-width: 90vw;
        text-align: center;
    `;
    messageDiv.innerHTML = `⚠️ ${message}`;
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        messageDiv.style.transform = 'translateX(-50%) translateY(-20px)';
        messageDiv.style.transition = 'all 0.5s ease';
        setTimeout(() => messageDiv.remove(), 500);
    }, 6000);
}

function showLoadingMessage(message) {
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'loading-message';
    loadingDiv.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #2196F3, #1976D2);
        color: white;
        padding: 15px 25px;
        border-radius: 10px;
        font-weight: 600;
        z-index: 10000;
        box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
        font-size: 14px;
        max-width: 90vw;
        text-align: center;
    `;
    loadingDiv.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${message}`;
    document.body.appendChild(loadingDiv);
}

function hideLoadingMessage() {
    const loadingDiv = document.getElementById('loading-message');
    if (loadingDiv) {
        loadingDiv.remove();
    }
}

// Clean up on page unload
window.addEventListener('beforeunload', function () {
    console.log('Page unloading, stopping location tracking...');
    if (window.mobileLocationService) {
        window.mobileLocationService.stopTracking();
    }
});

// Export for potential module use
window.dashboardLocationManager = {
    getCurrentLocation,
    showSuccessMessage,
    showErrorMessage,
    isUserCheckedIn
};

console.log('Dashboard Location Manager loaded successfully');
