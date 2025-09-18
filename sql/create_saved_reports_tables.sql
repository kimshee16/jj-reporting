-- Create tables for Saved Reports & Custom Dashboards feature
-- Run this SQL script to set up the database tables for the reports functionality

-- Table for saved reports
CREATE TABLE IF NOT EXISTS `saved_reports` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text,
    `filters_json` text NOT NULL,
    `custom_view` varchar(50) DEFAULT 'all',
    `created_by` int(11) NOT NULL,
    `is_public` boolean DEFAULT FALSE,
    `is_active` boolean DEFAULT TRUE,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for report schedules
CREATE TABLE IF NOT EXISTS `report_schedules` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `report_id` int(11) NOT NULL,
    `frequency` varchar(20) NOT NULL,
    `time_of_day` time NOT NULL,
    `day_of_week` int(1) DEFAULT NULL,
    `day_of_month` int(2) DEFAULT NULL,
    `email_recipients` text NOT NULL,
    `is_active` boolean DEFAULT TRUE,
    `last_sent_at` timestamp NULL DEFAULT NULL,
    `next_run` timestamp NULL DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for report execution logs
CREATE TABLE IF NOT EXISTS `report_execution_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `schedule_id` int(11) DEFAULT NULL,
    `report_id` int(11) NOT NULL,
    `executed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `status` varchar(20) NOT NULL,
    `recipients_count` int(11) DEFAULT 0,
    `execution_time` decimal(10,3) DEFAULT NULL,
    `error_message` text,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for report sharing
CREATE TABLE IF NOT EXISTS `report_sharing` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `report_id` int(11) NOT NULL,
    `shared_with_admin_id` int(11) NOT NULL,
    `permission` varchar(20) DEFAULT 'read',
    `shared_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `shared_by` int(11) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for report favorites
CREATE TABLE IF NOT EXISTS `report_favorites` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `report_id` int(11) NOT NULL,
    `admin_id` int(11) NOT NULL,
    `favorited_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample saved reports
INSERT INTO `saved_reports` (`name`, `description`, `filters_json`, `custom_view`, `created_by`, `is_public`) VALUES
('Top Performing Campaigns This Quarter', 'Campaigns with highest ROAS in Q4 2024', '{"date_range":"last_90_days","sort_by":"roas","sort_order":"desc","limit":20}', 'top_performing', 1, 1),
('Pakistan Market Analysis', 'All campaigns targeting Pakistan with performance metrics', '{"countries":["PK"],"date_range":"last_30_days","platforms":["facebook","instagram"]}', 'country_analysis', 1, 1),
('Video Ads Performance', 'Video format ads with CTR analysis', '{"ad_formats":["video"],"ctr_threshold":2.0,"date_range":"last_14_days"}', 'video_performance', 1, 0),
('High Spend Campaigns', 'Campaigns with daily spend over $500', '{"min_spend":500,"date_range":"last_7_days","status":"active"}', 'high_spend', 1, 1),
('Mobile vs Desktop Comparison', 'Performance comparison across device types', '{"device_types":["mobile","desktop"],"date_range":"last_30_days","group_by":"device"}', 'device_comparison', 1, 1);

-- Insert sample report schedules
INSERT INTO `report_schedules` (`report_id`, `frequency`, `time_of_day`, `day_of_week`, `email_recipients`, `is_active`, `next_run`) VALUES
(1, 'weekly', '09:00:00', 1, '["admin@company.com","manager@company.com"]', 1, DATE_ADD(CURDATE(), INTERVAL (8 - WEEKDAY(CURDATE())) DAY) + INTERVAL 9 HOUR),
(2, 'monthly', '10:00:00', NULL, '["admin@company.com","analyst@company.com"]', 1, DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY) + INTERVAL 10 HOUR),
(3, 'daily', '08:00:00', NULL, '["admin@company.com"]', 0, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 8 HOUR);

-- Insert sample report execution logs
INSERT INTO `report_execution_logs` (`schedule_id`, `report_id`, `executed_at`, `status`, `recipients_count`, `execution_time`, `error_message`) VALUES
(1, 1, NOW() - INTERVAL 3 DAYS, 'success', 2, 2.345, NULL),
(2, 2, NOW() - INTERVAL 1 WEEK, 'success', 2, 1.876, NULL),
(3, 3, NOW() - INTERVAL 2 DAYS, 'failed', 0, 0.500, 'Email server timeout'),
(NULL, 1, NOW() - INTERVAL 1 HOUR, 'success', 1, 1.234, NULL);