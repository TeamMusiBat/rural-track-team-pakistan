
<?php
require_once 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
    exit;
}

$loggedInUserRole = $_SESSION['role'];

// Get locations from FastAPI ONLY - but filter to show only checked-in users
$locations = [];

try {
    // Get FastAPI base URL from settings
    $fastapi_base_url = getSettings('fastapi_base_url', 'http://54.250.198.0:8000');
    
    // Call FastAPI to get all locations using GET method
    $api_url = $fastapi_base_url . "/fetch_all_locations";
    $response = makeApiRequest($api_url, 'GET');

    if ($response !== false && is_array($response)) {
        // Convert FastAPI response to our format - ONLY CHECKED IN USERS
        foreach ($response as $location) {
            if (isset($location['username'])) {
                // Get user details from database
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$location['username']]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Check if user should be visible to current admin
                    if ($loggedInUserRole === 'master' && ($user['role'] === 'developer' || $user['role'] === 'master')) {
                        continue; // Skip developers and masters for master users
                    }
                    
                    // Check if user is CURRENTLY checked in using Pakistani time
                    $stmt = $pdo->prepare("SELECT id, check_in FROM attendance WHERE user_id = ? AND check_out IS NULL ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$user['id']]);
                    $checkin = $stmt->fetch();
                    $isCheckedIn = !empty($checkin);
                    
                    // ONLY INCLUDE CHECKED IN USERS
                    if ($isCheckedIn) {
                        // Calculate work duration if checked in using Pakistani time
                        $workDuration = '';
                        if ($checkin) {
                            $checkinTime = new DateTime($checkin['check_in'], new DateTimeZone('Asia/Karachi'));
                            $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
                            $totalSeconds = $now->getTimestamp() - $checkinTime->getTimestamp();
                            $hours = floor($totalSeconds / 3600);
                            $minutes = floor(($totalSeconds % 3600) / 60);
                            $workDuration = sprintf('%d:%02d', $hours, $minutes);
                        }
                        
                        $locations[] = [
                            'user_id' => $user['id'],
                            'username' => $user['username'],
                            'full_name' => $user['full_name'],
                            'role' => $user['role'],
                            'user_role' => $user['user_role'],
                            'latitude' => (float)$location['latitude'],
                            'longitude' => (float)$location['longitude'],
                            'address' => $location['address'] ?? 'Unknown location',
                            'is_location_enabled' => 1,
                            'is_checked_in' => true, // All users in this list are checked in
                            'work_duration' => $workDuration,
                            'timestamp' => getPakistaniTime('Y-m-d H:i:s'),
                            'formatted_time' => getPakistaniTime('h:i A')
                        ];
                    }
                }
            }
        }
    } else {
        error_log("FastAPI fetch_all_locations returned invalid response: " . json_encode($response));
    }
} catch (Exception $e) {
    error_log("Admin data fetch error: " . $e->getMessage());
}

// Get total stats using Pakistani time - count only checked in users
$totalUsers = 0;
$checkedInUsers = count($locations); // All users in locations array are checked in
$totalLocations = count($locations);

// Count total users visible to current admin
if ($loggedInUserRole === 'developer') {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $totalUsers = $stmt->fetch()['count'];
} else {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $totalUsers = $stmt->fetch()['count'];
}

echo json_encode([
    'success' => true,
    'locations' => $locations,
    'stats' => [
        'total_users' => (int)$totalUsers,
        'checked_in_users' => $checkedInUsers,
        'total_locations' => $totalLocations,
        'last_updated' => getPakistaniTime('h:i:s A'),
        'current_time' => getPakistaniTime('Y-m-d h:i:s A'),
        'fastapi_endpoint' => $fastapi_base_url . "/fetch_all_locations"
    ]
]);
