-- Create facebook_ads_accounts_ads table
-- This table stores Facebook ads data synced from the Marketing API

CREATE TABLE IF NOT EXISTS `facebook_ads_accounts_ads` (
  `id` varchar(255) NOT NULL,
  `account_id` varchar(255) NOT NULL,
  `campaign_id` varchar(255) NOT NULL,
  `adset_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `creative_preview` text DEFAULT NULL,
  `copy_text` text DEFAULT NULL,
  `headline` varchar(500) DEFAULT NULL,
  `cta_button` varchar(100) DEFAULT NULL,
  `format` varchar(100) DEFAULT NULL,
  `creative_type` varchar(100) DEFAULT NULL,
  `impressions` decimal(15,2) DEFAULT 0.00,
  `reach` decimal(15,2) DEFAULT 0.00,
  `clicks` decimal(15,2) DEFAULT 0.00,
  `ctr` decimal(15,2) DEFAULT 0.00,
  `cpc` decimal(15,2) DEFAULT 0.00,
  `spend` decimal(15,2) DEFAULT 0.00,
  `likes` decimal(15,2) DEFAULT 0.00,
  `shares` decimal(15,2) DEFAULT 0.00,
  `comments` decimal(15,2) DEFAULT 0.00,
  `reactions` decimal(15,2) DEFAULT 0.00,
  `cost_per_result` decimal(15,2) DEFAULT 0.00,
  `conversion_value` decimal(15,2) DEFAULT 0.00,
  `roas` decimal(15,2) DEFAULT 0.00,
  `results` decimal(15,2) DEFAULT 0.00,
  `created_time` datetime DEFAULT NULL,
  `last_synced_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_adset_id` (`adset_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_time` (`created_time`),
  KEY `idx_last_synced_at` (`last_synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key constraints (optional, uncomment if needed)
-- ALTER TABLE `facebook_ads_accounts_ads` 
--   ADD CONSTRAINT `fk_ads_account` FOREIGN KEY (`account_id`) REFERENCES `facebook_ads_accounts` (`act_id`) ON DELETE CASCADE,
--   ADD CONSTRAINT `fk_ads_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `facebook_ads_accounts_campaigns` (`id`) ON DELETE CASCADE,
--   ADD CONSTRAINT `fk_ads_adset` FOREIGN KEY (`adset_id`) REFERENCES `facebook_ads_accounts_adsets` (`id`) ON DELETE CASCADE;