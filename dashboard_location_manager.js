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
            getCurrentLocation(function () {
                sendLocation("checkin");
            });
        });
    }

    if (checkoutBtn) {
        checkoutBtn.addEventListener("click", function () {
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

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "dashboard.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert(response.message);
                        // Reload page to show updated status
                        location.reload();
                    } else {
                        alert("Failed: " + response.message);
                    }
                } catch (err) {
                    alert("Failed to parse server response.");
                }
            }
        };

        const params = `latitude=${userLatitude}&longitude=${userLongitude}&action=${action}&ajax=1`;
        xhr.send(params);
    }
});
