<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Get campaign ID from URL parameter
$campaign_id = $_GET['id'] ?? '';
if (empty($campaign_id)) {
    header('Location: campaigns.php');
    exit();
}

// Get admin's access token from session or fetch from DB if needed
$access_token = '';
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    require_once 'config.php';
    
    // Try to get access token from DB (optional fallback)
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("SELECT access_token FROM facebook_access_tokens WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($token_data) {
            $access_token = $token_data['access_token'];
        }
    } catch (PDOException $e) {
        // fallback: leave $access_token empty
    }
}

// Fetch campaign details from Facebook Graph API
$campaign = null;
$error_message = '';
if (empty($access_token)) {
    $error_message = 'No access token found. Please update your access token.';
} else {
    $fields = 'id,name,objective,status,daily_budget,lifetime_budget,start_time,stop_time,insights{spend,actions,purchase_roas,ctr,cpc,cpm}';
    $api_url = "https://graph.facebook.com/v21.0/{$campaign_id}?fields=" . urlencode($fields) . "&access_token=" . urlencode($access_token);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: JJ-Reporting-Dashboard/1.0',
            'timeout' => 30
        ]
    ]);
    $response = @file_get_contents($api_url, false, $context);
    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['error'])) {
            $error_message = 'API Error: ' . ($data['error']['message'] ?? 'Unknown error');
        } elseif (isset($data['id'])) {
            $campaign = $data;
        } else {
            $error_message = 'Campaign not found.';
        }
    } else {
        $error_message = 'Failed to fetch campaign details from Facebook API.';
    }
}

// Set page variables for template
$page_title = "Campaign Details - JJ Reporting Dashboard";
include 'templates/header.php';
?>

<?php include 'templates/sidebar.php'; ?>

<main class="main-content">
    <?php 
    $header_actions = '<a href="javascript:window.close();" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Close</a>';
    include 'templates/top_header.php'; 
    ?>

    <div class="dashboard-content">
        <div class="details-container">
            <h2>Campaign Details</h2>
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php else: ?>
                <?php 
                $insights = $campaign['insights']['data'][0] ?? [];
                $spend = $insights['spend'] ?? null;
                $roas = $insights['purchase_roas'] ?? null;
                $ctr = $insights['ctr'] ?? null;
                $cpc = $insights['cpc'] ?? null;
                $cpm = $insights['cpm'] ?? null;
                ?>
                <table class="details-table">
                    <tr><th>Campaign Name</th><td><?php echo htmlspecialchars($campaign['name'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Objective</th><td><?php echo htmlspecialchars($campaign['objective'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Status</th><td><?php echo htmlspecialchars($campaign['status'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Daily Budget</th><td><?php echo isset($campaign['daily_budget']) ? '$' . number_format($campaign['daily_budget']/100, 2) : 'N/A'; ?></td></tr>
                    <tr><th>Lifetime Budget</th><td><?php echo isset($campaign['lifetime_budget']) ? '$' . number_format($campaign['lifetime_budget']/100, 2) : 'N/A'; ?></td></tr>
                    <tr><th>Spend</th><td><?php echo isset($spend) ? '$' . number_format($spend, 2) : 'N/A'; ?></td></tr>
                    <tr><th>ROAS</th><td><?php echo isset($roas) ? number_format($roas, 2) : 'N/A'; ?></td></tr>
                    <tr><th>CTR</th><td><?php echo isset($ctr) ? number_format($ctr, 2) . '%' : 'N/A'; ?></td></tr>
                    <tr><th>CPC</th><td><?php echo isset($cpc) ? '$' . number_format($cpc, 2) : 'N/A'; ?></td></tr>
                    <tr><th>CPM</th><td><?php echo isset($cpm) ? '$' . number_format($cpm, 2) : 'N/A'; ?></td></tr>
                    <tr><th>Start Date</th><td><?php echo isset($campaign['start_time']) ? date('M j, Y', strtotime($campaign['start_time'])) : 'N/A'; ?></td></tr>
                    <tr><th>End Date</th><td><?php echo isset($campaign['stop_time']) ? date('M j, Y', strtotime($campaign['stop_time'])) : 'N/A'; ?></td></tr>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'templates/footer.php'; ?>
