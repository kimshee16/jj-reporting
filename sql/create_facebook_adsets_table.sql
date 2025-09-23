-- Create facebook_ads_accounts_adsets table
-- This table stores Facebook ad sets data synced from the Marketing API

CREATE TABLE IF NOT EXISTS `facebook_ads_accounts_adsets` (
  `id` varchar(255) NOT NULL,
  `account_id` varchar(255) NOT NULL,
  `campaign_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `budget_type` varchar(50) DEFAULT NULL,
  `daily_budget` decimal(15,2) DEFAULT NULL,
  `lifetime_budget` decimal(15,2) DEFAULT NULL,
  `age_min` int DEFAULT NULL,
  `age_max` int DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `interests` text DEFAULT NULL,
  `custom_audience_type` varchar(100) DEFAULT NULL,
  `lookalike_audience_id` varchar(255) DEFAULT NULL,
  `placement` text DEFAULT NULL,
  `device_breakdown` text DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `results` decimal(15,2) DEFAULT 0.00,
  `impressions` decimal(15,2) DEFAULT 0.00,
  `reach` decimal(15,2) DEFAULT 0.00,
  `cpm` decimal(15,2) DEFAULT 0.00,
  `ctr` decimal(15,2) DEFAULT 0.00,
  `cpc` decimal(15,2) DEFAULT 0.00,
  `created_time` datetime DEFAULT NULL,
  `last_synced_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_time` (`created_time`),
  KEY `idx_last_synced_at` (`last_synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key constraints (optional, uncomment if needed)
-- ALTER TABLE `facebook_ads_accounts_adsets` 
--   ADD CONSTRAINT `fk_adsets_account` FOREIGN KEY (`account_id`) REFERENCES `facebook_ads_accounts` (`act_id`) ON DELETE CASCADE,
--   ADD CONSTRAINT `fk_adsets_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `facebook_ads_accounts_campaigns` (`id`) ON DELETE CASCADE;
