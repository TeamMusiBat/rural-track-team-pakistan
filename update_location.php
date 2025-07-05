
<?php
require_once 'config.php';
require_once 'location_utils.php';

// This file handles AJAX location updates

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

// Save location with address
$locationResult = saveLocationWithAddress($user_id, $latitude, $longitude);

// Update user location status
updateUserLocationStatus($user_id, true);

// Return success response
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'message' => 'Location updated successfully',
    'address' => $locationResult['address'] ?? 'Unknown location',
    'latitude' => $latitude,
    'longitude' => $longitude
]);
exit;
