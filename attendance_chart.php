
<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

// Get date range from query parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get attendance data
$attendanceData = getAttendanceData($start_date, $end_date);

// Process data for chart
$chartData = [];
$userStats = [];

foreach ($attendanceData as $record) {
    $date = $record['date'];
    $user = $record['full_name'];
    $hours = round($record['total_minutes'] / 60, 2);
    
    if (!isset($chartData[$date])) {
        $chartData[$date] = [];
    }
    
    $chartData[$date][$user] = $hours;
    
    if (!isset($userStats[$user])) {
        $userStats[$user] = [
            'total_hours' => 0,
            'total_days' => 0
        ];
    }
    
    $userStats[$user]['total_hours'] += $hours;
    $userStats[$user]['total_days']++;
}

// Get all users for consistent chart display
$allUsers = array_keys($userStats);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Chart</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8fafc;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            padding: 20px;
        }
        
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filters input, .filters button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .filters button {
            background-color: #4f46e5;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
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
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .table-container {
            overflow-x: auto;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h2>Attendance Chart</h2>
            
            <div class="filters">
                <input type="date" id="start_date" value="<?php echo $start_date; ?>">
                <input type="date" id="end_date" value="<?php echo $end_date; ?>">
                <button onclick="updateChart()">Update Chart</button>
                <button onclick="window.location.href='admin.php'">Back to Admin</button>
            </div>
            
            <div class="chart-container">
                <canvas id="attendanceChart"></canvas>
            </div>
            
            <div class="stats-grid">
                <?php foreach ($userStats as $user => $stats): ?>
                <div class="stat-card">
                    <div class="stat-title"><?php echo htmlspecialchars($user); ?></div>
                    <div class="stat-value"><?php echo $stats['total_hours']; ?>h</div>
                    <div style="font-size: 12px; color: #64748b;"><?php echo $stats['total_days']; ?> days</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card">
            <h3>Detailed Attendance Data</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Check-ins</th>
                            <th>Hours Worked</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceData as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['date']); ?></td>
                            <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                            <td><?php echo $record['total_checkins']; ?></td>
                            <td><?php echo round($record['total_minutes'] / 60, 2); ?> hours</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const chartData = <?php echo json_encode($chartData); ?>;
        const allUsers = <?php echo json_encode($allUsers); ?>;
        
        // Generate colors for users
        const colors = [
            '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
            '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
        ];
        
        // Prepare data for Chart.js
        const dates = Object.keys(chartData).sort();
        const datasets = allUsers.map((user, index) => ({
            label: user,
            data: dates.map(date => chartData[date][user] || 0),
            backgroundColor: colors[index % colors.length],
            borderColor: colors[index % colors.length],
            borderWidth: 2,
            fill: false
        }));
        
        // Create chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours Worked'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Attendance Hours'
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
        
        function updateChart() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            window.location.href = `attendance_chart.php?start_date=${startDate}&end_date=${endDate}`;
        }
    </script>
</body>
</html>
