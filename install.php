
<?php
// Immediately check if the file should exist - security measure
$install_lock_file = 'install.lock';
if (file_exists($install_lock_file)) {
    die('Installation is locked. For security reasons, delete the install.lock file if you need to run the installation again.');
}

// Database configuration
$host = "localhost";
$dbname = "u696686061_smartort";
$username = "u696686061_smartort";
$password = "Atifkhan83##";

$installed = false;
$error = false;
$message = '';
$developer_username = 'asifjamali83';
$developer_password = password_hash('Atifkhan83##', PASSWORD_DEFAULT); // Pre-hash the password

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Try to connect to the database
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create tables
        
        // Users table
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(20),
            imei VARCHAR(50),
            role ENUM('master', 'developer', 'user') NOT NULL DEFAULT 'user',
            user_role VARCHAR(100) DEFAULT 'Research Specialist',
            is_location_enabled TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_active TIMESTAMP
        )");
        
        // Check-in/Check-out records
        $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            check_in DATETIME,
            check_out DATETIME,
            duration_minutes INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        
        // Location history
        $pdo->exec("CREATE TABLE IF NOT EXISTS locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            address VARCHAR(255),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        
        // Activity logs
        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            activity_type ENUM('login', 'check_in', 'check_out', 'location_update', 'logout', 'system') NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        
        // Settings table
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Create developer account if it doesn't exist
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$developer_username]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, role) VALUES ('Developer', ?, ?, 'developer')");
            $stmt->execute([$developer_username, $developer_password]);
        }
        
        // Initialize settings
        $stmt = $pdo->prepare("SELECT id FROM settings WHERE name = 'auto_checkout_enabled'");
        $stmt->execute();
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO settings (name, value) VALUES ('auto_checkout_enabled', '1')");
            $stmt->execute();
            
            $stmt = $pdo->prepare("INSERT INTO settings (name, value) VALUES ('auto_checkout_hours', '10')");
            $stmt->execute();
            
            $stmt = $pdo->prepare("INSERT INTO settings (name, value) VALUES ('auto_checkout_time', '20:00')");
            $stmt->execute();
        }
        
        $installed = true;
        $message = 'Installation completed successfully! Credentials for developer access have been created.';
        
        // Create installation lock file for security
        file_put_contents($install_lock_file, date('Y-m-d H:i:s'));
        
    } catch (PDOException $e) {
        $error = true;
        $message = 'Installation failed: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartOutreach - Installation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #4a6cf7 0%, #2a3e82 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #333;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 600px;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #4a6cf7, #7c8ff8, #4a6cf7);
            background-size: 200% 100%;
            animation: gradientAnimation 3s linear infinite;
        }
        
        @keyframes gradientAnimation {
            0% {background-position: 0% 0%;}
            100% {background-position: 200% 0%;}
        }
        
        .app-logo {
            font-size: 28px;
            font-weight: 700;
            color: #4a6cf7;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .app-logo i {
            margin-right: 10px;
            font-size: 24px;
        }
        
        h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        
        p {
            margin-bottom: 20px;
            color: #555;
            line-height: 1.6;
        }
        
        .steps {
            text-align: left;
            margin-bottom: 30px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
        }
        
        .steps h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .steps ol {
            margin-left: 20px;
            margin-bottom: 20px;
        }
        
        .steps li {
            margin-bottom: 10px;
            color: #555;
        }
        
        .credentials {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
        }
        
        .credentials h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #4a6cf7;
        }
        
        .credentials p {
            margin-bottom: 5px;
            color: #555;
        }
        
        .credentials code {
            background: #e2e8f0;
            padding: 3px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
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
        
        button {
            background-color: #4a6cf7;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 14px 20px;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(74, 108, 247, 0.3);
            margin-bottom: 20px;
        }
        
        button:hover {
            background-color: #3a5bd9;
            box-shadow: 0 6px 16px rgba(74, 108, 247, 0.4);
            transform: translateY(-1px);
        }
        
        button:active {
            transform: translateY(1px);
            box-shadow: 0 2px 8px rgba(74, 108, 247, 0.3);
        }
        
        a {
            color: #4a6cf7;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        a:hover {
            color: #3a5bd9;
            text-decoration: underline;
        }
        
        .footer {
            margin-top: 20px;
            font-size: 14px;
            color: #64748b;
        }
        
        .security-note {
            background-color: #fff8e1;
            border: 1px solid #ffe082;
            color: #ff8f00;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 14px;
        }
        
        .security-note i {
            margin-right: 8px;
        }
        
        /* Added responsive styling for smaller screens */
        @media (max-width: 480px) {
            .container {
                padding: 20px 15px;
            }
            
            h1 {
                font-size: 20px;
            }
            
            .app-logo {
                font-size: 24px;
            }
            
            button {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="app-logo"><i class="fas fa-map-marker-alt"></i> SmartOutreach</div>
        <h1>Installation Wizard</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $error ? 'message-error' : 'message-success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($installed): ?>
            <p>Your SmartOutreach application has been successfully installed. You can now proceed to the login page to start using the application.</p>
            
            <div class="credentials">
                <h3>Developer Access:</h3>
                <p>The initial developer account has been created.</p>
                <p>For security reasons, the credentials are not displayed here.</p>
                <p>If you are the administrator, you already know these credentials.</p>
                <p>If you need to recover credentials, please contact support.</p>
            </div>
            
            <div class="security-note">
                <i class="fas fa-shield-alt"></i> <strong>Security Note:</strong> For security purposes:
                <ul style="margin-left: 20px; margin-top: 5px;">
                    <li>Please change the default credentials after your first login</li>
                    <li>Delete the install.php file after installation</li>
                    <li>The installation is now locked with install.lock file</li>
                </ul>
            </div>
            
            <a href="index.php" class="button">
                <button type="button">
                    <i class="fas fa-sign-in-alt"></i> Go to Login Page
                </button>
            </a>
        <?php else: ?>
            <p>Welcome to the SmartOutreach installation wizard. This tool will help you set up the database tables and initial configuration for your application.</p>
            
            <div class="steps">
                <h2>Installation Process:</h2>
                <ol>
                    <li>Click the "Install Now" button below to create the necessary database tables.</li>
                    <li>A default developer account will be created for you.</li>
                    <li>After installation, you'll be able to log in and start using the application.</li>
                </ol>
                
                <h2>Prerequisites:</h2>
                <ul>
                    <li>PHP 7.4 or higher</li>
                    <li>MySQL database</li>
                    <li>PDO PHP extension</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <button type="submit">
                    <i class="fas fa-download"></i> Install Now
                </button>
            </form>
        <?php endif; ?>
        
        <div class="footer">
            &copy; <?php echo date('Y'); ?> SmartOutreach
        </div>
    </div>
</body>
</html>
