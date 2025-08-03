
// Auto-refresh functionality for admin panel
let refreshInterval;
let isRefreshing = false;

// Light color themes for visual feedback
const lightThemes = [
    { bg: '#E8F5E8', border: '#4CAF50', name: 'Green' },
    { bg: '#E3F2FD', border: '#2196F3', name: 'Blue' },
    { bg: '#FFF3E0', border: '#FF9800', name: 'Orange' },
    { bg: '#F3E5F5', border: '#9C27B0', name: 'Purple' },
    { bg: '#E0F2F1', border: '#009688', name: 'Teal' },
    { bg: '#FFF8E1', border: '#FFC107', name: 'Amber' },
    { bg: '#FCE4EC', border: '#E91E63', name: 'Pink' },
    { bg: '#E8F5E8', border: '#8BC34A', name: 'Lime' }
];

// Start auto-refresh for admin panel every 59 seconds
function startAdminAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    
    // Refresh every 59 seconds to respect hosting limits
    refreshInterval = setInterval(() => {
        if (!isRefreshing) {
            refreshAdminData();
        }
    }, 59000);
    
    console.log('Admin auto-refresh started (59 seconds interval)');
    
    // Initial refresh after 2 seconds
    setTimeout(() => {
        refreshAdminData();
    }, 2000);
}

// Stop auto-refresh
function stopAdminAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
        console.log('Admin auto-refresh stopped');
    }
}

// Refresh admin data from FastAPI endpoints with enhanced error handling
async function refreshAdminData() {
    if (isRefreshing) {
        console.log('Admin refresh already in progress, skipping');
        return;
    }
    
    isRefreshing = true;
    const startTime = Date.now();
    
    try {
        // Add cache busting to prevent stale data
        const cacheBuster = `?t=${Date.now()}`;
        const response = await fetch(`get_admin_data.php${cacheBuster}`, {
            method: 'GET',
            headers: { 
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        if (data.success) {
            updateAdminStats(data.stats);
            updateUserLocations(data.locations);
            
            // Only update tracking map if on tracking tab
            if (window.location.search.includes('tab=tracking')) {
                updateTrackingMap(data.locations);
            }
            
            // Update status indicator
            const lastUpdatedElement = document.querySelector('.last-updated');
            if (lastUpdatedElement) {
                const updateTime = new Date().toLocaleTimeString();
                const refreshDuration = Date.now() - startTime;
                lastUpdatedElement.textContent = `Last updated: ${updateTime} (${refreshDuration}ms)`;
                lastUpdatedElement.style.color = '#4CAF50';
                
                // Reset color after animation
                setTimeout(() => {
                    lastUpdatedElement.style.color = '';
                }, 3000);
            }
            
            // Apply visual feedback
            applyRandomColorTheme();
            
            console.log(`Admin data refreshed successfully in ${refreshDuration}ms - ${data.locations.length} checked-in users loaded from FastAPI`);
        } else {
            throw new Error(data.message || 'Unknown error from server');
        }
    } catch (error) {
        console.error('Error refreshing admin data:', error);
        
        const lastUpdatedElement = document.querySelector('.last-updated');
        if (lastUpdatedElement) {
            lastUpdatedElement.textContent = `Update failed: ${error.message} - Retrying in 59s...`;
            lastUpdatedElement.style.color = '#f44336';
            
            // Flash background to indicate error
            document.body.style.transition = 'background-color 0.3s ease';
            document.body.style.backgroundColor = '#ffebee';
            setTimeout(() => {
                document.body.style.backgroundColor = '';
            }, 1000);
        }
    } finally {
        isRefreshing = false;
    }
}

// Update admin statistics
function updateAdminStats(stats) {
    const totalUsersElement = document.querySelector('#total-users');
    const checkedInUsersElement = document.querySelector('#checked-in-users');
    const totalLocationsElement = document.querySelector('#total-locations');
    const currentTimeElement = document.querySelector('#current-time');
    
    if (totalUsersElement) totalUsersElement.textContent = stats.total_users;
    if (checkedInUsersElement) checkedInUsersElement.textContent = stats.checked_in_users;
    if (totalLocationsElement) totalLocationsElement.textContent = stats.total_locations;
    if (currentTimeElement) currentTimeElement.textContent = stats.current_time;
}

// Update user locations in the admin panel
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
    
    console.log(`Updated ${locations.length} user location cards`);
}

// Create user location card with enhanced styling
function createUserLocationCard(location) {
    const card = document.createElement('div');
    card.className = 'bg-white border rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow';
    card.innerHTML = `
        <div class="flex justify-between items-start mb-2">
            <div>
                <h3 class="font-semibold text-gray-900">${location.full_name}</h3>
                <p class="text-sm text-gray-600">@${location.username}</p>
            </div>
            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                <i class="fas fa-check-circle"></i> Checked In
            </span>
        </div>
        <div class="space-y-1 text-sm text-gray-600">
            <p><strong>Role:</strong> ${location.user_role || location.role}</p>
            ${location.work_duration ? `<p><strong>Work Duration:</strong> ${location.work_duration}</p>` : ''}
            <p><strong>Location:</strong> ${location.address}</p>
            <p><strong>Last Updated:</strong> ${location.formatted_time}</p>
            <p class="text-xs text-gray-500">Lat: ${location.latitude.toFixed(6)}, Lng: ${location.longitude.toFixed(6)}</p>
        </div>
        <div class="mt-3 flex space-x-2">
            <a href="https://maps.google.com/?q=${location.latitude},${location.longitude}" 
               target="_blank" 
               class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                <i class="fas fa-map-marker-alt mr-1"></i> View in Google Maps
            </a>
        </div>
    `;
    return card;
}

// Update tracking map with FastAPI data
function updateTrackingMap(locations) {
    console.log('Updating tracking map with', locations.length, 'checked-in user locations from FastAPI');
    if (typeof updateMapMarkers === 'function') {
        updateMapMarkers(locations);
    }
    if (typeof updateMap === 'function') {
        updateMap(locations);
    }
}

// Apply random color theme to dashboard (for user feedback)
function applyRandomColorTheme() {
    const randomTheme = lightThemes[Math.floor(Math.random() * lightThemes.length)];
    const dashboardElement = document.body;
    if (dashboardElement) {
        dashboardElement.style.transition = 'background-color 0.3s ease';
        dashboardElement.style.backgroundColor = randomTheme.bg;
        setTimeout(() => {
            dashboardElement.style.backgroundColor = '';
        }, 2000);
        console.log(`Applied ${randomTheme.name} theme for admin refresh feedback`);
    }
}

// Initialize auto-refresh when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Only start on admin pages
    if (window.location.pathname.includes('admin.php') || 
        window.location.pathname.includes('admin_tracking_fixes.php') ||
        document.querySelector('body').classList.contains('admin-page')) {
        
        // Start auto-refresh immediately
        console.log('Admin page detected, starting auto-refresh');
        startAdminAutoRefresh();
        
        console.log('Admin panel FastAPI auto-refresh initialized (59 seconds interval)');
        
        // Create status indicator
        const statusIndicator = document.createElement('div');
        statusIndicator.className = 'refresh-status';
        statusIndicator.innerHTML = `
            <div class="last-updated" style="position: fixed; bottom: 20px; left: 20px; background: rgba(255,255,255,0.9); padding: 8px 12px; border-radius: 6px; font-size: 12px; box-shadow: 0 2px 8px; z-index: 1000;">
                Connecting to FastAPI...
            </div>
        `;
        document.body.appendChild(statusIndicator);
    }
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    stopAdminAutoRefresh();
});

// Make functions available globally
window.startAdminAutoRefresh = startAdminAutoRefresh;
window.stopAdminAutoRefresh = stopAdminAutoRefresh;
window.refreshAdminData = refreshAdminData;
window.applyRandomColorTheme = applyRandomColorTheme;
