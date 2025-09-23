<?php
/**
 * ReportScheduler Class
 * Handles scheduling and execution of saved reports
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/ExportHandler.php';

class ReportScheduler {
    private $pdo;
    private $emailService;
    private $exportHandler;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
        
        $this->emailService = new EmailService();
        $this->exportHandler = new ExportHandler();
    }
    
    /**
     * Get all active schedules that need to run
     */
    public function getSchedulesToRun() {
        $sql = "SELECT rs.*, sr.name as report_name, sr.description, sr.filters_json, sr.custom_view
                FROM report_schedules rs
                JOIN saved_reports sr ON rs.report_id = sr.id
                WHERE rs.is_active = 1 
                AND rs.next_run <= NOW()
                ORDER BY rs.next_run ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Execute a scheduled report
     */
    public function executeScheduledReport($schedule_id) {
        $start_time = microtime(true);
        
        try {
            // Get schedule details
            $schedule = $this->getScheduleById($schedule_id);
            if (!$schedule) {
                throw new Exception("Schedule not found: {$schedule_id}");
            }
            
            // Get report details
            $report = $this->getReportById($schedule['report_id']);
            if (!$report) {
                throw new Exception("Report not found: {$schedule['report_id']}");
            }
            
            // Parse email recipients
            $recipients = json_decode($schedule['email_recipients'], true);
            if (!$this->emailService->validateEmails($recipients)) {
                throw new Exception("Invalid email recipients");
            }
            
            // Generate report data
            $report_data = $this->generateReportData($report);
            
            // Create CSV export
            $csv_file = $this->createCSVExport($report, $report_data);
            
            // Send email with attachment
            $email_sent = $this->emailService->sendReportWithAttachment(
                $report_data, 
                $recipients, 
                $csv_file, 
                $schedule
            );
            
            if (!$email_sent) {
                throw new Exception("Failed to send email");
            }
            
            // Update schedule next run time
            $this->updateNextRunTime($schedule_id);
            
            // Log successful execution
            $execution_time = microtime(true) - $start_time;
            $this->logExecution($schedule_id, $schedule['report_id'], 'success', count($recipients), $execution_time);
            
            // Clean up temporary file
            if (file_exists($csv_file)) {
                unlink($csv_file);
            }
            
            return true;
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            $this->logExecution($schedule_id, $schedule['report_id'] ?? 0, 'failed', 0, $execution_time, $e->getMessage());
            
            error_log("Report execution failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get schedule by ID
     */
    private function getScheduleById($schedule_id) {
        $sql = "SELECT * FROM report_schedules WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $schedule_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get report by ID
     */
    private function getReportById($report_id) {
        $sql = "SELECT * FROM saved_reports WHERE id = :id AND is_active = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $report_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate report data based on filters
     */
    private function generateReportData($report) {
        $filters = json_decode($report['filters_json'], true);
        
        // Use ExportHandler to get data
        $data = $this->exportHandler->fetchCrossAccountData($filters);
        
        return [
            'id' => $report['id'],
            'name' => $report['name'],
            'description' => $report['description'],
            'filters' => $filters,
            'custom_view' => $report['custom_view'],
            'data' => $data,
            'total_records' => count($data),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Create CSV export file
     */
    private function createCSVExport($report, $report_data) {
        $filename = 'report_' . $report['id'] . '_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = sys_get_temp_dir() . '/' . $filename;
        
        // Create CSV content
        $csv_content = $this->generateCSVContent($report_data['data']);
        
        // Write to file
        file_put_contents($filepath, $csv_content);
        
        return $filepath;
    }
    
    /**
     * Generate CSV content from data
     */
    private function generateCSVContent($data) {
        if (empty($data)) {
            return "No data available\n";
        }
        
        $csv = '';
        
        // Headers
        $headers = array_keys($data[0]);
        $csv .= '"' . implode('","', $headers) . '"' . "\n";
        
        // Data rows
        foreach ($data as $row) {
            $csv .= '"' . implode('","', array_map('strval', $row)) . '"' . "\n";
        }
        
        return $csv;
    }
    
    /**
     * Update next run time for schedule
     */
    private function updateNextRunTime($schedule_id) {
        $schedule = $this->getScheduleById($schedule_id);
        if (!$schedule) return;
        
        $next_run = $this->calculateNextRunTime($schedule);
        
        $sql = "UPDATE report_schedules 
                SET next_run = :next_run, last_sent_at = NOW() 
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':next_run', $next_run);
        $stmt->bindParam(':id', $schedule_id);
        $stmt->execute();
    }
    
    /**
     * Calculate next run time based on frequency
     */
    private function calculateNextRunTime($schedule) {
        $now = new DateTime();
        $time_of_day = $schedule['time_of_day'];
        
        switch ($schedule['frequency']) {
            case 'daily':
                $next_run = clone $now;
                $next_run->add(new DateInterval('P1D'));
                $next_run->setTime(
                    substr($time_of_day, 0, 2),
                    substr($time_of_day, 3, 2)
                );
                break;
                
            case 'weekly':
                $day_of_week = $schedule['day_of_week'];
                $next_run = clone $now;
                $days_until = ($day_of_week - $now->format('N') + 7) % 7;
                if ($days_until == 0) $days_until = 7;
                $next_run->add(new DateInterval('P' . $days_until . 'D'));
                $next_run->setTime(
                    substr($time_of_day, 0, 2),
                    substr($time_of_day, 3, 2)
                );
                break;
                
            case 'monthly':
                $day_of_month = $schedule['day_of_month'];
                $next_run = clone $now;
                $next_run->setDate(
                    $now->format('Y'),
                    $now->format('n') + 1,
                    $day_of_month
                );
                $next_run->setTime(
                    substr($time_of_day, 0, 2),
                    substr($time_of_day, 3, 2)
                );
                break;
                
            default:
                $next_run = clone $now;
                $next_run->add(new DateInterval('P1D'));
                break;
        }
        
        return $next_run->format('Y-m-d H:i:s');
    }
    
    /**
     * Log report execution
     */
    private function logExecution($schedule_id, $report_id, $status, $recipients_count, $execution_time, $error_message = null) {
        $sql = "INSERT INTO report_execution_logs 
                (schedule_id, report_id, status, recipients_count, execution_time, error_message) 
                VALUES (:schedule_id, :report_id, :status, :recipients_count, :execution_time, :error_message)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':schedule_id', $schedule_id);
        $stmt->bindParam(':report_id', $report_id);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':recipients_count', $recipients_count);
        $stmt->bindParam(':execution_time', $execution_time);
        $stmt->bindParam(':error_message', $error_message);
        $stmt->execute();
    }
    
    /**
     * Create new schedule
     */
    public function createSchedule($report_id, $frequency, $time_of_day, $day_of_week, $day_of_month, $email_recipients) {
        // Validate email recipients
        if (!$this->emailService->validateEmails($email_recipients)) {
            throw new Exception("Invalid email recipients");
        }
        
        // Calculate next run time
        $schedule_data = [
            'report_id' => $report_id,
            'frequency' => $frequency,
            'time_of_day' => $time_of_day,
            'day_of_week' => $day_of_week,
            'day_of_month' => $day_of_month,
            'email_recipients' => json_encode($email_recipients),
            'is_active' => 1
        ];
        
        $next_run = $this->calculateNextRunTime($schedule_data);
        
        $sql = "INSERT INTO report_schedules 
                (report_id, frequency, time_of_day, day_of_week, day_of_month, email_recipients, is_active, next_run) 
                VALUES (:report_id, :frequency, :time_of_day, :day_of_week, :day_of_month, :email_recipients, :is_active, :next_run)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':report_id', $report_id);
        $stmt->bindParam(':frequency', $frequency);
        $stmt->bindParam(':time_of_day', $time_of_day);
        $stmt->bindParam(':day_of_week', $day_of_week);
        $stmt->bindParam(':day_of_month', $day_of_month);
        $stmt->bindParam(':email_recipients', $schedule_data['email_recipients']);
        $stmt->bindParam(':is_active', $schedule_data['is_active']);
        $stmt->bindParam(':next_run', $next_run);
        
        return $stmt->execute();
    }
    
    /**
     * Update schedule
     */
    public function updateSchedule($schedule_id, $frequency, $time_of_day, $day_of_week, $day_of_month, $email_recipients, $is_active) {
        // Validate email recipients
        if (!$this->emailService->validateEmails($email_recipients)) {
            throw new Exception("Invalid email recipients");
        }
        
        $schedule_data = [
            'report_id' => 0, // Not used in calculation
            'frequency' => $frequency,
            'time_of_day' => $time_of_day,
            'day_of_week' => $day_of_week,
            'day_of_month' => $day_of_month,
            'email_recipients' => json_encode($email_recipients),
            'is_active' => $is_active
        ];
        
        $next_run = $this->calculateNextRunTime($schedule_data);
        
        $sql = "UPDATE report_schedules 
                SET frequency = :frequency, time_of_day = :time_of_day, day_of_week = :day_of_week, 
                    day_of_month = :day_of_month, email_recipients = :email_recipients, 
                    is_active = :is_active, next_run = :next_run 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':frequency', $frequency);
        $stmt->bindParam(':time_of_day', $time_of_day);
        $stmt->bindParam(':day_of_week', $day_of_week);
        $stmt->bindParam(':day_of_month', $day_of_month);
        $stmt->bindParam(':email_recipients', $schedule_data['email_recipients']);
        $stmt->bindParam(':is_active', $is_active);
        $stmt->bindParam(':next_run', $next_run);
        $stmt->bindParam(':id', $schedule_id);
        
        return $stmt->execute();
    }
    
    /**
     * Delete schedule
     */
    public function deleteSchedule($schedule_id) {
        $sql = "DELETE FROM report_schedules WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $schedule_id);
        return $stmt->execute();
    }
    
    /**
     * Get all schedules for a report
     */
    public function getSchedulesForReport($report_id) {
        $sql = "SELECT * FROM report_schedules WHERE report_id = :report_id ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':report_id', $report_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get execution logs for a schedule
     */
    public function getExecutionLogs($schedule_id, $limit = 50) {
        $sql = "SELECT * FROM report_execution_logs 
                WHERE schedule_id = :schedule_id 
                ORDER BY executed_at DESC 
                LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':schedule_id', $schedule_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
