<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Sync Campaigns</h2>";

// Test session
session_start();
echo "<p>Session admin_logged_in: " . (isset($_SESSION['admin_logged_in']) ? 'true' : 'false') . "</p>";
echo "<p>Session admin_id: " . ($_SESSION['admin_id'] ?? 'not set') . "</p>";

// Test database connection
$host = 'localhost';
$dbname = 'report-database';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✓ Database connection successful</p>";
} catch(PDOException $e) {
    echo "<p>✗ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Test CampaignSync class
try {
    require_once 'classes/CampaignSync.php';
    echo "<p>✓ CampaignSync class loaded</p>";
} catch(Exception $e) {
    echo "<p>✗ Error loading CampaignSync: " . $e->getMessage() . "</p>";
}

// Test account_id parameter
$account_id = $_GET['account_id'] ?? '';
echo "<p>Account ID from URL: " . ($account_id ?: 'not provided') . "</p>";

if ($account_id) {
    echo "<p><a href='sync_campaigns.php?account_id=" . urlencode($account_id) . "'>Go to Sync Page</a></p>";
} else {
    echo "<p>Please provide account_id parameter: ?account_id=your_account_id</p>";
}
?>
