
<?php
require_once 'config.php';
require_once 'location_utils.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('index.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// If master user and checkin not required, redirect to admin panel
if ($user['role'] === 'master' && getSettings('master_checkin_required', '0') == '0') {
    redirect('admin.php?tab=dashboard');
}

// Check if the user needs to check in based on role
$needsToCheckIn = userNeedsToCheckIn($user['role']);

// Check if the user is checked in
$lastCheckin = getLastCheckin($user_id);
$isCheckedIn = !empty($lastCheckin);

// If checked in, calculate duration accurately (Pakistan time)
$checkinDuration = '';
if ($isCheckedIn) {
    // Make sure to use the Pakistan timezone for all time calculations
    $checkin_time = new DateTime($lastCheckin['check_in'], new DateTimeZone('Asia/Karachi'));
    $current_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    $interval = $current_time->diff($checkin_time);
    
    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;
    
    $checkinDuration = sprintf('%d:%02d', $hours, $minutes);
}

// Handle check-in/check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'checkin' && !$isCheckedIn && $needsToCheckIn) {
            // Perform check-in with explicit Pakistan time
            $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
            $timestamp = $now->format('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, check_in) VALUES (?, ?)");
            $stmt->execute([$user_id, $timestamp]);
            
            if (!isUserDeveloper($user_id)) {
                logActivity($user_id, 'check_in', 'User checked in');
            }
            
            // Update user location status
            updateUserLocationStatus($user_id, true);
            
            // Redirect to refresh page
            redirect('dashboard.php');
        } 
        else if ($_POST['action'] === 'checkout' && $isCheckedIn) {
            // Calculate accurate duration in minutes (Pakistan time)
            $checkin_time = new DateTime($lastCheckin['check_in'], new DateTimeZone('Asia/Karachi'));
            $checkout_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
            $interval = $checkout_time->diff($checkin_time);
            
            $hours = $interval->h + ($interval->days * 24);
            $minutes = $interval->i;
            $duration_minutes = ($hours * 60) + $minutes;
            
            // Perform check-out with explicit Pakistan time
            $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
            $timestamp = $now->format('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("UPDATE attendance SET check_out = ?, duration_minutes = ? WHERE id = ?");
            $stmt->execute([$timestamp, $duration_minutes, $lastCheckin['id']]);
            
            if (!isUserDeveloper($user_id)) {
                logActivity($user_id, 'check_out', "User checked out. Duration: $duration_minutes minutes");
            }
            
            // Update user location status
            updateUserLocationStatus($user_id, false);
            
            // Redirect to refresh page
            redirect('dashboard.php');
        }
        else if ($_POST['action'] === 'update_location' && $isCheckedIn) {
            // Update location
            $latitude = $_POST['latitude'] ?? 0;
            $longitude = $_POST['longitude'] ?? 0;
            
            if ($latitude != 0 && $longitude != 0) {
                saveLocationWithAddress($user_id, $latitude, $longitude);
                
                // Update user location status
                updateUserLocationStatus($user_id, true);
                
                // Return success for AJAX calls
                if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Location updated successfully']);
                    exit;
                }
            } else {
                // Return error for AJAX calls
                if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Invalid location data']);
                    exit;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartOutreach</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        
        @media (min-width: 640px) {
            .container {
                max-width: 800px;
            }
        }
        
        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }
        
        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #eef1f5;
            display: flex;
            align-items: center;
        }
        
        .card-header-icon {
            background-color: #eef3ff;
            color: #4f46e5;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 16px;
        }
        
        .card-title {
            font-size: 17px;
            font-weight: 600;
            color: #333;
        }
        
        .card-body {
            padding: 24px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
            font-size: 24px;
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .user-details {
            flex-grow: 1;
        }
        
        .user-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #333;
        }
        
        .user-role {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .status-info {
            margin-bottom: 24px;
        }
        
        .status-label {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .status-value {
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }
        
        .status-active {
            color: #10b981;
            font-weight: 600;
        }
        
        .status-inactive {
            color: #f59e0b;
            font-weight: 600;
        }
        
        .action-button {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            border: none;
            transition: all 0.2s;
            margin-bottom: 12px;
        }
        
        .check-in {
            background-color: #10b981;
            color: white;
        }
        
        .check-in:hover {
            background-color: #059669;
        }
        
        .check-out {
            background-color: #ef4444;
            color: white;
        }
        
        .check-out:hover {
            background-color: #dc2626;
        }
        
        .admin-card .card-body {
            padding: 0;
        }
        
        .admin-link {
            display: block;
            padding: 18px 24px;
            text-decoration: none;
            color: #333;
            transition: background-color 0.2s;
            border-top: 1px solid #eef1f5;
        }
        
        .admin-link:first-child {
            border-top: none;
        }
        
        .admin-link:hover {
            background-color: #f8fafc;
        }
        
        .admin-link i {
            color: #4f46e5;
            margin-right: 12px;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        .admin-link-title {
            font-weight: 500;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 13px;
            margin-top: 20px;
        }
        
        .offline-banner {
            background-color: #f97316;
            color: white;
            padding: 12px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .offline-banner i {
            margin-right: 8px;
        }
        
        .permission-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.7);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .permission-modal-content {
            background-color: white;
            border-radius: 12px;
            padding: 24px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .permission-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
        }
        
        .permission-text {
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 20px;
            color: #4b5563;
        }
        
        .permission-buttons {
            display: flex;
            gap: 12px;
        }
        
        .permission-button {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        
        .permission-allow {
            background-color: #10b981;
            color: white;
        }
        
        .permission-deny {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        
        .location-warning {
            background-color: #fef2f2;
            border: 1px solid #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            font-size: 14px;
        }
        
        .location-warning i {
            margin-right: 8px;
            font-size: 16px;
        }
        
        /* Additional responsive styles */
        @media (max-width: 640px) {
            .container {
                padding: 15px 10px;
            }
            
            .card-header {
                padding: 16px 16px;
            }
            
            .card-body {
                padding: 16px;
            }
            
            .user-name {
                font-size: 18px;
            }
            
            .user-avatar {
                width: 50px;
                height: 50px;
                font-size: 18px;
                margin-right: 15px;
            }
            
            .action-button {
                padding: 12px;
                font-size: 15px;
            }
            
            .admin-link {
                padding: 15px 16px;
            }
            
            .footer {
                padding: 15px 10px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div id="offline-banner" class="offline-banner" style="display: none">
        <i class="fas fa-wifi-slash"></i> You are currently offline. Limited functionality available.
    </div>
    
    <div id="permission-modal" class="permission-modal" style="display: none;">
        <div class="permission-modal-content">
            <div class="permission-title">Enable Location Services</div>
            <div class="permission-text">
                SmartOutreach needs access to your location to track your work activities. Please click "Allow" when prompted by your browser.
            </div>
            <div class="permission-buttons">
                <button class="permission-button permission-allow" onclick="requestLocationPermission()">Allow</button>
                <button class="permission-button permission-deny" onclick="closePermissionModal()">Cancel</button>
            </div>
        </div>
    </div>
    
    <div class="header">
        <h1>SmartOutreach</h1>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="card-title">Your Profile</div>
            </div>
            
            <div class="card-body">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php 
                            $initials = "";
                            $names = explode(" ", $user['full_name']);
                            foreach ($names as $name) {
                                if (!empty($name)) {
                                    $initials .= strtoupper(substr($name, 0, 1));
                                    if (strlen($initials) >= 2) break;
                                }
                            }
                            echo $initials;
                        ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div class="user-role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?><?php echo !empty($user['user_role']) ? ' - ' . htmlspecialchars($user['user_role']) : ''; ?></div>
                    </div>
                </div>
                
                <div class="status-info">
                    <div class="status-label">Current Status</div>
                    <div class="status-value">
                        <?php if ($isCheckedIn): ?>
                            <span class="status-active"><i class="fas fa-check-circle"></i> Checked In</span>
                            <div style="margin-top: 8px; font-size: 14px; color: #64748b;">
                                Active Time: <?php echo $checkinDuration; ?>
                            </div>
                        <?php else: ?>
                            <span class="status-inactive"><i class="fas fa-times-circle"></i> Checked Out</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($needsToCheckIn): ?>
                    <?php if (!$isCheckedIn): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="checkin">
                        <button type="submit" class="action-button check-in">
                            <i class="fas fa-sign-in-alt"></i> Check In
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="checkout">
                        <button type="submit" class="action-button check-out">
                            <i class="fas fa-sign-out-alt"></i> Check Out
                        </button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; color: #64748b; font-size: 14px; margin: 20px 0;">
                        <i class="fas fa-info-circle"></i> Master users don't need to check in/out
                    </div>
                <?php endif; ?>
                
                <div id="location-status" style="display: none;"></div>
                
                <form method="GET" action="logout.php" style="margin-top: 20px;">
                    <button type="submit" class="action-button" style="background-color: #64748b;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>
        
        <?php if (isAdmin()): ?>
        <div class="card admin-card">
            <div class="card-header">
                <div class="card-header-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="card-title">Administrative Tools</div>
            </div>
            
            <div class="card-body">
                <a href="admin.php" class="admin-link">
                    <i class="fas fa-shield-alt"></i>
                    <span class="admin-link-title">View Admin Dashboard</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        SmartOutreach Tracking System &copy; <?php echo date('Y'); ?> | Pakistan Standard Time: <?php echo date('h:i A'); ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const isCheckedIn = <?php echo $isCheckedIn ? 'true' : 'false'; ?>;
            const offlineBanner = document.getElementById('offline-banner');
            const permissionModal = document.getElementById('permission-modal');
            const locationStatus = document.getElementById('location-status');
            let locationAttempts = 0;
            let refreshAttempts = 0;
            
            // Check online/offline status
            function updateOnlineStatus() {
                if (navigator.onLine) {
                    offlineBanner.style.display = 'none';
                } else {
                    offlineBanner.style.display = 'flex';
                }
            }
            
            window.addEventListener('online', updateOnlineStatus);
            window.addEventListener('offline', updateOnlineStatus);
            updateOnlineStatus();
            
            // Handle location tracking
            if (isCheckedIn) {
                // Check if geolocation is available
                if ('geolocation' in navigator) {
                    // Check current permission status
                    navigator.permissions.query({ name: 'geolocation' }).then(function(permissionStatus) {
                        if (permissionStatus.state === 'granted') {
                            // Permission already granted, start tracking
                            startLocationTracking();
                        } else if (permissionStatus.state === 'prompt') {
                            // Show our custom permission modal
                            permissionModal.style.display = 'flex';
                        } else if (permissionStatus.state === 'denied') {
                            // Permission denied - show modal to explain why we need it
                            permissionModal.style.display = 'flex';
                        }
                        
                        // Listen for permission changes
                        permissionStatus.onchange = function() {
                            if (this.state === 'granted') {
                                permissionModal.style.display = 'none';
                                startLocationTracking();
                            }
                        };
                    });
                }
            }
            
            // Function to request location permission
            window.requestLocationPermission = function() {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        permissionModal.style.display = 'none';
                        startLocationTracking();
                    },
                    function(error) {
                        permissionModal.style.display = 'none';
                        // Show warning but don't display details to user per requirements
                        showLocationWarning();
                    },
                    { 
                        enableHighAccuracy: true, 
                        timeout: 10000, 
                        maximumAge: 0 
                    }
                );
            };
            
            // Function to show location warning
            function showLocationWarning() {
                // For security reasons, don't show specific error details to user
                // Just show a warning popup instead
                alert("Location services are required for accurate tracking while checked in. Please enable location services in your browser settings.");
            }
            
            // Function to close permission modal
            window.closePermissionModal = function() {
                permissionModal.style.display = 'none';
                showLocationWarning();
            };
            
            // Function to start tracking location
            function startLocationTracking() {
                // Get location immediately
                getAndUpdateLocation();
                
                // Then update every 60 seconds (1 minute)
                setInterval(getAndUpdateLocation, 60000);
            }
            
            // Function to get and update location
            function getAndUpdateLocation() {
                if (!isCheckedIn) return;
                
                if ('geolocation' in navigator) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            // Reset counters on success
                            locationAttempts = 0;
                            refreshAttempts = 0;
                            
                            const latitude = position.coords.latitude;
                            const longitude = position.coords.longitude;
                            
                            // Send location to server
                            const formData = new FormData();
                            formData.append('action', 'update_location');
                            formData.append('latitude', latitude);
                            formData.append('longitude', longitude);
                            formData.append('ajax', '1');
                            
                            fetch('dashboard.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .catch(error => {
                                console.error('Error sending location:', error);
                            });
                        },
                        function(error) {
                            // Handle error
                            locationAttempts++;
                            
                            if (error.code === error.TIMEOUT) {
                                console.error('Geolocation error: Timeout');
                                
                                // If we've had multiple failures, try refreshing page (limited to 2-3 times)
                                if (locationAttempts >= 3 && refreshAttempts < 2) {
                                    refreshAttempts++;
                                    console.log(`Refreshing page due to location timeout. Attempt ${refreshAttempts}`);
                                    window.location.reload();
                                } else {
                                    // Try again in 1 minute
                                    setTimeout(getAndUpdateLocation, 60000);
                                }
                            } else if (error.code === error.PERMISSION_DENIED) {
                                // Show the permission modal again
                                permissionModal.style.display = 'flex';
                            }
                        },
                        { 
                            enableHighAccuracy: true, 
                            timeout: 15000, 
                            maximumAge: 0 
                        }
                    );
                }
            }
        });
    </script>
</body>
</html>
