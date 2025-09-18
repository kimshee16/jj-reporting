-- Create tables for Export Center feature
-- Run this SQL script to set up the database tables for the export functionality

-- Table for export jobs (scheduled exports)
CREATE TABLE IF NOT EXISTS `export_jobs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text,
    `export_type` varchar(50) NOT NULL,
    `export_format` varchar(20) NOT NULL,
    `export_view` varchar(20) DEFAULT 'table',
    `account_filter` text,
    `campaign_filter` text,
    `date_range` varchar(50) DEFAULT 'last_30_days',
    `custom_filters` text,
    `selected_fields` text,
    `schedule_type` varchar(20) DEFAULT 'once',
    `schedule_time` time DEFAULT NULL,
    `schedule_day` tinyint(1) DEFAULT NULL,
    `email_recipients` text,
    `is_active` boolean DEFAULT TRUE,
    `created_by` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_run` timestamp NULL DEFAULT NULL,
    `next_run` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for export history (completed exports)
CREATE TABLE IF NOT EXISTS `export_history` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `job_id` int(11) DEFAULT NULL,
    `export_type` varchar(50) NOT NULL,
    `export_format` varchar(20) NOT NULL,
    `export_view` varchar(20) NOT NULL,
    `file_name` varchar(255) NOT NULL,
    `file_path` varchar(500) NOT NULL,
    `file_size` bigint(20) DEFAULT NULL,
    `record_count` int(11) DEFAULT NULL,
    `status` varchar(20) DEFAULT 'processing',
    `error_message` text,
    `execution_time` decimal(10,3) DEFAULT NULL,
    `created_by` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `downloaded_at` timestamp NULL DEFAULT NULL,
    `download_count` int(11) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for export templates (reusable configurations)
CREATE TABLE IF NOT EXISTS `export_templates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text,
    `export_type` varchar(50) NOT NULL,
    `export_format` varchar(20) NOT NULL,
    `export_view` varchar(20) DEFAULT 'table',
    `default_fields` text,
    `default_filters` text,
    `is_public` boolean DEFAULT FALSE,
    `is_active` boolean DEFAULT TRUE,
    `usage_count` int(11) DEFAULT 0,
    `created_by` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for export settings (user preferences)
CREATE TABLE IF NOT EXISTS `export_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `default_format` varchar(20) DEFAULT 'excel',
    `default_view` varchar(20) DEFAULT 'table',
    `auto_download` boolean DEFAULT TRUE,
    `email_notifications` boolean DEFAULT TRUE,
    `max_file_size` int(11) DEFAULT 50,
    `retention_days` int(11) DEFAULT 30,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample export templates
INSERT INTO `export_templates` (`name`, `description`, `export_type`, `export_format`, `export_view`, `default_fields`, `default_filters`, `is_public`, `created_by`) VALUES
('Campaign Performance Summary', 'Export key campaign metrics for reporting', 'campaigns', 'excel', 'table', '["name","status","spend","impressions","clicks","ctr","cpc","cpa","roas"]', '{"date_range":"last_30_days","status":"active"}', 1, 1),
('Ad Set Details', 'Detailed ad set performance data', 'adsets', 'excel', 'table', '["name","campaign_name","status","spend","impressions","clicks","ctr","cpc","cpa","roas","targeting"]', '{"date_range":"last_7_days"}', 1, 1),
('Cross-Account Overview', 'Multi-account campaign comparison', 'cross_account', 'excel', 'table', '["account_name","campaign_name","spend","impressions","clicks","ctr","cpc","cpa","roas","platform"]', '{"date_range":"last_30_days","platform":"all"}', 1, 1),
('Raw Ad Data', 'Complete ad performance dataset', 'ads', 'csv', 'raw', '[]', '{"date_range":"last_30_days"}', 0, 1),
('Weekly Performance Report', 'PDF report with charts and tables', 'campaigns', 'pdf', 'chart', '["name","spend","impressions","clicks","ctr","cpc","cpa","roas"]', '{"date_range":"last_7_days","status":"active"}', 1, 1);

-- Insert sample export jobs
INSERT INTO `export_jobs` (`name`, `description`, `export_type`, `export_format`, `export_view`, `account_filter`, `date_range`, `selected_fields`, `schedule_type`, `schedule_time`, `email_recipients`, `created_by`, `next_run`) VALUES
('Daily Campaign Report', 'Daily automated campaign performance report', 'campaigns', 'excel', 'table', '["all"]', 'last_1_days', '["name","status","spend","impressions","clicks","ctr","cpc","cpa","roas"]', 'daily', '08:00:00', '["admin@company.com","manager@company.com"]', 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 8 HOUR),
('Weekly Cross-Account Summary', 'Weekly summary of all accounts performance', 'cross_account', 'pdf', 'chart', '["all"]', 'last_7_days', '["account_name","campaign_name","spend","impressions","clicks","ctr","cpc","cpa","roas"]', 'weekly', '09:00:00', '["admin@company.com"]', 1, DATE_ADD(CURDATE(), INTERVAL (7 - WEEKDAY(CURDATE())) DAY) + INTERVAL 9 HOUR),
('Monthly Ad Performance', 'Monthly detailed ad performance analysis', 'ads', 'excel', 'raw', '["all"]', 'last_30_days', '[]', 'monthly', '10:00:00', '["admin@company.com","analyst@company.com"]', 1, DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY) + INTERVAL 10 HOUR);

-- Insert default export settings
INSERT INTO `export_settings` (`admin_id`, `default_format`, `default_view`, `auto_download`, `email_notifications`, `max_file_size`, `retention_days`) VALUES
(1, 'excel', 'table', 1, 1, 50, 30);

-- Insert sample export history
INSERT INTO `export_history` (`job_id`, `export_type`, `export_format`, `export_view`, `file_name`, `file_path`, `file_size`, `record_count`, `status`, `execution_time`, `created_by`, `created_at`) VALUES
(1, 'campaigns', 'excel', 'table', 'campaigns_2024-01-15.xlsx', '/exports/campaigns_2024-01-15.xlsx', 245760, 25, 'completed', 2.345, 1, NOW() - INTERVAL 1 DAY),
(NULL, 'adsets', 'csv', 'raw', 'adsets_manual_2024-01-14.csv', '/exports/adsets_manual_2024-01-14.csv', 156789, 150, 'completed', 1.234, 1, NOW() - INTERVAL 2 DAYS),
(2, 'cross_account', 'pdf', 'chart', 'cross_account_weekly_2024-01-13.pdf', '/exports/cross_account_weekly_2024-01-13.pdf', 1024000, 45, 'completed', 5.678, 1, NOW() - INTERVAL 3 DAYS),
(NULL, 'ads', 'excel', 'table', 'ads_performance_2024-01-12.xlsx', '/exports/ads_performance_2024-01-12.xlsx', 567890, 300, 'failed', 0.500, 1, NOW() - INTERVAL 4 DAYS);
