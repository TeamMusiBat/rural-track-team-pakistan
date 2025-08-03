
-- Add last_location_update column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS last_location_update TIMESTAMP NULL DEFAULT NULL;

-- Update existing records to have a default timestamp for the column
UPDATE users 
SET last_location_update = NOW() - INTERVAL 2 MINUTE 
WHERE last_location_update IS NULL;
