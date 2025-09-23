<?php
session_start();

// API token authentication (fallback for tools like Postman)
// $api_token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
// $valid_token = false;

// if ($api_token) {
//     // Example: Bearer <token>
//     if (preg_match('/Bearer\s(\S+)/', $api_token, $matches)) {
//         $token = $matches[1];
//         require_once 'config.php';
//         try {
//             $pdo_token = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
//             $pdo_token->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//             $stmt = $pdo_token->prepare("SELECT admin_id FROM facebook_access_tokens WHERE access_token = ?");
//             $stmt->execute([$token]);
//             $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
//             if ($token_data) {
//                 $_SESSION['admin_logged_in'] = true;
//                 $_SESSION['admin_id'] = $token_data['admin_id'];
//                 $valid_token = true;
//             }
//         } catch (PDOException $e) {
//             // Ignore, will fail below
//         }
//     }
// }

// Check if user is logged in (session or valid token)
// if ((!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) && !$valid_token) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Unauthorized']);
//     exit;
// }

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

// Debug logging (remove in production)
if (DEBUG_MODE) {
    error_log("Global Search - Query: " . $query . ", Type: " . $type . ", Admin ID: " . $_GET['admin']);
}

$results = [];

try {
    $admin_id = $_GET['admin'];
    
    // Search campaigns
    if ($type === 'all' || $type === 'campaigns') {
        $campaigns_sql = "
            SELECT 
                'campaign' as type,
                c.id,
                c.name as title,
                CONCAT('Campaign: ', c.name, ' (', c.objective, ')') as description,
                CONCAT('campaigns.php?account_id=', c.account_id, '&campaign_id=', c.id) as url,
                c.created_at,
                'campaign' as icon,
                c.status,
                c.total_spend,
                c.roas
            FROM facebook_ads_accounts_campaigns c
            INNER JOIN facebook_ads_accounts faa ON c.account_id COLLATE utf8mb4_unicode_ci = faa.act_id COLLATE utf8mb4_unicode_ci
            INNER JOIN facebook_access_tokens fat ON faa.access_token_id = fat.id
            WHERE fat.admin_id = :admin_id 
               AND (c.name LIKE :query 
                   OR c.objective LIKE :query
                   OR c.status LIKE :query)
            ORDER BY c.name ASC 
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($campaigns_sql);
        $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
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
                -- a.spend, -- removed because column does not exist
                a.ctr,
                a.format
            FROM facebook_ads_accounts_ads a
            LEFT JOIN facebook_ads_accounts_campaigns c ON a.campaign_id = c.id
            INNER JOIN facebook_ads_accounts faa ON a.account_id COLLATE utf8mb4_unicode_ci = faa.act_id COLLATE utf8mb4_unicode_ci
            INNER JOIN facebook_access_tokens fat ON faa.access_token_id = fat.id
            WHERE fat.admin_id = :admin_id 
               AND (a.name LIKE :query 
                   OR a.copy_text LIKE :query
                   OR a.headline LIKE :query
                   OR a.status LIKE :query
                   OR a.format LIKE :query
                   OR a.cta_button LIKE :query)
            ORDER BY a.name ASC 
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($ads_sql);
        $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
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
                (
                    SELECT SUM(spend)
                    FROM facebook_ads_accounts_ads ads
                    WHERE ads.adset_id = a.id
                ) as total_spend
            FROM facebook_ads_accounts_adsets a
            LEFT JOIN facebook_ads_accounts_campaigns c ON a.campaign_id = c.id
            INNER JOIN facebook_ads_accounts faa ON a.account_id COLLATE utf8mb4_unicode_ci = faa.act_id COLLATE utf8mb4_unicode_ci
            INNER JOIN facebook_access_tokens fat ON faa.access_token_id = fat.id
            WHERE fat.admin_id = :admin_id 
               AND (a.name LIKE :query 
                   OR a.status LIKE :query
                   OR a.gender LIKE :query
                   OR a.interests LIKE :query
                   OR a.placement LIKE :query
                   OR a.custom_audience_type LIKE :query)
            ORDER BY a.name ASC 
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($adsets_sql);
        $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $adset_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = array_merge($results, $adset_results);
    }
    
    // Search accounts
    if ($type === 'all' || $type === 'accounts') {
        $accounts_sql = "
            SELECT 
                'account' as type,
                faa.act_id as id,
                fad.act_name as title,
                CONCAT('Account: ', fad.act_name, ' (', fad.currency, ')') as description,
                CONCAT('accounts.php?account_id=', faa.act_id) as url,
                faa.created_at as created_time,
                'account' as icon,
                fad.account_status as status,
                fad.timezone_name,
                fad.currency
            FROM facebook_ads_accounts faa
            INNER JOIN facebook_access_tokens fat ON faa.access_token_id = fat.id
            LEFT JOIN facebook_ads_accounts_details fad ON faa.act_id COLLATE utf8mb4_general_ci = fad.act_id COLLATE utf8mb4_general_ci
            WHERE fat.admin_id = :admin_id 
               AND (
                   fad.act_name LIKE :query 
                   OR faa.act_id LIKE :query
                   OR fad.currency LIKE :query
                   OR fad.account_status LIKE :query
                   OR fad.timezone_name LIKE :query
               )
            ORDER BY fad.act_name ASC 
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($accounts_sql);
        $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $account_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = array_merge($results, $account_results);
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
        'type' => $type,
        'admin_id' => $admin_id,
        'total' => count($results),
        'results' => $results
    ];
    
    // Debug logging
    if (DEBUG_MODE) {
        error_log("Global Search Results - Found " . count($results) . " results for query: " . $query);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
}
?>

