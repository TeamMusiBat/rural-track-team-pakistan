
<?php
require_once 'config.php';

// Function to get geolocation information from coordinates
function getAddressFromCoordinates($latitude, $longitude) {
    // Try to get address from Nominatim OpenStreetMap service
    try {
        // Add a unique user agent as required by Nominatim usage policy
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: SmartOutreach-Tracker/1.0'
            ]
        ];
        $context = stream_context_create($opts);
        
        // Create API URL with parameters
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$latitude&lon=$longitude&zoom=18&addressdetails=1";
        
        // Make the request
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['display_name'])) {
                return $data['display_name'];
            }
        }
    } catch (Exception $e) {
        // Log error silently
        error_log("Error getting address from coordinates: " . $e->getMessage());
    }
    
    // If we couldn't get the address, return coordinates
    return "Location: $latitude, $longitude";
}

// Function to save location with address
function saveLocationWithAddress($user_id, $latitude, $longitude) {
    global $pdo;
    
    try {
        // Get address from coordinates
        $address = getAddressFromCoordinates($latitude, $longitude);
        
        // Save location with address
        $stmt = $pdo->prepare("INSERT INTO locations (user_id, latitude, longitude, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $latitude, $longitude, $address]);
        
        return ['success' => true, 'address' => $address];
    } catch (Exception $e) {
        error_log("Error saving location: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Function to get user's last location
function getUserLastLocation($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM locations 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    
    return $stmt->fetch();
}

// Function to check if user needs to check in based on role
function userNeedsToCheckIn($role) {
    if ($role === 'user') {
        return true;
    }
    
    if ($role === 'master') {
        // Check if master users are required to check in based on settings
        $masterCheckinRequired = getSettings('master_checkin_required', '0');
        return $masterCheckinRequired == '1';
    }
    
    return false;
}

// Function to check user management permissions
function canManageUser($managerRole, $targetUserRole) {
    if ($managerRole === 'developer') {
        return true; // Developer can manage all users
    }
    
    if ($managerRole === 'master') {
        // Masters can only manage regular users, cannot see or manage developers or other masters
        return $targetUserRole === 'user';
    }
    
    return false;
}

// Set default auto checkout hours to 8 if not already set
function setDefaultAutoCheckoutHours() {
    $currentValue = getSettings('auto_checkout_hours', null);
    if ($currentValue === null) {
        saveSettings('auto_checkout_hours', '8');
    }
}

// Run once to ensure defaults are set
setDefaultAutoCheckoutHours();

// Handle AJAX requests for address retrieval
if (isset($_GET['get_address']) && isset($_GET['lat']) && isset($_GET['lng'])) {
    // Check if user is logged in for all GET requests
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
    
    $lat = floatval($_GET['lat']);
    $lng = floatval($_GET['lng']);
    
    $address = getAddressFromCoordinates($lat, $lng);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'address' => $address
    ]);
    exit;
}
?>
