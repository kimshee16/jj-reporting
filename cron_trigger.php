<?php
/**
 * Cron Job Trigger for Scheduled Reports
 * This file can be accessed via web URL for cron-job.org
 * 
 * URL: https://your-domain.com/jj-reports-dashboard-2/cron_trigger.php
 * 
 * Security: Add a secret key to prevent unauthorized access
 */

// Security check - require a secret key
$secret_key = 'your-secret-key-here'; // Change this to a secure random string
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $secret_key) {
    http_response_code(403);
    die('Unauthorized access');
}

// Set timezone
date_default_timezone_set('UTC');

// Include required files
require_once 'config.php';
require_once 'classes/ReportScheduler.php';

// Set content type for JSON response
header('Content-Type: application/json');

// Log function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[Cron Trigger] {$message}");
    return "[{$timestamp}] {$message}";
}

$response = [
    'success' => false,
    'message' => '',
    'schedules_found' => 0,
    'schedules_executed' => 0,
    'schedules_failed' => 0,
    'execution_time' => 0
];

$start_time = microtime(true);

try {
    $log_message = logMessage("Cron trigger started");
    
    $scheduler = new ReportScheduler();
    $schedules = $scheduler->getSchedulesToRun();
    
    $response['schedules_found'] = count($schedules);
    
    if (empty($schedules)) {
        $response['message'] = 'No schedules to run at this time';
        $response['success'] = true;
    } else {
        $log_message .= "\n" . logMessage("Found " . count($schedules) . " schedules to execute");
        
        $success_count = 0;
        $failure_count = 0;
        
        foreach ($schedules as $schedule) {
            $log_message .= "\n" . logMessage("Executing schedule ID: {$schedule['id']} for report: {$schedule['report_name']}");
            
            try {
                $result = $scheduler->executeScheduledReport($schedule['id']);
                
                if ($result) {
                    $success_count++;
                    $log_message .= "\n" . logMessage("Successfully executed schedule ID: {$schedule['id']}");
                } else {
                    $failure_count++;
                    $log_message .= "\n" . logMessage("Failed to execute schedule ID: {$schedule['id']}");
                }
            } catch (Exception $e) {
                $failure_count++;
                $log_message .= "\n" . logMessage("Error executing schedule ID: {$schedule['id']} - " . $e->getMessage());
            }
        }
        
        $response['schedules_executed'] = $success_count;
        $response['schedules_failed'] = $failure_count;
        $response['message'] = "Executed {$success_count} schedules successfully, {$failure_count} failed";
        $response['success'] = true;
        
        $log_message .= "\n" . logMessage("Execution completed. Success: {$success_count}, Failures: {$failure_count}");
    }
    
} catch (Exception $e) {
    $response['message'] = 'Fatal error: ' . $e->getMessage();
    $response['success'] = false;
    $log_message .= "\n" . logMessage("Fatal error: " . $e->getMessage());
}

$response['execution_time'] = round(microtime(true) - $start_time, 3);

// Log the complete execution
logMessage("Cron trigger completed in {$response['execution_time']}s");

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT);

// Also log to file for debugging
file_put_contents('cron_log.txt', $log_message . "\n\n", FILE_APPEND | LOCK_EX);
?>
