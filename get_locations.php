
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

// Get logged in user's role
$loggedInUserRole = $_SESSION['role'];

// Get locations from FastAPI ONLY
$locations = [];
$fastapi_success = false;

try {
    // Get FastAPI base URL from settings
    $fastapi_base_url = getSettings('fastapi_base_url', 'http://54.250.198.0:8000');
    
    // Call FastAPI to get all locations
    $api_url = $fastapi_base_url . "/fetch_all_locations";
    $response = makeApiRequest($api_url, 'GET');

    if ($response !== false && is_array($response)) {
        $fastapi_success = true;
        
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
                    $checkin_data = $stmt->fetch();
                    $isCheckedIn = !empty($checkin_data);
                    
                    // Calculate work duration if checked in using Pakistani time
                    $workDuration = '';
                    if ($isCheckedIn && $checkin_data) {
                        $checkinTime = new DateTime($checkin_data['check_in'], new DateTimeZone('Asia/Karachi'));
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
                            'timestamp' => getPakistaniTime('Y-m-d H:i:s'),
                            'formatted_time' => getPakistaniTime('h:i A')
                        ];
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("FastAPI locations fetch error: " . $e->getMessage());
    $fastapi_success = false;
}

// If FastAPI failed, return empty array
if (!$fastapi_success) {
    $locations = [];
}

echo json_encode($locations);
exit;
