
<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check PHP version
echo "<p>PHP Version: " . phpversion() . "</p>";

// Check if config.php exists and is readable
echo "<p>Config file exists: " . (file_exists('config.php') ? 'Yes' : 'No') . "</p>";
echo "<p>Config file readable: " . (is_readable('config.php') ? 'Yes' : 'No') . "</p>";

// Try to include config.php
try {
    require_once 'config.php';
    echo "<p>Config file loaded successfully.</p>";
    
    // Check if database connection works
    try {
        $test_query = $pdo->query("SELECT 1");
        echo "<p>Database connection test: Success</p>";
    } catch (PDOException $e) {
        echo "<p>Database connection error: " . $e->getMessage() . "</p>";
    }
    
    // Check if the user is logged in
    echo "<p>User logged in: " . (isLoggedIn() ? 'Yes' : 'No') . "</p>";
    
    // If logged in, check user role
    if (isLoggedIn()) {
        echo "<p>User role: " . getUserRole() . "</p>";
        echo "<p>Is admin: " . (isAdmin() ? 'Yes' : 'No') . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error loading config file: " . $e->getMessage() . "</p>";
}

// Try to include admin.php to see where it fails
try {
    echo "<p>Attempting to include admin.php...</p>";
    ob_start();
    include 'admin.php';
    ob_end_clean();
    echo "<p>admin.php included without fatal errors.</p>";
} catch (Exception $e) {
    echo "<p>Error including admin.php: " . $e->getMessage() . "</p>";
}

echo "<p>Debug completed.</p>";
?>
