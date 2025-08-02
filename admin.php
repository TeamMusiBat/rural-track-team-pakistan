<?php
require_once 'config.php';
require_once 'location_utils.php';
require_once 'admin_handlers.php';
require_once 'admin_data.php';
require_once 'admin_views.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

// Set the default tab from URL or default to dashboard
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Handle all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleAutoCheckoutUpdate($pdo);
    handleMasterCheckinUpdate($pdo);
    handleDeleteUser($pdo);
    handleResetLogs($pdo);
    handleAdminActions($pdo);
}

// Get all data needed for admin panel
$adminData = getAdminData($pdo);
$settings = getAdminSettings();
$messages = getAdminMessages();

// Extract data for easy access
extract($adminData);
$message = $messages['message'];
$error = $messages['error'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartOutreach - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: #f8fafc;
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background-color: #4f46e5;
            color: white;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 22px;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            background-color: rgba(255,255,255,0.2);
            border-radius: 8px;
            transition: background-color 0.2s;
        }
        
        .back-link:hover {
            background-color: rgba(255,255,255,0.3);
        }
        
        .back-link i {
            margin-right: 6px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }
        
        .message {
            padding: 12px 16px;
            margin-bottom: 24px;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .message i {
            margin-right: 12px;
            font-size: 16px;
        }
        
        .message-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .message-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .tabs {
            display: flex;
            overflow-x: auto;
            background-color: #f8fafc;
            border-bottom: 1px solid #eee;
        }
        
        .tab {
            padding: 16px 20px;
            cursor: pointer;
            font-weight: 500;
            color: #64748b;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .tab i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        .tab:hover {
            color: #4f46e5;
        }
        
        .tab.active {
            color: #4f46e5;
            border-bottom-color: #4f46e5;
            background-color: rgba(79, 70, 229, 0.05);
        }
        
        .tab-content {
            display: none;
            padding: 24px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: #4f46e5;
        }
        
        .section-note {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 16px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            font-weight: 600;
            color: #334155;
            background-color: #f8fafc;
        }
        
        tr:hover {
            background-color: #f8fafc;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn i {
            margin-right: 6px;
        }
        
        .btn:hover {
            background-color: #4338ca;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn-danger {
            background-color: #ef4444;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .btn-success {
            background-color: #10b981;
        }
        
        .btn-success:hover {
            background-color: #059669;
        }
        
        .btn-warning {
            background-color: #f59e0b;
        }
        
        .btn-warning:hover {
            background-color: #d97706;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #4f46e5;
            color: #4f46e5;
        }
        
        .btn-outline:hover {
            background-color: #f0f4ff;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #334155;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="password"],
        input[type="email"],
        input[type="number"],
        input[type="time"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background-color: #f8fafc;
            transition: all 0.2s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        input[type="number"]:focus,
        input[type="time"]:focus,
        select:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
            background-color: #fff;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #10b981;
        }
        
        input:focus + .toggle-slider {
            box-shadow: 0 0 1px #10b981;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        #map {
            height: 500px;
            width: 100%;
            border-radius: 8px;
            margin-bottom: 24px;
            border: 1px solid #ddd;
        }
        
        .user-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #4f46e5;
            color: white;
            font-size: 16px;
            border: 2px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .user-popup {
            min-width: 200px;
        }
        
        .user-popup-header {
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
        }
        
        .user-popup-info {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        
        .settings-card {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #eee;
        }
        
        .settings-title {
            font-weight: 600;
            margin-bottom: 12px;
            color: #333;
            font-size: 16px;
            display: flex;
            align-items: center;
        }
        
        .settings-title i {
            margin-right: 8px;
            color: #4f46e5;
        }
        
        .settings-description {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 16px;
            line-height: 1.5;
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .toggle-text {
            font-weight: 500;
            font-size: 14px;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #dcfce7;
            color: #16a34a;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #d97706;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #dc2626;
        }
        
        .badge i {
            margin-right: 4px;
            font-size: 10px;
        }
        
        .location-status {
            display: flex;
            align-items: center;
            font-size: 13px;
        }
        
        .location-status i {
            margin-right: 6px;
        }
        
        .status-enabled {
            color: #16a34a;
        }
        
        .status-disabled {
            color: #dc2626;
        }
        
        .user-card {
            display: flex;
            flex-direction: column;
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #eee;
        }
        
        .user-card-header {
            padding: 15px;
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        
        .user-card-body {
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .user-card-footer {
            padding: 10px 15px;
            background-color: #f8fafc;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
        }
        
        .user-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #64748b;
        }
        
        .user-card-row strong {
            color: #334155;
        }
        
        .user-card-location {
            font-size: 13px;
            color: #64748b;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #eee;
            line-height: 1.4;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
            
            .tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            
            .tab {
                padding: 14px 16px;
                font-size: 14px;
            }
            
            .tab-content {
                padding: 16px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            #map {
                height: 350px;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
        
        /* Responsive form fields */
        @media (max-width: 480px) {
            th, td {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .btn-small {
                padding: 4px 8px;
                font-size: 12px;
            }
        }
        
        /* User Card Grid */
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        
        @media (max-width: 640px) {
            .user-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-shield-alt"></i> Admin Dashboard</h1>
        <div class="header-actions">
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="tabs">
                <a href="?tab=dashboard" class="tab <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="?tab=users" class="tab <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="?tab=tracking" class="tab <?php echo $activeTab === 'tracking' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marked-alt"></i> Location Tracking
                </a>
                <a href="?tab=activity" class="tab <?php echo $activeTab === 'activity' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Activity Logs
                </a>
                <a href="?tab=attendance" class="tab <?php echo $activeTab === 'attendance' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Attendance
                </a>
                <a href="?tab=settings" class="tab <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
            
            <!-- Dashboard Tab -->
            <div class="tab-content <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>" id="dashboard-tab">
                <?php echo renderDashboardTab($users, $activeUsers, $pdo); ?>
            </div>
            
            <!-- Manage Users Tab -->
            <div class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>" id="users-tab">
                <?php echo renderUsersTab($users); ?>
            </div>
            
            <!-- Location Tracking Tab -->
            <div class="tab-content <?php echo $activeTab === 'tracking' ? 'active' : ''; ?>" id="tracking-tab">
                <?php echo renderTrackingTab($locations); ?>
            </div>
            
            <!-- Activity Logs Tab -->
            <div class="tab-content <?php echo $activeTab === 'activity' ? 'active' : ''; ?>" id="activity-tab">
                <?php echo renderActivityTab($logs); ?>
            </div>
            
            <!-- Attendance Tab -->
            <div class="tab-content <?php echo $activeTab === 'attendance' ? 'active' : ''; ?>" id="attendance-tab">
                <?php echo renderAttendanceTab($attendance); ?>
            </div>
            
            <!-- Settings Tab -->
            <div class="tab-content <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" id="settings-tab">
                <?php echo renderSettingsTab($settings); ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map for location tracking
            let map = null;
            let markers = [];
            
            <?php if ($activeTab === 'tracking'): ?>
            initializeMap();
            <?php endif; ?>
            
            // Auto-refresh dashboard data
            <?php if ($activeTab === 'dashboard'): ?>
            setInterval(updateDashboardData, 10000);  // Update every 10 seconds
            <?php endif; ?>
            
            // Function to update dashboard data without page reload
            function updateDashboardData() {
                // Fetch latest user locations and statuses
                fetch('get_locations.php')
                    .then(response => response.json())
                    .then(locations => {
                        // Update Google Maps links for each user
                        locations.forEach(location => {
                            const mapLinkElement = document.getElementById(`map-link-user-${location.user_id}`);
                            const activeMapLinkElement = document.getElementById(`active-map-link-user-${location.user_id}`);
                            
                            if (mapLinkElement) {
                                mapLinkElement.href = `https://www.google.com/maps?q=${location.latitude},${location.longitude}`;
                            }
                            
                            if (activeMapLinkElement) {
                                activeMapLinkElement.href = `https://www.google.com/maps?q=${location.latitude},${location.longitude}`;
                            }
                        });
                    })
                    .catch(error => console.error('Error updating dashboard data:', error));
            }
            
            // Function to initialize map
            function initializeMap() {
                if (document.getElementById('map')) {
                    // Create a map centered on Pakistan
                    map = L.map('map').setView([30.3753, 69.3451], 5);
                    
                    // Add OpenStreetMap tiles
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(map);
                    
                    // Add markers for each active user
                    addUserMarkers();
                    
                    // Set up refresh interval (every 15 seconds)
                    setInterval(function() {
                        fetch('get_locations.php')
                            .then(response => response.json())
                            .then(data => {
                                // Update markers with new data
                                updateMarkers(data);
                            })
                            .catch(error => console.error('Error fetching locations:', error));
                    }, 15000);
                }
            }
            
            // Function to add user markers to the map
            function addUserMarkers() {
                // Clear existing markers
                if (markers.length > 0) {
                    markers.forEach(marker => map.removeLayer(marker));
                    markers = [];
                }
                
                // Get location data
                <?php
                echo "const locationData = " . json_encode($locations) . ";\n";
                echo "const activeUsers = " . json_encode($activeUsers) . ";\n";
                ?>
                
                // Function to check if user is active
                function isUserActive(userId) {
                    return activeUsers.some(user => user.id == userId);
                }
                
                // Add markers for users (excluding developers)
                locationData.forEach(location => {
                    // Skip developers
                    if (location.role === 'developer') return;
                    
                    const isActive = isUserActive(location.user_id);
                    const markerColor = isActive ? '#10b981' : '#64748b';
                    
                    // Create a custom marker icon with user's initials
                    const fullName = location.full_name || 'User';
                    const initials = fullName.split(' ').map(n => n[0]).join('').toUpperCase();
                    
                    const markerHtml = `
                        <div class="user-marker" style="background-color: ${markerColor};">
                            ${initials}
                        </div>
                    `;
                    
                    // Custom marker icon
                    const markerIcon = L.divIcon({
                        className: '', // Remove default class
                        html: markerHtml,
                        iconSize: [36, 36],
                        iconAnchor: [18, 18]
                    });
                    
                    // Create marker
                    const marker = L.marker([location.latitude, location.longitude], {
                        icon: markerIcon,
                        title: fullName // Shows full name on hover
                    }).addTo(map);
                    
                    // Format timestamp to local time
                    const timestamp = new Date(location.timestamp);
                    const formattedTime = timestamp.toLocaleString('en-US', { 
                        hour: 'numeric', 
                        minute: 'numeric',
                        hour12: true,
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric',
                        timeZone: 'Asia/Karachi'
                    });
                    
                    // Add popup with user info
                    marker.bindPopup(`
                        <div class="user-popup">
                            <div class="user-popup-header">${location.full_name}</div>
                            <div class="user-popup-info">Role: ${location.user_role || 'Research Specialist'}</div>
                            <div class="user-popup-info">Status: ${isActive ? '<span style="color: #10b981; font-weight: 500;">Active</span>' : '<span style="color: #64748b;">Inactive</span>'}</div>
                            <div class="user-popup-info">Location: ${location.address || 'Unknown location'}</div>
                            <div class="user-popup-info">Last updated: ${formattedTime}</div>
                            <div class="user-popup-info">Coordinates: ${parseFloat(location.latitude).toFixed(6)}, ${parseFloat(location.longitude).toFixed(6)}</div>
                            <div style="margin-top: 10px;">
                                <a href="https://www.google.com/maps?q=${location.latitude},${location.longitude}" 
                                   class="btn btn-small" 
                                   target="_blank"
                                   style="font-size: 12px; padding: 4px 8px;">
                                    <i class="fas fa-external-link-alt"></i> Open in Google Maps
                                </a>
                            </div>
                        </div>
                    `);
                    
                    // Store marker for later reference
                    markers.push(marker);
                });
                
                // If we have markers, fit map to show all markers
                if (markers.length > 0) {
                    const group = new L.featureGroup(markers);
                    map.fitBounds(group.getBounds().pad(0.2));
                }
            }
            
            // Function to update markers with new data
            function updateMarkers(locationData) {
                // Clear existing markers
                if (markers.length > 0) {
                    markers.forEach(marker => map.removeLayer(marker));
                    markers = [];
                }
                
                // Add new markers
                locationData.forEach(location => {
                    // Skip developers
                    if (location.role === 'developer') return;
                    
                    const isActive = location.is_checked_in;
                    const markerColor = isActive ? '#10b981' : '#64748b';
                    
                    // Create a custom marker icon with user's initials
                    const fullName = location.full_name || 'User';
                    const initials = fullName.split(' ').map(n => n[0]).join('').toUpperCase();
                    
                    const markerHtml = `
                        <div class="user-marker" style="background-color: ${markerColor};">
                            ${initials}
                        </div>
                    `;
                    
                    // Custom marker icon
                    const markerIcon = L.divIcon({
                        className: '', // Remove default class
                        html: markerHtml,
                        iconSize: [36, 36],
                        iconAnchor: [18, 18]
                    });
                    
                    // Create marker
                    const marker = L.marker([location.latitude, location.longitude], {
                        icon: markerIcon,
                        title: fullName // Shows name on hover
                    }).addTo(map);
                    
                    // Add popup with user info
                    marker.bindPopup(`
                        <div class="user-popup">
                            <div class="user-popup-header">${location.full_name}</div>
                            <div class="user-popup-info">Role: ${location.user_role || 'Research Specialist'}</div>
                            <div class="user-popup-info">Status: ${location.is_checked_in ? '<span style="color: #10b981; font-weight: 500;">Active</span>' : '<span style="color: #64748b;">Inactive</span>'}</div>
                            <div class="user-popup-info">Location: ${location.address || 'Unknown location'}</div>
                            <div class="user-popup-info">Last updated: ${location.formatted_time}</div>
                            <div class="user-popup-info">Coordinates: ${parseFloat(location.latitude).toFixed(6)}, ${parseFloat(location.longitude).toFixed(6)}</div>
                            <div style="margin-top: 10px;">
                                <a href="https://www.google.com/maps?q=${location.latitude},${location.longitude}" 
                                   class="btn btn-small" 
                                   target="_blank"
                                   style="font-size: 12px; padding: 4px 8px;">
                                    <i class="fas fa-external-link-alt"></i> Open in Google Maps
                                </a>
                            </div>
                        </div>
                    `);
                    
                    // Store marker for later reference
                    markers.push(marker);
                });
                
                // If we have markers, fit map to show all markers
                if (markers.length > 0) {
                    const group = new L.featureGroup(markers);
                    map.fitBounds(group.getBounds().pad(0.2));
                }
            }
        });
    </script>
</body>
</html>
