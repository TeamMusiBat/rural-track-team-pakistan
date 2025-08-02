
-- Add last_location_update column to users table for rate limiting
ALTER TABLE users ADD COLUMN last_location_update TIMESTAMP NULL DEFAULT NULL;

-- Add index for better performance
CREATE INDEX idx_users_last_location_update ON users(last_location_update);
