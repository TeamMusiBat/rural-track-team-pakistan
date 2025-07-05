
<?php
require_once 'config.php';
require_once 'location_utils.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Get user ID from query params or use logged in user
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];

// Check permissions - only allow admins to view other users' locations
if ($user_id != $_SESSION['user_id']) {
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
    
    // Get the role of the requested user and the logged in user
    $stmt = $pdo->prepare("SELECT role, user_role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $requestedUser = $stmt->fetch();
    $loggedInUserRole = $_SESSION['user_role'] ?? $_SESSION['role'];
    
    // If master, they can't view developer or other master locations
    if ($loggedInUserRole === 'master' && 
        ($requestedUser['role'] === 'developer' || $requestedUser['role'] === 'master')) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
    
    // Check if user is checked in (for admin requests)
    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND check_out IS NULL LIMIT 1");
    $stmt->execute([$user_id]);
    $isCheckedIn = $stmt->rowCount() > 0;
    
    // If checked out and not the current user, don't show location
    if (!$isCheckedIn) {
        echo json_encode(['success' => false, 'message' => 'User is not checked in']);
        exit;
    }
}

// Get user's last location
$locationData = getUserLastLocation($user_id);

if ($locationData) {
    echo json_encode([
        'success' => true,
        'latitude' => $locationData['latitude'],
        'longitude' => $locationData['longitude'],
        'address' => $locationData['address'] ?? 'Unknown location',
        'timestamp' => $locationData['timestamp'],
        'formatted_time' => date('h:i A', strtotime($locationData['timestamp']))
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No location data found']);
}
?>
