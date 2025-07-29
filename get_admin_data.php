
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

// Get locations from FastAPI ONLY
$locations = [];
$users_data = [];

// Call FastAPI to get all locations
$api_url = $fastapi_base_url . "/fetch_all_locations";
$response = makeApiRequest($api_url, 'GET');

if ($response !== false && is_array($response)) {
    // Convert FastAPI response to our format
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
                
                // Check if user is checked in
                $stmt = $pdo->prepare("SELECT id, check_in FROM attendance WHERE user_id = ? AND check_out IS NULL LIMIT 1");
                $stmt->execute([$user['id']]);
                $checkin = $stmt->fetch();
                $isCheckedIn = !empty($checkin);
                
                // Calculate work duration if checked in
                $workDuration = '';
                if ($isCheckedIn) {
                    $checkinTime = new DateTime($checkin['check_in'], new DateTimeZone('Asia/Karachi'));
                    $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
                    $totalSeconds = $now->getTimestamp() - $checkinTime->getTimestamp();
                    $hours = floor($totalSeconds / 3600);
                    $minutes = floor(($totalSeconds % 3600) / 60);
                    $workDuration = sprintf('%d:%02d', $hours, $minutes);
                }
                
                // Only show checked in users (unless developer viewing all)
                if ($isCheckedIn || ($loggedInUserRole === 'developer')) {
                    $locations[] = [
                        'user_id' => $user['id'],
                        'username' => $user['username'],
                        'full_name' => $user['full_name'],
                        'role' => $user['role'],
                        'user_role' => $user['user_role'],
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude'],
                        'address' => $location['address'] ?? 'Unknown location',
                        'is_location_enabled' => $isCheckedIn ? 1 : 0,
                        'is_checked_in' => $isCheckedIn,
                        'work_duration' => $workDuration,
                        'timestamp' => date('Y-m-d H:i:s'), // Current Pakistani time
                        'formatted_time' => date('h:i A')
                    ];
                }
            }
        }
    }
}

// Get total stats
$totalUsers = 0;
$checkedInUsers = 0;
$totalLocations = count($locations);

// Count total users visible to current admin
if ($loggedInUserRole === 'developer') {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $totalUsers = $stmt->fetch()['count'];
} else {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $totalUsers = $stmt->fetch()['count'];
}

// Count checked in users
foreach ($locations as $location) {
    if ($location['is_checked_in']) {
        $checkedInUsers++;
    }
}

echo json_encode([
    'success' => true,
    'locations' => $locations,
    'stats' => [
        'total_users' => $totalUsers,
        'checked_in_users' => $checkedInUsers,
        'total_locations' => $totalLocations,
        'last_updated' => date('h:i:s A')
    ]
]);
?>
