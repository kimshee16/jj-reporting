<?php
require_once __DIR__ . '/../vendor/autoload.php'; // For Facebook SDK

use Facebook\Facebook;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;

class AdsetSync {
    private $pdo;
    private $access_token;
    private $fb;

    public function __construct(PDO $pdo, $access_token) {
        $this->pdo = $pdo;
        $this->access_token = $access_token;
        $this->fb = new Facebook([
            'app_id' => META_APP_ID,
            'app_secret' => META_APP_SECRET,
            'default_graph_version' => 'v21.0',
        ]);
        $this->fb->setDefaultAccessToken($this->access_token);
    }

    public function syncAdsets($account_id, $date_preset = 'this_month') {
        $synced_count = 0;
        $total_adsets = 0;
        $errors = [];

        try {
            // Use direct API call instead of Facebook SDK to avoid deprecation warnings
            $api_url = "https://graph.facebook.com/v21.0/act_{$account_id}/adsets?";
            $api_url .= "fields=id,name,status,daily_budget,lifetime_budget,campaign_id,start_time,end_time,created_time,targeting,insights{spend,impressions,reach,ctr,cpc,cpm,actions}";
            $api_url .= "&date_preset={$date_preset}";
            $api_url .= "&access_token={$this->access_token}";
            
            $response = file_get_contents($api_url);
            $data = json_decode($response, true);
            
            if (isset($data['error'])) {
                return ['success' => false, 'message' => 'Graph API error: ' . $data['error']['message']];
            }
            
            if (!isset($data['data']) || !is_array($data['data'])) {
                return ['success' => false, 'message' => 'No ad sets data received'];
            }

            foreach ($data['data'] as $adset) {
                $total_adsets++;
                try {
                    $this->saveAdsetToDbFromArray($account_id, $adset);
                    $synced_count++;
                } catch (Exception $e) {
                    $errors[] = "Adset " . ($adset['name'] ?? 'Unknown') . " (ID: " . ($adset['id'] ?? 'Unknown') . "): " . $e->getMessage();
                }
            }

            return ['success' => true, 'synced_count' => $synced_count, 'total_adsets' => $total_adsets, 'errors' => $errors];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()];
        }
    }

    private function saveAdsetToDbFromArray($account_id, $adset) {
        $adset_id = $adset['id'] ?? '';
        $name = $adset['name'] ?? '';
        $status = $adset['status'] ?? '';
        $campaign_id = $adset['campaign_id'] ?? '';
        $daily_budget = $adset['daily_budget'] ?? null;
        $lifetime_budget = $adset['lifetime_budget'] ?? null;
        $start_time = isset($adset['start_time']) ? date('Y-m-d H:i:s', strtotime($adset['start_time'])) : null;
        $end_time = isset($adset['end_time']) ? date('Y-m-d H:i:s', strtotime($adset['end_time'])) : null;
        $created_time = isset($adset['created_time']) ? date('Y-m-d H:i:s', strtotime($adset['created_time'])) : null;

        // Parse targeting data
        $targeting = $adset['targeting'] ?? [];
        $age_min = null;
        $age_max = null;
        $gender = 'ALL';
        $interests = [];
        $custom_audience_type = 'NONE';
        $lookalike_audience_id = null;
        $placement = [];
        $device_breakdown = [];

        if (!empty($targeting)) {
            // Age targeting
            if (isset($targeting['age_min'])) {
                $age_min = $targeting['age_min'];
            }
            if (isset($targeting['age_max'])) {
                $age_max = $targeting['age_max'];
            }

            // Gender targeting
            if (isset($targeting['genders'])) {
                $genders = $targeting['genders'];
                if (count($genders) == 1) {
                    $gender = $genders[0];
                } else {
                    $gender = 'MIXED';
                }
            }

            // Interests
            if (isset($targeting['interests'])) {
                foreach ($targeting['interests'] as $interest) {
                    $interests[] = $interest['name'] ?? $interest;
                }
            }

            // Custom audiences
            if (isset($targeting['custom_audiences'])) {
                $custom_audience_type = 'CUSTOM';
            }
            if (isset($targeting['lookalike_audiences'])) {
                $custom_audience_type = 'LOOKALIKE';
                $lookalike_audience_id = $targeting['lookalike_audiences'][0]['id'] ?? null;
            }

            // Placement
            if (isset($targeting['publisher_platforms'])) {
                $placement = $targeting['publisher_platforms'];
            }

            // Device targeting
            if (isset($targeting['device_platforms'])) {
                $device_breakdown = $targeting['device_platforms'];
            }
        }

        // Get insights data
        $insights = $adset['insights']['data'][0] ?? [];
        $spend = 0;
        $impressions = 0;
        $reach = 0;
        $ctr = 0;
        $cpc = 0;
        $cpm = 0;
        $results = 0;

        if (!empty($insights)) {
            $spend = $insights['spend'] ?? 0;
            $impressions = $insights['impressions'] ?? 0;
            $reach = $insights['reach'] ?? 0;
            $ctr = $insights['ctr'] ?? 0;
            $cpc = $insights['cpc'] ?? 0;
            $cpm = $insights['cpm'] ?? 0;

            $actions = $insights['actions'] ?? [];
            if (!empty($actions)) {
                foreach ($actions as $action) {
                    if (strpos($action['action_type'], 'purchase') !== false) {
                        $results += $action['value'];
                    } elseif (strpos($action['action_type'], 'link_click') !== false && $results == 0) {
                        $results += $action['value'];
                    }
                }
            }
        }

        $budget_type = null;
        if ($daily_budget) {
            $budget_type = 'DAILY';
        } elseif ($lifetime_budget) {
            $budget_type = 'LIFETIME';
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO facebook_ads_accounts_adsets (
                id, account_id, campaign_id, name, status, budget_type, daily_budget, lifetime_budget,
                age_min, age_max, gender, interests, custom_audience_type, lookalike_audience_id,
                placement, device_breakdown, start_time, end_time, results, impressions, reach,
                cpm, ctr, cpc, created_time, last_synced_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                status = VALUES(status),
                budget_type = VALUES(budget_type),
                daily_budget = VALUES(daily_budget),
                lifetime_budget = VALUES(lifetime_budget),
                age_min = VALUES(age_min),
                age_max = VALUES(age_max),
                gender = VALUES(gender),
                interests = VALUES(interests),
                custom_audience_type = VALUES(custom_audience_type),
                lookalike_audience_id = VALUES(lookalike_audience_id),
                placement = VALUES(placement),
                device_breakdown = VALUES(device_breakdown),
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                results = VALUES(results),
                impressions = VALUES(impressions),
                reach = VALUES(reach),
                cpm = VALUES(cpm),
                ctr = VALUES(ctr),
                cpc = VALUES(cpc),
                created_time = VALUES(created_time),
                last_synced_at = NOW(),
                updated_at = NOW()
        ");

        $stmt->execute([
            $adset_id, $account_id, $campaign_id, $name, $status, $budget_type, $daily_budget, $lifetime_budget,
            $age_min, $age_max, $gender, json_encode($interests), $custom_audience_type, $lookalike_audience_id,
            json_encode($placement), json_encode($device_breakdown), $start_time, $end_time, $results, $impressions, $reach,
            $cpm, $ctr, $cpc, $created_time
        ]);
    }

    private function saveAdsetToDb($account_id, $adset) {
        $adset_id = $adset->getField('id');
        $name = $adset->getField('name');
        $status = $adset->getField('status');
        $campaign_id = $adset->getField('campaign_id');
        $daily_budget = $adset->getField('daily_budget');
        $lifetime_budget = $adset->getField('lifetime_budget');
        $start_time = $adset->getField('start_time') ? $adset->getField('start_time')->format('Y-m-d H:i:s') : null;
        $end_time = $adset->getField('end_time') ? $adset->getField('end_time')->format('Y-m-d H:i:s') : null;
        $created_time = $adset->getField('created_time') ? $adset->getField('created_time')->format('Y-m-d H:i:s') : null;

        // Parse targeting data
        $targeting = $adset->getField('targeting');
        $age_min = null;
        $age_max = null;
        $gender = 'ALL';
        $interests = [];
        $custom_audience_type = 'NONE';
        $lookalike_audience_id = null;
        $placement = [];
        $device_breakdown = [];

        if ($targeting) {
            // Age targeting
            if (isset($targeting['age_min'])) {
                $age_min = $targeting['age_min'];
            }
            if (isset($targeting['age_max'])) {
                $age_max = $targeting['age_max'];
            }

            // Gender targeting
            if (isset($targeting['genders'])) {
                $genders = $targeting['genders'];
                if (count($genders) == 1) {
                    $gender = $genders[0];
                } else {
                    $gender = 'MIXED';
                }
            }

            // Interests
            if (isset($targeting['interests'])) {
                foreach ($targeting['interests'] as $interest) {
                    $interests[] = $interest['name'] ?? $interest;
                }
            }

            // Custom audiences
            if (isset($targeting['custom_audiences'])) {
                $custom_audience_type = 'CUSTOM';
            }
            if (isset($targeting['lookalike_audiences'])) {
                $custom_audience_type = 'LOOKALIKE';
                $lookalike_audience_id = $targeting['lookalike_audiences'][0]['id'] ?? null;
            }

            // Placement
            if (isset($targeting['publisher_platforms'])) {
                $placement = $targeting['publisher_platforms'];
            }

            // Device targeting
            if (isset($targeting['device_platforms'])) {
                $device_breakdown = $targeting['device_platforms'];
            }
        }

        // Get insights data
        $insights = $adset->getInsights();
        $spend = 0;
        $impressions = 0;
        $reach = 0;
        $ctr = 0;
        $cpc = 0;
        $cpm = 0;
        $results = 0;

        if ($insights && $insights->count() > 0) {
            $insight = $insights->getIterator()->current();
            $spend = $insight->getField('spend') ?? 0;
            $impressions = $insight->getField('impressions') ?? 0;
            $reach = $insight->getField('reach') ?? 0;
            $ctr = $insight->getField('ctr') ?? 0;
            $cpc = $insight->getField('cpc') ?? 0;
            $cpm = $insight->getField('cpm') ?? 0;

            $actions = $insight->getField('actions');
            if ($actions) {
                foreach ($actions as $action) {
                    if (strpos($action->getField('action_type'), 'purchase') !== false) {
                        $results += $action->getField('value');
                    } elseif (strpos($action->getField('action_type'), 'link_click') !== false && $results == 0) {
                        $results += $action->getField('value');
                    }
                }
            }
        }

        $budget_type = null;
        if ($daily_budget) {
            $budget_type = 'DAILY';
        } elseif ($lifetime_budget) {
            $budget_type = 'LIFETIME';
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO facebook_ads_accounts_adsets (
                id, account_id, campaign_id, name, status, budget_type, daily_budget, lifetime_budget,
                age_min, age_max, gender, interests, custom_audience_type, lookalike_audience_id,
                placement, device_breakdown, start_time, end_time, results, impressions, reach,
                cpm, ctr, cpc, created_time, last_synced_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                status = VALUES(status),
                budget_type = VALUES(budget_type),
                daily_budget = VALUES(daily_budget),
                lifetime_budget = VALUES(lifetime_budget),
                age_min = VALUES(age_min),
                age_max = VALUES(age_max),
                gender = VALUES(gender),
                interests = VALUES(interests),
                custom_audience_type = VALUES(custom_audience_type),
                lookalike_audience_id = VALUES(lookalike_audience_id),
                placement = VALUES(placement),
                device_breakdown = VALUES(device_breakdown),
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                results = VALUES(results),
                impressions = VALUES(impressions),
                reach = VALUES(reach),
                cpm = VALUES(cpm),
                ctr = VALUES(ctr),
                cpc = VALUES(cpc),
                created_time = VALUES(created_time),
                last_synced_at = NOW(),
                updated_at = NOW()
        ");

        $stmt->execute([
            $adset_id, $account_id, $campaign_id, $name, $status, $budget_type, $daily_budget, $lifetime_budget,
            $age_min, $age_max, $gender, json_encode($interests), $custom_audience_type, $lookalike_audience_id,
            json_encode($placement), json_encode($device_breakdown), $start_time, $end_time, $results, $impressions, $reach,
            $cpm, $ctr, $cpc, $created_time
        ]);
    }

    public function getAdsets($account_id, $filters = []) {
        $sql = "SELECT * FROM facebook_ads_accounts_adsets WHERE account_id = ?";
        $params = [$account_id];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['campaign_id']) && $filters['campaign_id'] !== 'all') {
            $sql .= " AND campaign_id = ?";
            $params[] = $filters['campaign_id'];
        }
        if (!empty($filters['min_budget'])) {
            $sql .= " AND (daily_budget >= ? OR lifetime_budget >= ?)";
            $params[] = $filters['min_budget'];
            $params[] = $filters['min_budget'];
        }
        if (!empty($filters['max_budget'])) {
            $sql .= " AND (daily_budget <= ? OR lifetime_budget <= ?)";
            $params[] = $filters['max_budget'];
            $params[] = $filters['max_budget'];
        }

        $sort_by = $filters['sort_by'] ?? 'results';
        $sql .= " ORDER BY {$sort_by} DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAdsetStats($account_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(id) as total_adsets,
                SUM(CASE WHEN budget_type = 'DAILY' THEN daily_budget ELSE 0 END) as total_daily_budget,
                SUM(CASE WHEN budget_type = 'LIFETIME' THEN lifetime_budget ELSE 0 END) as total_lifetime_budget,
                SUM(results) as total_results,
                SUM(impressions) as total_impressions,
                SUM(reach) as total_reach,
                AVG(ctr) as avg_ctr,
                AVG(cpm) as avg_cpm,
                AVG(cpc) as avg_cpc
            FROM facebook_ads_accounts_adsets
            WHERE account_id = ?
        ");
        $stmt->execute([$account_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
