-- Migration: Add indexes for bulk user import validation and performance
-- Created: 2026-09-07

-- Add index on tr_number if not already present
SET @dbname = DATABASE();
SET @tablename = "users";
SET @columnname = "tr_number";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "CREATE INDEX idx_users_tr_number ON users (tr_number)"
));
PREPARE addIndexIfNotExists FROM @preparedStatement;
EXECUTE addIndexIfNotExists;
DEALLOCATE PREPARE addIndexIfNotExists;

-- Ensure classification column exists with proper enum values
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `classification` enum('Talabat','Taalebaat','Muntasebeen','Muntasebaat') NOT NULL DEFAULT 'Talabat' AFTER `category`;
