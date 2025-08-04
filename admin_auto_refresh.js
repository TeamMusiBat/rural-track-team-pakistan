
// Enhanced Admin Auto-refresh for checked-in users only

let refreshInterval;
let isRefreshing = false;
let lastRefreshTime = 0;

// Start auto-refresh for admin panel - ONLY CHECKED-IN USERS
function startAdminAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    
    // Refresh every 30 seconds for better real-time updates
    refreshInterval = setInterval(() => {
        if (!isRefreshing && canRefresh()) {
            refreshAdminData();
        }
    }, 30000);
    
    console.log('Admin auto-refresh started (30 seconds interval) - CHECKED-IN USERS ONLY');
    
    // Initial refresh after 2 seconds
    setTimeout(() => {
        refreshAdminData();
    }, 2000);
}

// Rate limiting check
function canRefresh() {
    const now = Date.now();
    return (now - lastRefreshTime) >= 30000; // 30 seconds minimum
}

// Refresh admin data - ONLY CHECKED-IN USERS from FastAPI
function refreshAdminData() {
    if (isRefreshing || !canRefresh()) {
        return;
    }
    
    isRefreshing = true;
    lastRefreshTime = Date.now();
    
    const xhr = new XMLHttpRequest();
    const cacheBuster = `?t=${Date.now()}&checked_in_only=1`;
    
    xhr.open('GET', `get_admin_data.php${cacheBuster}`, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    xhr.setRequestHeader('Pragma', 'no-cache');
    xhr.setRequestHeader('Expires', '0');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-Bypass-Service-Worker', 'true');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            isRefreshing = false;
            
            if (xhr.status === 200) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    
                    if (data.success && data.locations) {
                        // Only process checked-in users
                        const checkedInUsers = data.locations.filter(user => user.is_checked_in || user.check_out === null);
                        
                        updateAdminStats({
                            total_users: data.stats?.total_users || 0,
                            checked_in_users: checkedInUsers.length,
                            total_locations: checkedInUsers.length,
                            current_time: new Date().toLocaleTimeString()
                        });
                        
                        updateUserLocations(checkedInUsers);
                        
                        // Update tracking map if on tracking tab
                        if (window.location.search.includes('tab=tracking')) {
                            updateTrackingMap(checkedInUsers);
                        }
                        
                        updateLastRefreshIndicator(true);
                        console.log(`Admin data refreshed - ${checkedInUsers.length} checked-in users`);
                    } else {
                        throw new Error(data.message || 'No location data received');
                    }
                } catch (error) {
                    console.error('Error parsing admin data:', error);
                    updateLastRefreshIndicator(false, error.message);
                }
            } else {
                updateLastRefreshIndicator(false, `HTTP ${xhr.status}`);
            }
        }
    };
    
    xhr.onerror = function() {
        isRefreshing = false;
        updateLastRefreshIndicator(false, 'Network error');
    };
    
    xhr.send();
}

// Update admin statistics
function updateAdminStats(stats) {
    const elements = {
        totalUsers: document.querySelector('#total-users'),
        checkedInUsers: document.querySelector('#checked-in-users'),
        totalLocations: document.querySelector('#total-locations'),
        currentTime: document.querySelector('#current-time')
    };
    
    if (elements.totalUsers) elements.totalUsers.textContent = stats.total_users;
    if (elements.checkedInUsers) elements.checkedInUsers.textContent = stats.checked_in_users;
    if (elements.totalLocations) elements.totalLocations.textContent = stats.total_locations;
    if (elements.currentTime) elements.currentTime.textContent = stats.current_time;
}

// Update user locations - ONLY CHECKED-IN USERS
function updateUserLocations(locations) {
    const userListElement = document.querySelector('#user-locations-list');
    if (!userListElement) return;
    
    userListElement.innerHTML = '';
    
    if (locations.length === 0) {
        userListElement.innerHTML = '<div class="no-data-message">No checked-in users with location data available</div>';
        return;
    }
    
    locations.forEach(location => {
        const userCard = createUserLocationCard(location);
        userListElement.appendChild(userCard);
    });
}

// Create user location card for checked-in users only
function createUserLocationCard(location) {
    const card = document.createElement('div');
    card.className = 'bg-white border rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow mb-2';
    
    const statusColor = location.is_checked_in ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    const statusIcon = location.is_checked_in ? 'fa-check-circle' : 'fa-times-circle';
    const statusText = location.is_checked_in ? 'Checked In' : 'Checked Out';
    
    card.innerHTML = `
        <div class="flex justify-between items-start mb-2">
            <div>
                <h3 class="font-semibold text-gray-900">${location.full_name || 'Unknown User'}</h3>
                <p class="text-sm text-gray-600">@${location.username}</p>
            </div>
            <span class="px-2 py-1 text-xs rounded-full ${statusColor}">
                <i class="fas ${statusIcon}"></i> ${statusText}
            </span>
        </div>
        <div class="space-y-1 text-sm text-gray-600">
            <p><strong>Role:</strong> ${location.role || location.user_role || 'User'}</p>
            ${location.work_duration ? `<p><strong>Work Duration:</strong> ${location.work_duration}</p>` : ''}
            <p><strong>Location:</strong> ${location.address || 'Location not available'}</p>
            <p><strong>Last Updated:</strong> ${location.formatted_time || new Date().toLocaleTimeString()}</p>
            ${location.latitude && location.longitude ? 
                `<p class="text-xs text-gray-500">Lat: ${parseFloat(location.latitude).toFixed(6)}, Lng: ${parseFloat(location.longitude).toFixed(6)}</p>` 
                : ''
            }
        </div>
        ${location.latitude && location.longitude ? 
            `<div class="mt-3">
                <a href="https://maps.google.com/?q=${location.latitude},${location.longitude}" 
                   target="_blank" 
                   class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                    <i class="fas fa-map-marker-alt mr-1"></i> View on Map
                </a>
            </div>` 
            : ''
        }
    `;
    return card;
}

// Update tracking map with checked-in users only
function updateTrackingMap(locations) {
    const checkedInLocations = locations.filter(loc => loc.is_checked_in && loc.latitude && loc.longitude);
    
    console.log('Updating tracking map with', checkedInLocations.length, 'checked-in user locations');
    
    if (typeof updateMapMarkers === 'function') {
        updateMapMarkers(checkedInLocations);
    }
    if (typeof updateMap === 'function') {
        updateMap(checkedInLocations);
    }
}

// Update last refresh indicator
function updateLastRefreshIndicator(success, errorMessage = '') {
    const indicator = document.querySelector('.last-updated') || createRefreshIndicator();
    const time = new Date().toLocaleTimeString();
    
    if (success) {
        indicator.textContent = `Last updated: ${time} ✓`;
        indicator.style.color = '#4CAF50';
        
        // Flash background
        document.body.style.transition = 'background-color 0.3s ease';
        document.body.style.backgroundColor = '#E8F5E8';
        setTimeout(() => {
            document.body.style.backgroundColor = '';
        }, 1000);
    } else {
        indicator.textContent = `Update failed: ${errorMessage} - Retrying...`;
        indicator.style.color = '#f44336';
    }
}

// Create refresh indicator if it doesn't exist
function createRefreshIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'last-updated';
    indicator.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(255,255,255,0.9);
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        z-index: 1000;
    `;
    document.body.appendChild(indicator);
    return indicator;
}

// Initialize on admin pages
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('admin.php') || 
        document.querySelector('body').classList.contains('admin-page')) {
        
        console.log('Admin page detected, starting auto-refresh for CHECKED-IN USERS ONLY');
        startAdminAutoRefresh();
    }
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});

// Export functions
window.startAdminAutoRefresh = startAdminAutoRefresh;
window.refreshAdminData = refreshAdminData;
