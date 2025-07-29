
<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User is not checked in']);
    exit;
}

// Get location data
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0;

if ($latitude == 0 || $longitude == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid location data']);
    exit;
}

// Use FastAPI ONLY for location updates
$address = 'Unknown location';
$fastapi_success = false;

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
    
    // Update user location status
    updateUserLocationStatus($user_id, true);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Location updated successfully via FastAPI',
        'address' => $address,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'fastapi_status' => true
    ]);
} else {
    // If FastAPI fails, just return error (don't save to database)
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to update location via FastAPI']);
}

exit;
