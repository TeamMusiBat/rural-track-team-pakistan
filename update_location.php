
<?php
require_once 'config.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Check if the user is checked in
$lastCheckin = getLastCheckin($user_id);
$isCheckedIn = !empty($lastCheckin);

if (!$isCheckedIn) {
    echo json_encode(['success' => false, 'message' => 'User is not checked in']);
    exit;
}

// Get location data
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0;

if ($latitude == 0 || $longitude == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid location data']);
    exit;
}

// Enhanced rate limiting: Check last update time (1 minute = 60 seconds)
$stmt = $pdo->prepare("SELECT last_location_update FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user && !empty($user['last_location_update'])) {
    $lastUpdateTime = strtotime($user['last_location_update']);
    $currentTime = time();
    $timeDifference = $currentTime - $lastUpdateTime;
    
    if ($timeDifference < 60) { // Less than 60 seconds
        $remainingTime = 60 - $timeDifference;
        echo json_encode([
            'success' => false, 
            'message' => "Rate limited: Please wait {$remainingTime} seconds before next location update",
            'rate_limited' => true,
            'remaining_seconds' => $remainingTime,
            'last_update' => $user['last_location_update']
        ]);
        exit;
    }
}

// Generate random light theme colors for screen feedback
$lightColors = [
    ['bg' => '#E8F5E8', 'border' => '#4CAF50', 'name' => 'Green'], 
    ['bg' => '#E3F2FD', 'border' => '#2196F3', 'name' => 'Blue'],
    ['bg' => '#FFF3E0', 'border' => '#FF9800', 'name' => 'Orange'],
    ['bg' => '#F3E5F5', 'border' => '#9C27B0', 'name' => 'Purple'],
    ['bg' => '#E0F2F1', 'border' => '#009688', 'name' => 'Teal'],
    ['bg' => '#FFF8E1', 'border' => '#FFC107', 'name' => 'Amber'],
    ['bg' => '#FCE4EC', 'border' => '#E91E63', 'name' => 'Pink'],
    ['bg' => '#E8F5E8', 'border' => '#8BC34A', 'name' => 'Lime']
];

$randomColor = $lightColors[array_rand($lightColors)];

// Use FastAPI EXCLUSIVELY for location updates
try {
    // Get FastAPI base URL from settings
    $fastapi_base_url = getSettings('fastapi_base_url', 'http://54.250.198.0:8000');
    
    // Call FastAPI to update location - using POST method as specified
    $api_url = $fastapi_base_url . "/update_location/{$username}/{$longitude}_{$latitude}";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($api_url, false, $context);
    
    if ($response !== false) {
        $result = json_decode($response, true);
        
        if ($result && isset($result['message']) && strpos($result['message'], 'Location added') !== false) {
            // Success - update last update time in database
            $stmt = $pdo->prepare("UPDATE users SET last_location_update = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Try to get the address from FastAPI
            $address = 'Unknown location';
            try {
                $fetch_url = $fastapi_base_url . "/fetch_location/{$username}";
                $fetch_context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\n",
                        'timeout' => 5
                    ]
                ]);
                
                $location_response = @file_get_contents($fetch_url, false, $fetch_context);
                if ($location_response) {
                    $location_data = json_decode($location_response, true);
                    if ($location_data && isset($location_data['address'])) {
                        $address = $location_data['address'];
                    }
                }
            } catch (Exception $e) {
                error_log("Error fetching address from FastAPI: " . $e->getMessage());
            }
            
            // Log activity (only for non-developers)
            if (!isUserDeveloper($user_id)) {
                logActivity($user_id, 'location_update', "Location updated via FastAPI: $address (Lat: $latitude, Lng: $longitude)");
            }
            
            // Return success response with color theme
            echo json_encode([
                'success' => true, 
                'message' => 'Location updated successfully via FastAPI',
                'address' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'fastapi_status' => true,
                'timestamp' => getPakistaniTime('h:i:s A'),
                'color_theme' => $randomColor,
                'data_source' => 'FastAPI Only',
                'next_update_allowed_in' => 60 // Next update allowed in 60 seconds
            ]);
        } else {
            throw new Exception('FastAPI returned unexpected response: ' . json_encode($result));
        }
    } else {
        throw new Exception('Failed to connect to FastAPI endpoint');
    }
} catch (Exception $e) {
    // Log the error
    error_log("FastAPI location update error for user {$username}: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to update location via FastAPI: ' . $e->getMessage(),
        'fastapi_status' => false,
        'data_source' => 'FastAPI Only - Update Failed',
        'api_url' => $api_url ?? 'Unknown'
    ]);
}

exit;
