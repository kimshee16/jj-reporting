-- Create tables for Saved Reports & Custom Dashboards feature

-- Table for saved reports
CREATE TABLE IF NOT EXISTS `saved_reports` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text,
    `filters_json` longtext NOT NULL,
    `custom_view` varchar(100) DEFAULT 'all',
    `created_by` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_public` tinyint(1) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `created_by` (`created_by`),
    KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for report schedules
CREATE TABLE IF NOT EXISTS `report_schedules` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `report_id` int(11) NOT NULL,
    `frequency` enum('daily','weekly','monthly') NOT NULL,
    `day_of_week` tinyint(1) DEFAULT NULL COMMENT '1=Monday, 7=Sunday',
    `day_of_month` tinyint(2) DEFAULT NULL COMMENT '1-31',
    `time_of_day` time NOT NULL DEFAULT '09:00:00',
    `email_recipients` text NOT NULL COMMENT 'JSON array of email addresses',
    `email_subject` varchar(255) DEFAULT 'Scheduled Report',
    `email_message` text,
    `last_sent` timestamp NULL DEFAULT NULL,
    `next_run` timestamp NULL DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `report_id` (`report_id`),
    KEY `next_run` (`next_run`),
    KEY `is_active` (`is_active`),
    FOREIGN KEY (`report_id`) REFERENCES `saved_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for report execution logs
CREATE TABLE IF NOT EXISTS `report_execution_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `report_id` int(11) NOT NULL,
    `schedule_id` int(11) DEFAULT NULL,
    `execution_type` enum('manual','scheduled') NOT NULL,
    `status` enum('success','failed','partial') NOT NULL,
    `records_found` int(11) DEFAULT 0,
    `execution_time` decimal(10,3) DEFAULT NULL COMMENT 'Execution time in seconds',
    `error_message` text,
    `email_sent` tinyint(1) DEFAULT 0,
    `email_recipients` text,
    `executed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `report_id` (`report_id`),
    KEY `schedule_id` (`schedule_id`),
    KEY `executed_at` (`executed_at`),
    FOREIGN KEY (`report_id`) REFERENCES `saved_reports` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`schedule_id`) REFERENCES `report_schedules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for report sharing
CREATE TABLE IF NOT EXISTS `report_sharing` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `report_id` int(11) NOT NULL,
    `shared_with_admin_id` int(11) NOT NULL,
    `permission_level` enum('view','edit') DEFAULT 'view',
    `shared_by` int(11) NOT NULL,
    `shared_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_share` (`report_id`, `shared_with_admin_id`),
    KEY `report_id` (`report_id`),
    KEY `shared_with_admin_id` (`shared_with_admin_id`),
    FOREIGN KEY (`report_id`) REFERENCES `saved_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data
INSERT INTO `saved_reports` (`name`, `description`, `filters_json`, `custom_view`, `created_by`, `is_public`) VALUES
('Top Performing Q4 2024', 'Best performing campaigns this quarter with ROAS > 2x', '{"date_preset":"this_quarter","platform":"all","device":"all","country":"all","age":"all","placement":"all","format":"all","objective":"all","ctr_threshold":"","roas_threshold":"2.0","sort_by":"roas"}', 'top_performing_quarter', 1, 1),
('Pakistan Mobile Campaigns', 'Mobile-optimized campaigns targeting Pakistan', '{"date_preset":"this_month","platform":"all","device":"mobile","country":"PK","age":"all","placement":"all","format":"all","objective":"all","ctr_threshold":"","roas_threshold":"","sort_by":"spend"}', 'mobile_optimized', 1, 1),
('Video Ads Analysis', 'All video ads with performance metrics', '{"date_preset":"this_month","platform":"all","device":"all","country":"all","age":"all","placement":"all","format":"VIDEO","objective":"all","ctr_threshold":"","roas_threshold":"","sort_by":"ctr"}', 'video_ctr_2', 1, 0);
