
<?php
require_once 'config.php';
require_once 'location_utils.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

// Set the default tab from URL or default to dashboard
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Handle auto checkout setting toggle
if (isset($_POST['update_auto_checkout'])) {
    $enabled = isset($_POST['auto_checkout_enabled']) ? '1' : '0';
    $hours = $_POST['auto_checkout_hours'] ?? '10';
    $time = $_POST['auto_checkout_time'] ?? '20:00';
    
    updateSettings('auto_checkout_enabled', $enabled);
    updateSettings('auto_checkout_hours', $hours);
    updateSettings('auto_checkout_time', $time);
    
    // Redirect to settings tab
    redirect('admin.php?tab=settings&msg=settings_updated');
}

// Handle master checkin requirement setting toggle
if (isset($_POST['update_master_checkin'])) {
    $enabled = isset($_POST['master_checkin_required']) ? '1' : '0';
    
    updateSettings('master_checkin_required', $enabled);
    
    // Redirect to settings tab
    redirect('admin.php?tab=settings&msg=settings_updated');
}

// Handle delete user
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $userId = $_POST['user_id'];
    
    // Check if the user exists and is not a developer (only developers can delete anyone)
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Only developer can delete master users
        if ($user['role'] === 'master' && !isDeveloper()) {
            redirect('admin.php?tab=users&error=cannot_delete_master');
        }
        
        // Force logout the user if they're logged in
        forceLogoutUser($userId);
        
        // Delete all user data
        $stmt = $pdo->prepare("DELETE FROM locations WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        $adminId = $_SESSION['user_id'];
        logActivity($adminId, 'system', "Admin deleted user ID: $userId");
        
        redirect('admin.php?tab=users&msg=user_deleted');
    } else {
        redirect('admin.php?tab=users&error=user_not_found');
    }
}

// Handle reset logs
if (isset($_POST['reset_logs'])) {
    if (!isDeveloper() && !isAdmin()) {
        redirect('admin.php?tab=settings&error=permission_denied');
    }
    
    // Truncate logs table
    $stmt = $pdo->prepare("TRUNCATE TABLE activity_logs");
    $stmt->execute();
    
    $adminId = $_SESSION['user_id'];
    
    // Add a single log entry about the reset
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, description) VALUES (?, 'system', 'All activity logs were reset')");
    $stmt->execute([$adminId]);
    
    redirect('admin.php?tab=activity&msg=logs_reset');
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Reset IMEI
        if ($_POST['action'] === 'reset_imei' && isset($_POST['user_id'])) {
            $userId = $_POST['user_id'];
            
            $stmt = $pdo->prepare("UPDATE users SET imei = NULL WHERE id = ?");
            $stmt->execute([$userId]);
            
            $adminId = $_SESSION['user_id'];
            logActivity($adminId, 'system', "Admin reset IMEI for user ID: $userId");
            
            // Redirect to refresh
            redirect('admin.php?tab=users&msg=imei_reset');
        }
        
        // Create new user
        else if ($_POST['action'] === 'create_user') {
            $fullName = $_POST['full_name'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $userRole = $_POST['user_role'] ?? 'Research Specialist';
            
            // Validate
            if (empty($fullName) || empty($username) || empty($password)) {
                redirect('admin.php?tab=users&error=missing_fields');
            }
            
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                redirect('admin.php?tab=users&error=username_taken');
            }
            
            // Create user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, email, phone, role, user_role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fullName, $username, $hashedPassword, $email, $phone, $role, $userRole]);
            
            $adminId = $_SESSION['user_id'];
            logActivity($adminId, 'system', "Admin created new user: $username with role: $role, user_role: $userRole");
            
            redirect('admin.php?tab=users&msg=user_created');
        }
        
        // Reset location history
        else if ($_POST['action'] === 'reset_locations') {
            // Delete all location history
            $stmt = $pdo->prepare("TRUNCATE TABLE locations");
            $stmt->execute();
            
            $adminId = $_SESSION['user_id'];
            logActivity($adminId, 'system', "Admin manually reset all location history");
            
            redirect('admin.php?tab=settings&msg=locations_reset');
        }
    }
}

// Get auto checkout settings
$autoCheckoutEnabled = getSettings('auto_checkout_enabled', '1') == '1';
$autoCheckoutHours = getSettings('auto_checkout_hours', '10');
$autoCheckoutTime = getSettings('auto_checkout_time', '20:00');

// Get master checkin requirement setting
$masterCheckinRequired = getSettings('master_checkin_required', '0') == '1';

// Convert auto checkout time to AM/PM format for display
$timeObj = DateTime::createFromFormat('H:i', $autoCheckoutTime);
$autoCheckoutTimeDisplay = $timeObj ? $timeObj->format('h:i A') : '08:00 PM';

// Get all users
$stmt = $pdo->prepare("SELECT * FROM users ORDER BY role, full_name");
$stmt->execute();
$users = $stmt->fetchAll();

// Get all checked-in users
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.username, u.role, u.user_role, u.is_location_enabled, a.check_in, a.id AS attendance_id
    FROM users u
    JOIN attendance a ON u.id = a.user_id
    WHERE a.check_out IS NULL
    ORDER BY a.check_in DESC
");
$stmt->execute();
$activeUsers = $stmt->fetchAll();

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
$locations = $stmt->fetchAll();

// Get message/error from URL
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
$logs = $stmt->fetchAll();

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
$attendance = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartOutreach - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: #f8fafc;
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background-color: #4f46e5;
            color: white;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 22px;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            background-color: rgba(255,255,255,0.2);
            border-radius: 8px;
            transition: background-color 0.2s;
        }
        
        .back-link:hover {
            background-color: rgba(255,255,255,0.3);
        }
        
        .back-link i {
            margin-right: 6px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }
        
        .message {
            padding: 12px 16px;
            margin-bottom: 24px;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .message i {
            margin-right: 12px;
            font-size: 16px;
        }
        
        .message-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .message-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .tabs {
            display: flex;
            overflow-x: auto;
            background-color: #f8fafc;
            border-bottom: 1px solid #eee;
        }
        
        .tab {
            padding: 16px 20px;
            cursor: pointer;
            font-weight: 500;
            color: #64748b;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .tab i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        .tab:hover {
            color: #4f46e5;
        }
        
        .tab.active {
            color: #4f46e5;
            border-bottom-color: #4f46e5;
            background-color: rgba(79, 70, 229, 0.05);
        }
        
        .tab-content {
            display: none;
            padding: 24px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: #4f46e5;
        }
        
        .section-note {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 16px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            font-weight: 600;
            color: #334155;
            background-color: #f8fafc;
        }
        
        tr:hover {
            background-color: #f8fafc;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn i {
            margin-right: 6px;
        }
        
        .btn:hover {
            background-color: #4338ca;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn-danger {
            background-color: #ef4444;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .btn-success {
            background-color: #10b981;
        }
        
        .btn-success:hover {
            background-color: #059669;
        }
        
        .btn-warning {
            background-color: #f59e0b;
        }
        
        .btn-warning:hover {
            background-color: #d97706;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #4f46e5;
            color: #4f46e5;
        }
        
        .btn-outline:hover {
            background-color: #f0f4ff;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #334155;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="password"],
        input[type="email"],
        input[type="number"],
        input[type="time"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background-color: #f8fafc;
            transition: all 0.2s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        input[type="number"]:focus,
        input[type="time"]:focus,
        select:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
            background-color: #fff;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #10b981;
        }
        
        input:focus + .toggle-slider {
            box-shadow: 0 0 1px #10b981;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        #map {
            height: 500px;
            width: 100%;
            border-radius: 8px;
            margin-bottom: 24px;
            border: 1px solid #ddd;
        }
        
        .user-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #4f46e5;
            color: white;
            font-size: 16px;
            border: 2px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .user-popup {
            min-width: 200px;
        }
        
        .user-popup-header {
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
        }
        
        .user-popup-info {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        
        .settings-card {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #eee;
        }
        
        .settings-title {
            font-weight: 600;
            margin-bottom: 12px;
            color: #333;
            font-size: 16px;
            display: flex;
            align-items: center;
        }
        
        .settings-title i {
            margin-right: 8px;
            color: #4f46e5;
        }
        
        .settings-description {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 16px;
            line-height: 1.5;
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .toggle-text {
            font-weight: 500;
            font-size: 14px;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #dcfce7;
            color: #16a34a;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #d97706;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #dc2626;
        }
        
        .badge i {
            margin-right: 4px;
            font-size: 10px;
        }
        
        .location-status {
            display: flex;
            align-items: center;
            font-size: 13px;
        }
        
        .location-status i {
            margin-right: 6px;
        }
        
        .status-enabled {
            color: #16a34a;
        }
        
        .status-disabled {
            color: #dc2626;
        }
        
        .user-card {
            display: flex;
            flex-direction: column;
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #eee;
        }
        
        .user-card-header {
            padding: 15px;
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        
        .user-card-body {
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .user-card-footer {
            padding: 10px 15px;
            background-color: #f8fafc;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
        }
        
        .user-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #64748b;
        }
        
        .user-card-row strong {
            color: #334155;
        }
        
        .user-card-location {
            font-size: 13px;
            color: #64748b;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #eee;
            line-height: 1.4;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
            
            .tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            
            .tab {
                padding: 14px 16px;
                font-size: 14px;
            }
            
            .tab-content {
                padding: 16px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            #map {
                height: 350px;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
        
        /* Responsive form fields */
        @media (max-width: 480px) {
            th, td {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .btn-small {
                padding: 4px 8px;
                font-size: 12px;
            }
        }
        
        /* User Card Grid */
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        
        @media (max-width: 640px) {
            .user-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-shield-alt"></i> Admin Dashboard</h1>
        <div class="header-actions">
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="tabs">
                <a href="?tab=dashboard" class="tab <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="?tab=users" class="tab <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="?tab=tracking" class="tab <?php echo $activeTab === 'tracking' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marked-alt"></i> Location Tracking
                </a>
                <a href="?tab=activity" class="tab <?php echo $activeTab === 'activity' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Activity Logs
                </a>
                <a href="?tab=attendance" class="tab <?php echo $activeTab === 'attendance' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Attendance
                </a>
                <a href="?tab=settings" class="tab <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
            
            <!-- Dashboard Tab -->
            <div class="tab-content <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>" id="dashboard-tab">
                <div class="section-title">
                    <i class="fas fa-users"></i> All Users Status
                </div>
                
                <div class="user-grid">
                    <?php foreach ($users as $user): ?>
                    <?php
                        // Skip developers
                        if ($user['role'] === 'developer') continue;
                        
                        // Check if user is checked in
                        $stmt = $pdo->prepare("
                            SELECT * FROM attendance 
                            WHERE user_id = ? 
                            ORDER BY check_in DESC 
                            LIMIT 1
                        ");
                        $stmt->execute([$user['id']]);
                        $lastAttendance = $stmt->fetch();
                        
                        $isCheckedIn = $lastAttendance && empty($lastAttendance['check_out']);
                        
                        // Get user's last location
                        $locationData = getUserLastLocation($user['id']);
                        
                        // Get user's last activity time
                        $stmt = $pdo->prepare("
                            SELECT MAX(timestamp) as last_update 
                            FROM activity_logs 
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$user['id']]);
                        $lastActivity = $stmt->fetch();
                        $lastUpdateTime = $lastActivity && $lastActivity['last_update'] ? 
                            date('h:i A', strtotime($lastActivity['last_update'])) : 'N/A';
                    ?>
                    <div class="user-card">
                        <div class="user-card-header">
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </div>
                        <div class="user-card-body">
                            <div class="user-card-row">
                                <strong>Location:</strong> 
                                <?php if ($user['is_location_enabled']): ?>
                                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Enabled</span>
                                <?php else: ?>
                                <span class="badge badge-warning"><i class="fas fa-times-circle"></i> Disabled</span>
                                <?php endif; ?>
                            </div>
                            <div class="user-card-row">
                                <strong>Status:</strong> 
                                <?php if ($isCheckedIn): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i> Checked In (<?php echo date('h:i A', strtotime($lastAttendance['check_in'])); ?>)
                                </span>
                                <?php else: ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-times-circle"></i> Checked Out
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="user-card-row">
                                <strong>Last Update:</strong> <?php echo $lastUpdateTime; ?>
                            </div>
                            <?php if ($locationData): ?>
                            <div class="user-card-location">
                                <?php echo htmlspecialchars($locationData['address'] ?? 'Unknown location'); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="user-card-footer">
                            <?php if ($locationData): ?>
                            <a href="https://www.google.com/maps?q=<?php echo $locationData['latitude']; ?>,<?php echo $locationData['longitude']; ?>" 
                               class="btn btn-small" 
                               target="_blank"
                               id="map-link-user-<?php echo $user['id']; ?>">
                                <i class="fas fa-map-marker-alt"></i> View On Map
                            </a>
                            <?php else: ?>
                            <span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> No Location Data</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($activeUsers) > 0): ?>
                <div class="section-title" style="margin-top: 30px;">
                    <i class="fas fa-user-clock"></i> Currently Active Users (<?php echo count($activeUsers); ?>)
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>User Role</th>
                            <th>Location Status</th>
                            <th>Checked In At</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeUsers as $user): ?>
                        <?php
                            // Skip displaying developer activity
                            if ($user['role'] === 'developer') continue;
                            
                            // Calculate check-in duration
                            $checkin_time = new DateTime($user['check_in'], new DateTimeZone('Asia/Karachi'));
                            $current_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
                            $interval = $current_time->diff($checkin_time);
                            
                            $hours = $interval->h + ($interval->days * 24);
                            $minutes = $interval->i;
                            
                            if ($hours > 0) {
                                $duration = $hours . ' hour' . ($hours != 1 ? 's' : '') . ', ' . $minutes . ' min' . ($minutes != 1 ? 's' : '');
                            } else {
                                $duration = $minutes . ' min' . ($minutes != 1 ? 's' : '');
                            }
                            
                            // Get user's last location
                            $locationData = getUserLastLocation($user['id']);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($user['role'])); ?></td>
                            <td><?php echo htmlspecialchars($user['user_role'] ?? 'Research Specialist'); ?></td>
                            <td>
                                <?php if ($user['is_location_enabled']): ?>
                                <div class="location-status status-enabled">
                                    <i class="fas fa-map-marker-alt"></i> Enabled
                                </div>
                                <?php else: ?>
                                <div class="location-status status-disabled">
                                    <i class="fas fa-map-marker-alt"></i> Disabled
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('h:i A', strtotime($user['check_in'])); ?> on <?php echo date('d M Y', strtotime($user['check_in'])); ?></td>
                            <td><?php echo $duration; ?></td>
                            <td>
                                <?php if ($locationData): ?>
                                <a href="https://www.google.com/maps?q=<?php echo $locationData['latitude']; ?>,<?php echo $locationData['longitude']; ?>" 
                                   class="btn btn-small" 
                                   target="_blank"
                                   id="active-map-link-user-<?php echo $user['id']; ?>">
                                    <i class="fas fa-map-marker-alt"></i> View On Map
                                </a>
                                <?php else: ?>
                                <span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> No Location</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <!-- Manage Users Tab -->
            <div class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>" id="users-tab">
                <div class="section-title">
                    <i class="fas fa-user-plus"></i> Create New User
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email (Optional)</label>
                            <input type="email" id="email" name="email">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone (Optional)</label>
                            <input type="text" id="phone" name="phone">
                        </div>
                        
                        <div class="form-group">
                            <label for="role">System Role</label>
                            <select id="role" name="role">
                                <option value="user">User</option>
                                <?php if ($_SESSION['role'] === 'developer' || $_SESSION['role'] === 'master'): ?>
                                <option value="master">Master</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_role">User Role (Job Title)</label>
                            <input type="text" id="user_role" name="user_role" value="Research Specialist">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-user-plus"></i> Create User
                    </button>
                </form>
                
                <div class="section-title" style="margin-top: 30px;">
                    <i class="fas fa-users"></i> All Users
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>User Role</th>
                            <th>Location Status</th>
                            <th>Device ID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <?php 
                            // Always show all users to developer, but for masters, don't show developers
                            if ($_SESSION['role'] === 'master' && $user['role'] === 'developer') continue;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($user['role'])); ?></td>
                            <td><?php echo htmlspecialchars($user['user_role'] ?? 'Research Specialist'); ?></td>
                            <td>
                                <?php if ($user['is_location_enabled']): ?>
                                <div class="location-status status-enabled">
                                    <i class="fas fa-map-marker-alt"></i> Enabled
                                </div>
                                <?php else: ?>
                                <div class="location-status status-disabled">
                                    <i class="fas fa-map-marker-alt"></i> Disabled
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($user['imei'])): ?>
                                <span style="color: #999;">Not set</span>
                                <?php else: ?>
                                <span title="<?php echo htmlspecialchars($user['imei']); ?>"><?php echo substr($user['imei'], 0, 8); ?>...</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <?php if (!empty($user['imei'])): ?>
                                    <form method="POST" action="" style="display: inline-block; margin-right: 5px;">
                                        <input type="hidden" name="action" value="reset_imei">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-warning" onclick="return confirm('Are you sure you want to reset this Device ID?')">
                                            <i class="fas fa-redo-alt"></i> Reset Device
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Only developers can delete masters
                                    $canDelete = true;
                                    if ($user['role'] === 'master' && $_SESSION['role'] !== 'developer') {
                                        $canDelete = false;
                                    }
                                    // Can't delete self or developers unless you're a developer
                                    if ($user['id'] === $_SESSION['user_id'] || 
                                        ($user['role'] === 'developer' && $_SESSION['role'] !== 'developer')) {
                                        $canDelete = false;
                                    }
                                    
                                    if ($canDelete):
                                    ?>
                                    <form method="POST" action="" style="display: inline-block;">
                                        <input type="hidden" name="delete_user" value="1">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone!')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Location Tracking Tab -->
            <div class="tab-content <?php echo $activeTab === 'tracking' ? 'active' : ''; ?>" id="tracking-tab">
                <div class="section-title">
                    <i class="fas fa-map-marked-alt"></i> Live Location Map
                </div>
                
                <div class="section-note">
                    This map shows the current locations of all checked-in users. Only non-developer users are shown.
                </div>
                
                <div id="map"></div>
                
                <div class="section-title">
                    <i class="fas fa-history"></i> Recent Location History
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>User Role</th>
                            <th>Last Location</th>
                            <th>Address</th>
                            <th>Updated At</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $location): ?>
                        <?php 
                            // Skip displaying developer activity
                            if (isUserDeveloper($location['user_id'])) continue;
                            
                            // Check if user is checked in
                            $stmt = $pdo->prepare("
                                SELECT id FROM attendance 
                                WHERE user_id = ? AND check_out IS NULL
                                ORDER BY check_in DESC LIMIT 1
                            ");
                            $stmt->execute([$location['user_id']]);
                            $isCheckedIn = $stmt->rowCount() > 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($location['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($location['user_role'] ?? 'Research Specialist'); ?></td>
                            <td><?php echo round($location['latitude'], 6); ?>, <?php echo round($location['longitude'], 6); ?></td>
                            <td><?php echo htmlspecialchars($location['address'] ?? 'Unknown location'); ?></td>
                            <td><?php echo date('h:i A', strtotime($location['timestamp'])); ?> on <?php echo date('d M Y', strtotime($location['timestamp'])); ?></td>
                            <td>
                                <?php if ($isCheckedIn): ?>
                                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>
                                <?php else: ?>
                                <span class="badge badge-warning"><i class="fas fa-clock"></i> Checked Out</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="https://www.google.com/maps?q=<?php echo $location['latitude']; ?>,<?php echo $location['longitude']; ?>" 
                                   class="btn btn-small" 
                                   target="_blank"
                                   id="history-map-link-user-<?php echo $location['user_id']; ?>">
                                    <i class="fas fa-external-link-alt"></i> View in Google Maps
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Activity Logs Tab -->
            <div class="tab-content <?php echo $activeTab === 'activity' ? 'active' : ''; ?>" id="activity-tab">
                <div class="section-title">
                    <i class="fas fa-list"></i> Activity Logs
                </div>
                
                <div class="section-note">
                    Displaying recent activity logs. Developer activities are hidden.
                </div>
                
                <?php if (isDeveloper() || ($_SESSION['role'] === 'master')): ?>
                <form method="POST" action="" class="mb-4" style="margin-bottom: 20px;">
                    <input type="hidden" name="reset_logs" value="1">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset all activity logs? This action cannot be undone!')">
                        <i class="fas fa-trash"></i> Reset All Activity Logs
                    </button>
                </form>
                <?php endif; ?>
                
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Activity</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('h:i A', strtotime($log['timestamp'])); ?> on <?php echo date('d M Y', strtotime($log['timestamp'])); ?></td>
                            <td>
                                <?php if ($log['user_id'] == 0): ?>
                                    <span>System</span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($log['full_name']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo str_replace('_', ' ', ucfirst($log['activity_type'])); ?></td>
                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Attendance Tab -->
            <div class="tab-content <?php echo $activeTab === 'attendance' ? 'active' : ''; ?>" id="attendance-tab">
                <div class="section-title">
                    <i class="fas fa-clock"></i> Attendance History
                </div>
                
                <div class="section-note">
                    Displaying recent attendance records. Developer activities are hidden.
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>User Role</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['user_role'] ?? 'Research Specialist'); ?></td>
                            <td><?php echo date('h:i A', strtotime($record['check_in'])); ?> on <?php echo date('d M Y', strtotime($record['check_in'])); ?></td>
                            <td>
                                <?php if (empty($record['check_out'])): ?>
                                    <span class="badge badge-success"><i class="fas fa-user-clock"></i> Currently Active</span>
                                <?php else: ?>
                                    <?php echo date('h:i A', strtotime($record['check_out'])); ?> on <?php echo date('d M Y', strtotime($record['check_out'])); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($record['check_out'])): ?>
                                    <?php
                                    // Calculate ongoing duration
                                    $checkin_time = new DateTime($record['check_in'], new DateTimeZone('Asia/Karachi'));
                                    $current_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
                                    $interval = $current_time->diff($checkin_time);
                                    
                                    $hours = $interval->h + ($interval->days * 24);
                                    $minutes = $interval->i;
                                    
                                    if ($hours > 0) {
                                        echo "$hours hour" . ($hours != 1 ? "s" : "") . ", $minutes minute" . ($minutes != 1 ? "s" : "");
                                    } else {
                                        echo "$minutes minute" . ($minutes != 1 ? "s" : "");
                                    }
                                    ?>
                                <?php else: ?>
                                    <?php 
                                    $minutes = $record['duration_minutes'];
                                    if ($minutes < 60) {
                                        echo "$minutes minutes";
                                    } else {
                                        $hours = floor($minutes / 60);
                                        $mins = $minutes % 60;
                                        echo "$hours hour" . ($hours != 1 ? "s" : "") . ($mins > 0 ? ", $mins minutes" : "");
                                    }
                                    ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Settings Tab -->
            <div class="tab-content <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" id="settings-tab">
                <div class="section-title">
                    <i class="fas fa-cog"></i> System Settings
                </div>
                
                <div class="settings-grid">
                    <div class="settings-card">
                        <div class="settings-title">
                            <i class="fas fa-clock"></i> Automatic Checkout Settings
                        </div>
                        <div class="settings-description">
                            Configure automatic checkout behavior. Users will be checked out after the specified number of hours or at the specified time (Pakistan time), whichever comes first.
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="update_auto_checkout" value="1">
                            
                            <div class="toggle-label" style="margin-bottom: 16px;">
                                <span class="toggle-text">Auto Checkout Enabled</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="auto_checkout_enabled" <?php echo $autoCheckoutEnabled ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label for="auto_checkout_hours">Auto-checkout after (hours):</label>
                                <input type="number" name="auto_checkout_hours" id="auto_checkout_hours" min="1" max="24" value="<?php echo $autoCheckoutHours; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="auto_checkout_time">Auto-checkout time (Pakistan):</label>
                                <input type="time" name="auto_checkout_time" id="auto_checkout_time" value="<?php echo $autoCheckoutTime; ?>">
                                <div class="text-sm text-gray-500 mt-1" style="font-size: 12px; color: #64748b; margin-top: 4px;">
                                    Current setting: <?php echo $autoCheckoutTimeDisplay; ?> Pakistan time
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success" style="margin-top: 10px;">
                                <i class="fas fa-save"></i> Update Auto-Checkout Settings
                            </button>
                        </form>
                    </div>
                    
                    <?php if (isDeveloper()): ?>
                    <div class="settings-card">
                        <div class="settings-title">
                            <i class="fas fa-user-shield"></i> Master Users Check-in Requirement
                        </div>
                        <div class="settings-description">
                            Configure whether master users need to check in and out. By default, master users do not need to check in.
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="update_master_checkin" value="1">
                            
                            <div class="toggle-label" style="margin-bottom: 16px;">
                                <span class="toggle-text">Require Masters to Check In</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="master_checkin_required" <?php echo $masterCheckinRequired ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                                    <?php if ($masterCheckinRequired): ?>
                                    Master users currently need to check in/out like regular users.
                                    <?php else: ?>
                                    Master users currently do not need to check in/out.
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success" style="margin-top: 10px;">
                                <i class="fas fa-save"></i> Update Master Check-in Setting
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <div class="settings-card">
                        <div class="settings-title">
                            <i class="fas fa-trash-alt"></i> Data Cleanup
                        </div>
                        <div class="settings-description">
                            Reset all location history data. This action cannot be undone. Very old location data (older than 90 days) is automatically reset at midnight Pakistan time.
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="reset_locations">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset all location history? This cannot be undone.')">
                                <i class="fas fa-trash-alt"></i> Reset All Location History
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map for location tracking
            let map = null;
            let markers = [];
            
            <?php if ($activeTab === 'tracking'): ?>
            initializeMap();
            <?php endif; ?>
            
            // Auto-refresh dashboard data
            <?php if ($activeTab === 'dashboard'): ?>
            setInterval(updateDashboardData, 10000);  // Update every 10 seconds
            <?php endif; ?>
            
            // Function to update dashboard data without page reload
            function updateDashboardData() {
                // Fetch latest user locations and statuses
                fetch('get_locations.php')
                    .then(response => response.json())
                    .then(locations => {
                        // Update Google Maps links for each user
                        locations.forEach(location => {
                            const mapLinkElement = document.getElementById(`map-link-user-${location.user_id}`);
                            const activeMapLinkElement = document.getElementById(`active-map-link-user-${location.user_id}`);
                            
                            if (mapLinkElement) {
                                mapLinkElement.href = `https://www.google.com/maps?q=${location.latitude},${location.longitude}`;
                            }
                            
                            if (activeMapLinkElement) {
                                activeMapLinkElement.href = `https://www.google.com/maps?q=${location.latitude},${location.longitude}`;
                            }
                        });
                    })
                    .catch(error => console.error('Error updating dashboard data:', error));
            }
            
            // Function to initialize map
            function initializeMap() {
                if (document.getElementById('map')) {
                    // Create a map centered on Pakistan
                    map = L.map('map').setView([30.3753, 69.3451], 5);
                    
                    // Add OpenStreetMap tiles
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(map);
                    
                    // Add markers for each active user
                    addUserMarkers();
                    
                    // Set up refresh interval (every 15 seconds)
                    setInterval(function() {
                        fetch('get_locations.php')
                            .then(response => response.json())
                            .then(data => {
                                // Update markers with new data
                                updateMarkers(data);
                            })
                            .catch(error => console.error('Error fetching locations:', error));
                    }, 15000);
                }
            }
            
            // Function to add user markers to the map
            function addUserMarkers() {
                // Clear existing markers
                if (markers.length > 0) {
                    markers.forEach(marker => map.removeLayer(marker));
                    markers = [];
                }
                
                // Get location data
                <?php
                echo "const locationData = " . json_encode($locations) . ";\n";
                
                // Get additional user data (for active status)
                echo "const activeUsers = " . json_encode($activeUsers) . ";\n";
                ?>
                
                // Function to check if user is active
                function isUserActive(userId) {
                    return activeUsers.some(user => user.id == userId);
                }
                
                // Add markers for users (excluding developers)
                locationData.forEach(location => {
                    // Skip developers
                    if (location.role === 'developer') return;
                    
                    const isActive = isUserActive(location.user_id);
                    const markerColor = isActive ? '#10b981' : '#64748b';
                    
                    // Create a custom marker icon with user's initials
                    const fullName = location.full_name || 'User';
                    const initials = fullName.split(' ').map(n => n[0]).join('').toUpperCase();
                    
                    const markerHtml = `
                        <div class="user-marker" style="background-color: ${markerColor};">
                            ${initials}
                        </div>
                    `;
                    
                    // Custom marker icon
                    const markerIcon = L.divIcon({
                        className: '', // Remove default class
                        html: markerHtml,
                        iconSize: [36, 36],
                        iconAnchor: [18, 18]
                    });
                    
                    // Create marker
                    const marker = L.marker([location.latitude, location.longitude], {
                        icon: markerIcon,
                        title: fullName // Shows full name on hover
                    }).addTo(map);
                    
                    // Format timestamp to local time
                    const timestamp = new Date(location.timestamp);
                    const formattedTime = timestamp.toLocaleString('en-US', { 
                        hour: 'numeric', 
                        minute: 'numeric',
                        hour12: true,
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric',
                        timeZone: 'Asia/Karachi'
                    });
                    
                    // Add popup with user info
                    marker.bindPopup(`
                        <div class="user-popup">
                            <div class="user-popup-header">${location.full_name}</div>
                            <div class="user-popup-info">Role: ${location.user_role || 'Research Specialist'}</div>
                            <div class="user-popup-info">Status: ${isActive ? '<span style="color: #10b981; font-weight: 500;">Active</span>' : '<span style="color: #64748b;">Inactive</span>'}</div>
                            <div class="user-popup-info">Location: ${location.address || 'Unknown location'}</div>
                            <div class="user-popup-info">Last updated: ${formattedTime}</div>
                            <div class="user-popup-info">Coordinates: ${parseFloat(location.latitude).toFixed(6)}, ${parseFloat(location.longitude).toFixed(6)}</div>
                            <div style="margin-top: 10px;">
                                <a href="https://www.google.com/maps?q=${location.latitude},${location.longitude}" 
                                   class="btn btn-small" 
                                   target="_blank"
                                   style="font-size: 12px; padding: 4px 8px;">
                                    <i class="fas fa-external-link-alt"></i> Open in Google Maps
                                </a>
                            </div>
                        </div>
                    `);
                    
                    // Store marker for later reference
                    markers.push(marker);
                });
                
                // If we have markers, fit map to show all markers
                if (markers.length > 0) {
                    const group = new L.featureGroup(markers);
                    map.fitBounds(group.getBounds().pad(0.2));
                }
            }
            
            // Function to update markers with new data
            function updateMarkers(locationData) {
                // Clear existing markers
                if (markers.length > 0) {
                    markers.forEach(marker => map.removeLayer(marker));
                    markers = [];
                }
                
                // Add new markers
                locationData.forEach(location => {
                    // Skip developers
                    if (location.role === 'developer') return;
                    
                    const isActive = location.is_checked_in;
                    const markerColor = isActive ? '#10b981' : '#64748b';
                    
                    // Create a custom marker icon with user's initials
                    const fullName = location.full_name || 'User';
                    const initials = fullName.split(' ').map(n => n[0]).join('').toUpperCase();
                    
                    const markerHtml = `
                        <div class="user-marker" style="background-color: ${markerColor};">
                            ${initials}
                        </div>
                    `;
                    
                    // Custom marker icon
                    const markerIcon = L.divIcon({
                        className: '', // Remove default class
                        html: markerHtml,
                        iconSize: [36, 36],
                        iconAnchor: [18, 18]
                    });
                    
                    // Create marker
                    const marker = L.marker([location.latitude, location.longitude], {
                        icon: markerIcon,
                        title: fullName // Shows name on hover
                    }).addTo(map);
                    
                    // Add popup with user info
                    marker.bindPopup(`
                        <div class="user-popup">
                            <div class="user-popup-header">${location.full_name}</div>
                            <div class="user-popup-info">Role: ${location.user_role || 'Research Specialist'}</div>
                            <div class="user-popup-info">Status: ${location.is_checked_in ? '<span style="color: #10b981; font-weight: 500;">Active</span>' : '<span style="color: #64748b;">Inactive</span>'}</div>
                            <div class="user-popup-info">Location: ${location.address || 'Unknown location'}</div>
                            <div class="user-popup-info">Last updated: ${location.formatted_time}</div>
                            <div class="user-popup-info">Coordinates: ${parseFloat(location.latitude).toFixed(6)}, ${parseFloat(location.longitude).toFixed(6)}</div>
                            <div style="margin-top: 10px;">
                                <a href="https://www.google.com/maps?q=${location.latitude},${location.longitude}" 
                                   class="btn btn-small" 
                                   target="_blank"
                                   style="font-size: 12px; padding: 4px 8px;">
                                    <i class="fas fa-external-link-alt"></i> Open in Google Maps
                                </a>
                            </div>
                        </div>
                    `);
                    
                    // Store marker for later reference
                    markers.push(marker);
                });
                
                // If we have markers, fit map to show all markers
                if (markers.length > 0) {
                    const group = new L.featureGroup(markers);
                    map.fitBounds(group.getBounds().pad(0.2));
                }
            }
        });
    </script>
</body>
</html>
