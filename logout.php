
<?php
require_once 'config.php';

// Force checkout when logging out
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    
    // Check if user is checked in
    $lastCheckin = getLastCheckin($user_id);
    
    if ($lastCheckin) {
        // Get current Pakistan time
        $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
        $checkout_time = $now;
        
        // Calculate duration in minutes (Pakistan time)
        $checkin_time = new DateTime($lastCheckin['check_in'], new DateTimeZone('Asia/Karachi'));
        $interval = $checkout_time->diff($checkin_time);
        
        $hours = $interval->h + ($interval->days * 24);
        $minutes = $interval->i;
        $duration_minutes = ($hours * 60) + $minutes;
        
        // Perform check-out with explicit Pakistan time
        $timestamp = $now->format('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare("UPDATE attendance SET check_out = ?, duration_minutes = ? WHERE id = ?");
        $stmt->execute([$timestamp, $duration_minutes, $lastCheckin['id']]);
        
        if (!isUserDeveloper($user_id)) {
            logActivity($user_id, 'check_out', "User logged out and was checked out. Duration: $duration_minutes minutes");
        }
        
        // Update user location status
        updateUserLocationStatus($user_id, false);
    }
    
    // Log the logout
    if (!isUserDeveloper($user_id)) {
        logActivity($user_id, 'logout', 'User logged out');
    }
}

// Clear all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to login page
header("Location: index.php");
exit;
