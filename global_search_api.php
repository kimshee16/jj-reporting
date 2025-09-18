<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database configuration
require_once 'config.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get search parameters
$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'all'; // all, campaigns, ads, content
$limit = min(intval($_GET['limit'] ?? 10), 50); // Max 50 results

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];

try {
    // Search campaigns
    if ($type === 'all' || $type === 'campaigns') {
        $campaigns_sql = "
            SELECT 
                'campaign' as type,
                id,
                name as title,
                CONCAT('Campaign: ', name, ' (', objective, ')') as description,
                CONCAT('campaigns.php?account_id=', account_id, '&campaign_id=', id) as url,
                created_time,
                'campaign' as icon,
                status,
                total_spend,
                roas
            FROM facebook_ads_accounts_campaigns 
            WHERE name LIKE :query 
               OR objective LIKE :query
               OR status LIKE :query
            ORDER BY name ASC 
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($campaigns_sql);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $campaign_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = array_merge($results, $campaign_results);
    }
    
    // Search ads
    if ($type === 'all' || $type === 'ads') {
        $ads_sql = "
            SELECT 
                'ad' as type,
                a.id,
                a.name as title,
                CONCAT('Ad: ', a.name, ' (Campaign: ', c.name, ')') as description,
                CONCAT('ads.php?account_id=', a.account_id, '&campaign_id=', a.campaign_id, '&adset_id=', a.adset_id) as url,
                a.created_time,
                'ad' as icon,
                a.status,
                a.spend,
                a.ctr,
                a.format
            FROM facebook_ads_accounts_ads a
            LEFT JOIN facebook_ads_accounts_campaigns c ON a.campaign_id = c.id
            WHERE a.name LIKE :query 
               OR a.copy_text LIKE :query
               OR a.headline LIKE :query
               OR a.status LIKE :query
               OR a.format LIKE :query
               OR a.cta_button LIKE :query
            ORDER BY a.name ASC 
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($ads_sql);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $ad_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = array_merge($results, $ad_results);
    }
    
    // Search ad sets
    if ($type === 'all' || $type === 'adsets') {
        $adsets_sql = "
            SELECT 
                'adset' as type,
                a.id,
                a.name as title,
                CONCAT('Ad Set: ', a.name, ' (Campaign: ', c.name, ')') as description,
                CONCAT('adsets.php?account_id=', a.account_id, '&campaign_id=', a.campaign_id, '&adset_id=', a.id) as url,
                a.created_time,
                'adset' as icon,
                a.status,
                a.daily_budget,
                a.lifetime_budget,
                a.impressions,
                a.spend
            FROM facebook_ads_accounts_adsets a
            LEFT JOIN facebook_ads_accounts_campaigns c ON a.campaign_id = c.id
            WHERE a.name LIKE :query 
               OR a.status LIKE :query
               OR a.gender LIKE :query
               OR a.interests LIKE :query
               OR a.placement LIKE :query
               OR a.custom_audience_type LIKE :query
            ORDER BY a.name ASC 
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($adsets_sql);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $adset_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = array_merge($results, $adset_results);
    }
    
    // Search content performance (if content_performance table exists)
    if ($type === 'all' || $type === 'content') {
        try {
            $content_sql = "
                SELECT 
                    'content' as type,
                    id,
                    message as title,
                    CONCAT('Post: ', SUBSTRING(message, 1, 50), '...') as description,
                    CONCAT('content_performance.php?page_id=', page_id, '&post_id=', id) as url,
                    created_time,
                    'content' as icon
                FROM content_performance 
                WHERE message LIKE :query 
                   OR id LIKE :query
                ORDER BY created_time DESC 
                LIMIT :limit
            ";
            
            $stmt = $pdo->prepare($content_sql);
            $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $content_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results = array_merge($results, $content_results);
        } catch (PDOException $e) {
            // Content performance table doesn't exist, skip
        }
    }
    
    // Sort results by relevance (exact matches first, then partial matches)
    usort($results, function($a, $b) use ($query) {
        $a_exact = stripos($a['title'], $query) === 0;
        $b_exact = stripos($b['title'], $query) === 0;
        
        if ($a_exact && !$b_exact) return -1;
        if (!$a_exact && $b_exact) return 1;
        
        return strcasecmp($a['title'], $b['title']);
    });
    
    // Limit total results
    $results = array_slice($results, 0, $limit);
    
    // Add search metadata
    $response = [
        'query' => $query,
        'total' => count($results),
        'results' => $results
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
}
?>

