
<?php
require_once 'config.php';

// This file gets an address from latitude and longitude

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

// Try to get address from coordinates using OpenStreetMap Nominatim API
$address = getAddressFromCoordinates($latitude, $longitude);

// Return response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'address' => $address,
    'latitude' => $latitude,
    'longitude' => $longitude
]);
exit;

// Function to get address from coordinates
function getAddressFromCoordinates($lat, $lng) {
    // Use OpenStreetMap Nominatim API
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";
    
    // Set up cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SmartOutreach Location Service');
    
    // Execute request
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Parse response
    $data = json_decode($response, true);
    
    if (isset($data['display_name'])) {
        return $data['display_name'];
    }
    
    return 'Unknown location';
}
