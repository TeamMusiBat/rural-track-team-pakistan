<?php
require_once 'config.php';

/**
 * Helper to call FastAPI endpoints.
 */
function callFastAPI($endpoint, $method = 'GET', $payload = null) {
    $base_url = getSettings('fastapi_base_url', 'http://54.250.198.0:8000');
    $url = $base_url . $endpoint;
    
    $opts = [
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\nUser-Agent: SmartOutreach-Tracker/1.0\r\n"
        ]
    ];
    if ($method === 'POST' && $payload !== null) {
        $opts['http']['content'] = json_encode($payload);
    }
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) return false;
    $data = json_decode($response, true);
    return $data;
}

/**
 * Get location for specific user from FastAPI.
 */
function getUserLastLocation($user_id_or_username) {
    // Prefer username for FastAPI endpoint
    global $pdo;
    $username = $user_id_or_username;

    // If passed an ID, lookup username
    if (is_numeric($user_id_or_username)) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$user_id_or_username]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $username = $row['username'];
    }

    $endpoint = "/fetch_location/" . urlencode($username);
    $location = callFastAPI($endpoint, 'POST');
    if (isset($location['detail'])) return null; // Not found
    return $location;
}

/**
 * Save/update user location via FastAPI.
 */
function saveLocationWithAddress($user_id_or_username, $latitude, $longitude) {
    // Find username if ID passed
    global $pdo;
    $username = $user_id_or_username;
    if (is_numeric($user_id_or_username)) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$user_id_or_username]);
        $row = $stmt->fetch();
        if (!$row) return ['success' => false, 'error' => 'User not found'];
        $username = $row['username'];
    }

    $endpoint = "/update_location/" . urlencode($username) . "/" . $longitude . "_" . $latitude;
    $result = callFastAPI($endpoint, 'POST');
    if (isset($result['message'])) {
        return ['success' => true, 'message' => $result['message']];
    }
    return ['success' => false, 'error' => $result['detail'] ?? 'Unknown error'];
}

/**
 * Get address from coordinates (using OpenStreetMap, unchanged).
 */
function getAddressFromCoordinates($latitude, $longitude) {
    try {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: SmartOutreach-Tracker/1.0'
            ]
        ];
        $context = stream_context_create($opts);
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$latitude&lon=$longitude&zoom=18&addressdetails=1";
        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);
            if ($data && isset($data['display_name'])) {
                return $data['display_name'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting address from coordinates: " . $e->getMessage());
    }
    return "Location: $latitude, $longitude";
}

// Other utils (unchanged)
function userNeedsToCheckIn($role) {
    if ($role === 'user') {
        return true;
    }
    if ($role === 'master') {
        $masterCheckinRequired = getSettings('master_checkin_required', '0');
        return $masterCheckinRequired == '1';
    }
    return false;
}
function canManageUser($managerRole, $targetUserRole) {
    if ($managerRole === 'developer') {
        return true;
    }
    if ($managerRole === 'master') {
        return $targetUserRole === 'user';
    }
    return false;
}
function setDefaultAutoCheckoutHours() {
    $currentValue = getSettings('auto_checkout_hours', null);
    if ($currentValue === null) {
        saveSettings('auto_checkout_hours', '8');
    }
}
setDefaultAutoCheckoutHours();

// AJAX address retrieval (unchanged)
if (isset($_GET['get_address']) && isset($_GET['lat']) && isset($_GET['lng'])) {
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
