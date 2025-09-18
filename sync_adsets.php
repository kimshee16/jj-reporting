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

// Get campaign ID and account ID from URL
$campaign_id = $_GET['campaign_id'] ?? '';
$account_id = $_GET['account_id'] ?? '';

if (empty($campaign_id) || empty($account_id)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing campaign_id or account_id']);
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
    
    // Fetch ad sets data from Facebook Graph API using cURL
    $api_url = "https://graph.facebook.com/v21.0/{$campaign_id}/adsets?";
    $api_url .= "fields=id,name,daily_budget,lifetime_budget,status,targeting,start_time,end_time,insights{impressions,reach,clicks,spend,cpc,ctr,cpm,actions}";
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
        echo json_encode(['success' => false, 'message' => isset($data['error']['message']) ? $data['error']['message'] : 'Failed to fetch ad sets data']);
        exit();
    }
    
    $adsets = $data['data'];
    $synced_count = 0;
    $errors = [];
    
    // Check if table exists, create if not
    $table_check = $pdo->query("SHOW TABLES LIKE 'facebook_ads_accounts_adsets'");
    if ($table_check->rowCount() == 0) {
        // Create the table
        $create_sql = "CREATE TABLE facebook_ads_accounts_adsets (
            id VARCHAR(255) PRIMARY KEY,
            account_id VARCHAR(255) NOT NULL,
            campaign_id VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            status VARCHAR(50),
            budget_type VARCHAR(50),
            daily_budget DECIMAL(15, 2),
            lifetime_budget DECIMAL(15, 2),
            age_min INT,
            age_max INT,
            gender VARCHAR(50),
            interests TEXT,
            custom_audience_type VARCHAR(100),
            lookalike_audience_id VARCHAR(255),
            placement TEXT,
            device_breakdown TEXT,
            start_time DATETIME,
            end_time DATETIME,
            results DECIMAL(15, 2) DEFAULT 0.00,
            impressions DECIMAL(15, 2) DEFAULT 0.00,
            reach DECIMAL(15, 2) DEFAULT 0.00,
            cpm DECIMAL(15, 2) DEFAULT 0.00,
            ctr DECIMAL(15, 2) DEFAULT 0.00,
            cpc DECIMAL(15, 2) DEFAULT 0.00,
            created_time DATETIME,
            last_synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $pdo->exec($create_sql);
    }
    
    // Prepare the insert/update statement
    $sql = "INSERT INTO facebook_ads_accounts_adsets (
        id, account_id, campaign_id, name, status, budget_type, daily_budget, lifetime_budget,
        age_min, age_max, gender, interests, custom_audience_type, lookalike_audience_id,
        placement, device_breakdown, start_time, end_time, results, impressions, reach,
        cpm, ctr, cpc, created_time, last_synced_at, updated_at
    ) VALUES (
        :id, :account_id, :campaign_id, :name, :status, :budget_type, :daily_budget, :lifetime_budget,
        :age_min, :age_max, :gender, :interests, :custom_audience_type, :lookalike_audience_id,
        :placement, :device_breakdown, :start_time, :end_time, :results, :impressions, :reach,
        :cpm, :ctr, :cpc, :created_time, :last_synced_at, :updated_at
    ) ON DUPLICATE KEY UPDATE
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
        last_synced_at = VALUES(last_synced_at),
        updated_at = VALUES(updated_at)";
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($adsets as $adset) {
        try {
            $adset_id = $adset['id'] ?? '';
            $adset_name = $adset['name'] ?? '';
            $adset_status = $adset['status'] ?? '';
            $daily_budget = $adset['daily_budget'] ?? null;
            $lifetime_budget = $adset['lifetime_budget'] ?? null;
            $targeting = $adset['targeting'] ?? [];
            $start_time = $adset['start_time'] ?? null;
            $end_time = $adset['end_time'] ?? null;
            
            // Determine budget type
            $budget_type = null;
            if ($daily_budget !== null) {
                $budget_type = 'daily';
            } elseif ($lifetime_budget !== null) {
                $budget_type = 'lifetime';
            }
            
            // Extract targeting information
            $age_min = $targeting['age_min'] ?? null;
            $age_max = $targeting['age_max'] ?? null;
            $genders = $targeting['genders'] ?? null;
            $interests = $targeting['interests'] ?? null;
            $custom_audiences = $targeting['custom_audiences'] ?? null;
            $lookalike_audiences = $targeting['lookalike_audiences'] ?? null;
            $publisher_platforms = $targeting['publisher_platforms'] ?? null;
            $device_platforms = $targeting['device_platforms'] ?? null;
            
            // Process gender
            $gender = null;
            if ($genders && is_array($genders)) {
                $gender_labels = [];
                foreach ($genders as $g) {
                    switch ($g) {
                        case 1:
                            $gender_labels[] = 'Male';
                            break;
                        case 2:
                            $gender_labels[] = 'Female';
                            break;
                        default:
                            $gender_labels[] = $g;
                    }
                }
                $gender = implode(',', $gender_labels);
            } elseif ($genders) {
                switch ($genders) {
                    case 1:
                        $gender = 'Male';
                        break;
                    case 2:
                        $gender = 'Female';
                        break;
                    default:
                        $gender = $genders;
                }
            }
            
            // Process interests
            $interests_json = null;
            if ($interests && is_array($interests)) {
                $interests_json = json_encode($interests);
            }
            
            // Process custom audiences
            $custom_audience_type = null;
            if ($custom_audiences && is_array($custom_audiences) && count($custom_audiences) > 0) {
                $custom_audience_type = 'custom_audience';
            }
            
            // Process lookalike audiences
            $lookalike_audience_id = null;
            if ($lookalike_audiences && is_array($lookalike_audiences) && count($lookalike_audiences) > 0) {
                $lookalike_audience_id = implode(',', array_column($lookalike_audiences, 'id'));
            }
            
            // Process placement
            $placement = null;
            if ($publisher_platforms && is_array($publisher_platforms)) {
                $placement = implode(',', $publisher_platforms);
            }
            
            // Process device breakdown
            $device_breakdown = null;
            if ($device_platforms && is_array($device_platforms)) {
                $device_breakdown = implode(',', $device_platforms);
            }
            
            // Get insights data
            $insights = $adset['insights']['data'][0] ?? [];
            $impressions = $insights['impressions'] ?? 0;
            $reach = $insights['reach'] ?? 0;
            $spend = $insights['spend'] ?? 0;
            $cpc = $insights['cpc'] ?? 0;
            $ctr = $insights['ctr'] ?? 0;
            $cpm = $insights['cpm'] ?? 0;
            
            // Get results (actions)
            $results = 0;
            if (isset($insights['actions']) && is_array($insights['actions'])) {
                foreach ($insights['actions'] as $action) {
                    if (isset($action['action_type']) && $action['action_type'] === 'link_click') {
                        $results += $action['value'] ?? 0;
                    }
                }
            }
            
            // Bind parameters
            $stmt->bindParam(':id', $adset_id);
            $stmt->bindParam(':account_id', $account_id);
            $stmt->bindParam(':campaign_id', $campaign_id);
            $stmt->bindParam(':name', $adset_name);
            $stmt->bindParam(':status', $adset_status);
            $stmt->bindParam(':budget_type', $budget_type);
            $stmt->bindParam(':daily_budget', $daily_budget);
            $stmt->bindParam(':lifetime_budget', $lifetime_budget);
            $stmt->bindParam(':age_min', $age_min);
            $stmt->bindParam(':age_max', $age_max);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':interests', $interests_json);
            $stmt->bindParam(':custom_audience_type', $custom_audience_type);
            $stmt->bindParam(':lookalike_audience_id', $lookalike_audience_id);
            $stmt->bindParam(':placement', $placement);
            $stmt->bindParam(':device_breakdown', $device_breakdown);
            $stmt->bindParam(':start_time', $start_time);
            $stmt->bindParam(':end_time', $end_time);
            $stmt->bindParam(':results', $results);
            $stmt->bindParam(':impressions', $impressions);
            $stmt->bindParam(':reach', $reach);
            $stmt->bindParam(':cpm', $cpm);
            $stmt->bindParam(':ctr', $ctr);
            $stmt->bindParam(':cpc', $cpc);
            $stmt->bindParam(':created_time', $start_time);
            $stmt->bindParam(':last_synced_at', date('Y-m-d H:i:s'));
            $stmt->bindParam(':updated_at', date('Y-m-d H:i:s'));
            
            if ($stmt->execute()) {
                $synced_count++;
            } else {
                $errors[] = "Failed to sync ad set: {$adset_name}";
            }
            
        } catch (Exception $e) {
            $errors[] = "Error syncing ad set {$adset_name}: " . $e->getMessage();
            error_log("Error syncing ad set {$adset_id}: " . $e->getMessage());
        }
    }
    
    // Return response
    ob_clean();
    if ($synced_count > 0) {
        $message = "Successfully synced {$synced_count} ad sets to database.";
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
            'message' => 'No ad sets were synced. ' . implode('; ', $errors)
        ]);
    }
    
} catch (Exception $e) {
    ob_clean();
    error_log("Error in sync_adsets.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}
?>
