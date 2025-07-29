
<?php
require_once 'config.php';

// Check if user is developer
if (!isLoggedIn() || !isDeveloper()) {
    redirect('index.php');
}

// Get database request statistics
$stmt = $pdo->prepare("
    SELECT 
        DATE(timestamp) as date,
        request_type,
        COUNT(*) as request_count
    FROM db_requests 
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(timestamp), request_type
    ORDER BY date DESC, request_count DESC
");
$stmt->execute();
$requestStats = $stmt->fetchAll();

// Get total requests
$totalRequests = getSettings('db_request_count', '0');

// Get requests by user
$stmt = $pdo->prepare("
    SELECT 
        u.full_name,
        COUNT(*) as request_count
    FROM db_requests dr
    JOIN users u ON dr.user_id = u.id
    WHERE dr.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY u.id
    ORDER BY request_count DESC
");
$stmt->execute();
$userRequests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Statistics</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8fafc;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            padding: 20px;
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #4f46e5;
        }
        
        .stat-title {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #f8fafc;
            font-weight: 600;
        }
        
        .back-btn {
            background-color: #4f46e5;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php" class="back-btn">← Back to Admin</a>
        
        <div class="card">
            <h2>Database Request Statistics</h2>
            
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-title">Total Requests</div>
                    <div class="stat-value"><?php echo number_format($totalRequests); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-title">Last 30 Days</div>
                    <div class="stat-value"><?php echo number_format(array_sum(array_column($requestStats, 'request_count'))); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-title">Active Users</div>
                    <div class="stat-value"><?php echo count($userRequests); ?></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3>Request Types (Last 30 Days)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Request Type</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requestStats as $stat): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($stat['date']); ?></td>
                        <td><?php echo htmlspecialchars($stat['request_type']); ?></td>
                        <td><?php echo number_format($stat['request_count']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h3>Requests by User (Last 30 Days)</h3>
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Request Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userRequests as $userReq): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($userReq['full_name']); ?></td>
                        <td><?php echo number_format($userReq['request_count']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
