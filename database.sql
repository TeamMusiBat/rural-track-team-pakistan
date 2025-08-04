
-- Production-ready database schema with optimizations
-- Drop existing tables if they exist (for fresh install)
DROP TABLE IF EXISTS `db_requests`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `device_tracking`;
DROP TABLE IF EXISTS `locations`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;

-- Settings table with indexes
CREATE TABLE `settings` (
  `name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`name`),
  INDEX `idx_settings_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table with enhanced fields and indexes
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `user_role` varchar(100) DEFAULT 'Research Specialist',
  `role` enum('user','master','developer') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_location_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `device_locked` tinyint(1) DEFAULT 0,
  `flagged_reason` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_active` (`is_active`),
  INDEX `idx_users_location` (`is_location_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance table with enhanced tracking and indexes
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `check_in` timestamp NOT NULL DEFAULT current_timestamp(),
  `check_out` timestamp NULL DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `check_in_latitude` decimal(10,8) DEFAULT NULL,
  `check_in_longitude` decimal(11,8) DEFAULT NULL,
  `check_out_latitude` decimal(10,8) DEFAULT NULL,
  `check_out_longitude` decimal(11,8) DEFAULT NULL,
  `check_in_address` text DEFAULT NULL,
  `check_out_address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_attendance_user` (`user_id`),
  INDEX `idx_attendance_checkin` (`check_in`),
  INDEX `idx_attendance_checkout` (`check_out`),
  INDEX `idx_attendance_active` (`check_out`),
  INDEX `idx_attendance_date` (DATE(`check_in`)),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Locations table with enhanced tracking and partitioning support
CREATE TABLE `locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `accuracy` float DEFAULT NULL,
  `altitude` float DEFAULT NULL,
  `speed` float DEFAULT NULL,
  `heading` float DEFAULT NULL,
  `address` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_background` tinyint(1) DEFAULT 0,
  `battery_level` int(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_locations_user` (`user_id`),
  INDEX `idx_locations_timestamp` (`timestamp`),
  INDEX `idx_locations_user_time` (`user_id`, `timestamp`),
  INDEX `idx_locations_coords` (`latitude`, `longitude`),
  INDEX `idx_locations_background` (`is_background`),
  CONSTRAINT `fk_locations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table with indexes for performance
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `metadata` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_activity_user` (`user_id`),
  INDEX `idx_activity_type` (`activity_type`),
  INDEX `idx_activity_timestamp` (`timestamp`),
  INDEX `idx_activity_ip` (`ip_address`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Device tracking table with enhanced security
CREATE TABLE `device_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_id` varchar(255) NOT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `location_permission` tinyint(1) DEFAULT 0,
  `push_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_device_user` (`user_id`),
  INDEX `idx_device_id` (`device_id`),
  INDEX `idx_device_active` (`is_active`),
  INDEX `idx_device_ip` (`ip_address`),
  UNIQUE KEY `unique_user_device` (`user_id`, `device_id`),
  CONSTRAINT `fk_device_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Database requests counter table for monitoring
CREATE TABLE `db_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_type` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `execution_time` decimal(8,4) DEFAULT NULL,
  `query_hash` varchar(64) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_requests_user` (`user_id`),
  INDEX `idx_requests_type` (`request_type`),
  INDEX `idx_requests_timestamp` (`timestamp`),
  INDEX `idx_requests_hash` (`query_hash`),
  CONSTRAINT `fk_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings with production values
INSERT INTO `settings` (`name`, `value`) VALUES
('auto_checkout_enabled', '1'),
('auto_checkout_hours', '10'),
('auto_checkout_time', '20:00'),
('master_checkin_required', '0'),
('app_name', 'SmartOutreach Tracker'),
('default_position', 'Research Specialist'),
('location_update_interval', '60'),
('db_request_count', '0'),
('fastapi_base_url', 'http://54.250.198.0:8000'),
('max_location_history_days', '30'),
('location_accuracy_threshold', '100'),
('background_sync_enabled', '1'),
('push_notifications_enabled', '1'),
('debug_mode', '0'),
('maintenance_mode', '0'),
('app_version', '1.0.0'),
('timezone', 'Asia/Karachi');

-- Insert default developer user with secure password
INSERT INTO `users` (`username`, `password`, `full_name`, `role`, `is_active`) VALUES
('developer', '$2y$12$LQ2XNwSdOiZGZNgOzVUJw.HxQCrj8MYGXkwMYpFiCHBzNP8iPhOyW', 'System Developer', 'developer', 1);

-- Create a view for active users with current status
CREATE VIEW `active_users_view` AS
SELECT 
    u.id,
    u.username,
    u.full_name,
    u.user_role,
    u.role,
    u.is_location_enabled,
    u.last_login,
    CASE 
        WHEN a.check_out IS NULL THEN 1
        ELSE 0
    END as is_checked_in,
    a.check_in as current_checkin,
    a.id as attendance_id,
    l.latitude as last_latitude,
    l.longitude as last_longitude,
    l.timestamp as last_location_update
FROM users u
LEFT JOIN attendance a ON u.id = a.user_id AND a.check_out IS NULL
LEFT JOIN locations l ON u.id = l.user_id AND l.id = (
    SELECT MAX(id) FROM locations WHERE user_id = u.id
)
WHERE u.is_active = 1 AND u.role != 'developer';

-- Create triggers for automatic cleanup
DELIMITER //

CREATE TRIGGER `cleanup_old_locations` 
AFTER INSERT ON `locations`
FOR EACH ROW
BEGIN
    DELETE FROM locations 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY) 
    AND user_id = NEW.user_id;
END //

CREATE TRIGGER `cleanup_old_requests` 
AFTER INSERT ON `db_requests`
FOR EACH ROW
BEGIN
    DELETE FROM db_requests 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL 7 DAY);
END //

DELIMITER ;

-- Create indexes for better performance
CREATE INDEX idx_locations_cleanup ON locations (user_id, timestamp);
CREATE INDEX idx_requests_cleanup ON db_requests (timestamp);
CREATE INDEX idx_users_login ON users (last_login);
CREATE INDEX idx_attendance_duration ON attendance (duration_minutes);

-- Set MySQL settings for optimal performance
SET GLOBAL innodb_buffer_pool_size = 268435456; -- 256MB
SET GLOBAL max_connections = 151;
SET GLOBAL query_cache_type = 1;
SET GLOBAL query_cache_size = 67108864; -- 64MB

-- Grant permissions for application user (replace 'app_user' with your actual username)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON *.* TO 'app_user'@'localhost';
-- FLUSH PRIVILEGES;
