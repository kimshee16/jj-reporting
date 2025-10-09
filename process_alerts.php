<?php
// This file should be run as a cron job to process alerts
// Example cron entry: */15 * * * * /usr/bin/php /path/to/process_alerts.php

require_once 'config.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    exit(1);
}

// Get all active alert rules
try {
    $stmt = $pdo->prepare("
        SELECT ar.*, as_settings.email_notifications, as_settings.in_app_notifications, as_settings.email_frequency
        FROM alert_rules ar
        LEFT JOIN alert_settings as_settings ON ar.created_by = as_settings.admin_id
        WHERE ar.is_active = 1
    ");
    $stmt->execute();
    $alert_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching alert rules: " . $e->getMessage());
    exit(1);
}

$total_alerts_triggered = 0;

foreach ($alert_rules as $rule) {
    try {
        $alerts_triggered = processAlertRule($pdo, $rule);
        $total_alerts_triggered += $alerts_triggered;
        
        // Log execution
        logAlertExecution($pdo, $rule['id'], $alerts_triggered, 'success');
        
        echo "Rule '{$rule['name']}': {$alerts_triggered} alerts triggered\n";
        
    } catch (Exception $e) {
        error_log("Error processing rule {$rule['id']}: " . $e->getMessage());
        logAlertExecution($pdo, $rule['id'], 0, 'failed', $e->getMessage());
    }
}

echo "Alert processing completed. Total alerts triggered: {$total_alerts_triggered}\n";

function processAlertRule($pdo, $rule) {
    $alerts_triggered = 0;
    
    // Get data based on scope
    $data = fetchDataForRule($pdo, $rule);
    
    foreach ($data as $item) {
        $metric_value = getMetricValue($item, $rule['rule_type']);
        
        if ($metric_value === null) {
            continue; // Skip if metric value is not available
        }
        
        $should_alert = evaluateCondition($metric_value, $rule['condition'], $rule['threshold_value']);
        
        if ($should_alert) {
            // Check if we already have a recent alert for this entity
            if (!hasRecentAlert($pdo, $rule['id'], $item['entity_type'], $item['entity_id'])) {
                $alert_message = generateAlertMessage($rule, $item, $metric_value);
                
                // Create alert notification
                createAlertNotification($pdo, $rule, $item, $metric_value, $alert_message);
                
                // Send email if enabled
                if ($rule['email_notifications']) {
                    sendAlertEmail($rule, $item, $alert_message);
                }
                
                $alerts_triggered++;
            }
        }
    }
    
    return $alerts_triggered;
}

function fetchDataForRule($pdo, $rule) {
    // This is a simplified version - in a real implementation, you would
    // call the Facebook API to get actual campaign/ad data
    
    // For demo purposes, return mock data
    $mock_data = [
        [
            'entity_type' => 'ad',
            'entity_id' => 'ad_123',
            'entity_name' => 'Test Ad 1',
            'account_id' => 'act_123',
            'account_name' => 'Test Account',
            'cpa' => rand(20, 80),
            'roas' => rand(50, 500) / 100,
            'ctr' => rand(50, 500) / 100,
            'cpc' => rand(100, 800) / 100,
            'cpm' => rand(500, 2000) / 100,
            'spend' => rand(100, 2000),
            'impressions' => rand(1000, 50000),
            'clicks' => rand(50, 2000)
        ],
        [
            'entity_type' => 'campaign',
            'entity_id' => 'camp_456',
            'entity_name' => 'Test Campaign 1',
            'account_id' => 'act_123',
            'account_name' => 'Test Account',
            'cpa' => rand(30, 70),
            'roas' => rand(80, 400) / 100,
            'ctr' => rand(60, 400) / 100,
            'cpc' => rand(120, 600) / 100,
            'cpm' => rand(400, 1500) / 100,
            'spend' => rand(200, 1500),
            'impressions' => rand(2000, 30000),
            'clicks' => rand(100, 1500)
        ]
    ];
    
    // Filter data based on rule filters
    return array_filter($mock_data, function($item) use ($rule) {
        // Apply platform, country, and objective filters here
        // This is simplified for demo purposes
        return true;
    });
}

function getMetricValue($item, $rule_type) {
    switch ($rule_type) {
        case 'cpa':
            return $item['cpa'] ?? null;
        case 'roas':
            return $item['roas'] ?? null;
        case 'ctr':
            return $item['ctr'] ?? null;
        case 'cpc':
            return $item['cpc'] ?? null;
        case 'cpm':
            return $item['cpm'] ?? null;
        case 'spend':
            return $item['spend'] ?? null;
        case 'impressions':
            return $item['impressions'] ?? null;
        case 'clicks':
            return $item['clicks'] ?? null;
        default:
            return null;
    }
}

function evaluateCondition($metric_value, $condition, $threshold) {
    switch ($condition) {
        case 'greater_than':
            return $metric_value > $threshold;
        case 'less_than':
            return $metric_value < $threshold;
        case 'equals':
            return abs($metric_value - $threshold) < 0.01; // Account for floating point precision
        case 'not_equals':
            return abs($metric_value - $threshold) >= 0.01;
        case 'greater_than_or_equal':
            return $metric_value >= $threshold;
        case 'less_than_or_equal':
            return $metric_value <= $threshold;
        default:
            return false;
    }
}

function hasRecentAlert($pdo, $rule_id, $entity_type, $entity_id) {
    // Check if there's already an alert for this entity in the last hour
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM alert_notifications 
            WHERE rule_id = ? AND entity_type = ? AND entity_id = ? 
            AND triggered_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$rule_id, $entity_type, $entity_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function generateAlertMessage($rule, $item, $metric_value) {
    $condition_text = str_replace('_', ' ', $rule['condition']);
    $threshold_text = formatMetricValue($rule['rule_type'], $rule['threshold_value']);
    $current_value = formatMetricValue($rule['rule_type'], $metric_value);
    
    return "Alert: {$rule['name']} - {$item['entity_name']} {$condition_text} {$threshold_text} (Current: {$current_value})";
}

function formatMetricValue($rule_type, $value) {
    switch ($rule_type) {
        case 'cpa':
        case 'cpc':
        case 'cpm':
        case 'spend':
            return '$' . number_format($value, 2);
        case 'roas':
            return number_format($value, 2) . 'x';
        case 'ctr':
            return number_format($value, 2) . '%';
        case 'impressions':
        case 'clicks':
            return number_format($value);
        default:
            return number_format($value, 2);
    }
}

function createAlertNotification($pdo, $rule, $item, $metric_value, $alert_message) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO alert_notifications 
            (rule_id, entity_type, entity_id, entity_name, account_id, account_name, 
             metric_value, threshold_value, alert_message, notification_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $notification_type = 'both';
        if ($rule['email_notifications'] && !$rule['in_app_notifications']) {
            $notification_type = 'email';
        } elseif (!$rule['email_notifications'] && $rule['in_app_notifications']) {
            $notification_type = 'in_app';
        }
        
        $stmt->execute([
            $rule['id'],
            $item['entity_type'],
            $item['entity_id'],
            $item['entity_name'],
            $item['account_id'],
            $item['account_name'],
            $metric_value,
            $rule['threshold_value'],
            $alert_message,
            $notification_type
        ]);
    } catch (PDOException $e) {
        error_log("Error creating alert notification: " . $e->getMessage());
    }
}

function sendAlertEmail($rule, $item, $alert_message) {
    // Use EmailService for robust SMTP and fallback support
    require_once __DIR__ . '/classes/EmailService.php';
    $emailService = new EmailService();

    // In a real implementation, fetch the admin's email from the database
    $admin_email = defined('FROM_EMAIL') ? FROM_EMAIL : 'admin@yourdomain.com';

    $subject = "Alert: {$rule['name']} - {$item['entity_name']}";

    $html_content = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$subject}</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: white; padding: 20px; border: 1px solid #ddd; }
            .footer { background: #f8f9fa; padding: 15px; border-radius: 0 0 8px 8px; font-size: 12px; color: #666; }
            .alert-details { background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .metric { font-size: 18px; font-weight: bold; color: #dc3545; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🚨 Alert Triggered</h2>
                <p>{$rule['name']}</p>
            </div>
            <div class='content'>
                <div class='alert-details'>
                    <p><strong>Alert Message:</strong> {$alert_message}</p>
                </div>
                <h3>Details:</h3>
                <ul>
                    <li><strong>Entity:</strong> {$item['entity_name']} ({$item['entity_type']})</li>
                    <li><strong>Account:</strong> {$item['account_name']}</li>
                    <li><strong>Triggered:</strong> " . date('F j, Y g:i A') . "</li>
                </ul>
                <p>Please check your dashboard for more details and take appropriate action.</p>
            </div>
            <div class='footer'>
                <p>This is an automated alert from JJ Reporting Dashboard.</p>
                <p>To modify or disable these alerts, please log into the dashboard.</p>
            </div>
        </div>
    </body>
    </html>";

    $text_content = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html_content));

    return $emailService->sendEmail($admin_email, $subject, $html_content, $text_content);
}

function logAlertExecution($pdo, $rule_id, $alerts_triggered, $status, $error_message = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO alert_execution_logs 
            (rule_id, execution_type, status, alerts_triggered, error_message) 
            VALUES (?, 'scheduled', ?, ?, ?)
        ");
        $stmt->execute([$rule_id, $status, $alerts_triggered, $error_message]);
    } catch (PDOException $e) {
        error_log("Error logging alert execution: " . $e->getMessage());
    }
}
?>
