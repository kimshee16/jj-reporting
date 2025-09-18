<?php
// Suppress all output except what we explicitly echo
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'config.php';

// Get adset ID, campaign ID and account ID from URL
$adset_id = $_GET['adset_id'] ?? '';
$campaign_id = $_GET['campaign_id'] ?? '';
$account_id = $_GET['account_id'] ?? '';

if (empty($adset_id) || empty($campaign_id) || empty($account_id)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing adset_id, campaign_id or account_id']);
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

try {
    // Get access token
    $stmt = $pdo->prepare("SELECT access_token FROM facebook_access_tokens WHERE admin_id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$token_data || empty($token_data['access_token'])) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Access token not found. Please update your access token first.']);
        exit();
    }
    
    $access_token = $token_data['access_token'];
    
    // Fetch ads data from Facebook Graph API using cURL
    $api_url = "https://graph.facebook.com/v21.0/{$adset_id}/ads?";
    $api_url .= "fields=id,name,status,creative{id,title,body,image_url,video_id,link_url,object_story_spec},insights{impressions,reach,clicks,spend,cpc,ctr,cpm,actions,reactions,shares,comments}";
    $api_url .= "&access_token={$access_token}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'cURL Error: ' . $curl_error]);
        exit();
    }
    
    if ($http_code !== 200) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "API request failed with HTTP code $http_code"]);
        exit();
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['data']) || !is_array($data['data'])) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => isset($data['error']['message']) ? $data['error']['message'] : 'Failed to fetch ads data']);
        exit();
    }
    
    $ads = $data['data'];
    $synced_count = 0;
    $errors = [];
    
    // Check if table exists, create if not
    $table_check = $pdo->query("SHOW TABLES LIKE 'facebook_ads_accounts_ads'");
    if ($table_check->rowCount() == 0) {
        // Create the table
        $create_sql = "CREATE TABLE facebook_ads_accounts_ads (
            id VARCHAR(255) PRIMARY KEY,
            account_id VARCHAR(255) NOT NULL,
            campaign_id VARCHAR(255) NOT NULL,
            adset_id VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            status VARCHAR(50),
            creative_preview TEXT,
            copy_text TEXT,
            headline VARCHAR(500),
            cta_button VARCHAR(100),
            format VARCHAR(100),
            creative_type VARCHAR(100),
            impressions DECIMAL(15, 2) DEFAULT 0.00,
            reach DECIMAL(15, 2) DEFAULT 0.00,
            clicks DECIMAL(15, 2) DEFAULT 0.00,
            ctr DECIMAL(15, 2) DEFAULT 0.00,
            cpc DECIMAL(15, 2) DEFAULT 0.00,
            spend DECIMAL(15, 2) DEFAULT 0.00,
            likes DECIMAL(15, 2) DEFAULT 0.00,
            shares DECIMAL(15, 2) DEFAULT 0.00,
            comments DECIMAL(15, 2) DEFAULT 0.00,
            reactions DECIMAL(15, 2) DEFAULT 0.00,
            cost_per_result DECIMAL(15, 2) DEFAULT 0.00,
            conversion_value DECIMAL(15, 2) DEFAULT 0.00,
            roas DECIMAL(15, 2) DEFAULT 0.00,
            results DECIMAL(15, 2) DEFAULT 0.00,
            created_time DATETIME,
            last_synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $pdo->exec($create_sql);
    }
    
    // Prepare the insert/update statement
    $sql = "INSERT INTO facebook_ads_accounts_ads (
        id, account_id, campaign_id, adset_id, name, status, creative_preview, copy_text, headline, cta_button,
        format, creative_type, impressions, reach, clicks, ctr, cpc, spend, likes, shares, comments, reactions,
        cost_per_result, conversion_value, roas, results, created_time, last_synced_at, updated_at
    ) VALUES (
        :id, :account_id, :campaign_id, :adset_id, :name, :status, :creative_preview, :copy_text, :headline, :cta_button,
        :format, :creative_type, :impressions, :reach, :clicks, :ctr, :cpc, :spend, :likes, :shares, :comments, :reactions,
        :cost_per_result, :conversion_value, :roas, :results, :created_time, :last_synced_at, :updated_at
    ) ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        status = VALUES(status),
        creative_preview = VALUES(creative_preview),
        copy_text = VALUES(copy_text),
        headline = VALUES(headline),
        cta_button = VALUES(cta_button),
        format = VALUES(format),
        creative_type = VALUES(creative_type),
        impressions = VALUES(impressions),
        reach = VALUES(reach),
        clicks = VALUES(clicks),
        ctr = VALUES(ctr),
        cpc = VALUES(cpc),
        spend = VALUES(spend),
        likes = VALUES(likes),
        shares = VALUES(shares),
        comments = VALUES(comments),
        reactions = VALUES(reactions),
        cost_per_result = VALUES(cost_per_result),
        conversion_value = VALUES(conversion_value),
        roas = VALUES(roas),
        results = VALUES(results),
        last_synced_at = VALUES(last_synced_at),
        updated_at = VALUES(updated_at)";
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($ads as $ad) {
        try {
            $ad_id = $ad['id'] ?? '';
            $ad_name = $ad['name'] ?? '';
            $ad_status = $ad['status'] ?? '';
            $creative = $ad['creative'] ?? [];
            
            // Extract creative information
            $creative_preview = null;
            $copy_text = null;
            $headline = null;
            $cta_button = null;
            $format = null;
            $creative_type = null;
            
            if (!empty($creative)) {
                // Get creative preview (image/video URL)
                if (isset($creative['image_url'])) {
                    $creative_preview = json_encode(['type' => 'image', 'url' => $creative['image_url']]);
                    $format = 'Single Image';
                    $creative_type = 'Image';
                } elseif (isset($creative['video_id'])) {
                    $creative_preview = json_encode(['type' => 'video', 'id' => $creative['video_id']]);
                    $format = 'Video';
                    $creative_type = 'Video';
                }
                
                // Get copy and headline
                $copy_text = $creative['body'] ?? null;
                $headline = $creative['title'] ?? null;
                
                // Determine CTA button (this would need additional API call to get full creative details)
                $cta_button = 'Learn More'; // Default CTA
            }
            
            // Get insights data
            $insights = $ad['insights']['data'][0] ?? [];
            $impressions = $insights['impressions'] ?? 0;
            $reach = $insights['reach'] ?? 0;
            $clicks = $insights['clicks'] ?? 0;
            $spend = $insights['spend'] ?? 0;
            $cpc = $insights['cpc'] ?? 0;
            $ctr = $insights['ctr'] ?? 0;
            $cpm = $insights['cpm'] ?? 0;
            
            // Get engagement metrics
            $likes = 0;
            $shares = 0;
            $comments = 0;
            $reactions = 0;
            
            if (isset($insights['actions']) && is_array($insights['actions'])) {
                foreach ($insights['actions'] as $action) {
                    switch ($action['action_type']) {
                        case 'post_reaction':
                            $reactions += $action['value'] ?? 0;
                            break;
                        case 'post_engagement':
                            $likes += $action['value'] ?? 0;
                            break;
                        case 'post_share':
                            $shares += $action['value'] ?? 0;
                            break;
                        case 'post_comment':
                            $comments += $action['value'] ?? 0;
                            break;
                    }
                }
            }
            
            // Get conversion metrics
            $cost_per_result = 0;
            $conversion_value = 0;
            $roas = 0;
            $results = 0;
            
            if (isset($insights['actions']) && is_array($insights['actions'])) {
                foreach ($insights['actions'] as $action) {
                    if (in_array($action['action_type'], ['link_click', 'conversion', 'purchase', 'add_to_cart'])) {
                        $results += $action['value'] ?? 0;
                    }
                }
                
                // Calculate cost per result
                if ($results > 0 && $spend > 0) {
                    $cost_per_result = $spend / $results;
                }
                
                // Calculate ROAS (Return on Ad Spend) - would need conversion value data
                if ($conversion_value > 0 && $spend > 0) {
                    $roas = $conversion_value / $spend;
                }
            }
            
            // Bind parameters
            $stmt->bindParam(':id', $ad_id);
            $stmt->bindParam(':account_id', $account_id);
            $stmt->bindParam(':campaign_id', $campaign_id);
            $stmt->bindParam(':adset_id', $adset_id);
            $stmt->bindParam(':name', $ad_name);
            $stmt->bindParam(':status', $ad_status);
            $stmt->bindParam(':creative_preview', $creative_preview);
            $stmt->bindParam(':copy_text', $copy_text);
            $stmt->bindParam(':headline', $headline);
            $stmt->bindParam(':cta_button', $cta_button);
            $stmt->bindParam(':format', $format);
            $stmt->bindParam(':creative_type', $creative_type);
            $stmt->bindParam(':impressions', $impressions);
            $stmt->bindParam(':reach', $reach);
            $stmt->bindParam(':clicks', $clicks);
            $stmt->bindParam(':ctr', $ctr);
            $stmt->bindParam(':cpc', $cpc);
            $stmt->bindParam(':spend', $spend);
            $stmt->bindParam(':likes', $likes);
            $stmt->bindParam(':shares', $shares);
            $stmt->bindParam(':comments', $comments);
            $stmt->bindParam(':reactions', $reactions);
            $stmt->bindParam(':cost_per_result', $cost_per_result);
            $stmt->bindParam(':conversion_value', $conversion_value);
            $stmt->bindParam(':roas', $roas);
            $stmt->bindParam(':results', $results);
            $stmt->bindParam(':created_time', date('Y-m-d H:i:s'));
            $stmt->bindParam(':last_synced_at', date('Y-m-d H:i:s'));
            $stmt->bindParam(':updated_at', date('Y-m-d H:i:s'));
            
            if ($stmt->execute()) {
                $synced_count++;
            } else {
                $errors[] = "Failed to sync ad: {$ad_name}";
            }
            
        } catch (Exception $e) {
            $errors[] = "Error syncing ad {$ad_name}: " . $e->getMessage();
            error_log("Error syncing ad {$ad_id}: " . $e->getMessage());
        }
    }
    
    // Return response
    ob_clean();
    if ($synced_count > 0) {
        $message = "Successfully synced {$synced_count} ads to database.";
        if (!empty($errors)) {
            $message .= " " . count($errors) . " errors occurred.";
        }
        echo json_encode([
            'success' => true,
            'synced_count' => $synced_count,
            'message' => $message,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No ads were synced. ' . implode('; ', $errors)
        ]);
    }
    
} catch (Exception $e) {
    ob_clean();
    error_log("Error in sync_ads.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}
?>
