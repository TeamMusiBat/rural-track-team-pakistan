
<?php
// Database configuration - In a production environment, these should be in a separate .env file outside the web root
$host = "srv1135.hstgr.io";
$dbname = "u769157863_ort";
$username = "u769157863_ort";
$password = "Atifkhan83##";

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Time zone setting for Pakistan - ENSURING THIS IS SET PROPERLY
date_default_timezone_set('Asia/Karachi');

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function isAdmin() {
    $role = getUserRole();
    return $role === 'master' || $role === 'developer';
}

function isDeveloper() {
    return getUserRole() === 'developer';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

// Added security headers to prevent direct script access
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

function logActivity($user_id, $activity_type, $description = "") {
    global $pdo;
    
    // Don't log developer activity
    if (isUserDeveloper($user_id)) {
        return;
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $activity_type, $description, $ip]);
}

function isUserDeveloper($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    return $user && $user['role'] === 'developer';
}

function getLastCheckin($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND check_out IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getSettings($name, $default = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = ?");
    $stmt->execute([$name]);
    $setting = $stmt->fetch();
    
    return $setting ? $setting['value'] : $default;
}

function updateSettings($name, $value) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
    $stmt->execute([$name, $value, $value]);
    
    return true;
}

function cleanOldLocations() {
    global $pdo;
    
    // Get current time in Pakistan
    $now = new DateTime();
    $now->setTimezone(new DateTimeZone('Asia/Karachi'));
    
    // If it's midnight (00:00), perform cleanup of very old data (90+ days)
    if ($now->format('H:i') === '00:00') {
        // Calculate timestamp 90 days ago
        $cutoff = new DateTime();
        $cutoff->setTimezone(new DateTimeZone('Asia/Karachi'));
        $cutoff->modify('-90 days');
        $cutoffStr = $cutoff->format('Y-m-d H:i:s');
        
        // Delete very old locations
        $stmt = $pdo->prepare("DELETE FROM locations WHERE timestamp < ?");
        $stmt->execute([$cutoffStr]);
        
        // Log the cleanup
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, description) VALUES (0, 'system', 'Cleaned location history older than 90 days')");
        $stmt->execute();
    }
}

// Auto checkout all users at configured time (default 8 PM) Pakistan time if feature is enabled
function autoCheckoutAtEndOfDay() {
    global $pdo;
    
    // Get current time in Pakistan
    $now = new DateTime();
    $now->setTimezone(new DateTimeZone('Asia/Karachi'));
    
    // Check if auto checkout is enabled
    $autoCheckoutEnabled = getSettings('auto_checkout_enabled', '1') == '1';
    $autoCheckoutTime = getSettings('auto_checkout_time', '20:00');
    
    // If current time matches auto checkout time and auto checkout is enabled, checkout all users
    if ($now->format('H:i') === $autoCheckoutTime && $autoCheckoutEnabled) {
        // Find all active check-ins
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE check_out IS NULL");
        $stmt->execute();
        $activeCheckIns = $stmt->fetchAll();
        
        foreach ($activeCheckIns as $checkIn) {
            // Skip if user is developer
            if (isUserDeveloper($checkIn['user_id'])) {
                continue;
            }
            
            // Calculate duration in minutes
            $checkinTime = new DateTime($checkIn['check_in'], new DateTimeZone('Asia/Karachi'));
            $checkout_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
            $duration = $checkout_time->getTimestamp() - $checkinTime->getTimestamp();
            $duration_minutes = round($duration / 60);
            
            // Perform check-out
            $stmt = $pdo->prepare("UPDATE attendance SET check_out = NOW(), duration_minutes = ? WHERE id = ?");
            $stmt->execute([$duration_minutes, $checkIn['id']]);
            
            // Disable location tracking for this user
            updateUserLocationStatus($checkIn['user_id'], false);
            
            logActivity($checkIn['user_id'], 'check_out', "User auto checked out at end of day. Duration: $duration_minutes minutes");
        }
        
        // Log the auto checkout
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, description) VALUES (0, 'system', 'Auto checkout performed at configured time')");
        $stmt->execute();
    }
}

// Auto checkout after configured hours of activity
function autoCheckoutAfterHours() {
    global $pdo;
    
    // Check if auto checkout is enabled
    $autoCheckoutEnabled = getSettings('auto_checkout_enabled', '1') == '1';
    $autoCheckoutHours = (int)getSettings('auto_checkout_hours', '10');
    
    if (!$autoCheckoutEnabled) {
        return;
    }
    
    // Find all active check-ins
    $stmt = $pdo->prepare("
        SELECT a.*, u.id as user_id, u.role 
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE a.check_out IS NULL
    ");
    $stmt->execute();
    $activeCheckIns = $stmt->fetchAll();
    
    $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    
    foreach ($activeCheckIns as $checkIn) {
        // Skip if user is developer
        if ($checkIn['role'] === 'developer') {
            continue;
        }
        
        // Calculate how long they've been checked in
        $checkinTime = new DateTime($checkIn['check_in'], new DateTimeZone('Asia/Karachi'));
        $hoursActive = ($now->getTimestamp() - $checkinTime->getTimestamp()) / 3600;
        
        // If they've been active for more than configured hours, check them out
        if ($hoursActive >= $autoCheckoutHours) {
            // Calculate duration in minutes
            $duration_minutes = round($hoursActive * 60);
            
            // Perform check-out
            $stmt = $pdo->prepare("UPDATE attendance SET check_out = NOW(), duration_minutes = ? WHERE id = ?");
            $stmt->execute([$duration_minutes, $checkIn['id']]);
            
            // Disable location tracking for this user
            updateUserLocationStatus($checkIn['user_id'], false);
            
            logActivity($checkIn['user_id'], 'check_out', "User auto checked out after {$autoCheckoutHours}+ hours. Duration: $duration_minutes minutes");
        }
    }
}

// Function to update user's location status
function updateUserLocationStatus($user_id, $status) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE users SET is_location_enabled = ? WHERE id = ?");
    $stmt->execute([$status ? 1 : 0, $user_id]);
}

// Function to delete user and all related data
function deleteUser($user_id) {
    global $pdo;
    
    // Start transaction for data consistency
    $pdo->beginTransaction();
    
    try {
        // Check if user exists and get their role
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Developers can delete anyone, masters can only delete regular users
        $currentUserRole = getUserRole();
        if (
            ($currentUserRole === 'master' && $user['role'] === 'developer') ||
            ($currentUserRole === 'user')
        ) {
            return false;
        }
        
        // Delete user's activity logs
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Delete user's locations
        $stmt = $pdo->prepare("DELETE FROM locations WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Delete user's attendance records
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Finally delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Commit the transaction
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        // Roll back if there's an error
        $pdo->rollBack();
        return false;
    }
}

// Function to reset logs
function resetLogs() {
    global $pdo;
    
    $currentUserRole = getUserRole();
    if ($currentUserRole !== 'developer' && $currentUserRole !== 'master') {
        return false;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Delete all activity logs except system logs
        if ($currentUserRole === 'developer') {
            // Developer can delete all logs
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE 1");
        } else {
            // Master can only delete regular user logs
            $stmt = $pdo->prepare("
                DELETE al FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                WHERE u.role = 'user'
            ");
        }
        $stmt->execute();
        
        // Log this action
        $action = ($currentUserRole === 'developer') ? 'All logs reset by developer' : 'User logs reset by master';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, description) VALUES (?, 'system', ?)");
        $stmt->execute([$_SESSION['user_id'], $action]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// Function to force logout a user by their ID
function forceLogoutUser($user_id) {
    global $pdo;
    
    // If we want to implement session tracking for force logout, we'd need to store session IDs
    // For now, we'll rely on the user being checked out (which will disable location tracking)
    
    // Check if user is checked in and force checkout
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND check_out IS NULL");
    $stmt->execute([$user_id]);
    $activeCheckIn = $stmt->fetch();
    
    if ($activeCheckIn) {
        // Calculate duration
        $checkinTime = new DateTime($activeCheckIn['check_in'], new DateTimeZone('Asia/Karachi'));
        $checkout_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
        $duration = $checkout_time->getTimestamp() - $checkinTime->getTimestamp();
        $duration_minutes = round($duration / 60);
        
        // Perform check-out
        $stmt = $pdo->prepare("UPDATE attendance SET check_out = NOW(), duration_minutes = ? WHERE id = ?");
        $stmt->execute([$duration_minutes, $activeCheckIn['id']]);
        
        // Disable location tracking
        updateUserLocationStatus($user_id, false);
        
        logActivity($user_id, 'forced_logout', "User was forcibly logged out by admin");
        
        return true;
    }
    
    return false;
}

// Added function to check for direct script access for better security
function preventDirectAccess() {
    if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
        header("HTTP/1.0 403 Forbidden");
        exit('Direct access to this file is prohibited.');
    }
}

// Make sure settings table exists and initialize settings
try {
    // Initialize auto checkout settings if they don't exist
    if (!getSettings('auto_checkout_enabled')) {
        updateSettings('auto_checkout_enabled', '1');
    }
    
    if (!getSettings('auto_checkout_hours')) {
        updateSettings('auto_checkout_hours', '10');
    }
    
    if (!getSettings('auto_checkout_time')) {
        updateSettings('auto_checkout_time', '20:00');
    }
    
    // Initialize master check-in requirement setting if it doesn't exist
    if (!getSettings('master_checkin_required')) {
        updateSettings('master_checkin_required', '0');
    }
} catch (PDOException $e) {
    // Silently fail if there's an issue
}

// Call cleanup and auto checkout functions
cleanOldLocations();
autoCheckoutAtEndOfDay();
autoCheckoutAfterHours();
