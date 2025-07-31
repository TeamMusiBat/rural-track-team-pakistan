
<?php
require_once 'config.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get coordinates from GET parameters
$latitude = isset($_GET['lat']) ? floatval($_GET['lat']) : 0;
$longitude = isset($_GET['lng']) ? floatval($_GET['lng']) : 0;

if ($latitude == 0 || $longitude == 0) {
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

// Return response with Pakistani time
echo json_encode([
    'success' => true,
    'address' => $address,
    'latitude' => $latitude,
    'longitude' => $longitude,
    'timestamp' => getPakistaniTime('h:i:s A')
]);
exit;

// Function to get address from FastAPI
function getAddressFromFastAPI($lat, $lng, $username) {
    try {
        // Get FastAPI base URL from settings
        $fastapi_base_url = getSettings('fastapi_base_url', 'http://54.250.198.0:8000');
        
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
    } catch (Exception $e) {
        error_log("FastAPI address fetch error: " . $e->getMessage());
    }
    
    return false;
}

// Function to get address from OpenStreetMap (fallback)
function getAddressFromOpenStreetMap($lat, $lng) {
    try {
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";
        
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: SmartORT-Tracker/1.0',
                'timeout' => 10
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
    } catch (Exception $e) {
        error_log("OpenStreetMap address fetch error: " . $e->getMessage());
    }
    
    return "Location: $lat, $lng";
}
