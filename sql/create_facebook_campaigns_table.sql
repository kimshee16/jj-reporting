-- Create facebook_ads_accounts_campaigns table
CREATE TABLE IF NOT EXISTS `facebook_ads_accounts_campaigns` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `campaign_id` varchar(50) NOT NULL,
    `account_id` varchar(50) NOT NULL,
    `name` varchar(255) NOT NULL,
    `objective` varchar(100) NOT NULL,
    `status` varchar(20) NOT NULL,
    `budget_type` varchar(20) NOT NULL,
    `daily_budget` decimal(10,2) DEFAULT NULL,
    `lifetime_budget` decimal(10,2) DEFAULT NULL,
    `total_spend` decimal(10,2) DEFAULT 0.00,
    `results` int(11) DEFAULT 0,
    `roas` decimal(5,2) DEFAULT NULL,
    `ctr` decimal(5,2) DEFAULT NULL,
    `cpm` decimal(10,2) DEFAULT NULL,
    `cpc` decimal(10,2) DEFAULT NULL,
    `start_date` datetime DEFAULT NULL,
    `end_date` datetime DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_campaign` (`campaign_id`, `account_id`),
    KEY `idx_account_id` (`account_id`),
    KEY `idx_status` (`status`),
    KEY `idx_objective` (`objective`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data
INSERT INTO `facebook_ads_accounts_campaigns` 
(`campaign_id`, `account_id`, `name`, `objective`, `status`, `budget_type`, `daily_budget`, `total_spend`, `results`, `roas`, `ctr`, `cpm`, `cpc`, `start_date`, `end_date`) 
VALUES 
('123456789', 'act_123456789', 'Summer Sale Campaign', 'CONVERSIONS', 'ACTIVE', 'daily', 100.00, 1250.50, 45, 2.85, 2.67, 15.50, 1.04, '2024-01-01 00:00:00', NULL),
('123456790', 'act_123456789', 'Holiday Campaign', 'TRAFFIC', 'PAUSED', 'lifetime', NULL, 2100.75, 120, 1.92, 2.50, 18.75, 1.08, '2024-11-01 00:00:00', '2024-12-31 23:59:59'),
('123456791', 'act_123456789', 'Brand Awareness Campaign', 'AWARENESS', 'ACTIVE', 'daily', 50.00, 650.25, 0, NULL, 1.85, 12.30, 0.95, '2024-02-01 00:00:00', NULL),
('123456792', 'act_123456789', 'Lead Generation Campaign', 'LEAD_GENERATION', 'COMPLETED', 'lifetime', NULL, 3200.00, 85, 1.45, 3.20, 22.10, 1.25, '2024-03-01 00:00:00', '2024-03-31 23:59:59');
