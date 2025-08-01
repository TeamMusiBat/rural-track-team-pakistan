
<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn()) {
    redirect('index.php');
}

if (!isAdmin()) {
    redirect('dashboard.php');
}

$user_id = $_SESSION['user_id'];
$loggedInUserRole = $_SESSION['role'];

// Get app name from settings
$appName = getSettings('app_name', 'SmartORT');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($appName); ?> - Live Tracking</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
        
        .back-btn {
            background: #6366f1;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            background: #4f46e5;
            transform: translateY(-1px);
        }
        
        .map-container {
            position: relative;
            height: calc(100vh - 80px);
            margin: 0;
            background: white;
        }
        
        #map {
            width: 100%;
            height: 100%;
        }
        
        .map-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 1000;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #6366f1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .stats-overlay {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 200px;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .stat-item:last-child {
            margin-bottom: 0;
        }
        
        .stat-label {
            color: #666;
        }
        
        .stat-value {
            font-weight: 600;
            color: #333;
        }
        
        .refresh-btn {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: #6366f1;
            color: white;
            padding: 12px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            transition: all 0.2s;
        }
        
        .refresh-btn:hover {
            background: #4f46e5;
            transform: scale(1.05);
        }
        
        .refresh-btn.spinning {
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-map-marker-alt"></i> Live Employee Tracking</h1>
        <a href="admin.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
    </div>
    
    <div class="map-container">
        <div class="map-loading" id="map-loading">
            <div class="loading-spinner"></div>
            <p style="color: #666;">Loading map and locations...</p>
        </div>
        
        <div id="map"></div>
        
        <div class="stats-overlay">
            <div class="stat-item">
                <span class="stat-label">Total Users:</span>
                <span class="stat-value" id="total-users">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Checked In:</span>
                <span class="stat-value" id="checked-in-users">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Locations:</span>
                <span class="stat-value" id="total-locations">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Updated:</span>
                <span class="stat-value" id="last-updated">--:--</span>
            </div>
        </div>
        
        <button class="refresh-btn" id="refresh-btn" onclick="refreshLocations()">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>

    <script>
        let map;
        let markers = [];
        let isLoading = false;
        
        // Initialize map
        function initMap() {
            // Default center (Pakistan)
            const defaultCenter = { lat: 30.3753, lng: 69.3451 };
            
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 6,
                center: defaultCenter,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                styles: [
                    {
                        featureType: "poi",
                        elementType: "labels",
                        stylers: [{ visibility: "off" }]
                    }
                ]
            });
            
            // Load locations immediately
            loadLocations();
            
            // Auto-refresh every 30 seconds
            setInterval(loadLocations, 30000);
        }
        
        function loadLocations() {
            if (isLoading) return;
            
            isLoading = true;
            const refreshBtn = document.getElementById('refresh-btn');
            refreshBtn.classList.add('spinning');
            
            fetch('get_admin_data.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStats(data.stats);
                        updateMap(data.locations);
                        hideLoading();
                    } else {
                        console.error('Failed to load data:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading locations:', error);
                })
                .finally(() => {
                    isLoading = false;
                    refreshBtn.classList.remove('spinning');
                });
        }
        
        function updateStats(stats) {
            document.getElementById('total-users').textContent = stats.total_users;
            document.getElementById('checked-in-users').textContent = stats.checked_in_users;
            document.getElementById('total-locations').textContent = stats.total_locations;
            document.getElementById('last-updated').textContent = stats.last_updated;
        }
        
        function updateMap(locations) {
            // Clear existing markers
            markers.forEach(marker => marker.setMap(null));
            markers = [];
            
            if (locations.length === 0) {
                return;
            }
            
            const bounds = new google.maps.LatLngBounds();
            
            locations.forEach(location => {
                const position = {
                    lat: parseFloat(location.latitude),
                    lng: parseFloat(location.longitude)
                };
                
                // Create marker
                const marker = new google.maps.Marker({
                    position: position,
                    map: map,
                    title: location.full_name,
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" fill="#6366f1" stroke="white" stroke-width="2"/>
                                <text x="12" y="16" text-anchor="middle" fill="white" font-size="10" font-family="Arial">
                                    ${location.username.charAt(0).toUpperCase()}
                                </text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(32, 32)
                    }
                });
                
                // Create info window
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="max-width: 250px; font-family: Inter, sans-serif;">
                            <h3 style="margin: 0 0 8px 0; color: #333; font-size: 16px;">
                                <i class="fas fa-user" style="color: #6366f1;"></i> 
                                ${location.full_name}
                            </h3>
                            <p style="margin: 4px 0; font-size: 13px; color: #666;">
                                <strong>Position:</strong> ${location.user_role}
                            </p>
                            <p style="margin: 4px 0; font-size: 13px; color: #666;">
                                <strong>Work Duration:</strong> ${location.work_duration}
                            </p>
                            <p style="margin: 4px 0; font-size: 13px; color: #666;">
                                <strong>Location:</strong> ${location.address}
                            </p>
                            <p style="margin: 4px 0; font-size: 13px; color: #666;">
                                <strong>Updated:</strong> ${location.formatted_time}
                            </p>
                        </div>
                    `
                });
                
                marker.addListener('click', () => {
                    // Close all other info windows
                    markers.forEach(m => {
                        if (m.infoWindow) {
                            m.infoWindow.close();
                        }
                    });
                    
                    infoWindow.open(map, marker);
                });
                
                marker.infoWindow = infoWindow;
                markers.push(marker);
                bounds.extend(position);
            });
            
            // Fit map to show all markers
            if (locations.length > 0) {
                map.fitBounds(bounds);
                if (locations.length === 1) {
                    map.setZoom(15); // Zoom closer for single location
                }
            }
        }
        
        function refreshLocations() {
            loadLocations();
        }
        
        function hideLoading() {
            const loading = document.getElementById('map-loading');
            if (loading) {
                loading.style.display = 'none';
            }
        }
        
        // Error handling for Google Maps
        window.gm_authFailure = function() {
            document.getElementById('map').innerHTML = 
                '<div style="display: flex; align-items: center; justify-content: center; height: 100%; background: #f8f9fa; color: #666; text-align: center;">' +
                '<div><i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ffc107; margin-bottom: 15px;"></i>' +
                '<h3>Google Maps API Error</h3><p>Please check your API key configuration.</p></div></div>';
        };
    </script>
    
    <!-- Google Maps API -->
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo getSettings('google_maps_api_key', ''); ?>&callback=initMap"></script>
</body>
</html>
