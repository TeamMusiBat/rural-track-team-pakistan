
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

// Try to update location via FastAPI first
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
}

// Always save to local database as backup
try {
    // If FastAPI didn't provide address, get it from OpenStreetMap
    if ($address === 'Unknown location') {
        $address = getAddressFromOpenStreetMap($latitude, $longitude);
    }
    
    $stmt = $pdo->prepare("INSERT INTO locations (user_id, latitude, longitude, address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $latitude, $longitude, $address]);
    
    // Update user location status
    updateUserLocationStatus($user_id, true);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => $fastapi_success ? 'Location updated successfully' : 'Location updated (local backup)',
        'address' => $address,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'fastapi_status' => $fastapi_success
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

exit;

// Function to get address from OpenStreetMap
function getAddressFromOpenStreetMap($lat, $lng) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";
    
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: SmartOutreach-Tracker/1.0'
        ]
    ];
    $context = stream_context_create($opts);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        
        if ($data && isset($data['display_name'])) {
            return $data['display_name'];
        }
    }
    
    return "Location: $lat, $lng";
}
