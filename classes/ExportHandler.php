<?php
/**
 * ExportHandler Class
 * Handles data export functionality for reports
 */

require_once __DIR__ . '/../config.php';

class ExportHandler {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Fetch cross-account data for reports
     */
    public function fetchCrossAccountData($filters) {
        try {
            $sql = "SELECT 
                        c.name as campaign_name,
                        c.status as campaign_status,
                        c.total_spend as spend,
                        c.impressions,
                        c.clicks,
                        c.ctr,
                        c.cpc,
                        c.cpa,
                        c.roas,
                        c.objective,
                        c.created_time,
                        a.act_name as account_name,
                        'Campaign' as type
                    FROM facebook_ads_accounts_campaigns c
                    LEFT JOIN facebook_ads_accounts a ON c.account_id = a.act_id
                    WHERE 1=1";
            
            $params = [];
            
            // Apply date filter
            if (isset($filters['date_range'])) {
                $date_condition = $this->getDateCondition($filters['date_range'], 'c');
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
            
            // Apply objective filter
            if (isset($filters['objective_filter']) && $filters['objective_filter'] !== 'all') {
                $sql .= " AND c.objective = :objective";
                $params[':objective'] = $filters['objective_filter'];
            }
            
            // Apply ROAS threshold
            if (isset($filters['min_roas']) && $filters['min_roas'] > 0) {
                $sql .= " AND c.roas >= :min_roas";
                $params[':min_roas'] = $filters['min_roas'];
            }
            
            // Apply CTR threshold
            if (isset($filters['min_ctr']) && $filters['min_ctr'] > 0) {
                $sql .= " AND c.ctr >= :min_ctr";
                $params[':min_ctr'] = $filters['min_ctr'];
            }
            
            // Apply spend filter
            if (isset($filters['min_spend']) && $filters['min_spend'] > 0) {
                $sql .= " AND c.total_spend >= :min_spend";
                $params[':min_spend'] = $filters['min_spend'];
            }
            
            // Apply sorting
            $sort_by = $filters['sort_by'] ?? 'total_spend';
            $sort_order = $filters['sort_order'] ?? 'DESC';
            $sql .= " ORDER BY c.{$sort_by} {$sort_order}";
            
            // Apply limit
            if (isset($filters['limit']) && $filters['limit'] > 0) {
                $sql .= " LIMIT " . (int)$filters['limit'];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add ad sets data if requested
            $adsets = [];
            if (isset($filters['include_adsets']) && $filters['include_adsets']) {
                $adsets = $this->fetchAdsets($filters);
            }
            
            // Add ads data if requested
            $ads = [];
            if (isset($filters['include_ads']) && $filters['include_ads']) {
                $ads = $this->fetchAds($filters);
            }
            
            // Combine all data
            $all_data = array_merge($campaigns, $adsets, $ads);
            
            return $all_data;
            
        } catch (PDOException $e) {
            error_log("Error fetching cross-account data: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetch ad sets data
     */
    private function fetchAdsets($filters) {
        try {
            $sql = "SELECT 
                        ads.name as adset_name,
                        ads.status as adset_status,
                        ads.results as spend,
                        ads.impressions,
                        ads.reach,
                        ads.ctr,
                        ads.cpc,
                        ads.cpm,
                        ads.created_time,
                        a.act_name as account_name,
                        c.name as campaign_name,
                        'Ad Set' as type
                    FROM facebook_ads_accounts_adsets ads
                    LEFT JOIN facebook_ads_accounts a ON ads.account_id = a.act_id
                    LEFT JOIN facebook_ads_accounts_campaigns c ON ads.campaign_id = c.id
                    WHERE 1=1";
            
            $params = [];
            
            // Apply date filter
            if (isset($filters['date_range'])) {
                $date_condition = $this->getDateCondition($filters['date_range'], 'ads');
                if ($date_condition) {
                    $sql .= " AND " . $date_condition;
                }
            }
            
            // Apply status filter
            if (isset($filters['status_filter']) && $filters['status_filter'] !== 'all') {
                $sql .= " AND ads.status = :status";
                $params[':status'] = strtoupper($filters['status_filter']);
            }
            
            // Apply account filter
            if (isset($filters['account_filter']) && $filters['account_filter'] !== 'all') {
                $sql .= " AND ads.account_id = :account_id";
                $params[':account_id'] = $filters['account_filter'];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching adsets: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetch ads data
     */
    private function fetchAds($filters) {
        try {
            $sql = "SELECT 
                        ad.name as ad_name,
                        ad.status as ad_status,
                        ad.spend,
                        ad.impressions,
                        ad.reach,
                        ad.clicks,
                        ad.ctr,
                        ad.cpc,
                        ad.cost_per_result,
                        ad.conversion_value,
                        ad.roas,
                        ad.created_time,
                        a.act_name as account_name,
                        c.name as campaign_name,
                        ads.name as adset_name,
                        'Ad' as type
                    FROM facebook_ads_accounts_ads ad
                    LEFT JOIN facebook_ads_accounts a ON ad.account_id = a.act_id
                    LEFT JOIN facebook_ads_accounts_campaigns c ON ad.campaign_id = c.id
                    LEFT JOIN facebook_ads_accounts_adsets ads ON ad.adset_id = ads.id
                    WHERE 1=1";
            
            $params = [];
            
            // Apply date filter
            if (isset($filters['date_range'])) {
                $date_condition = $this->getDateCondition($filters['date_range'], 'ad');
                if ($date_condition) {
                    $sql .= " AND " . $date_condition;
                }
            }
            
            // Apply status filter
            if (isset($filters['status_filter']) && $filters['status_filter'] !== 'all') {
                $sql .= " AND ad.status = :status";
                $params[':status'] = strtoupper($filters['status_filter']);
            }
            
            // Apply account filter
            if (isset($filters['account_filter']) && $filters['account_filter'] !== 'all') {
                $sql .= " AND ad.account_id = :account_id";
                $params[':account_id'] = $filters['account_filter'];
            }
            
            // Apply format filter
            if (isset($filters['ad_formats']) && !empty($filters['ad_formats'])) {
                $placeholders = str_repeat('?,', count($filters['ad_formats']) - 1) . '?';
                $sql .= " AND ad.format IN ($placeholders)";
                $params = array_merge($params, $filters['ad_formats']);
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching ads: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get date condition for SQL queries
     */
    private function getDateCondition($date_range, $table_alias = 'c') {
        $now = new DateTime();
        
        switch ($date_range) {
            case 'last_1_days':
                return "DATE({$table_alias}.created_time) = CURDATE()";
            case 'last_7_days':
                return "{$table_alias}.created_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            case 'last_14_days':
                return "{$table_alias}.created_time >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)";
            case 'last_30_days':
                return "{$table_alias}.created_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            case 'last_90_days':
                return "{$table_alias}.created_time >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
            case 'this_month':
                return "MONTH({$table_alias}.created_time) = MONTH(CURDATE()) AND YEAR({$table_alias}.created_time) = YEAR(CURDATE())";
            case 'last_month':
                return "{$table_alias}.created_time >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY), INTERVAL 1 MONTH) AND {$table_alias}.created_time < DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY)";
            case 'this_quarter':
                $quarter = ceil($now->format('n') / 3);
                $start_month = ($quarter - 1) * 3 + 1;
                return "MONTH({$table_alias}.created_time) >= {$start_month} AND MONTH({$table_alias}.created_time) < " . ($start_month + 3) . " AND YEAR({$table_alias}.created_time) = " . $now->format('Y');
            case 'this_year':
                return "YEAR({$table_alias}.created_time) = " . $now->format('Y');
            default:
                return "{$table_alias}.created_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
    }
}
