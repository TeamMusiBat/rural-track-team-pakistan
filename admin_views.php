
<?php
// Admin view helper functions

function renderDashboardTab($users, $activeUsers, $pdo) {
    ob_start();
    ?>
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
            
            // Get user's last location (from FastAPI in location_utils.php)
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

            // Change card color after location update
            $cardStyle = '';
            if ($locationData && isset($locationData['latitude'])) {
                $cardStyle = 'background-color: #f0f5ff;'; // light blue
            }
        ?>
        <div class="user-card" style="<?php echo $cardStyle; ?>">
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
                   style="background-color:#2f3ab2; color:#fff;"
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
    <!-- ... rest unchanged ... -->
    <?php
    // ... rest of function unchanged ...
    return ob_get_clean();
}

function renderUsersTab($users) {
    ob_start();
    ?>
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
    <?php
    return ob_get_clean();
}

function renderTrackingTab($locations) {
    ob_start();
    ?>
    <div class="section-title">
        <i class="fas fa-map-marked-alt"></i> Live Location Map
    </div>
    <div class="section-note">
        This map shows the current locations of all checked-in users. Only non-developer users are shown.
    </div>
    <div id="map"></div>
    <!-- Removed Recent Location History table and heading per request -->
    <?php
    return ob_get_clean();
}

function renderActivityTab($logs) {
    ob_start();
    ?>
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
    <?php
    return ob_get_clean();
}

function renderAttendanceTab($attendance) {
    ob_start();
    ?>
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
    <?php
    return ob_get_clean();
}

function renderSettingsTab($settings) {
    ob_start();
    ?>
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
                        <input type="checkbox" name="auto_checkout_enabled" <?php echo $settings['autoCheckoutEnabled'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="auto_checkout_hours">Auto-checkout after (hours):</label>
                    <input type="number" name="auto_checkout_hours" id="auto_checkout_hours" min="1" max="24" value="<?php echo $settings['autoCheckoutHours']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="auto_checkout_time">Auto-checkout time (Pakistan):</label>
                    <input type="time" name="auto_checkout_time" id="auto_checkout_time" value="<?php echo $settings['autoCheckoutTime']; ?>">
                    <div class="text-sm text-gray-500 mt-1" style="font-size: 12px; color: #64748b; margin-top: 4px;">
                        Current setting: <?php echo $settings['autoCheckoutTimeDisplay']; ?> Pakistan time
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
                        <input type="checkbox" name="master_checkin_required" <?php echo $settings['masterCheckinRequired'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div class="form-group">
                    <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                        <?php if ($settings['masterCheckinRequired']): ?>
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
    <?php
    return ob_get_clean();
}
