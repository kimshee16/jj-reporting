<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Get ad ID from URL parameter
$ad_id = $_GET['id'] ?? '';
if (empty($ad_id)) {
    header('Location: ads.php');
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


// Fetch ad details from the local database
$ad = null;
$error_message = '';
try {
    $stmt = $pdo->prepare("SELECT * FROM facebook_ads_accounts_ads WHERE id = ?");
    $stmt->execute([$ad_id]);
    $ad = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ad) {
        $error_message = 'Ad not found in the database.';
    }
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Set page variables for template
$page_title = "Ad Details - JJ Reporting Dashboard";
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
            <h2>Ad Details</h2>
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php else: ?>
                <?php 
                $insights = $ad ?? [];
                $spend = $insights['spend'] ?? null;
                $impressions = $insights['impressions'] ?? null;
                $reach = $insights['reach'] ?? null;
                $cpc = $insights['cpc'] ?? null;
                $ctr = $insights['ctr'] ?? null;
                $cpm = $insights['cpm'] ?? null;
                $clicks = $insights['clicks'] ?? null;
                $actions = $insights['actions'] ?? null;
                $creative = $ad['creative'] ?? [];
                ?>
                <table class="details-table">
                    <tr><th>Ad Name</th><td><?php echo htmlspecialchars($ad['name'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Status</th><td><?php echo htmlspecialchars($ad['status'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Ad Set ID</th><td><?php echo htmlspecialchars($ad['adset_id'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Campaign ID</th><td><?php echo htmlspecialchars($ad['campaign_id'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Spend</th><td><?php echo isset($spend) ? '$' . number_format($spend, 2) : 'N/A'; ?></td></tr>
                    <tr><th>Impressions</th><td><?php echo isset($impressions) ? number_format($impressions) : 'N/A'; ?></td></tr>
                    <tr><th>Reach</th><td><?php echo isset($reach) ? number_format($reach) : 'N/A'; ?></td></tr>
                    <tr><th>Clicks</th><td><?php echo isset($clicks) ? number_format($clicks) : 'N/A'; ?></td></tr>
                    <tr><th>CPC</th><td><?php echo isset($cpc) ? '$' . number_format($cpc, 2) : 'N/A'; ?></td></tr>
                    <tr><th>CTR</th><td><?php echo isset($ctr) ? number_format($ctr, 2) . '%' : 'N/A'; ?></td></tr>
                    <tr><th>CPM</th><td><?php echo isset($cpm) ? '$' . number_format($cpm, 2) : 'N/A'; ?></td></tr>
                    <tr><th>Creative</th><td><pre style="white-space:pre-wrap;"><?php echo htmlspecialchars(json_encode($creative, JSON_PRETTY_PRINT)); ?></pre></td></tr>
                    <tr><th>Actions</th><td><pre style="white-space:pre-wrap;"><?php echo htmlspecialchars(json_encode($actions, JSON_PRETTY_PRINT)); ?></pre></td></tr>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'templates/footer.php'; ?>
