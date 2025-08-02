
<?php
require_once 'config.php';

// Handle auto checkout setting toggle
function handleAutoCheckoutUpdate($pdo) {
    if (isset($_POST['update_auto_checkout'])) {
        $enabled = isset($_POST['auto_checkout_enabled']) ? '1' : '0';
        $hours = $_POST['auto_checkout_hours'] ?? '10';
        $time = $_POST['auto_checkout_time'] ?? '20:00';
        
        updateSettings('auto_checkout_enabled', $enabled);
        updateSettings('auto_checkout_hours', $hours);
        updateSettings('auto_checkout_time', $time);
        
        redirect('admin.php?tab=settings&msg=settings_updated');
    }
}

// Handle master checkin requirement setting toggle
function handleMasterCheckinUpdate($pdo) {
    if (isset($_POST['update_master_checkin'])) {
        $enabled = isset($_POST['master_checkin_required']) ? '1' : '0';
        
        updateSettings('master_checkin_required', $enabled);
        
        redirect('admin.php?tab=settings&msg=settings_updated');
    }
}

// Handle delete user
function handleDeleteUser($pdo) {
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
}

// Handle reset logs
function handleResetLogs($pdo) {
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
}

// Handle admin actions
function handleAdminActions($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            // Reset IMEI
            if ($_POST['action'] === 'reset_imei' && isset($_POST['user_id'])) {
                $userId = $_POST['user_id'];
                
                $stmt = $pdo->prepare("UPDATE users SET imei = NULL WHERE id = ?");
                $stmt->execute([$userId]);
                
                $adminId = $_SESSION['user_id'];
                logActivity($adminId, 'system', "Admin reset IMEI for user ID: $userId");
                
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
}
