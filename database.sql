
-- Settings table for auto checkout configuration
CREATE TABLE IF NOT EXISTS `settings` (
  `name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings if they don't exist
INSERT IGNORE INTO `settings` (`name`, `value`) VALUES
('auto_checkout_enabled', '1'),
('auto_checkout_hours', '10'),
('auto_checkout_time', '20:00');

-- Make sure users table has all needed columns
ALTER TABLE `users` 
  ADD COLUMN IF NOT EXISTS `is_location_enabled` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `user_role` varchar(100) DEFAULT 'Research Specialist',
  ADD COLUMN IF NOT EXISTS `imei` varchar(100) DEFAULT NULL;

-- Make sure attendance table has duration_minutes column
ALTER TABLE `attendance` 
  ADD COLUMN IF NOT EXISTS `duration_minutes` int(11) DEFAULT NULL;

-- Make sure locations table has address column
ALTER TABLE `locations` 
  ADD COLUMN IF NOT EXISTS `address` text DEFAULT NULL;
