<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('index.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check device lock for users only (never for masters or developers)
if ($user['role'] === 'user' && checkDeviceLock($user_id)) {
    session_destroy();
    redirect('index.php?error=device_locked');
}

// If master user, redirect to admin panel (no checkin required for masters)
if ($user['role'] === 'master') {
    redirect('admin.php?tab=dashboard');
}

// Check if the user needs to check in based on role
function userNeedsToCheckIn($role) {
    // Masters don't need to check in
    if ($role === 'master') {
        return false;
    }
    
    // Users need to check in
    if ($role === 'user') {
        return true;
    }
    
    // Developers don't need to check in
    return false;
}

$needsToCheckIn = userNeedsToCheckIn($user['role']);

// Check if the user is checked in
$lastCheckin = getLastCheckin($user_id);
$isCheckedIn = !empty($lastCheckin);

// If checked in, calculate duration using Pakistani time
$checkinDuration = '';
if ($isCheckedIn) {
    $checkinTime = new DateTime($lastCheckin['check_in'], new DateTimeZone('Asia/Karachi'));
    $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    
    $totalMinutes = ($now->getTimestamp() - $checkinTime->getTimestamp()) / 60;
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    
    $checkinDuration = sprintf('%d:%02d', $hours, $minutes);
}

// Handle check-in/check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'checkin' && !$isCheckedIn && $needsToCheckIn) {
            // Perform check-in
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, check_in) VALUES (?, NOW())");
            $stmt->execute([$user_id]);
            
            if (!isUserDeveloper($user_id)) {
                logActivity($user_id, 'check_in', 'User checked in');
            }
            
            // Update user location status
            updateUserLocationStatus($user_id, true);
            
            redirect('dashboard.php');
        } 
        else if ($_POST['action'] === 'checkout' && $isCheckedIn) {
            // Calculate duration in minutes
            $checkinTime = new DateTime($lastCheckin['check_in'], new DateTimeZone('Asia/Karachi'));
            $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
            $duration_minutes = ($now->getTimestamp() - $checkinTime->getTimestamp()) / 60;
            
            // Perform check-out
            $stmt = $pdo->prepare("UPDATE attendance SET check_out = NOW(), duration_minutes = ? WHERE id = ?");
            $stmt->execute([$duration_minutes, $lastCheckin['id']]);
            
            if (!isUserDeveloper($user_id)) {
                logActivity($user_id, 'check_out', "User checked out. Duration: $duration_minutes minutes");
            }
            
            // Update user location status
            updateUserLocationStatus($user_id, false);
            
            redirect('dashboard.php');
        }
        else if ($_POST['action'] === 'update_location' && $isCheckedIn) {
            // Update location using FastAPI ONLY
            $latitude = $_POST['latitude'] ?? 0;
            $longitude = $_POST['longitude'] ?? 0;
            
            if ($latitude != 0 && $longitude != 0) {
                // Call FastAPI to update location
                $api_url = $fastapi_base_url . "/update_location/{$user['username']}/{$longitude}_{$latitude}";
                $response = makeApiRequest($api_url, 'POST');
                
                if ($response !== false) {
                    // Update user location status
                    updateUserLocationStatus($user_id, true);
                    
                    // Return success for AJAX calls
                    if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Location updated successfully']);
                        exit;
                    }
                } else {
                    // If FastAPI fails, still update status but log error
                    updateUserLocationStatus($user_id, true);
                    
                    if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Failed to update location']);
                        exit;
                    }
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

// Get app name from settings
$appName = getSettings('app_name', 'SmartOutreach');
$locationUpdateInterval = getSettings('location_update_interval', '300') * 1000; // Convert to milliseconds

// Get current Pakistani time
$currentTime = new DateTime('now', new DateTimeZone('Asia/Karachi'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($appName); ?></title>
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
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
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
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background-color: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #4f46e5;
        }
        
        .info-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .status-active {
            color: #10b981;
        }
        
        .status-inactive {
            color: #f59e0b;
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
            text-decoration: none;
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
        
        .logout-btn {
            background-color: #64748b;
            color: white;
        }
        
        .logout-btn:hover {
            background-color: #475569;
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
        
        .clock {
            font-size: 18px;
            font-weight: 600;
            color: #4f46e5;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 13px;
        }
        
        @media (max-width: 640px) {
            .container {
                padding: 15px 10px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($appName); ?></h1>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="card-title">Dashboard</div>
            </div>
            
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">User</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Position</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['user_role']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <?php if ($isCheckedIn): ?>
                                <span class="status-active"><i class="fas fa-check-circle"></i> Checked In</span>
                            <?php else: ?>
                                <span class="status-inactive"><i class="fas fa-times-circle"></i> Checked Out</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($isCheckedIn): ?>
                    <div class="info-item">
                        <div class="info-label">Work Duration</div>
                        <div class="info-value" id="work-duration"><?php echo $checkinDuration; ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <div class="info-label">Pakistani Time</div>
                        <div class="info-value clock" id="current-time"><?php echo $currentTime->format('h:i:s A'); ?></div>
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
                <?php endif; ?>
                
                <a href="logout.php" class="action-button logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <?php if (isAdmin()): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="card-title">Administrative Tools</div>
            </div>
            
            <div class="card-body" style="padding: 0;">
                <a href="admin.php" class="admin-link">
                    <i class="fas fa-shield-alt"></i>
                    <span>Admin Dashboard</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <?php echo htmlspecialchars($appName); ?> &copy; <?php echo date('Y'); ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const isCheckedIn = <?php echo $isCheckedIn ? 'true' : 'false'; ?>;
            const username = '<?php echo $user['username']; ?>';
            const fastApiBaseUrl = '<?php echo $fastapi_base_url; ?>';
            const updateInterval = <?php echo $locationUpdateInterval; ?>;
            
            // Update Pakistani time clock
            function updateClock() {
                const now = new Date();
                // Convert to Pakistani time
                const pakistaniTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Karachi"}));
                const timeString = pakistaniTime.toLocaleTimeString('en-US', {hour12: true});
                document.getElementById('current-time').textContent = timeString;
            }
            
            // Update work duration
            function updateWorkDuration() {
                if (isCheckedIn) {
                    const durationElement = document.getElementById('work-duration');
                    if (durationElement) {
                        // This would need to be updated via AJAX for real-time updates
                        // For now, it shows the duration at page load
                    }
                }
            }
            
            // Update clock every second
            setInterval(updateClock, 1000);
            updateClock();
            
            // Location tracking for checked-in users using FastAPI ONLY
            if (isCheckedIn) {
                if ('geolocation' in navigator) {
                    // Get location immediately
                    getAndUpdateLocation();
                    
                    // Then update at configured interval
                    setInterval(getAndUpdateLocation, updateInterval);
                }
            }
            
            function getAndUpdateLocation() {
                if (!isCheckedIn) return;
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const latitude = position.coords.latitude;
                        const longitude = position.coords.longitude;
                        
                        // Send location to server (will use FastAPI)
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
                        .then(data => {
                            console.log('Location updated via FastAPI:', data);
                        })
                        .catch(error => {
                            console.error('Error updating location:', error);
                        });
                    },
                    function(error) {
                        console.error('Geolocation error:', error);
                    },
                    { 
                        enableHighAccuracy: true, 
                        timeout: 15000, 
                        maximumAge: 0 
                    }
                );
            }
        });
    </script>
</body>
</html>
