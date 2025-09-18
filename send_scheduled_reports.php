<?php
// This file should be run as a cron job to send scheduled reports
// Example cron entry: 0 9 * * * /usr/bin/php /path/to/send_scheduled_reports.php

// Database connection
$host = 'localhost';
$dbname = 'report-database';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    exit(1);
}

// Get reports that need to be sent
$current_time = date('Y-m-d H:i:s');
try {
    $stmt = $pdo->prepare("
        SELECT rs.*, sr.name as report_name, sr.filters_json, sr.custom_view
        FROM report_schedules rs
        JOIN saved_reports sr ON rs.report_id = sr.id
        WHERE rs.is_active = 1 
        AND rs.next_run <= ?
        ORDER BY rs.next_run ASC
    ");
    $stmt->execute([$current_time]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching scheduled reports: " . $e->getMessage());
    exit(1);
}

foreach ($schedules as $schedule) {
    try {
        // Generate report data
        $report_data = generateReportData($schedule['filters_json'], $schedule['custom_view']);
        
        // Generate email content
        $email_content = generateEmailContent($schedule, $report_data);
        
        // Send email
        $email_sent = sendEmail(
            json_decode($schedule['email_recipients'], true),
            $schedule['email_subject'],
            $email_content
        );
        
        // Log execution
        logExecution($pdo, $schedule['report_id'], $schedule['id'], $report_data, $email_sent, $schedule['email_recipients']);
        
        // Update next run time
        updateNextRunTime($pdo, $schedule['id'], $schedule['frequency'], $schedule['day_of_week'], $schedule['day_of_month'], $schedule['time_of_day']);
        
        echo "Report '{$schedule['report_name']}' sent successfully.\n";
        
    } catch (Exception $e) {
        error_log("Error processing schedule {$schedule['id']}: " . $e->getMessage());
        
        // Log failed execution
        logExecution($pdo, $schedule['report_id'], $schedule['id'], [], false, $schedule['email_recipients'], $e->getMessage());
    }
}

function generateReportData($filters_json, $custom_view) {
    // This is a simplified version - in a real implementation, you would
    // call the same functions used in cross_account_reports.php
    $filters = json_decode($filters_json, true);
    
    // For demo purposes, return mock data
    return [
        'total_ads' => rand(50, 200),
        'total_spend' => rand(1000, 10000),
        'avg_roas' => rand(150, 400) / 100,
        'avg_ctr' => rand(100, 500) / 100,
        'top_performers' => [
            ['name' => 'Campaign A', 'spend' => 1500, 'roas' => 3.2],
            ['name' => 'Campaign B', 'spend' => 1200, 'roas' => 2.8],
            ['name' => 'Campaign C', 'spend' => 800, 'roas' => 4.1]
        ]
    ];
}

function generateEmailContent($schedule, $report_data) {
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$schedule['email_subject']}</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: white; padding: 20px; border: 1px solid #ddd; }
            .footer { background: #f8f9fa; padding: 15px; border-radius: 0 0 8px 8px; font-size: 12px; color: #666; }
            .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin: 20px 0; }
            .stat { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px; }
            .stat-value { font-size: 24px; font-weight: bold; color: #1877f2; }
            .stat-label { font-size: 12px; color: #666; margin-top: 5px; }
            .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            .table th { background: #f8f9fa; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$schedule['report_name']}</h2>
                <p>Automated Report - " . date('F j, Y g:i A') . "</p>
            </div>
            
            <div class='content'>
                " . ($schedule['email_message'] ? "<p>{$schedule['email_message']}</p>" : "") . "
                
                <div class='stats'>
                    <div class='stat'>
                        <div class='stat-value'>{$report_data['total_ads']}</div>
                        <div class='stat-label'>Total Ads</div>
                    </div>
                    <div class='stat'>
                        <div class='stat-value'>$" . number_format($report_data['total_spend'], 0) . "</div>
                        <div class='stat-label'>Total Spend</div>
                    </div>
                    <div class='stat'>
                        <div class='stat-value'>{$report_data['avg_roas']}x</div>
                        <div class='stat-label'>Avg ROAS</div>
                    </div>
                    <div class='stat'>
                        <div class='stat-value'>{$report_data['avg_ctr']}%</div>
                        <div class='stat-label'>Avg CTR</div>
                    </div>
                </div>
                
                <h3>Top Performing Campaigns</h3>
                <table class='table'>
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Spend</th>
                            <th>ROAS</th>
                        </tr>
                    </thead>
                    <tbody>";
    
    foreach ($report_data['top_performers'] as $performer) {
        $html .= "
                        <tr>
                            <td>{$performer['name']}</td>
                            <td>$" . number_format($performer['spend'], 0) . "</td>
                            <td>{$performer['roas']}x</td>
                        </tr>";
    }
    
    $html .= "
                    </tbody>
                </table>
            </div>
            
            <div class='footer'>
                <p>This is an automated report from JJ Reporting Dashboard.</p>
                <p>To modify or stop these reports, please log into the dashboard.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

function sendEmail($recipients, $subject, $html_content) {
    // Simple mail function - in production, use a proper SMTP library like PHPMailer
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: JJ Reporting Dashboard <noreply@yourdomain.com>',
        'Reply-To: noreply@yourdomain.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $to = implode(', ', $recipients);
    $headers_string = implode("\r\n", $headers);
    
    return mail($to, $subject, $html_content, $headers_string);
}

function logExecution($pdo, $report_id, $schedule_id, $report_data, $email_sent, $email_recipients, $error_message = null) {
    try {
        $status = $error_message ? 'failed' : ($email_sent ? 'success' : 'partial');
        $records_found = isset($report_data['total_ads']) ? $report_data['total_ads'] : 0;
        
        $stmt = $pdo->prepare("
            INSERT INTO report_execution_logs 
            (report_id, schedule_id, execution_type, status, records_found, email_sent, email_recipients, error_message) 
            VALUES (?, ?, 'scheduled', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$report_id, $schedule_id, $status, $records_found, $email_sent ? 1 : 0, $email_recipients, $error_message]);
    } catch (PDOException $e) {
        error_log("Error logging execution: " . $e->getMessage());
    }
}

function updateNextRunTime($pdo, $schedule_id, $frequency, $day_of_week, $day_of_month, $time_of_day) {
    try {
        $next_run = calculateNextRun($frequency, $day_of_week, $day_of_month, $time_of_day);
        
        $stmt = $pdo->prepare("
            UPDATE report_schedules 
            SET last_sent = NOW(), next_run = ? 
            WHERE id = ?
        ");
        $stmt->execute([$next_run, $schedule_id]);
    } catch (PDOException $e) {
        error_log("Error updating next run time: " . $e->getMessage());
    }
}

function calculateNextRun($frequency, $day_of_week, $day_of_month, $time_of_day) {
    $now = new DateTime();
    $next_run = clone $now;
    
    switch ($frequency) {
        case 'daily':
            $next_run->setTime(explode(':', $time_of_day)[0], explode(':', $time_of_day)[1]);
            if ($next_run <= $now) {
                $next_run->add(new DateInterval('P1D'));
            }
            break;
            
        case 'weekly':
            $target_day = $day_of_week;
            $current_day = $now->format('N');
            
            $next_run->setTime(explode(':', $time_of_day)[0], explode(':', $time_of_day)[1]);
            
            $days_until_target = ($target_day - $current_day + 7) % 7;
            if ($days_until_target === 0 && $next_run <= $now) {
                $days_until_target = 7;
            }
            
            $next_run->add(new DateInterval('P' . $days_until_target . 'D'));
            break;
            
        case 'monthly':
            $next_run->setTime(explode(':', $time_of_day)[0], explode(':', $time_of_day)[1]);
            $next_run->setDate($now->format('Y'), $now->format('m'), $day_of_month);
            
            if ($next_run <= $now) {
                $next_run->add(new DateInterval('P1M'));
            }
            break;
    }
    
    return $next_run->format('Y-m-d H:i:s');
}

echo "Scheduled reports processing completed.\n";
?>
