<?php
/**
 * Scheduled Exports Cron Job
 * 
 * This script should be run via cron job to process scheduled export jobs.
 * Example cron entry: 0 * * * * /usr/bin/php /path/to/scheduled_exports.php
 * 
 * This will run every hour and process any export jobs that are due.
 */

require_once 'config.php';
require_once 'export_handler.php';

class ScheduledExports {
    private $pdo;
    private $export_handler;
    
    public function __construct() {
        $this->pdo = $pdo;
        $this->export_handler = new ExportHandler($pdo, 1); // Use admin ID 1 for system exports
    }
    
    public function processScheduledExports() {
        echo "[" . date('Y-m-d H:i:s') . "] Starting scheduled export processing...\n";
        
        try {
            // Get all active export jobs that are due to run
            $due_jobs = $this->getDueExportJobs();
            
            if (empty($due_jobs)) {
                echo "[" . date('Y-m-d H:i:s') . "] No export jobs due for execution.\n";
                return;
            }
            
            echo "[" . date('Y-m-d H:i:s') . "] Found " . count($due_jobs) . " export jobs due for execution.\n";
            
            foreach ($due_jobs as $job) {
                $this->processExportJob($job);
            }
            
            echo "[" . date('Y-m-d H:i:s') . "] Completed processing all due export jobs.\n";
            
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Error processing scheduled exports: " . $e->getMessage() . "\n";
        }
    }
    
    private function getDueExportJobs() {
        $sql = "SELECT * FROM export_jobs 
                WHERE is_active = 1 
                AND (next_run IS NULL OR next_run <= NOW()) 
                ORDER BY next_run ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    private function processExportJob($job) {
        echo "[" . date('Y-m-d H:i:s') . "] Processing job: " . $job['name'] . "\n";
        
        try {
            // Update last run time
            $this->updateJobLastRun($job['id']);
            
            // Prepare filters from job configuration
            $filters = $this->prepareFilters($job);
            
            // Process the export
            $result = $this->export_handler->processExport(
                $job['export_type'],
                $job['export_format'],
                $job['export_view'],
                $filters,
                json_decode($job['selected_fields'] ?? '[]', true)
            );
            
            if ($result['success']) {
                echo "[" . date('Y-m-d H:i:s') . "] Export completed successfully: " . $result['file_name'] . "\n";
                
                // Send email notifications if configured
                $this->sendEmailNotifications($job, $result);
                
                // Calculate next run time
                $this->updateJobNextRun($job);
                
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] Export failed: " . $result['error'] . "\n";
            }
            
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Error processing job " . $job['name'] . ": " . $e->getMessage() . "\n";
        }
    }
    
    private function prepareFilters($job) {
        $filters = [];
        
        // Add date range filter
        if ($job['date_range']) {
            $filters['date_range'] = $job['date_range'];
        }
        
        // Add account filter
        if ($job['account_filter']) {
            $account_filter = json_decode($job['account_filter'], true);
            if ($account_filter && $account_filter !== 'all') {
                $filters['account_filter'] = $account_filter;
            }
        }
        
        // Add campaign filter
        if ($job['campaign_filter']) {
            $campaign_filter = json_decode($job['campaign_filter'], true);
            if ($campaign_filter) {
                $filters['campaign_filter'] = $campaign_filter;
            }
        }
        
        // Add custom filters
        if ($job['custom_filters']) {
            $custom_filters = json_decode($job['custom_filters'], true);
            if ($custom_filters) {
                $filters = array_merge($filters, $custom_filters);
            }
        }
        
        return $filters;
    }
    
    private function updateJobLastRun($job_id) {
        $sql = "UPDATE export_jobs SET last_run = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$job_id]);
    }
    
    private function updateJobNextRun($job) {
        $next_run = $this->calculateNextRun($job['schedule_type'], $job['schedule_time'], $job['schedule_day']);
        
        $sql = "UPDATE export_jobs SET next_run = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$next_run, $job['id']]);
    }
    
    private function calculateNextRun($schedule_type, $schedule_time, $schedule_day) {
        if ($schedule_type === 'once') {
            return null; // Don't schedule next run for one-time jobs
        }
        
        $time = new DateTime($schedule_time);
        
        switch ($schedule_type) {
            case 'daily':
                $next_run = new DateTime('tomorrow');
                break;
                
            case 'weekly':
                $next_run = new DateTime('next monday');
                if ($schedule_day !== null) {
                    $days_ahead = $schedule_day - $next_run->format('w');
                    if ($days_ahead <= 0) {
                        $days_ahead += 7;
                    }
                    $next_run->modify("+{$days_ahead} days");
                }
                break;
                
            case 'monthly':
                $next_run = new DateTime('first day of next month');
                if ($schedule_day !== null && $schedule_day <= 28) {
                    $next_run->modify("+{$schedule_day} days");
                }
                break;
                
            default:
                return null;
        }
        
        $next_run->setTime($time->format('H'), $time->format('i'));
        return $next_run->format('Y-m-d H:i:s');
    }
    
    private function sendEmailNotifications($job, $result) {
        if (empty($job['email_recipients'])) {
            return;
        }
        
        $recipients = json_decode($job['email_recipients'], true);
        if (empty($recipients)) {
            return;
        }
        
        $subject = "Scheduled Export Completed: " . $job['name'];
        $message = $this->buildEmailMessage($job, $result);
        
        foreach ($recipients as $email) {
            $this->sendEmail($email, $subject, $message, $result['file_path']);
        }
    }
    
    private function buildEmailMessage($job, $result) {
        $message = "<html><body>";
        $message .= "<h2>Scheduled Export Completed</h2>";
        $message .= "<p>Your scheduled export job has completed successfully.</p>";
        
        $message .= "<h3>Export Details:</h3>";
        $message .= "<ul>";
        $message .= "<li><strong>Job Name:</strong> " . htmlspecialchars($job['name']) . "</li>";
        $message .= "<li><strong>Type:</strong> " . ucfirst(str_replace('_', ' ', $job['export_type'])) . "</li>";
        $message .= "<li><strong>Format:</strong> " . strtoupper($job['export_format']) . "</li>";
        $message .= "<li><strong>View:</strong> " . ucfirst($job['export_view']) . "</li>";
        $message .= "<li><strong>File Name:</strong> " . htmlspecialchars($result['file_name']) . "</li>";
        $message .= "<li><strong>Records:</strong> " . number_format($result['record_count']) . "</li>";
        $message .= "<li><strong>File Size:</strong> " . $this->formatFileSize($result['file_size']) . "</li>";
        $message .= "<li><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</li>";
        $message .= "</ul>";
        
        if (!empty($job['description'])) {
            $message .= "<h3>Description:</h3>";
            $message .= "<p>" . htmlspecialchars($job['description']) . "</p>";
        }
        
        $message .= "<p>You can download the file from the Export Center in your dashboard.</p>";
        $message .= "<p>Best regards,<br>JJ Reporting System</p>";
        $message .= "</body></html>";
        
        return $message;
    }
    
    private function sendEmail($to, $subject, $message, $file_path) {
        // Simple email sending - in production, use PHPMailer or similar
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@jjreports.com" . "\r\n";
        
        if (mail($to, $subject, $message, $headers)) {
            echo "[" . date('Y-m-d H:i:s') . "] Email notification sent to: " . $to . "\n";
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] Failed to send email to: " . $to . "\n";
        }
    }
    
    private function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    public function cleanupOldExports() {
        echo "[" . date('Y-m-d H:i:s') . "] Starting cleanup of old export files...\n";
        
        try {
            // Get retention settings
            $sql = "SELECT retention_days FROM export_settings WHERE admin_id = 1 LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $settings = $stmt->fetch();
            
            $retention_days = $settings['retention_days'] ?? 30;
            
            // Find old exports
            $sql = "SELECT * FROM export_history 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) 
                    AND status = 'completed'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$retention_days]);
            $old_exports = $stmt->fetchAll();
            
            echo "[" . date('Y-m-d H:i:s') . "] Found " . count($old_exports) . " old export files to clean up.\n";
            
            foreach ($old_exports as $export) {
                // Delete file if it exists
                if (file_exists($export['file_path'])) {
                    if (unlink($export['file_path'])) {
                        echo "[" . date('Y-m-d H:i:s') . "] Deleted file: " . $export['file_name'] . "\n";
                    } else {
                        echo "[" . date('Y-m-d H:i:s') . "] Failed to delete file: " . $export['file_name'] . "\n";
                    }
                }
                
                // Update database record
                $sql = "UPDATE export_history SET file_path = NULL, file_size = NULL WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$export['id']]);
            }
            
            echo "[" . date('Y-m-d H:i:s') . "] Completed cleanup of old export files.\n";
            
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Error during cleanup: " . $e->getMessage() . "\n";
        }
    }
}

// Run the scheduled exports
$scheduled_exports = new ScheduledExports();

// Process scheduled exports
$scheduled_exports->processScheduledExports();

// Cleanup old exports (run less frequently)
if (date('H') == '02') { // Run cleanup at 2 AM
    $scheduled_exports->cleanupOldExports();
}

echo "[" . date('Y-m-d H:i:s') . "] Scheduled exports script completed.\n";
?>
