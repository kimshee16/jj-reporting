<?php
require_once 'config.php';
session_start();

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check for errors
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $error_description = $_GET['error_description'] ?? 'Unknown error';
    
    // Redirect back to dashboard with error
    header('Location: dashboard.php?oauth_error=' . urlencode($error_description));
    exit;
}

// Verify state parameter
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    header('Location: dashboard.php?oauth_error=' . urlencode('Invalid state parameter'));
    exit;
}

// Get authorization code
if (!isset($_GET['code'])) {
    header('Location: dashboard.php?oauth_error=' . urlencode('No authorization code received'));
    exit;
}

$code = $_GET['code'];

// Exchange code for access token
$token_data = [
    'client_id' => META_APP_ID,
    'client_secret' => META_APP_SECRET,
    'redirect_uri' => META_REDIRECT_URI,
    'code' => $code
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, META_TOKEN_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    header('Location: dashboard.php?oauth_error=' . urlencode('Failed to get access token'));
    exit;
}

$token_data = json_decode($response, true);

if (!isset($token_data['access_token'])) {
    header('Location: dashboard.php?oauth_error=' . urlencode('No access token in response'));
    exit;
}

$access_token = $token_data['access_token'];

// Get user's ad accounts from Facebook Marketing API
$ad_accounts_url = META_GRAPH_URL . '/me/adaccounts?fields=id,name,account_status,currency,timezone_name,amount_spent,balance&access_token=' . $access_token;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ad_accounts_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$ad_accounts_response = curl_exec($ch);
$ad_accounts_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($ad_accounts_http_code !== 200) {
    // If we can't get ad accounts, show error with fallback to simulated data
    error_log("Failed to fetch ad accounts. HTTP Code: $ad_accounts_http_code, Response: $ad_accounts_response");
    
    // Fallback to simulated data for demonstration
    $available_accounts = [
        [
            'id' => 'act_' . rand(100000000, 999999999),
            'name' => 'Demo Account (No Real Access)',
            'account_status' => 'ACTIVE',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'amount_spent' => 0,
            'balance' => 0
        ]
    ];
} else {
    $ad_accounts_data = json_decode($ad_accounts_response, true);
    
    if (isset($ad_accounts_data['data']) && !empty($ad_accounts_data['data'])) {
        // Process real ad accounts
        $available_accounts = [];
        foreach ($ad_accounts_data['data'] as $account) {
            $available_accounts[] = [
                'id' => $account['id'],
                'name' => $account['name'] ?? 'Unnamed Account',
                'account_status' => $account['account_status'] ?? 'UNKNOWN',
                'currency' => $account['currency'] ?? 'USD',
                'timezone' => $account['timezone_name'] ?? 'UTC',
                'amount_spent' => $account['amount_spent'] ?? 0,
                'balance' => $account['balance'] ?? 0
            ];
        }
    } else {
        // No ad accounts found
        $available_accounts = [
            [
                'id' => 'no_accounts',
                'name' => 'No Ad Accounts Found',
                'account_status' => 'NONE',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'amount_spent' => 0,
                'balance' => 0
            ]
        ];
    }
}



// Store access token and accounts in session for account selection
$_SESSION['meta_access_token'] = $access_token;
$_SESSION['available_accounts'] = $available_accounts;
$_SESSION['access_token_id'] = 1; // Set default access_token_id for now

// Redirect to account selection page
header('Location: account_selection.php');
exit;
?>
