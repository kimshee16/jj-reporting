<?php
class CampaignSync {
    private $pdo;
    private $access_token;
    
    public function __construct($pdo, $access_token) {
        $this->pdo = $pdo;
        $this->access_token = $access_token;
    }
    
    /**
     * Sync campaigns for a specific account
     */
    public function syncCampaigns($account_id, $date_preset = 'this_month') {
        try {
            // Fetch campaigns from Facebook API
            $campaigns = $this->fetchCampaignsFromAPI($account_id, $date_preset);
            
            if (empty($campaigns)) {
                return ['success' => false, 'message' => 'No campaigns found'];
            }
            
            $synced_count = 0;
            $errors = [];
            
            foreach ($campaigns as $campaign) {
                try {
                    $this->saveOrUpdateCampaign($account_id, $campaign);
                    $synced_count++;
                } catch (Exception $e) {
                    $errors[] = "Campaign {$campaign['id']}: " . $e->getMessage();
                }
            }
            
            return [
                'success' => true,
                'synced_count' => $synced_count,
                'total_campaigns' => count($campaigns),
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Fetch campaigns from Facebook Graph API
     */
    private function fetchCampaignsFromAPI($account_id, $date_preset) {
        $api_url = "https://graph.facebook.com/v21.0/{$account_id}/campaigns";
        $fields = "id,name,objective,status,daily_budget,lifetime_budget,start_time,stop_time,insights{spend,actions,purchase_roas,ctr,cpc,cpm}";
        
        $params = [
            'fields' => $fields,
            'date_preset' => $date_preset,
            'access_token' => $this->access_token
        ];
        
        $api_url .= '?' . http_build_query($params);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: JJ-Reporting-Dashboard/1.0',
                'timeout' => 30
            ]
        ]);
        
        $response = @file_get_contents($api_url, false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to fetch campaigns from Facebook API');
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            throw new Exception('Facebook API Error: ' . $data['error']['message']);
        }
        
        return $data['data'] ?? [];
    }
    
    /**
     * Save or update campaign in database
     */
    private function saveOrUpdateCampaign($account_id, $campaign) {
        // Extract insights data
        $insights = $campaign['insights']['data'][0] ?? [];
        $spend = $insights['spend'] ?? 0;
        $roas = $insights['purchase_roas'] ?? null;
        $ctr = $insights['ctr'] ?? null;
        $cpm = $insights['cpm'] ?? null;
        $cpc = $insights['cpc'] ?? null;
        
        // Calculate results based on objective
        $results = $this->calculateResults($campaign['objective'], $insights['actions'] ?? []);
        
        // Determine budget type and amount
        $budget_type = !empty($campaign['daily_budget']) ? 'daily' : 'lifetime';
        $daily_budget = !empty($campaign['daily_budget']) ? $campaign['daily_budget'] : null;
        $lifetime_budget = !empty($campaign['lifetime_budget']) ? $campaign['lifetime_budget'] : null;
        
        // Format dates
        $start_date = $this->formatDate($campaign['start_time'] ?? null);
        $end_date = $this->formatDate($campaign['stop_time'] ?? null);
        
        $sql = "INSERT INTO facebook_ads_accounts_campaigns 
                (campaign_id, account_id, name, objective, status, budget_type, daily_budget, lifetime_budget, 
                 total_spend, results, roas, ctr, cpm, cpc, start_date, end_date, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                objective = VALUES(objective),
                status = VALUES(status),
                budget_type = VALUES(budget_type),
                daily_budget = VALUES(daily_budget),
                lifetime_budget = VALUES(lifetime_budget),
                total_spend = VALUES(total_spend),
                results = VALUES(results),
                roas = VALUES(roas),
                ctr = VALUES(ctr),
                cpm = VALUES(cpm),
                cpc = VALUES(cpc),
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                updated_at = NOW()";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $campaign['id'],
            $account_id,
            $campaign['name'],
            $campaign['objective'],
            $campaign['status'],
            $budget_type,
            $daily_budget,
            $lifetime_budget,
            $spend,
            $results,
            $roas,
            $ctr,
            $cpm,
            $cpc,
            $start_date,
            $end_date
        ]);
    }
    
    /**
     * Calculate results based on campaign objective
     */
    private function calculateResults($objective, $actions) {
        $results = 0;
        
        switch ($objective) {
            case 'CONVERSIONS':
                foreach ($actions as $action) {
                    if ($action['action_type'] === 'purchase') {
                        $results += $action['value'] ?? 0;
                    }
                }
                break;
                
            case 'TRAFFIC':
                foreach ($actions as $action) {
                    if ($action['action_type'] === 'link_click') {
                        $results += $action['value'] ?? 0;
                    }
                }
                break;
                
            case 'AWARENESS':
            case 'REACH':
                foreach ($actions as $action) {
                    if ($action['action_type'] === 'post_engagement') {
                        $results += $action['value'] ?? 0;
                    }
                }
                break;
                
            case 'LEAD_GENERATION':
                foreach ($actions as $action) {
                    if ($action['action_type'] === 'lead') {
                        $results += $action['value'] ?? 0;
                    }
                }
                break;
                
            default:
                // For other objectives, sum all action values
                foreach ($actions as $action) {
                    $results += $action['value'] ?? 0;
                }
                break;
        }
        
        return $results;
    }
    
    /**
     * Format date string for database storage
     */
    private function formatDate($date_string) {
        if (empty($date_string) || $date_string === null) {
            return null;
        }
        
        // Handle Unix timestamp 0 (1970-01-01)
        if ($date_string === '1970-01-01T00:00:00+0000' || $date_string === '1970-01-01T07:59:59+0800') {
            return null;
        }
        
        try {
            $timestamp = strtotime($date_string);
            if ($timestamp === false || $timestamp <= 0) {
                return null;
            }
            return date('Y-m-d H:i:s', $timestamp);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get campaigns from database
     */
    public function getCampaigns($account_id, $filters = []) {
        $sql = "SELECT * FROM facebook_ads_accounts_campaigns WHERE account_id = ?";
        $params = [$account_id];
        
        // Apply filters
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['objective']) && $filters['objective'] !== 'all') {
            $sql .= " AND objective = ?";
            $params[] = $filters['objective'];
        }
        
        if (!empty($filters['min_spend'])) {
            $sql .= " AND total_spend >= ?";
            $params[] = $filters['min_spend'];
        }
        
        if (!empty($filters['max_spend'])) {
            $sql .= " AND total_spend <= ?";
            $params[] = $filters['max_spend'];
        }
        
        // Apply sorting
        $sort_by = $filters['sort_by'] ?? 'total_spend';
        $sort_order = $filters['sort_order'] ?? 'DESC';
        
        switch ($sort_by) {
            case 'name':
                $sql .= " ORDER BY name {$sort_order}";
                break;
            case 'roas':
                $sql .= " ORDER BY roas {$sort_order}";
                break;
            case 'ctr':
                $sql .= " ORDER BY ctr {$sort_order}";
                break;
            case 'cpc':
                $sql .= " ORDER BY cpc {$sort_order}";
                break;
            default:
                $sql .= " ORDER BY total_spend {$sort_order}";
                break;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get campaign statistics
     */
    public function getCampaignStats($account_id) {
        $sql = "SELECT 
                    COUNT(*) as total_campaigns,
                    SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) as active_campaigns,
                    SUM(CASE WHEN status = 'PAUSED' THEN 1 ELSE 0 END) as paused_campaigns,
                    SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_campaigns,
                    SUM(total_spend) as total_spend,
                    AVG(roas) as avg_roas,
                    AVG(ctr) as avg_ctr,
                    AVG(cpm) as avg_cpm,
                    AVG(cpc) as avg_cpc
                FROM facebook_ads_accounts_campaigns 
                WHERE account_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$account_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
