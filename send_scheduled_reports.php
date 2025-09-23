<?php
/**
 * Send Scheduled Reports
 * This script should be run via cron job to execute scheduled reports
 * 
 * Cron job example:
 * # Run every 15 minutes
 * * /15 * * * * /usr/bin/php /path/to/send_scheduled_reports.php
 * 
 * # Run every hour at minute 0
 * 0 * * * * /usr/bin/php /path/to/send_scheduled_reports.php
 */

// Prevent direct access from web
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line');
}

// Set timezone
date_default_timezone_set('UTC');

// Include required files
require_once 'config.php';
require_once 'classes/ReportScheduler.php';

// Log function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
    error_log("[Scheduled Reports] {$message}");
}

try {
    logMessage("Starting scheduled reports execution");
    
    $scheduler = new ReportScheduler();
    $schedules = $scheduler->getSchedulesToRun();
    
    if (empty($schedules)) {
        logMessage("No schedules to run at this time");
        exit(0);
    }
    
    logMessage("Found " . count($schedules) . " schedules to execute");
    
    $success_count = 0;
    $failure_count = 0;
    
    foreach ($schedules as $schedule) {
        logMessage("Executing schedule ID: {$schedule['id']} for report: {$schedule['report_name']}");
        
        try {
            $result = $scheduler->executeScheduledReport($schedule['id']);
            
            if ($result) {
                $success_count++;
                logMessage("Successfully executed schedule ID: {$schedule['id']}");
            } else {
                $failure_count++;
                logMessage("Failed to execute schedule ID: {$schedule['id']}");
            }
        } catch (Exception $e) {
            $failure_count++;
            logMessage("Error executing schedule ID: {$schedule['id']} - " . $e->getMessage());
        }
    }
    
    logMessage("Execution completed. Success: {$success_count}, Failures: {$failure_count}");
    
} catch (Exception $e) {
    logMessage("Fatal error: " . $e->getMessage());
    exit(1);
}
?>