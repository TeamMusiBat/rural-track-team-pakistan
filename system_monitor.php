
<?php
// Production System Monitor
require_once 'config.php';

class SystemMonitor {
    private $pdo;
    private $logFile;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logFile = '/var/www/smartort/logs/system_monitor.log';
        
        // Create logs directory if not exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function run() {
        $this->log("System monitor started");
        
        while (true) {
            try {
                $this->checkDatabaseHealth();
                $this->checkLocationUpdates();
                $this->cleanupOldData();
                $this->checkSystemResources();
                $this->checkActiveUsers();
                
                // Sleep for 5 minutes
                sleep(300);
                
            } catch (Exception $e) {
                $this->log("Monitor error: " . $e->getMessage());
                sleep(60); // Wait 1 minute before retry
            }
        }
    }
    
    private function checkDatabaseHealth() {
        try {
            $start = microtime(true);
            $stmt = $this->pdo->query("SELECT 1");
            $end = microtime(true);
            
            $responseTime = round(($end - $start) * 1000, 2);
            
            if ($responseTime > 1000) { // Over 1 second
                $this->log("WARNING: Database response time slow: {$responseTime}ms");
            }
            
            // Check for locked tables
            $stmt = $this->pdo->query("SHOW PROCESSLIST");
            $processes = $stmt->fetchAll();
            
            $longRunning = 0;
            foreach ($processes as $process) {
                if ($process['Time'] > 30) { // Over 30 seconds
                    $longRunning++;
                }
            }
            
            if ($longRunning > 0) {
                $this->log("WARNING: {$longRunning} long-running database queries detected");
            }
            
        } catch (Exception $e) {
            $this->log("ERROR: Database health check failed: " . $e->getMessage());
        }
    }
    
    private function checkLocationUpdates() {
        try {
            // Check for users who haven't updated location in over 10 minutes
            $stmt = $this->pdo->query("
                SELECT u.username, u.full_name, MAX(l.timestamp) as last_update
                FROM users u
                LEFT JOIN locations l ON u.id = l.user_id
                WHERE u.is_location_enabled = 1 
                AND u.role = 'user'
                GROUP BY u.id
                HAVING last_update < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                OR last_update IS NULL
            ");
            
            $staleUsers = $stmt->fetchAll();
            
            if (count($staleUsers) > 0) {
                $usernames = array_column($staleUsers, 'username');
                $this->log("WARNING: Stale location data for users: " . implode(', ', $usernames));
            }
            
        } catch (Exception $e) {
            $this->log("ERROR: Location update check failed: " . $e->getMessage());
        }
    }
    
    private function cleanupOldData() {
        try {
            // Clean up old location data (older than 30 days)
            $stmt = $this->pdo->prepare("DELETE FROM locations WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $deletedLocations = $stmt->rowCount();
            
            // Clean up old activity logs (older than 90 days)
            $stmt = $this->pdo->prepare("DELETE FROM activity_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            $deletedLogs = $stmt->rowCount();
            
            // Clean up old db requests (older than 7 days)
            $stmt = $this->pdo->prepare("DELETE FROM db_requests WHERE timestamp < DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $deletedRequests = $stmt->rowCount();
            
            if ($deletedLocations > 0 || $deletedLogs > 0 || $deletedRequests > 0) {
                $this->log("Cleanup: Deleted {$deletedLocations} locations, {$deletedLogs} logs, {$deletedRequests} requests");
            }
            
        } catch (Exception $e) {
            $this->log("ERROR: Data cleanup failed: " . $e->getMessage());
        }
    }
    
    private function checkSystemResources() {
        try {
            // Check disk space
            $diskUsage = disk_free_bytes('/var/www/smartort');
            $diskTotal = disk_total_space('/var/www/smartort');
            $diskPercent = round((($diskTotal - $diskUsage) / $diskTotal) * 100, 2);
            
            if ($diskPercent > 85) {
                $this->log("WARNING: Disk usage at {$diskPercent}%");
            }
            
            // Check memory usage
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matches);
            $memAvailable = isset($matches[1]) ? intval($matches[1]) : 0;
            
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matches);
            $memTotal = isset($matches[1]) ? intval($matches[1]) : 1;
            
            $memPercent = round((($memTotal - $memAvailable) / $memTotal) * 100, 2);
            
            if ($memPercent > 90) {
                $this->log("WARNING: Memory usage at {$memPercent}%");
            }
            
        } catch (Exception $e) {
            $this->log("ERROR: System resource check failed: " . $e->getMessage());
        }
    }
    
    private function checkActiveUsers() {
        try {
            // Check for users who have been checked in for more than 12 hours
            $stmt = $this->pdo->query("
                SELECT u.username, u.full_name, a.check_in,
                       TIMESTAMPDIFF(HOUR, a.check_in, NOW()) as hours_checked_in
                FROM users u
                JOIN attendance a ON u.id = a.user_id
                WHERE a.check_out IS NULL
                AND u.role = 'user'
                HAVING hours_checked_in > 12
            ");
            
            $longCheckedIn = $stmt->fetchAll();
            
            foreach ($longCheckedIn as $user) {
                $this->log("WARNING: User {$user['username']} has been checked in for {$user['hours_checked_in']} hours");
            }
            
            // Auto checkout after configured hours
            $autoCheckoutHours = getSettings('auto_checkout_hours', '10');
            if ($autoCheckoutHours > 0) {
                $stmt = $this->pdo->prepare("
                    UPDATE attendance a
                    JOIN users u ON a.user_id = u.id
                    SET a.check_out = NOW(),
                        a.duration_minutes = TIMESTAMPDIFF(MINUTE, a.check_in, NOW())
                    WHERE a.check_out IS NULL
                    AND u.role = 'user'
                    AND TIMESTAMPDIFF(HOUR, a.check_in, NOW()) >= ?
                ");
                $stmt->execute([$autoCheckoutHours]);
                
                $autoCheckedOut = $stmt->rowCount();
                if ($autoCheckedOut > 0) {
                    $this->log("AUTO: Checked out {$autoCheckedOut} users after {$autoCheckoutHours} hours");
                }
            }
            
        } catch (Exception $e) {
            $this->log("ERROR: Active users check failed: " . $e->getMessage());
        }
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        echo $logMessage;
    }
}

// Run the monitor
if (php_sapi_name() === 'cli') {
    $monitor = new SystemMonitor($pdo);
    $monitor->run();
} else {
    http_response_code(403);
    die('Access denied');
}
?>
