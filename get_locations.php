
<?php
require_once 'config.php';
require_once 'location_utils.php';

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
$loggedInUserRole = $_SESSION['user_role'] ?? $_SESSION['role'];

// Get locations for all users that are checked in, hiding developers from masters
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
    " . ($loggedInUserRole === 'master' ? "AND u.role != 'developer' AND u.role != 'master'" : "") . "
");
$stmt->execute();
$locations = $stmt->fetchAll();

// Check if each user is checked in and only include those who are
$activeLocations = [];
foreach ($locations as $location) {
    $stmt = $pdo->prepare("
        SELECT id FROM attendance 
        WHERE user_id = ? AND check_out IS NULL
        LIMIT 1
    ");
    $stmt->execute([$location['user_id']]);
    $isCheckedIn = $stmt->rowCount() > 0;
    
    // Only include users who are checked in
    if ($isCheckedIn) {
        // Format time for display
        $timestamp = new DateTime($location['timestamp'], new DateTimeZone('Asia/Karachi'));
        $location['formatted_time'] = $timestamp->format('h:i A');
        $activeLocations[] = $location;
    }
}

echo json_encode($activeLocations);
?>
