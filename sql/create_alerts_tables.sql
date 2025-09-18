-- Create tables for Scheduled Alerts feature

-- Table for alert rules
CREATE TABLE IF NOT EXISTS `alert_rules` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text,
    `rule_type` enum('cpa','roas','ctr','cpc','cpm','spend','impressions','clicks') NOT NULL,
    `condition` enum('greater_than','less_than','equals','not_equals','greater_than_or_equal','less_than_or_equal') NOT NULL,
    `threshold_value` decimal(10,2) NOT NULL,
    `scope` enum('campaign','ad','account') NOT NULL DEFAULT 'ad',
    `platform_filter` varchar(50) DEFAULT 'all',
    `country_filter` varchar(10) DEFAULT 'all',
    `objective_filter` varchar(50) DEFAULT 'all',
    `is_active` tinyint(1) DEFAULT 1,
    `created_by` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `created_by` (`created_by`),
    KEY `is_active` (`is_active`),
    KEY `rule_type` (`rule_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for alert notifications
CREATE TABLE IF NOT EXISTS `alert_notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `rule_id` int(11) NOT NULL,
    `entity_type` enum('campaign','ad','account') NOT NULL,
    `entity_id` varchar(100) NOT NULL,
    `entity_name` varchar(255) NOT NULL,
    `account_id` varchar(100) NOT NULL,
    `account_name` varchar(255) NOT NULL,
    `metric_value` decimal(10,2) NOT NULL,
    `threshold_value` decimal(10,2) NOT NULL,
    `alert_message` text NOT NULL,
    `status` enum('new','read','dismissed') DEFAULT 'new',
    `notification_type` enum('in_app','email','both') DEFAULT 'both',
    `email_sent` tinyint(1) DEFAULT 0,
    `triggered_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `read_at` timestamp NULL DEFAULT NULL,
    `dismissed_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `rule_id` (`rule_id`),
    KEY `status` (`status`),
    KEY `triggered_at` (`triggered_at`),
    KEY `entity_type` (`entity_type`),
    FOREIGN KEY (`rule_id`) REFERENCES `alert_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for alert settings
CREATE TABLE IF NOT EXISTS `alert_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `email_notifications` tinyint(1) DEFAULT 1,
    `in_app_notifications` tinyint(1) DEFAULT 1,
    `email_frequency` enum('immediate','hourly','daily') DEFAULT 'immediate',
    `quiet_hours_start` time DEFAULT NULL,
    `quiet_hours_end` time DEFAULT NULL,
    `max_alerts_per_hour` int(11) DEFAULT 10,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for alert execution logs
CREATE TABLE IF NOT EXISTS `alert_execution_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `rule_id` int(11) NOT NULL,
    `execution_type` enum('manual','scheduled') NOT NULL,
    `status` enum('success','failed','partial') NOT NULL,
    `alerts_triggered` int(11) DEFAULT 0,
    `execution_time` decimal(10,3) DEFAULT NULL,
    `error_message` text,
    `executed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `rule_id` (`rule_id`),
    KEY `executed_at` (`executed_at`),
    FOREIGN KEY (`rule_id`) REFERENCES `alert_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample alert rules
INSERT INTO `alert_rules` (`name`, `description`, `rule_type`, `condition`, `threshold_value`, `scope`, `platform_filter`, `country_filter`, `objective_filter`, `created_by`, `is_active`) VALUES
('High CPA Alert', 'Alert when cost per acquisition exceeds $50', 'cpa', 'greater_than', 50.00, 'ad', 'all', 'all', 'all', 1, 1),
('Low ROAS Alert', 'Alert when return on ad spend drops below 1.0', 'roas', 'less_than', 1.00, 'campaign', 'all', 'all', 'all', 1, 1),
('High CPC Alert', 'Alert when cost per click exceeds $5', 'cpc', 'greater_than', 5.00, 'ad', 'all', 'all', 'all', 1, 1),
('Low CTR Alert', 'Alert when click-through rate drops below 1%', 'ctr', 'less_than', 1.00, 'ad', 'all', 'all', 'all', 1, 1),
('High Spend Alert', 'Alert when daily spend exceeds $1000', 'spend', 'greater_than', 1000.00, 'campaign', 'all', 'all', 'all', 1, 1);

-- Insert default alert settings for admin
INSERT INTO `alert_settings` (`admin_id`, `email_notifications`, `in_app_notifications`, `email_frequency`, `max_alerts_per_hour`) VALUES
(1, 1, 1, 'immediate', 10);
