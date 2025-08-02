// Dashboard Location Manager - Handles 1-minute location updates and background sync
let locationUpdateInterval;
let lastLocationUpdateTime = 0;
let isLocationEnabled = false;
let watchId = null;
let pendingLocationUpdates = JSON.parse(localStorage.getItem('pendingLocationUpdates') || '[]');

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

function initializeDashboardLocationManager() {
    if (document.querySelector('#check-out-btn')) {
        startLocationTracking();
        console.log('Dashboard location manager initialized - user is checked in');
    } else {
        console.log('Dashboard location manager - user not checked in');
    }
}

function startLocationTracking() {
    if (locationUpdateInterval) clearInterval(locationUpdateInterval);

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

function handleLocationError(error) {
    let errorMessage = '';
    switch (error.code) {
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
        pendingLocationUpdates.push({
            latitude, longitude, timestamp: new Date().toISOString()
        });
        localStorage.setItem('pendingLocationUpdates', JSON.stringify(pendingLocationUpdates));

        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            navigator.serviceWorker.ready.then(registration => {
                registration.sync.register('location-sync');
            });
        }

        console.error('Error updating location to FastAPI:', error);
        showLocationError('Failed to update location, will retry when online');
    }
}

window.addEventListener('online', () => {
    if (pendingLocationUpdates.length > 0) {
        pendingLocationUpdates.forEach(update => {
            updateLocationToFastAPI(update.latitude, update.longitude);
        });
        pendingLocationUpdates = [];
        localStorage.removeItem('pendingLocationUpdates');
    }
});

function applyDashboardColorTheme(colorTheme) {
    const dashboard = document.body;
    const theme = colorTheme || dashboardThemes[Math.floor(Math.random() * dashboardThemes.length)];

    dashboard.style.transition = 'background-color 0.8s ease';
    dashboard.style.backgroundColor = theme.bg;

    setTimeout(() => {
        dashboard.style.backgroundColor = '';
    }, 3000);
}

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
        errorElement.remove();
    }, 5000);
}

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
    if (warningElement) warningElement.remove();

    console.log('Location tracking stopped');
}

document.addEventListener('visibilitychange', () => {
    if (!document.hidden && document.querySelector('#check-out-btn')) {
        setTimeout(() => {
            getCurrentLocationAndUpdate();
        }, 1000);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
        initializeDashboardLocationManager();
    }, 1000);

    const checkinBtn = document.getElementById('checkin-btn');
    if (checkinBtn) {
        checkinBtn.addEventListener('click', function () {
            if (checkinBtn.disabled) return;
            checkinBtn.disabled = true;
            checkinBtn.textContent = 'Checking in...';

            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    const formData = new FormData();
                    formData.append('action', 'checkin');
                    formData.append('ajax', '1');
                    formData.append('latitude', position.coords.latitude);
                    formData.append('longitude', position.coords.longitude);

                    fetch('dashboard.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.location.reload();
                            } else {
                                alert('Check-in failed: ' + (data.message || 'Unknown error'));
                                checkinBtn.disabled = false;
                                checkinBtn.textContent = 'Check In';
                            }
                        })
                        .catch(() => {
                            alert('Network error. Please try again.');
                            checkinBtn.disabled = false;
                            checkinBtn.textContent = 'Check In';
                        });
                }, function () {
                    alert('Location permission is required for check-in.');
                    checkinBtn.disabled = false;
                    checkinBtn.textContent = 'Check In';
                });
            } else {
                alert('Geolocation not supported.');
                checkinBtn.disabled = false;
                checkinBtn.textContent = 'Check In';
            }
        });
    }

    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function () {
            if (checkoutBtn.disabled) return;
            checkoutBtn.disabled = true;
            checkoutBtn.textContent = 'Checking out...';

            const formData = new FormData();
            formData.append('action', 'checkout');
            formData.append('ajax', '1');

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Check-out failed: ' + (data.message || 'Unknown error'));
                        checkoutBtn.disabled = false;
                        checkoutBtn.textContent = 'Check Out';
                    }
                })
                .catch(() => {
                    alert('Network error. Please try again.');
                    checkoutBtn.disabled = false;
                    checkoutBtn.textContent = 'Check Out';
                });
        });
    }
});

window.startLocationTracking = startLocationTracking;
window.stopLocationTracking = stopLocationTracking;
window.getCurrentLocationAndUpdate = getCurrentLocationAndUpdate;
