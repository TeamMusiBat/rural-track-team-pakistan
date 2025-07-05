
<?php
require_once 'config.php';
require_once 'location_utils.php'; 

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $deviceId = isset($_POST['device_id']) ? $_POST['device_id'] : '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter all required fields';
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check IMEI/Device ID
            if (empty($user['imei'])) {
                // First login, save device ID
                $stmt = $pdo->prepare("UPDATE users SET imei = ? WHERE id = ?");
                $stmt->execute([$deviceId, $user['id']]);
                
                if (!isUserDeveloper($user['id'])) {
                    logActivity($user['id'], 'login', 'First login, device ID registered: ' . $deviceId);
                }
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['device_id'] = $deviceId;
                
                redirect('dashboard.php');
            } else if ($user['imei'] === $deviceId || $deviceId === 'browser-fallback' || empty($deviceId)) {
                // Device ID matches or this is a browser login with no device ID
                
                if (!isUserDeveloper($user['id'])) {
                    logActivity($user['id'], 'login', 'Login successful');
                }
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['device_id'] = $deviceId;
                
                redirect('dashboard.php');
            } else {
                $error = 'This account is locked to another device. Please contact administrator.';
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartOutreach - Login</title>
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
        }
        
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 420px;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::after {
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
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 30px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 24px;
            text-align: left;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #444;
            font-weight: 500;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 14px;
            top: 14px;
            color: #aaa;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s;
            background-color: #f8f9fa;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #4a6cf7;
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.15);
            background-color: #fff;
        }
        
        .error-message {
            color: #e74c3c;
            margin-bottom: 20px;
            font-size: 14px;
            background-color: #fdecea;
            padding: 10px;
            border-radius: 8px;
            border-left: 4px solid #e74c3c;
            text-align: left;
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
        
        .info-text {
            font-size: 13px;
            color: #777;
            margin-top: 25px;
            line-height: 1.5;
        }
        
        .platform-support {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 15px;
        }
        
        .platform-icon {
            font-size: 18px;
            color: #666;
        }
        
        .hidden {
            display: none;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .remember-me input {
            margin-right: 8px;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="app-logo"><i class="fas fa-map-marker-alt"></i> SmartOutreach</div>
        <h1>Welcome back to SmartOutreach</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form id="login-form" method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-with-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" required>
                </div>
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember" checked>
                <label for="remember">Remember me for offline access</label>
            </div>
            
            <input type="hidden" id="device_id" name="device_id" value="browser-fallback">
            
            <button type="submit" id="login-button">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
            
            <p class="info-text">This application records your device ID for security purposes. Login information will be saved locally for offline access if "Remember me" is checked.</p>
            
            <div class="platform-support">
                <span title="Android"><i class="fab fa-android platform-icon"></i></span>
                <span title="iOS"><i class="fab fa-apple platform-icon"></i></span>
                <span title="Windows"><i class="fab fa-windows platform-icon"></i></span>
                <span title="Mac"><i class="fab fa-apple platform-icon"></i></span>
                <span title="Web"><i class="fas fa-globe platform-icon"></i></span>
            </div>
        </form>
    </div>

    <script>
        // Generate a unique device ID
        function generateDeviceId() {
            // Check if we already have a device ID in localStorage
            let deviceId = localStorage.getItem('device_id');
            
            if (!deviceId) {
                // Generate a random ID based on platform
                const platform = detectPlatform();
                deviceId = platform + '-' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
                localStorage.setItem('device_id', deviceId);
            }
            
            return deviceId;
        }
        
        // Detect platform
        function detectPlatform() {
            const userAgent = navigator.userAgent || navigator.vendor || window.opera;
            
            // Device detection
            if (/android/i.test(userAgent)) {
                return 'ANDROID';
            }
            
            if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream) {
                return 'IOS';
            }
            
            if (/windows/i.test(userAgent)) {
                return 'WINDOWS';
            }
            
            if (/macintosh|mac os x/i.test(userAgent)) {
                return 'MAC';
            }
            
            return 'BROWSER';
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Set the device ID field
            document.getElementById('device_id').value = generateDeviceId();
            
            // Check if we have stored credentials in offline mode
            const storedUsername = localStorage.getItem('offline_username');
            
            if (storedUsername) {
                document.getElementById('username').value = storedUsername;
            }
            
            // Handle offline login capability
            document.getElementById('login-form').addEventListener('submit', function(e) {
                const rememberMe = document.getElementById('remember').checked;
                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;
                const deviceId = document.getElementById('device_id').value;
                
                // If remember me is checked, store for offline login
                if (rememberMe) {
                    localStorage.setItem('offline_username', username);
                    // Store password securely - in a real app, you'd want to hash this
                    localStorage.setItem('offline_password', btoa(password)); // Simple encoding, not secure encryption
                    localStorage.setItem('offline_device_id', deviceId);
                } else {
                    // Clear any stored credentials
                    localStorage.removeItem('offline_username');
                    localStorage.removeItem('offline_password');
                }
                
                // If offline and we have credentials, handle offline login
                if (!navigator.onLine && localStorage.getItem('offline_username')) {
                    e.preventDefault();
                    alert('You are offline. The app will function in offline mode with limited capabilities.');
                    
                    // Store that we're offline
                    sessionStorage.setItem('is_offline', 'true');
                    
                    // Redirect to offline dashboard
                    window.location.href = 'dashboard.php';
                }
            });
            
            // Check if we're online
            function updateOnlineStatus() {
                if (!navigator.onLine) {
                    document.querySelector('.info-text').innerHTML += '<br><strong style="color: #e67e22;"><i class="fas fa-exclamation-triangle"></i> You are currently offline. Offline login is available if you previously logged in.</strong>';
                }
            }
            
            window.addEventListener('online', updateOnlineStatus);
            window.addEventListener('offline', updateOnlineStatus);
            updateOnlineStatus();
            
            // Request location permission immediately
            function requestLocationPermission() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            console.log("Location permission granted on startup");
                        },
                        function(error) {
                            console.log("Location permission denied or error on startup:", error.code);
                            if (/android/i.test(navigator.userAgent) || /iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                                // On mobile, show a prompt
                                showLocationPrompt();
                            }
                        },
                        { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                    );
                }
            }
            
            // Show a prompt for location permission
            function showLocationPrompt() {
                // Create a modal for location permission
                const modal = document.createElement('div');
                modal.style.position = 'fixed';
                modal.style.top = '0';
                modal.style.left = '0';
                modal.style.right = '0';
                modal.style.bottom = '0';
                modal.style.backgroundColor = 'rgba(0,0,0,0.7)';
                modal.style.zIndex = '1000';
                modal.style.display = 'flex';
                modal.style.alignItems = 'center';
                modal.style.justifyContent = 'center';
                
                const content = document.createElement('div');
                content.style.backgroundColor = 'white';
                content.style.borderRadius = '12px';
                content.style.padding = '24px';
                content.style.width = '90%';
                content.style.maxWidth = '400px';
                content.style.boxShadow = '0 4px 20px rgba(0,0,0,0.15)';
                
                const title = document.createElement('div');
                title.textContent = 'Enable Location Services';
                title.style.fontSize = '18px';
                title.style.fontWeight = '600';
                title.style.marginBottom = '16px';
                
                const text = document.createElement('div');
                text.textContent = 'SmartOutreach needs access to your location to track your work location while checked in. This helps us provide better service and ensure accurate attendance records.';
                text.style.fontSize = '14px';
                text.style.lineHeight = '1.5';
                text.style.marginBottom = '20px';
                
                const buttons = document.createElement('div');
                buttons.style.display = 'flex';
                buttons.style.gap = '12px';
                
                const allowButton = document.createElement('button');
                allowButton.textContent = 'Allow';
                allowButton.style.flex = '1';
                allowButton.style.padding = '12px';
                allowButton.style.borderRadius = '8px';
                allowButton.style.fontWeight = '600';
                allowButton.style.border = 'none';
                allowButton.style.backgroundColor = '#10b981';
                allowButton.style.color = 'white';
                allowButton.style.cursor = 'pointer';
                
                const denyButton = document.createElement('button');
                denyButton.textContent = 'Not Now';
                denyButton.style.flex = '1';
                denyButton.style.padding = '12px';
                denyButton.style.borderRadius = '8px';
                denyButton.style.fontWeight = '600';
                denyButton.style.border = 'none';
                denyButton.style.backgroundColor = '#f3f4f6';
                denyButton.style.color = '#4b5563';
                denyButton.style.cursor = 'pointer';
                
                buttons.appendChild(allowButton);
                buttons.appendChild(denyButton);
                
                content.appendChild(title);
                content.appendChild(text);
                content.appendChild(buttons);
                
                modal.appendChild(content);
                
                document.body.appendChild(modal);
                
                // Handle button clicks
                allowButton.addEventListener('click', function() {
                    document.body.removeChild(modal);
                    requestLocationPermission();
                });
                
                denyButton.addEventListener('click', function() {
                    document.body.removeChild(modal);
                });
            }
            
            // Request location permission for Android/iOS devices immediately
            if (/android/i.test(navigator.userAgent) || /iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                requestLocationPermission();
            }
        });
    </script>
</body>
</html>
