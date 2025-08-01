
<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn()) {
    redirect('index.php');
}

if (!isAdmin()) {
    redirect('dashboard.php');
}

$user_id = $_SESSION['user_id'];

// Get app name from settings
$appName = getSettings('app_name', 'SmartORT');

// Get current tab
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($appName); ?> - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info span {
            color: #666;
            font-size: 14px;
        }
        
        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .tabs {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .tab-nav {
            display: flex;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 15px 20px;
            background: none;
            border: none;
            color: #64748b;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            border-bottom: 3px solid transparent;
        }
        
        .tab-btn:hover {
            background: #e2e8f0;
            color: #334155;
        }
        
        .tab-btn.active {
            background: white;
            color: #6366f1;
            border-bottom-color: #6366f1;
        }
        
        .tab-content {
            padding: 20px;
            min-height: 600px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-card .change {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .data-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .table-header h2 {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 4px;
        }
        
        .table-header p {
            color: #64748b;
            font-size: 14px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 20px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        td {
            color: #6b7280;
            font-size: 14px;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-online {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .status-offline {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #6366f1;
            color: white;
        }
        
        .btn-primary:hover {
            background: #4f46e5;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .tracking-map {
            width: 100%;
            height: 500px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .map-placeholder {
            width: 100%;
            height: 500px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            border-radius: 12px;
            border: 2px dashed #d1d5db;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-tachometer-alt"></i> Admin Panel</h1>
        <div class="user-info">
            <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo ucfirst($_SESSION['role']); ?>)</span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="container">
        <div class="tabs">
            <div class="tab-nav">
                <a href="?tab=dashboard" class="tab-btn <?php echo $currentTab === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="?tab=users" class="tab-btn <?php echo $currentTab === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="?tab=tracking" class="tab-btn <?php echo $currentTab === 'tracking' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marker-alt"></i> Location Tracking
                </a>
                <a href="?tab=logs" class="tab-btn <?php echo $currentTab === 'logs' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i> Activity Logs
                </a>
                <a href="?tab=attendance" class="tab-btn <?php echo $currentTab === 'attendance' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i> Attendance
                </a>
                <a href="?tab=settings" class="tab-btn <?php echo $currentTab === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
            
            <div class="tab-content">
                <?php if ($currentTab === 'dashboard'): ?>
                    <!-- Dashboard content -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Total Users</h3>
                            <div class="value"><?php echo getTotalUsers(); ?></div>
                            <div class="change">+2 this week</div>
                        </div>
                        <div class="stat-card">
                            <h3>Active Today</h3>
                            <div class="value"><?php echo getActiveUsersToday(); ?></div>
                            <div class="change">Currently online</div>
                        </div>
                        <div class="stat-card">
                            <h3>Check-ins Today</h3>
                            <div class="value"><?php echo getCheckinsToday(); ?></div>
                            <div class="change">Pakistan timezone</div>
                        </div>
                        <div class="stat-card">
                            <h3>Locations Tracked</h3>
                            <div class="value"><?php echo getLocationsTracked(); ?></div>
                            <div class="change">Real-time</div>
                        </div>
                    </div>

                <?php elseif ($currentTab === 'tracking'): ?>
                    <!-- Location Tracking Tab - ONLY MAP DISPLAY -->
                    <div class="data-table">
                        <div class="table-header">
                            <h2><i class="fas fa-map-marker-alt"></i> Live Location Map</h2>
                            <p>This map shows the current locations of all checked-in users. Only non-developer users are shown.</p>
                        </div>
                        <div style="padding: 20px;">
                            <iframe 
                                src="admin_tracking_fixes.php" 
                                class="tracking-map"
                                frameborder="0">
                            </iframe>
                        </div>
                    </div>
                    
                <?php elseif ($currentTab === 'users'): ?>
                    <!-- Manage Users tab content -->
                    <?php
                    // Example content for users tab
                    // You can replace this with your actual users management code
                    ?>
                    <h2>Manage Users</h2>
                    <p>Users management interface coming soon.</p>

                <?php elseif ($currentTab === 'logs'): ?>
                    <!-- Activity Logs tab content -->
                    <?php
                    // Example content for logs tab
                    ?>
                    <h2>Activity Logs</h2>
                    <p>Activity logs interface coming soon.</p>

                <?php elseif ($currentTab === 'attendance'): ?>
                    <!-- Attendance tab content -->
                    <?php
                    // Example content for attendance tab
                    ?>
                    <h2>Attendance</h2>
                    <p>Attendance management interface coming soon.</p>

                <?php elseif ($currentTab === 'settings'): ?>
                    <!-- Settings tab content -->
                    <?php
                    // Example content for settings tab
                    ?>
                    <h2>Settings</h2>
                    <p>Settings interface coming soon.</p>

                <?php else: ?>
                    <p>Invalid tab selected.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Auto-refresh script for dashboard stats -->
    <script src="admin_auto_refresh.js"></script>
</body>
</html>

<?php
// Helper functions for stats
function getTotalUsers() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    return $stmt->fetch()['count'];
}

function getActiveUsersToday() {
    global $pdo;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count FROM attendance WHERE DATE(check_in) = ?");
    $stmt->execute([$today]);
    return $stmt->fetch()['count'];
}

function getCheckinsToday() {
    global $pdo;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE DATE(check_in) = ?");
    $stmt->execute([$today]);
    return $stmt->fetch()['count'];
}

function getLocationsTracked() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as count FROM location_logs WHERE DATE(timestamp) = CURDATE()");
    return $stmt->fetch()['count'];
}
?>
