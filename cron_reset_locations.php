
<?php
// This file should be executed by a cron job at midnight Pakistan time
require_once 'config.php';

// Get current time in Pakistan
$now = new DateTime();
$now->setTimezone(new DateTimeZone('Asia/Karachi'));

// Extract the hours and minutes
$currentHour = $now->format('H');
$currentMinute = $now->format('i');

// Only run if it's midnight (00:00-00:05)
if ($currentHour == '00' && $currentMinute < '05') {
    // Calculate timestamp 72 hours ago
    $cutoff = new DateTime();
    $cutoff->setTimezone(new DateTimeZone('Asia/Karachi'));
    $cutoff->modify('-72 hours');
    $cutoffStr = $cutoff->format('Y-m-d H:i:s');
    
    // Delete locations older than cutoff
    $stmt = $pdo->prepare("DELETE FROM locations WHERE timestamp < ?");
    $stmt->execute([$cutoffStr]);
    $deleted = $stmt->rowCount();
    
    // Log the cleanup
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, description) VALUES (0, 'system', 'CRON: Cleaned location history older than 72 hours. Deleted " . $deleted . " records.')");
    $stmt->execute();
    
    echo "Location history reset completed at " . $now->format('Y-m-d H:i:s') . ". Deleted " . $deleted . " records.\n";
} else {
    echo "Not running cleanup. Current time: " . $now->format('Y-m-d H:i:s') . "\n";
}
?>
