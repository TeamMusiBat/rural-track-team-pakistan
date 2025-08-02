
<?php
require_once 'config.php';
require_once 'location_utils.php';

// Get admin data for different tabs
function getAdminData($pdo) {
    $data = [];
    
    // Get all users
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY role, full_name");
    $stmt->execute();
    $data['users'] = $stmt->fetchAll();
    
    // Get all checked-in users
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.role, u.user_role, u.is_location_enabled, a.check_in, a.id AS attendance_id
        FROM users u
        JOIN attendance a ON u.id = a.user_id
        WHERE a.check_out IS NULL
        ORDER BY a.check_in DESC
    ");
    $stmt->execute();
    $data['activeUsers'] = $stmt->fetchAll();
    
    // Get locations for map view
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name, u.username, u.role, u.is_location_enabled, u.user_role
        FROM locations l
        JOIN users u ON l.user_id = u.id
        WHERE l.id IN (
            SELECT MAX(id) 
            FROM locations 
            GROUP BY user_id
        )
        AND u.role != 'developer'
    ");
    $stmt->execute();
    $data['locations'] = $stmt->fetchAll();
    
    // Get recent activity logs (exclude developer activities)
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name, u.username, u.role 
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE (u.role != 'developer' OR u.role IS NULL)
        ORDER BY l.timestamp DESC
        LIMIT 100
    ");
    $stmt->execute();
    $data['logs'] = $stmt->fetchAll();
    
    // Get attendance records (exclude developer records)
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name, u.username, u.role, u.user_role
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE u.role != 'developer'
        ORDER BY a.check_in DESC
        LIMIT 100
    ");
    $stmt->execute();
    $data['attendance'] = $stmt->fetchAll();
    
    return $data;
}

// Get admin settings
function getAdminSettings() {
    $settings = [];
    
    // Get auto checkout settings
    $settings['autoCheckoutEnabled'] = getSettings('auto_checkout_enabled', '1') == '1';
    $settings['autoCheckoutHours'] = getSettings('auto_checkout_hours', '10');
    $settings['autoCheckoutTime'] = getSettings('auto_checkout_time', '20:00');
    
    // Get master checkin requirement setting
    $settings['masterCheckinRequired'] = getSettings('master_checkin_required', '0') == '1';
    
    // Convert auto checkout time to AM/PM format for display
    $timeObj = DateTime::createFromFormat('H:i', $settings['autoCheckoutTime']);
    $settings['autoCheckoutTimeDisplay'] = $timeObj ? $timeObj->format('h:i A') : '08:00 PM';
    
    return $settings;
}

// Get messages and errors
function getAdminMessages() {
    $message = '';
    if (isset($_GET['msg'])) {
        switch ($_GET['msg']) {
            case 'imei_reset':
                $message = 'Device ID reset successfully.';
                break;
            case 'user_created':
                $message = 'New user created successfully.';
                break;
            case 'user_deleted':
                $message = 'User deleted successfully.';
                break;
            case 'locations_reset':
                $message = 'Location history has been reset.';
                break;
            case 'settings_updated':
                $message = 'Settings updated successfully.';
                break;
            case 'logs_reset':
                $message = 'Activity logs have been reset.';
                break;
        }
    }
    
    $error = '';
    if (isset($_GET['error'])) {
        switch ($_GET['error']) {
            case 'missing_fields':
                $error = 'Please fill all required fields.';
                break;
            case 'username_taken':
                $error = 'Username already exists. Please choose another.';
                break;
            case 'cannot_delete_master':
                $error = 'Only developer can delete master users.';
                break;
            case 'user_not_found':
                $error = 'User not found.';
                break;
            case 'permission_denied':
                $error = 'You do not have permission to perform this action.';
                break;
        }
    }
    
    return ['message' => $message, 'error' => $error];
}
