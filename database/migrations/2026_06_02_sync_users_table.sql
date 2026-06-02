-- Migration to add missing columns to users table
-- Created: 2026-06-02

-- Add tr_number column if it doesn't exist
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `tr_number` varchar(50) DEFAULT NULL AFTER `its_number`;

-- Add category column if it doesn't exist
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `category` enum('Surat','Marol','Karachi','Nairobi','Muntasib') DEFAULT NULL AFTER `tr_number`;

-- Add classification column if it doesn't exist
-- Note: 'classification' is NOT NULL in the schema, but adding it to an existing table requires a default or being NULLable initially.
-- We'll set a default 'Talabat' for existing users.
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `classification` enum('Talabat','Taalebaat','Muntasebeen','Muntasebaat') NOT NULL DEFAULT 'Talabat' AFTER `category`;

-- Add phone_number column if it doesn't exist
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `phone_number` varchar(20) DEFAULT NULL AFTER `name`;

-- Add admin_type column if it doesn't exist
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `admin_type` enum('super_admin','finance_admin','amali_coordinator','surat_amali_coordinator','marol_amali_coordinator','karachi_amali_coordinator','nairobi_amali_coordinator','muntasib_amali_coordinator') DEFAULT NULL AFTER `role`;

-- Note: MariaDB/MySQL < 10.5.2 / 8.0.19 does not support 'ADD COLUMN IF NOT EXISTS'.
-- If the above fails, use these standard commands (might fail if column exists):
-- ALTER TABLE `users` ADD `tr_number` varchar(50) DEFAULT NULL AFTER `its_number`;
-- ALTER TABLE `users` ADD `category` enum('Surat','Marol','Karachi','Nairobi','Muntasib') DEFAULT NULL AFTER `tr_number`;
-- ALTER TABLE `users` ADD `classification` enum('Talabat','Taalebaat','Muntasebeen','Muntasebaat') NOT NULL DEFAULT 'Talabat' AFTER `category`;
-- ALTER TABLE `users` ADD `phone_number` varchar(20) DEFAULT NULL AFTER `name`;
-- ALTER TABLE `users` ADD `admin_type` enum('super_admin','finance_admin','amali_coordinator','surat_amali_coordinator','marol_amali_coordinator','karachi_amali_coordinator','nairobi_amali_coordinator','muntasib_amali_coordinator') DEFAULT NULL AFTER `role`;
