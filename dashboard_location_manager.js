let userLatitude = null;
let userLongitude = null;

function getCurrentLocation(callback) {
    if (!navigator.geolocation) {
        alert("Geolocation is not supported by your browser.");
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function (position) {
            userLatitude = position.coords.latitude;
            userLongitude = position.coords.longitude;
            console.log("Location fetched", userLatitude, userLongitude);
            if (typeof callback === 'function') callback();
        },
        function (error) {
            alert("Error getting location: " + error.message);
        },
        { enableHighAccuracy: true }
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
            alert("Location not available. Please try again.");
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
});

