
<?php
require_once 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
    exit;
}

// Get logged in user's role
$loggedInUserRole = $_SESSION['role'];

// Try to get locations from FastAPI first
$locations = [];
$fastapi_success = false;

// Call FastAPI to get all locations
$api_url = $fastapi_base_url . "/fetch_all_locations";
$response = makeApiRequest($api_url, 'GET');

if ($response !== false && is_array($response)) {
    $fastapi_success = true;
    
    // Convert FastAPI response to our format
    foreach ($response as $location) {
        if (isset($location['username'])) {
            // Get user details from database
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$location['username']]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Check if user should be visible to current admin
                if ($loggedInUserRole === 'master' && ($user['role'] === 'developer' || $user['role'] === 'master')) {
                    continue; // Skip developers and masters for master users
                }
                
                // Check if user is checked in
                $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND check_out IS NULL LIMIT 1");
                $stmt->execute([$user['id']]);
                $isCheckedIn = $stmt->rowCount() > 0;
                
                // Only show checked in users (unless developer viewing checked out users)
                if ($isCheckedIn || ($loggedInUserRole === 'developer')) {
                    $locations[] = [
                        'user_id' => $user['id'],
                        'username' => $user['username'],
                        'full_name' => $user['full_name'],
                        'role' => $user['role'],
                        'user_role' => $user['user_role'],
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude'],
                        'address' => $location['address'] ?? 'Unknown location',
                        'is_location_enabled' => $isCheckedIn ? 1 : 0,
                        'timestamp' => date('Y-m-d H:i:s'), // FastAPI doesn't provide timestamp
                        'formatted_time' => date('h:i A')
                    ];
                }
            }
        }
    }
}

// If FastAPI failed, fallback to local database
if (!$fastapi_success) {
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name, u.username, u.role, u.is_location_enabled, u.user_role
        FROM locations l
        JOIN users u ON l.user_id = u.id
        WHERE l.id IN (
            SELECT MAX(id) 
            FROM locations 
            GROUP BY user_id
        )
        AND u.is_location_enabled = 1
        " . ($loggedInUserRole === 'master' ? "AND u.role = 'user'" : "") . "
    ");
    $stmt->execute();
    $localLocations = $stmt->fetchAll();
    
    // Check if each user is checked in
    foreach ($localLocations as $location) {
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND check_out IS NULL LIMIT 1");
        $stmt->execute([$location['user_id']]);
        $isCheckedIn = $stmt->rowCount() > 0;
        
        // Only include users who are checked in (unless developer viewing all)
        if ($isCheckedIn || ($loggedInUserRole === 'developer')) {
            $timestamp = new DateTime($location['timestamp']);
            $location['formatted_time'] = $timestamp->format('h:i A');
            $locations[] = $location;
        }
    }
}

echo json_encode($locations);
?>
