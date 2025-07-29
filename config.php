
<?php
// Set Pakistani timezone as default
date_default_timezone_set('Asia/Karachi');

// Database configuration
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

// Database request counter
function incrementDbRequest($request_type = 'general', $user_id = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO db_requests (request_type, user_id, timestamp) VALUES (?, ?, NOW())");
        $stmt->execute([$request_type, $user_id]);
        
        // Update settings counter
        $stmt = $pdo->prepare("UPDATE settings SET value = value + 1 WHERE name = 'db_request_count'");
        $stmt->execute();
    } catch (Exception $e) {
        // Silent fail
    }
}

// Device tracking functions - ONLY FOR USERS
function getDeviceId() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return md5($userAgent . $ip);
}

function checkDeviceLock($user_id) {
    global $pdo;
    
    // Get user role first
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // NEVER lock master or developer accounts
    if (!$user || $user['role'] === 'master' || $user['role'] === 'developer') {
        return false;
    }
    
    // Only check device lock for regular users
    if ($user['role'] !== 'user') {
        return false;
    }
    
    incrementDbRequest('device_check', $user_id);
    
    $currentDeviceId = getDeviceId();
    $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Check if user has an active device
    $stmt = $pdo->prepare("SELECT device_id, ip_address FROM device_tracking WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$user_id]);
    $activeDevice = $stmt->fetch();
    
    if ($activeDevice && $activeDevice['device_id'] !== $currentDeviceId) {
        // User trying to login from different device
        flagUser($user_id, "Login attempt from different device. IP: $currentIp");
        return true;
    }
    
    // Check if another user is using this device
    $stmt = $pdo->prepare("SELECT u.username, u.full_name FROM device_tracking dt JOIN users u ON dt.user_id = u.id WHERE dt.device_id = ? AND dt.user_id != ? AND dt.is_active = 1");
    $stmt->execute([$currentDeviceId, $user_id]);
    $otherUser = $stmt->fetch();
    
    if ($otherUser) {
        // Another user is using this device
        flagUser($user_id, "Login attempt on device already used by: " . $otherUser['username']);
        return true;
    }
    
    // Record current device
    $stmt = $pdo->prepare("INSERT INTO device_tracking (user_id, device_id, ip_address, user_agent, login_time) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE login_time = NOW(), is_active = 1");
    $stmt->execute([$user_id, $currentDeviceId, $currentIp, $currentUserAgent]);
    
    return false;
}

function flagUser($user_id, $reason) {
    global $pdo;
    
    incrementDbRequest('flag_user', $user_id);
    
    $stmt = $pdo->prepare("UPDATE users SET device_locked = 1, flagged_reason = ? WHERE id = ?");
    $stmt->execute([$reason, $user_id]);
    
    logActivity($user_id, 'device_flag', $reason);
}

function logActivity($user_id, $activity_type, $description = "") {
    global $pdo;
    
    // Don't log developer activity
    if (isUserDeveloper($user_id)) {
        return;
    }
    
    incrementDbRequest('activity_log', $user_id);
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address, timestamp) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $activity_type, $description, $ip]);
}

function isUserDeveloper($user_id) {
    global $pdo;
    
    incrementDbRequest('user_check', $user_id);
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    return $user && $user['role'] === 'developer';
}

function getLastCheckin($user_id) {
    global $pdo;
    
    incrementDbRequest('checkin_check', $user_id);
    
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND check_out IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getSettings($name, $default = null) {
    global $pdo;
    
    incrementDbRequest('settings_get');
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = ?");
    $stmt->execute([$name]);
    $setting = $stmt->fetch();
    
    return $setting ? $setting['value'] : $default;
}

function updateSettings($name, $value) {
    global $pdo;
    
    incrementDbRequest('settings_update');
    
    $stmt = $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
    $stmt->execute([$name, $value, $value]);
    
    return true;
}

// FastAPI configuration
$fastapi_base_url = 'http://54.250.198.0:8000';

// Function to make FastAPI requests
function makeApiRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false || $httpCode !== 200) {
        return false;
    }
    
    return json_decode($response, true);
}

// Update user location status
function updateUserLocationStatus($user_id, $status) {
    global $pdo;
    
    incrementDbRequest('location_status_update', $user_id);
    
    $stmt = $pdo->prepare("UPDATE users SET is_location_enabled = ? WHERE id = ?");
    $stmt->execute([$status ? 1 : 0, $user_id]);
}

// Auto checkout functions (with Pakistani time)
function autoCheckoutAtEndOfDay() {
    global $pdo;
    
    // Get current Pakistani time
    $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    
    // Check if auto checkout is enabled
    $autoCheckoutEnabled = getSettings('auto_checkout_enabled', '1') == '1';
    $autoCheckoutTime = getSettings('auto_checkout_time', '20:00');
    
    if ($now->format('H:i') === $autoCheckoutTime && $autoCheckoutEnabled) {
        incrementDbRequest('auto_checkout');
        
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
    }
}

// Auto checkout after configured hours
function autoCheckoutAfterHours() {
    global $pdo;
    
    $autoCheckoutEnabled = getSettings('auto_checkout_enabled', '1') == '1';
    $autoCheckoutHours = (int)getSettings('auto_checkout_hours', '10');
    
    if (!$autoCheckoutEnabled) {
        return;
    }
    
    incrementDbRequest('auto_checkout_hours');
    
    // Get current Pakistani time
    $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    
    // Find all active check-ins
    $stmt = $pdo->prepare("
        SELECT a.*, u.id as user_id, u.role 
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE a.check_out IS NULL
    ");
    $stmt->execute();
    $activeCheckIns = $stmt->fetchAll();
    
    foreach ($activeCheckIns as $checkIn) {
        // Skip if user is developer
        if ($checkIn['role'] === 'developer') {
            continue;
        }
        
        // Calculate how long they've been checked in
        $checkinTime = new DateTime($checkIn['check_in'], new DateTimeZone('Asia/Karachi'));
        $hoursActive = ($now->getTimestamp() - $checkinTime->getTimestamp()) / 3600;
        
        if ($hoursActive >= $autoCheckoutHours) {
            $duration_minutes = round($hoursActive * 60);
            
            $stmt = $pdo->prepare("UPDATE attendance SET check_out = NOW(), duration_minutes = ? WHERE id = ?");
            $stmt->execute([$duration_minutes, $checkIn['id']]);
            
            updateUserLocationStatus($checkIn['user_id'], false);
            
            logActivity($checkIn['user_id'], 'check_out', "User auto checked out after {$autoCheckoutHours}+ hours. Duration: $duration_minutes minutes");
        }
    }
}

// Function to get attendance data for charts
function getAttendanceData($start_date = null, $end_date = null) {
    global $pdo;
    
    incrementDbRequest('attendance_data');
    
    $sql = "
        SELECT 
            DATE(a.check_in) as date,
            u.full_name,
            u.username,
            COUNT(*) as total_checkins,
            SUM(CASE WHEN a.check_out IS NOT NULL THEN a.duration_minutes ELSE 0 END) as total_minutes
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE u.role = 'user'
    ";
    
    $params = [];
    
    if ($start_date && $end_date) {
        $sql .= " AND DATE(a.check_in) BETWEEN ? AND ?";
        $params = [$start_date, $end_date];
    }
    
    $sql .= " GROUP BY DATE(a.check_in), u.id ORDER BY date DESC, u.full_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// Initialize settings if they don't exist
try {
    if (!getSettings('app_name')) {
        updateSettings('app_name', 'SmartOutreach');
    }
    
    if (!getSettings('default_position')) {
        updateSettings('default_position', 'Research Specialist');
    }
    
    if (!getSettings('location_update_interval')) {
        updateSettings('location_update_interval', '300');
    }
    
    if (!getSettings('db_request_count')) {
        updateSettings('db_request_count', '0');
    }
    
    if (!getSettings('master_checkin_required')) {
        updateSettings('master_checkin_required', '0');
    }
} catch (PDOException $e) {
    // Silently fail if there's an issue
}

// Call auto checkout functions
autoCheckoutAtEndOfDay();
autoCheckoutAfterHours();
