<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once 'config.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

header('Content-Type: application/json');

try {
    // Get total ad spend across all accounts
    $total_spend_sql = "
        SELECT 
            COALESCE(SUM(c.total_spend), 0) as total_spend,
            COUNT(DISTINCT c.account_id) as total_accounts,
            COUNT(c.id) as total_campaigns
        FROM facebook_ads_accounts_campaigns c
        WHERE c.total_spend > 0
    ";
    $total_spend_result = $pdo->query($total_spend_sql)->fetch(PDO::FETCH_ASSOC);
    
    // Get total conversions from ads
    $total_conversions_sql = "
        SELECT 
            COALESCE(SUM(a.results), 0) as total_conversions,
            COALESCE(SUM(a.clicks), 0) as total_clicks,
            COALESCE(SUM(a.impressions), 0) as total_impressions
        FROM facebook_ads_accounts_ads a
        WHERE a.results > 0
    ";
    $conversions_result = $pdo->query($total_conversions_sql)->fetch(PDO::FETCH_ASSOC);
    
    // Get best performing campaigns (top 5 by ROAS)
    $best_campaigns_sql = "
        SELECT 
            c.name,
            c.status,
            c.total_spend,
            c.results,
            c.roas,
            c.ctr,
            c.cpc,
            a.act_name as account_name
        FROM facebook_ads_accounts_campaigns c
        LEFT JOIN facebook_ads_accounts a ON c.account_id = a.act_id
        WHERE c.roas > 0 AND c.total_spend > 0
        ORDER BY c.roas DESC
        LIMIT 5
    ";
    $best_campaigns = $pdo->query($best_campaigns_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get worst performing campaigns (bottom 5 by ROAS)
    $worst_campaigns_sql = "
        SELECT 
            c.name,
            c.status,
            c.total_spend,
            c.results,
            c.roas,
            c.ctr,
            c.cpc,
            a.act_name as account_name
        FROM facebook_ads_accounts_campaigns c
        LEFT JOIN facebook_ads_accounts a ON c.account_id = a.act_id
        WHERE c.total_spend > 0
        ORDER BY c.roas ASC
        LIMIT 5
    ";
    $worst_campaigns = $pdo->query($worst_campaigns_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent performance data for charts (last 7 days)
    $chart_data_sql = "
        SELECT 
            DATE(c.updated_at) as date,
            SUM(c.total_spend) as daily_spend,
            SUM(c.results) as daily_conversions,
            AVG(c.roas) as avg_roas
        FROM facebook_ads_accounts_campaigns c
        WHERE c.updated_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(c.updated_at)
        ORDER BY date ASC
    ";
    $chart_data = $pdo->query($chart_data_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get account performance summary
    $account_performance_sql = "
        SELECT 
            a.act_name as account_name,
            COUNT(c.id) as campaign_count,
            SUM(c.total_spend) as account_spend,
            SUM(c.results) as account_conversions,
            AVG(c.roas) as avg_roas
        FROM facebook_ads_accounts a
        LEFT JOIN facebook_ads_accounts_campaigns c ON a.act_id = c.account_id
        GROUP BY a.act_id, a.act_name
        HAVING account_spend > 0
        ORDER BY account_spend DESC
    ";
    $account_performance = $pdo->query($account_performance_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get alerts summary (campaigns with low performance)
    $alerts_sql = "
        SELECT 
            c.name as campaign_name,
            c.status,
            c.total_spend,
            c.roas,
            c.ctr,
            a.act_name as account_name,
            CASE 
                WHEN c.roas < 1.0 AND c.total_spend > 100 THEN 'Low ROAS'
                WHEN c.ctr < 1.0 AND c.total_spend > 50 THEN 'Low CTR'
                WHEN c.status = 'PAUSED' AND c.total_spend > 0 THEN 'Campaign Paused'
                ELSE 'Normal'
            END as alert_type
        FROM facebook_ads_accounts_campaigns c
        LEFT JOIN facebook_ads_accounts a ON c.account_id = a.act_id
        WHERE (c.roas < 1.0 AND c.total_spend > 100) 
           OR (c.ctr < 1.0 AND c.total_spend > 50)
           OR (c.status = 'PAUSED' AND c.total_spend > 0)
        ORDER BY c.total_spend DESC
        LIMIT 10
    ";
    $alerts = $pdo->query($alerts_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate overall CTR
    $overall_ctr = 0;
    if ($conversions_result['total_impressions'] > 0) {
        $overall_ctr = ($conversions_result['total_clicks'] / $conversions_result['total_impressions']) * 100;
    }
    
    // Calculate average ROAS
    $avg_roas_sql = "SELECT AVG(roas) as avg_roas FROM facebook_ads_accounts_campaigns WHERE roas > 0 AND total_spend > 0";
    $avg_roas_result = $pdo->query($avg_roas_sql)->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'data' => [
            'summary' => [
                'total_spend' => floatval($total_spend_result['total_spend']),
                'total_conversions' => intval($conversions_result['total_conversions']),
                'total_clicks' => intval($conversions_result['total_clicks']),
                'total_impressions' => intval($conversions_result['total_impressions']),
                'overall_ctr' => round($overall_ctr, 2),
                'avg_roas' => round(floatval($avg_roas_result['avg_roas']), 2),
                'total_accounts' => intval($total_spend_result['total_accounts']),
                'total_campaigns' => intval($total_spend_result['total_campaigns'])
            ],
            'best_campaigns' => $best_campaigns,
            'worst_campaigns' => $worst_campaigns,
            'chart_data' => $chart_data,
            'account_performance' => $account_performance,
            'alerts' => $alerts
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch dashboard data: ' . $e->getMessage()
    ]);
}
?>
