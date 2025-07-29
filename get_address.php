
<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get coordinates from GET parameters
$latitude = isset($_GET['lat']) ? floatval($_GET['lat']) : 0;
$longitude = isset($_GET['lng']) ? floatval($_GET['lng']) : 0;

if ($latitude == 0 || $longitude == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
    exit;
}

// Get username from session
$username = $_SESSION['username'];

// Try to get address from FastAPI first
$address = getAddressFromFastAPI($latitude, $longitude, $username);

// If FastAPI fails, fallback to OpenStreetMap
if (!$address) {
    $address = getAddressFromOpenStreetMap($latitude, $longitude);
}

// Return response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'address' => $address,
    'latitude' => $latitude,
    'longitude' => $longitude
]);
exit;

// Function to get address from FastAPI
function getAddressFromFastAPI($lat, $lng, $username) {
    global $fastapi_base_url;
    
    // Update location in FastAPI
    $api_url = $fastapi_base_url . "/update_location/{$username}/{$lng}_{$lat}";
    $response = makeApiRequest($api_url, 'POST');
    
    if ($response && isset($response['message'])) {
        // Now fetch the location to get the address
        $fetch_url = $fastapi_base_url . "/fetch_location/{$username}";
        $location_data = makeApiRequest($fetch_url, 'GET');
        
        if ($location_data && isset($location_data['address'])) {
            return $location_data['address'];
        }
    }
    
    return false;
}

// Function to get address from OpenStreetMap (fallback)
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
