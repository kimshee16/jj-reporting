<?php
require_once 'config.php';

// Set content type to JSON
// header('Content-Type: application/json');

try {
    // Database connection
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get the action parameter
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_accounts':
            // Get admin ID from session
            $admin_id = $_SESSION['user_id'] ?? 1;
            
            // Fetch all Facebook ad accounts for this admin through access_token relationship
            $stmt = $pdo->prepare("
                SELECT 
                    fa.id,
                    fa.access_token_id,
                    fa.act_name,
                    fa.act_id,
                    fa.account_expiry_date,
                    fa.created_at,
                    fa.updated_at
                FROM facebook_ads_accounts fa
                INNER JOIN facebook_access_tokens fat ON fa.access_token_id = fat.id
                WHERE fat.admin_id = :admin_id
                ORDER BY fa.created_at DESC
            ");
            $stmt->bindParam(':admin_id', $admin_id);
            $stmt->execute();
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for stats
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM facebook_ads_accounts fa
                INNER JOIN facebook_access_tokens fat ON fa.access_token_id = fat.id
                WHERE fat.admin_id = :admin_id
            ");
            $countStmt->bindParam(':admin_id', $admin_id);
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Calculate stats
            $stats = [
                'total_accounts' => $totalCount,
                'total_spend' => '$0', // This would need to be calculated from actual spend data
                'active_campaigns' => 0, // This would need to be calculated from campaigns data
                'avg_roas' => '0x' // This would need to be calculated from performance data
            ];
            
            echo json_encode([
                'success' => true,
                'accounts' => $accounts,
                'stats' => $stats
            ]);
            break;
            
        case 'get_account_stats':
            // Get individual account statistics
            $accountId = $_GET['account_id'] ?? '';
            
            if (empty($accountId)) {
                throw new Exception('Account ID is required');
            }
            
            // This would typically fetch real data from Facebook API
            // For now, return mock data
            $stats = [
                'spend_this_month' => '$' . number_format(rand(5000, 50000), 0),
                'active_campaigns' => rand(1, 20),
                'last_sync' => rand(1, 30) . ' days ago',
                'status' => rand(0, 1) ? 'active' : 'inactive'
            ];
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        case 'sync_account':
            // Sync individual account with Facebook API
            $accountId = $_GET['account_id'] ?? '';
            
            if (empty($accountId)) {
                throw new Exception('Account ID is required');
            }
            
            // Get access token for this account
            $stmt = $pdo->prepare("
                SELECT access_token_id, act_id 
                FROM facebook_ads_accounts 
                WHERE id = ? OR act_id = ?
            ");
            $stmt->execute([$accountId, $accountId]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$account) {
                throw new Exception('Account not found');
            }
            
            // Get the actual access token
            $tokenStmt = $pdo->prepare("
                SELECT access_token 
                FROM facebook_access_tokens 
                WHERE id = ?
            ");
            $tokenStmt->execute([$account['access_token_id']]);
            $tokenData = $tokenStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenData) {
                throw new Exception('Access token not found');
            }
            
            // Call Facebook Graph API
            $apiUrl = "https://graph.facebook.com/v19.0/{$account['act_id']}?fields=name,account_status,currency,timezone_name,spend_cap&access_token={$tokenData['access_token']}";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new Exception('Failed to sync with Facebook API: ' . $response);
            }
            
            $apiData = json_decode($response, true);
            
            if (isset($apiData['error'])) {
                throw new Exception('Facebook API Error: ' . $apiData['error']['message']);
            }
            
            // Check if account already exists in facebook_ads_accounts_details
            $checkStmt = $pdo->prepare("
                SELECT id FROM facebook_ads_accounts_details 
                WHERE act_id = ?
            ");
            $checkStmt->execute([$account['act_id']]);
            $existingAccount = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingAccount) {
                // UPDATE existing account
                $updateStmt = $pdo->prepare("
                    UPDATE facebook_ads_accounts_details 
                    SET 
                        act_name = ?,
                        account_status = ?,
                        currency = ?,
                        timezone_name = ?,
                        spend_cap = ?,
                        updated_at = NOW()
                    WHERE act_id = ?
                ");
                
                $updateStmt->execute([
                    $apiData['name'] ?? '',
                    $apiData['account_status'] ?? '',
                    $apiData['currency'] ?? '',
                    $apiData['timezone_name'] ?? '',
                    $apiData['spend_cap'] ?? null,
                    $account['act_id']
                ]);
            } else {
                // INSERT new account
                $insertStmt = $pdo->prepare("
                    INSERT INTO facebook_ads_accounts_details 
                    (act_id, act_name, account_status, currency, timezone_name, spend_cap, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $insertStmt->execute([
                    $account['act_id'],
                    $apiData['name'] ?? '',
                    $apiData['account_status'] ?? '',
                    $apiData['currency'] ?? '',
                    $apiData['timezone_name'] ?? '',
                    $apiData['spend_cap'] ?? null
                ]);
            }
            
            // Also update the updated_at field in facebook_ads_accounts table
            $updateMainStmt = $pdo->prepare("
                UPDATE facebook_ads_accounts 
                SET updated_at = NOW() 
                WHERE act_id = ?
            ");
            $updateMainStmt->execute([$account['act_id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Account synced successfully',
                'data' => $apiData
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>