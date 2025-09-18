<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

class ExportHandler {
    private $pdo;
    private $admin_id;
    private $export_dir;
    
    public function __construct($pdo, $admin_id) {
        $this->pdo = $pdo;
        $this->admin_id = $admin_id;
        $this->export_dir = __DIR__ . '/exports/';
        
        // Create exports directory if it doesn't exist
        if (!is_dir($this->export_dir)) {
            mkdir($this->export_dir, 0755, true);
        }
    }
    
    public function processExport($export_type, $export_format, $export_view, $filters = [], $selected_fields = []) {
        $start_time = microtime(true);
        
        try {
            // Log export start
            $log_id = $this->logExportStart($export_type, $export_format, $export_view);
            
            // Fetch data based on export type
            $data = $this->fetchData($export_type, $filters);
            
            if (empty($data)) {
                throw new Exception('No data found for the specified filters');
            }
            
            // Apply field selection if specified
            if (!empty($selected_fields)) {
                $data = $this->filterFields($data, $selected_fields);
            }
            
            // Generate file based on format
            $file_info = $this->generateFile($data, $export_format, $export_view, $export_type);
            
            // Log export completion
            $execution_time = microtime(true) - $start_time;
            $this->logExportCompletion($log_id, $file_info, count($data), $execution_time);
            
            return [
                'success' => true,
                'file_path' => $file_info['path'],
                'file_name' => $file_info['name'],
                'file_size' => $file_info['size'],
                'record_count' => count($data)
            ];
            
        } catch (Exception $e) {
            // Log export failure
            $execution_time = microtime(true) - $start_time;
            $this->logExportFailure($log_id ?? null, $e->getMessage(), $execution_time);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function fetchData($export_type, $filters) {
        switch ($export_type) {
            case 'campaigns':
                return $this->fetchCampaigns($filters);
            case 'adsets':
                return $this->fetchAdsets($filters);
            case 'ads':
                return $this->fetchAds($filters);
            case 'cross_account':
                return $this->fetchCrossAccountData($filters);
            default:
                throw new Exception('Invalid export type');
        }
    }
    
    private function fetchCampaigns($filters) {
        try {
            $sql = "SELECT 
                        c.name,
                        c.status,
                        c.total_spend as spend,
                        c.impressions,
                        c.clicks,
                        c.ctr,
                        c.cpc,
                        c.cpa,
                        c.roas,
                        c.objective,
                        c.created_time,
                        a.act_name as account_name
                    FROM facebook_ads_accounts_campaigns c
                    LEFT JOIN facebook_ads_accounts a ON c.account_id = a.act_id
                    WHERE 1=1";
            
            $params = [];
            
            // Apply date filter
            if (isset($filters['date_range'])) {
                $date_condition = $this->getDateCondition($filters['date_range']);
                if ($date_condition) {
                    $sql .= " AND " . $date_condition;
                }
            }
            
            // Apply status filter
            if (isset($filters['status_filter']) && $filters['status_filter'] !== 'all') {
                $sql .= " AND c.status = :status";
                $params[':status'] = strtoupper($filters['status_filter']);
            }
            
            // Apply account filter
            if (isset($filters['account_filter']) && $filters['account_filter'] !== 'all') {
                $sql .= " AND c.account_id = :account_id";
                $params[':account_id'] = $filters['account_filter'];
            }
            
            $sql .= " ORDER BY c.total_spend DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching campaigns: " . $e->getMessage());
            return [];
        }
    }
    
    private function fetchAdsets($filters) {
        try {
            $sql = "SELECT 
                        adset.name,
                        adset.status,
                        adset.daily_budget,
                        adset.lifetime_budget,
                        adset.age_min,
                        adset.age_max,
                        adset.gender,
                        adset.interests,
                        adset.placement,
                        adset.impressions,
                        adset.spend,
                        adset.results,
                        camp.name as campaign_name,
                        a.act_name as account_name
                    FROM facebook_ads_accounts_adsets adset
                    LEFT JOIN facebook_ads_accounts_campaigns camp ON adset.campaign_id = camp.id
                    LEFT JOIN facebook_ads_accounts a ON adset.account_id = a.act_id
                    WHERE 1=1";
            
            $params = [];
            
            // Apply date filter
            if (isset($filters['date_range'])) {
                $date_condition = $this->getDateCondition($filters['date_range'], 'adset');
                if ($date_condition) {
                    $sql .= " AND " . $date_condition;
                }
            }
            
            // Apply status filter
            if (isset($filters['status_filter']) && $filters['status_filter'] !== 'all') {
                $sql .= " AND adset.status = :status";
                $params[':status'] = strtoupper($filters['status_filter']);
            }
            
            $sql .= " ORDER BY adset.spend DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching adsets: " . $e->getMessage());
            return [];
        }
    }
    
    private function fetchAds($filters) {
        try {
            $sql = "SELECT 
                        a.name,
                        a.status,
                        a.format,
                        a.creative_type,
                        a.copy_text,
                        a.headline,
                        a.cta_button,
                        a.impressions,
                        a.reach,
                        a.clicks,
                        a.ctr,
                        a.cpc,
                        a.spend,
                        a.likes,
                        a.shares,
                        a.comments,
                        a.reactions,
                        a.cost_per_result,
                        a.conversion_value,
                        a.roas,
                        a.results,
                        a.created_time,
                        adset.name as adset_name,
                        camp.name as campaign_name,
                        acc.act_name as account_name
                    FROM facebook_ads_accounts_ads a
                    LEFT JOIN facebook_ads_accounts_adsets adset ON a.adset_id = adset.id
                    LEFT JOIN facebook_ads_accounts_campaigns camp ON a.campaign_id = camp.id
                    LEFT JOIN facebook_ads_accounts acc ON a.account_id = acc.act_id
                    WHERE 1=1";
            
            $params = [];
            
            // Apply date filter
            if (isset($filters['date_range'])) {
                $date_condition = $this->getDateCondition($filters['date_range'], 'a');
                if ($date_condition) {
                    $sql .= " AND " . $date_condition;
                }
            }
            
            // Apply status filter
            if (isset($filters['status_filter']) && $filters['status_filter'] !== 'all') {
                $sql .= " AND a.status = :status";
                $params[':status'] = strtoupper($filters['status_filter']);
            }
            
            $sql .= " ORDER BY a.spend DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching ads: " . $e->getMessage());
            return [];
        }
    }
    
    private function fetchCrossAccountData($filters) {
        try {
            $sql = "SELECT 
                        a.id as ad_id,
                        a.name as ad_name,
                        a.status as ad_status,
                        a.format,
                        a.impressions,
                        a.reach,
                        a.clicks,
                        a.ctr,
                        a.cpc,
                        a.spend,
                        a.roas,
                        a.results,
                        a.created_time,
                        adset.name as adset_name,
                        camp.name as campaign_name,
                        camp.objective,
                        acc.act_name as account_name
                    FROM facebook_ads_accounts_ads a
                    LEFT JOIN facebook_ads_accounts_adsets adset ON a.adset_id = adset.id
                    LEFT JOIN facebook_ads_accounts_campaigns camp ON a.campaign_id = camp.id
                    LEFT JOIN facebook_ads_accounts acc ON a.account_id = acc.act_id
                    WHERE 1=1";
            
            $params = [];
            
            // Apply date filter
            if (isset($filters['date_range'])) {
                $date_condition = $this->getDateCondition($filters['date_range'], 'a');
                if ($date_condition) {
                    $sql .= " AND " . $date_condition;
                }
            }
            
            $sql .= " ORDER BY a.spend DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching cross-account data: " . $e->getMessage());
            return [];
        }
    }
    
    private function getDateCondition($date_range, $table_alias = 'c') {
        $now = new DateTime();
        
        switch ($date_range) {
            case 'last_1_days':
                return "DATE($table_alias.created_time) = CURDATE()";
            case 'last_7_days':
                return "$table_alias.created_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            case 'last_30_days':
                return "$table_alias.created_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            case 'last_90_days':
                return "$table_alias.created_time >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
            case 'this_month':
                return "MONTH($table_alias.created_time) = MONTH(CURDATE()) AND YEAR($table_alias.created_time) = YEAR(CURDATE())";
            case 'last_month':
                return "$table_alias.created_time >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY), INTERVAL 1 MONTH) AND $table_alias.created_time < DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY)";
            case 'this_quarter':
                $quarter = ceil($now->format('n') / 3);
                $start_month = ($quarter - 1) * 3 + 1;
                return "MONTH($table_alias.created_time) >= $start_month AND MONTH($table_alias.created_time) < " . ($start_month + 3) . " AND YEAR($table_alias.created_time) = " . $now->format('Y');
            case 'this_year':
                return "YEAR($table_alias.created_time) = " . $now->format('Y');
            default:
                return "$table_alias.created_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
    }
    
    private function filterFields($data, $selected_fields) {
        if (empty($selected_fields) || empty($data)) {
            return $data;
        }
        
        $filtered_data = [];
        foreach ($data as $row) {
            $filtered_row = [];
            foreach ($selected_fields as $field) {
                if (isset($row[$field])) {
                    $filtered_row[$field] = $row[$field];
                }
            }
            $filtered_data[] = $filtered_row;
        }
        
        return $filtered_data;
    }
    
    private function generateFile($data, $format, $view, $export_type) {
        $timestamp = date('Y-m-d_H-i-s');
        $base_filename = $export_type . '_' . $timestamp;
        
        switch ($format) {
            case 'csv':
                return $this->generateCSV($data, $base_filename);
            case 'excel':
                return $this->generateExcel($data, $base_filename, $view);
            case 'pdf':
                return $this->generatePDF($data, $base_filename, $view);
            default:
                throw new Exception('Invalid export format');
        }
    }
    
    private function generateCSV($data, $base_filename) {
        $filename = $base_filename . '.csv';
        $filepath = $this->export_dir . $filename;
        
        $file = fopen($filepath, 'w');
        
        if (empty($data)) {
            fclose($file);
            return [
                'name' => $filename,
                'path' => $filepath,
                'size' => filesize($filepath)
            ];
        }
        
        // Write headers
        fputcsv($file, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        return [
            'name' => $filename,
            'path' => $filepath,
            'size' => filesize($filepath)
        ];
    }
    
    private function generateExcel($data, $base_filename, $view) {
        $filename = $base_filename . '.xlsx';
        $filepath = $this->export_dir . $filename;
        
        // For now, create a simple Excel file using basic PHP
        // In production, you'd use PhpSpreadsheet library
        $file = fopen($filepath, 'w');
        
        if (empty($data)) {
            fclose($file);
            return [
                'name' => $filename,
                'path' => $filepath,
                'size' => filesize($filepath)
            ];
        }
        
        // Create a simple CSV-like format for Excel
        fputcsv($file, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        return [
            'name' => $filename,
            'path' => $filepath,
            'size' => filesize($filepath)
        ];
    }
    
    private function generatePDF($data, $base_filename, $view) {
        $filename = $base_filename . '.pdf';
        $filepath = $this->export_dir . $filename;
        
        // For now, create a simple text file
        // In production, you'd use TCPDF or mPDF
        $content = "Export Report\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        if (!empty($data)) {
            $content .= "Data:\n";
            foreach ($data as $index => $row) {
                $content .= "Record " . ($index + 1) . ":\n";
                foreach ($row as $key => $value) {
                    $content .= "  " . $key . ": " . $value . "\n";
                }
                $content .= "\n";
            }
        }
        
        file_put_contents($filepath, $content);
        
        return [
            'name' => $filename,
            'path' => $filepath,
            'size' => filesize($filepath)
        ];
    }
    
    private function logExportStart($export_type, $export_format, $export_view) {
        try {
            $sql = "INSERT INTO export_history (export_type, export_format, export_view, status, created_by) VALUES (?, ?, ?, 'processing', ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$export_type, $export_format, $export_view, $this->admin_id]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            // If table doesn't exist, return null and continue without logging
            return null;
        }
    }
    
    private function logExportCompletion($log_id, $file_info, $record_count, $execution_time) {
        if ($log_id) {
            try {
                $sql = "UPDATE export_history SET 
                        file_name = ?, 
                        file_path = ?, 
                        file_size = ?, 
                        record_count = ?, 
                        status = 'completed', 
                        execution_time = ? 
                        WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $file_info['name'],
                    $file_info['path'],
                    $file_info['size'],
                    $record_count,
                    $execution_time,
                    $log_id
                ]);
            } catch (PDOException $e) {
                // If table doesn't exist, continue without logging
            }
        }
    }
    
    private function logExportFailure($log_id, $error_message, $execution_time) {
        if ($log_id) {
            try {
                $sql = "UPDATE export_history SET 
                        status = 'failed', 
                        error_message = ?, 
                        execution_time = ? 
                        WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$error_message, $execution_time, $log_id]);
            } catch (PDOException $e) {
                // If table doesn't exist, continue without logging
            }
        }
    }
    
    public function downloadFile($file_path) {
        if (!file_exists($file_path)) {
            throw new Exception('File not found');
        }
        
        // Update download count
        try {
            $sql = "UPDATE export_history SET download_count = download_count + 1, downloaded_at = NOW() WHERE file_path = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$file_path]);
        } catch (PDOException $e) {
            // If table doesn't exist, continue without logging
        }
        
        // Set headers for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        
        // Output file
        readfile($file_path);
        exit();
    }
}

// Only handle AJAX requests if this file is accessed directly (not included)
if (basename($_SERVER['PHP_SELF']) === 'export_handler.php') {
    // Check if user is logged in
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    
    // Handle AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        try {
            $export_handler = new ExportHandler($pdo, $_SESSION['admin_id']);
            
            switch ($action) {
                case 'export':
                    $export_type = $_POST['export_type'] ?? '';
                    $export_format = $_POST['export_format'] ?? 'csv';
                    $export_view = $_POST['export_view'] ?? 'table';
                    
                    // Handle filters - they come as an array from the form
                    $filters = $_POST['filters'] ?? [];
                    if (is_string($filters)) {
                        $filters = json_decode($filters, true) ?: [];
                    }
                    
                    // Handle selected fields - they come as an array from the form
                    $selected_fields = $_POST['selected_fields'] ?? [];
                    if (is_string($selected_fields)) {
                        $selected_fields = json_decode($selected_fields, true) ?: [];
                    }
                    
                    $result = $export_handler->processExport($export_type, $export_format, $export_view, $filters, $selected_fields);
                    
                    header('Content-Type: application/json');
                    echo json_encode($result);
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // Handle file downloads
    if (isset($_GET['download']) && isset($_GET['file'])) {
        try {
            $export_handler = new ExportHandler($pdo, $_SESSION['admin_id']);
            $file_path = $export_handler->export_dir . $_GET['file'];
            $export_handler->downloadFile($file_path);
        } catch (Exception $e) {
            http_response_code(404);
            echo "File not found: " . $e->getMessage();
        }
    }
}
?>
