<?php
require_once 'config.php';

// Test script to verify Meta API connection
echo "<h2>Meta API Connection Test</h2>";

// Test 1: Get user info
echo "<h3>1. Testing User Info</h3>";
$user_url = META_GRAPH_URL . '/me?access_token=' . urlencode('EAAbh9hWSWTwBPZA1xBYR3FEAfoWqLVabvNeB5uE6350gAyXmaUTZAjt1b3vJletUxSjrE2h8605TufL030005jTns7X5aE4EQIMTPP6veo0ZAP5p23p2vnteWVtZB0y0rXUSAy5I3iZCRJt124waVPfmVNcyZBvjoL8KqYbhPVsVMZBRD6VaH4v7sRNhehVpZBBDgZBblapsA09qJhT7EixZCK');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $user_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$user_response = curl_exec($ch);
$user_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $user_http_code</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($user_response) . "</pre>";

// Test 2: Get ad accounts
echo "<h3>2. Testing Ad Accounts</h3>";
$ad_accounts_url = META_GRAPH_URL . '/me/adaccounts?fields=id,name,account_status,currency,timezone_name,amount_spent,balance&access_token=' . urlencode('EAAbh9hWSWTwBPZA1xBYR3FEAfoWqLVabvNeB5uE6350gAyXmaUTZAjt1b3vJletUxSjrE2h8605TufL030005jTns7X5aE4EQIMTPP6veo0ZAP5p23p2vnteWVtZB0y0rXUSAy5I3iZCRJt124waVPfmVNcyZBvjoL8KqYbhPVsVMZBRD6VaH4v7sRNhehVpZBBDgZBblapsA09qJhT7EixZCK');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ad_accounts_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$ad_accounts_response = curl_exec($ch);
$ad_accounts_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $ad_accounts_http_code</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($ad_accounts_response) . "</pre>";

// Test 3: Get sandbox ad accounts (if any)
echo "<h3>3. Testing Sandbox Ad Accounts</h3>";
$sandbox_url = META_GRAPH_URL . '/me/adaccounts?fields=id,name,account_status,currency,timezone_name,amount_spent,balance&access_token=' . urlencode('EAAbh9hWSWTwBPZA1xBYR3FEAfoWqLVabvNeB5uE6350gAyXmaUTZAjt1b3vJletUxSjrE2h8605TufL030005jTns7X5aE4EQIMTPP6veo0ZAP5p23p2vnteWVtZB0y0rXUSAy5I3iZCRJt124waVPfmVNcyZBvjoL8KqYbhPVsVMZBRD6VaH4v7sRNhehVpZBBDgZBblapsA09qJhT7EixZCK');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $sandbox_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$sandbox_response = curl_exec($ch);
$sandbox_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $sandbox_http_code</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($sandbox_response) . "</pre>";

echo "<hr>";
echo "<p><strong>Note:</strong> This test uses the access token from your Meta App Dashboard. If you see errors, make sure:</p>";
echo "<ul>";
echo "<li>Your app has the correct permissions</li>";
echo "<li>The access token is valid and not expired</li>";
echo "<li>Your app is in the correct mode (Development/Live)</li>";
echo "</ul>";
?>
















