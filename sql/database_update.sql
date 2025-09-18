-- Database update to add new fields for Facebook account sync
-- Run this SQL to update your facebook_ads_accounts table

ALTER TABLE `facebook_ads_accounts` 
ADD COLUMN `account_status` VARCHAR(50) NULL AFTER `act_id`,
ADD COLUMN `currency` VARCHAR(10) NULL AFTER `account_status`,
ADD COLUMN `timezone_name` VARCHAR(100) NULL AFTER `currency`,
ADD COLUMN `spend_cap` DECIMAL(15,2) NULL AFTER `timezone_name`;

-- Update existing records with default values if needed
UPDATE `facebook_ads_accounts` 
SET 
    `account_status` = 'ACTIVE',
    `currency` = 'USD',
    `timezone_name` = 'UTC'
WHERE `account_status` IS NULL;
