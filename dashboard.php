
<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        redirect('index.php');
    }

    // Get user data
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        redirect('index.php?error=user_not_found');
    }

    // Check device lock ONLY for users with role 'user' (NEVER for masters or developers)
    if ($user['role'] === 'user' && checkDeviceLock($user_id)) {
        session_destroy();
        redirect('index.php?error=device_locked');
    }

    // If master user, redirect to admin panel (masters don't need checkin)
    if ($user['role'] === 'master') {
        redirect('admin.php?tab=dashboard');
    }

    // Function to determine if user needs to check in
    function userNeedsToCheckIn($role) {
        // Masters and developers NEVER need to check in
        if ($role === 'master' || $role === 'developer') {
            return false;
        }
        
        // Regular users need to check in
        if ($role === 'user') {
            return true;
        }
        
        return false;
    }

    $needsToCheckIn = userNeedsToCheckIn($user['role']);

    // Check if the user is checked in
    $lastCheckin = getLastCheckin($user_id);
    $isCheckedIn = !empty($lastCheckin);

    // Calculate real-time duration using Pakistani time
    $checkinDuration = '';
    if ($isCheckedIn && $lastCheckin) {
        $checkinTime = new DateTime($lastCheckin['check_in'], new DateTimeZone('Asia/Karachi'));
        $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
        
        $totalSeconds = $now->getTimestamp() - $checkinTime->getTimestamp();
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        
        $checkinDuration = sprintf('%d:%02d', $hours, $minutes);
    }

    // Handle check-in/check-out and location updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'checkin' && !$isCheckedIn && $needsToCheckIn) {
                // Perform check-in with Pakistani time
                $pakistani_time = getPakistaniTime();
                $stmt = $pdo->prepare("INSERT INTO attendance (user_id, check_in) VALUES (?, ?)");
                $stmt->execute([$user_id, $pakistani_time]);
                
                if (!isUserDeveloper($user_id)) {
                    logActivity($user_id, 'check_in', 'User checked in');
                }
                
                // Update user location status
                updateUserLocationStatus($user_id, true);
                
                // Return JSON for AJAX
                if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Checked in successfully']);
                    exit;
                }
                
                redirect('dashboard.php');
            } 
            else if ($_POST['action'] === 'checkout' && $isCheckedIn) {
                // Calculate duration in minutes using Pakistani time
                $checkinTime = new DateTime($lastCheckin['check_in'], new DateTimeZone('Asia/Karachi'));
                $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
                $duration_minutes = intval(($now->getTimestamp() - $checkinTime->getTimestamp()) / 60);
                
                // Perform check-out with Pakistani time
                $pakistani_checkout = getPakistaniTime();
                $stmt = $pdo->prepare("UPDATE attendance SET check_out = ?, duration_minutes = ? WHERE id = ?");
                $stmt->execute([$pakistani_checkout, $duration_minutes, $lastCheckin['id']]);
                
                if (!isUserDeveloper($user_id)) {
                    logActivity($user_id, 'check_out', "User checked out. Duration: $duration_minutes minutes");
                }
                
                // Update user location status
                updateUserLocationStatus($user_id, false);
                
                // Return JSON for AJAX
                if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Checked out successfully']);
                    exit;
                }
                
                redirect('dashboard.php');
            }
            else if ($_POST['action'] === 'update_location' && $isCheckedIn) {
                // Update location using FastAPI ONLY
                $latitude = floatval($_POST['latitude'] ?? 0);
                $longitude = floatval($_POST['longitude'] ?? 0);
                
                if ($latitude != 0 && $longitude != 0) {
                    try {
                        // Call FastAPI to update location
                        $fastapi_base_url = getSettings('fastapi_base_url', 'http://54.250.198.0:8000');
                        $api_url = $fastapi_base_url . "/update_location/{$user['username']}/{$longitude}_{$latitude}";
                        $response = makeApiRequest($api_url, 'POST');
                        
                        if ($response !== false) {
                            // Update user location status
                            updateUserLocationStatus($user_id, true);
                            
                            // Return success for AJAX calls
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'message' => 'Location updated successfully']);
                            exit;
                        } else {
                            throw new Exception('FastAPI request failed');
                        }
                    } catch (Exception $e) {
                        // Log error and return proper JSON response
                        error_log("Location update error: " . $e->getMessage());
                        
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Failed to update location: ' . $e->getMessage()]);
                        exit;
                    }
                } else {
                    // Return error for AJAX calls
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Invalid location data']);
                    exit;
                }
            }
        }
    }

    // Get app name from settings
    $appName = getSettings('app_name', 'SmartORT');
    $locationUpdateInterval = intval(getSettings('location_update_interval', '60')) * 1000; // Convert to milliseconds

    // Get current Pakistani time
    $currentTime = new DateTime('now', new DateTimeZone('Asia/Karachi'));

    // Generate random color for dashboard
    $dashboardColors = [
        ['bg' => '#4f46e5', 'accent' => '#6366f1'],
        ['bg' => '#059669', 'accent' => '#10b981'],
        ['bg' => '#dc2626', 'accent' => '#ef4444'],
        ['bg' => '#7c3aed', 'accent' => '#8b5cf6'],
        ['bg' => '#ea580c', 'accent' => '#f97316'],
        ['bg' => '#0891b2', 'accent' => '#06b6d4'],
    ];
    $randomColor = $dashboardColors[array_rand($dashboardColors)];

} catch (Exception $e) {
    // Log the error and show user-friendly message
    error_log("Dashboard error: " . $e->getMessage());
    
    // Return JSON error for AJAX requests
    if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
        exit;
    }
    
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
    echo "<h1>System Error</h1>";
    echo "<p>There was a problem loading the dashboard. Please try again later.</p>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='logout.php'>Logout and try again</a>";
    echo "</body></html>";
    exit;
}
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
            background: linear-gradient(135deg, <?php echo $randomColor['bg']; ?>, <?php echo $randomColor['accent']; ?>);
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
            background-color: <?php echo $randomColor['bg']; ?>20;
            color: <?php echo $randomColor['bg']; ?>;
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
            border-left: 4px solid <?php echo $randomColor['bg']; ?>;
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
            color: <?php echo $randomColor['bg']; ?>;
            margin-right: 12px;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        .clock {
            font-size: 18px;
            font-weight: 600;
            color: <?php echo $randomColor['bg']; ?>;
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
                    <button type="button" id="checkin-btn" class="action-button check-in">
                        <i class="fas fa-sign-in-alt"></i> Check In
                    </button>
                    <?php else: ?>
                    <button type="button" id="checkout-btn" class="action-button check-out">
                        <i class="fas fa-sign-out-alt"></i> Check Out
                    </button>
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
            const checkinTime = <?php echo $isCheckedIn && $lastCheckin ? '"' . $lastCheckin['check_in'] . '"' : 'null'; ?>;
            const updateInterval = <?php echo $locationUpdateInterval; ?>; // Every 1 minute as requested
            
            // Update Pakistani time clock
            function updateClock() {
                const now = new Date();
                const pakistaniTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Karachi"}));
                const timeString = pakistaniTime.toLocaleTimeString('en-US', {hour12: true});
                document.getElementById('current-time').textContent = timeString;
            }
            
            // Update work duration in real-time using Pakistani time
            function updateWorkDuration() {
                if (isCheckedIn && checkinTime) {
                    const checkin = new Date(checkinTime);
                    const now = new Date();
                    const pakistaniNow = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Karachi"}));
                    
                    const diffMs = pakistaniNow - checkin;
                    const hours = Math.floor(diffMs / (1000 * 60 * 60));
                    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
                    
                    const durationElement = document.getElementById('work-duration');
                    if (durationElement) {
                        durationElement.textContent = hours + ':' + (minutes < 10 ? '0' : '') + minutes;
                    }
                }
            }
            
            // Update clock and duration every second
            setInterval(updateClock, 1000);
            setInterval(updateWorkDuration, 1000);
            updateClock();
            updateWorkDuration();
            
            // Check-in button handler
            const checkinBtn = document.getElementById('checkin-btn');
            if (checkinBtn) {
                checkinBtn.addEventListener('click', async function() {
                    // Request location permission first
                    if ('geolocation' in navigator) {
                        try {
                            const permission = await navigator.permissions.query({name: 'geolocation'});
                            if (permission.state === 'denied') {
                                alert('Location permission is required for check-in. Please enable location access.');
                                return;
                            }
                            
                            // Get current position to ensure location works
                            navigator.geolocation.getCurrentPosition(
                                function(position) {
                                    // Location permission granted, proceed with check-in
                                    performCheckin();
                                },
                                function(error) {
                                    alert('Location access is required for check-in. Please allow location access and try again.');
                                },
                                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                            );
                        } catch (e) {
                            alert('Location access is required for check-in. Please allow location access.');
                        }
                    } else {
                        alert('Geolocation is not supported by this browser.');
                    }
                });
            }
            
            // Check-out button handler
            const checkoutBtn = document.getElementById('checkout-btn');
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', function() {
                    performCheckout();
                });
            }
            
            function performCheckin() {
                const formData = new FormData();
                formData.append('action', 'checkin');
                formData.append('ajax', '1');
                
                fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload(); // Reload to show new status
                    } else {
                        alert('Check-in failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Check-in error:', error);
                    alert('Check-in failed. Please try again.');
                });
            }
            
            function performCheckout() {
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
                        location.reload(); // Reload to show new status
                    } else {
                        alert('Check-out failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Check-out error:', error);
                    alert('Check-out failed. Please try again.');
                });
            }
            
            // Location tracking for checked-in users using FastAPI ONLY
            if (isCheckedIn) {
                if ('geolocation' in navigator) {
                    // Get location immediately
                    getAndUpdateLocation();
                    
                    // Then update every minute as requested
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
