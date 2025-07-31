
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

// Use FastAPI ONLY for location updates
$address = 'Unknown location';
$fastapi_success = false;

// Generate random light theme colors for screen feedback
$lightColors = [
    ['bg' => '#E8F5E8', 'border' => '#4CAF50'], // Light green
    ['bg' => '#E3F2FD', 'border' => '#2196F3'], // Light blue
    ['bg' => '#FFF3E0', 'border' => '#FF9800'], // Light orange
    ['bg' => '#F3E5F5', 'border' => '#9C27B0'], // Light purple
    ['bg' => '#E0F2F1', 'border' => '#009688'], // Light teal
    ['bg' => '#FFF8E1', 'border' => '#FFC107'], // Light amber
    ['bg' => '#FCE4EC', 'border' => '#E91E63'], // Light pink
    ['bg' => '#E8F5E8', 'border' => '#8BC34A']  // Light lime
];

$randomColor = $lightColors[array_rand($lightColors)];

try {
    // Get FastAPI base URL from settings
    $fastapi_base_url = getSettings('fastapi_base_url', 'http://54.250.198.0:8000');
    
    // Call FastAPI to update location
    $api_url = $fastapi_base_url . "/update_location/{$username}/{$longitude}_{$latitude}";
    $response = makeApiRequest($api_url, 'POST');

    if ($response !== false && isset($response['message'])) {
        $fastapi_success = true;
        
        // Try to get the address from FastAPI
        $fetch_url = $fastapi_base_url . "/fetch_location/{$username}";
        $location_data = makeApiRequest($fetch_url, 'GET');
        
        if ($location_data && isset($location_data['address'])) {
            $address = $location_data['address'];
        }
        
        // Update user location status in database
        updateUserLocationStatus($user_id, true);
        
        // Log activity with Pakistani time (only for non-developers)
        if (!isUserDeveloper($user_id)) {
            logActivity($user_id, 'location_update', "Location updated via FastAPI: $address");
        }
        
        // Return success response with color theme
        echo json_encode([
            'success' => true, 
            'message' => 'Location updated successfully',
            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'fastapi_status' => true,
            'timestamp' => getPakistaniTime('h:i:s A'),
            'color_theme' => $randomColor
        ]);
    } else {
        throw new Exception('FastAPI request failed or returned invalid response');
    }
} catch (Exception $e) {
    // Log the error
    error_log("FastAPI location update error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to update location via FastAPI: ' . $e->getMessage()
    ]);
}

exit;
