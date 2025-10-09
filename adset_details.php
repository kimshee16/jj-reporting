<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Get adset ID from URL parameter
$adset_id = $_GET['id'] ?? '';
if (empty($adset_id)) {
    header('Location: adsets.php');
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


// Fetch ad set details from the local database
$adset = null;
$error_message = '';
try {
    $stmt = $pdo->prepare("SELECT * FROM facebook_ads_accounts_adsets WHERE id = ?");
    $stmt->execute([$adset_id]);
    $adset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$adset) {
        $error_message = 'Ad Set not found in the database.';
    }
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Set page variables for template
$page_title = "Ad Set Details - JJ Reporting Dashboard";
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
            <h2>Ad Set Details</h2>
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php else: ?>
                <?php 
                $insights = $adset ?? [];
                $spend = $insights['spend'] ?? null;
                $impressions = $insights['impressions'] ?? null;
                $reach = $insights['reach'] ?? null;
                $cpc = $insights['cpc'] ?? null;
                $ctr = $insights['ctr'] ?? null;
                $cpm = $insights['cpm'] ?? null;
                $targeting = $adset['targeting'] ?? [];
                function summarizeTargeting($targeting) {
                    if (empty($targeting) || !is_array($targeting)) return 'N/A';
                    $parts = [];
                    if (isset($targeting['age_min']) || isset($targeting['age_max'])) {
                        $age = '';
                        if (isset($targeting['age_min'])) $age .= $targeting['age_min'];
                        $age .= ' - ';
                        if (isset($targeting['age_max'])) $age .= $targeting['age_max'];
                        $parts[] = 'Age: ' . trim($age, ' -');
                    }
                    if (isset($targeting['genders']) && is_array($targeting['genders'])) {
                        $gender_map = [1 => 'Male', 2 => 'Female'];
                        $genders = array_map(function($g) use ($gender_map) {
                            return $gender_map[$g] ?? $g;
                        }, $targeting['genders']);
                        $parts[] = 'Gender: ' . implode(', ', $genders);
                    }
                    if (isset($targeting['geo_locations']['countries']) && is_array($targeting['geo_locations']['countries'])) {
                        $parts[] = 'Country: ' . implode(', ', $targeting['geo_locations']['countries']);
                    }
                    if (isset($targeting['location_types']) && is_array($targeting['location_types'])) {
                        $parts[] = 'Location Type: ' . implode(', ', $targeting['location_types']);
                    }
                    if (isset($targeting['targeting_automation']['advantage_audience'])) {
                        $parts[] = 'Advantage Audience: ' . ($targeting['targeting_automation']['advantage_audience'] ? 'Yes' : 'No');
                    }
                    if (empty($parts)) return 'N/A';
                    return implode(' | ', $parts);
                }
                ?>
                <table class="details-table">
                    <tr><th>Ad Set Name</th><td><?php echo htmlspecialchars($adset['name'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Status</th><td><?php echo htmlspecialchars($adset['status'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Daily Budget</th><td><?php echo isset($adset['daily_budget']) ? '$' . number_format($adset['daily_budget']/100, 2) : 'N/A'; ?></td></tr>
                    <tr><th>Lifetime Budget</th><td><?php echo isset($adset['lifetime_budget']) ? '$' . number_format($adset['lifetime_budget']/100, 2) : 'N/A'; ?></td></tr>
                    <tr><th>Spend</th><td><?php echo isset($spend) ? '$' . number_format($spend, 2) : 'N/A'; ?></td></tr>
                    <tr><th>Impressions</th><td><?php echo isset($impressions) ? number_format($impressions) : 'N/A'; ?></td></tr>
                    <tr><th>Reach</th><td><?php echo isset($reach) ? number_format($reach) : 'N/A'; ?></td></tr>
                    <tr><th>CPC</th><td><?php echo isset($cpc) ? '$' . number_format($cpc, 2) : 'N/A'; ?></td></tr>
                    <tr><th>CTR</th><td><?php echo isset($ctr) ? number_format($ctr, 2) . '%' : 'N/A'; ?></td></tr>
                    <tr><th>CPM</th><td><?php echo isset($cpm) ? '$' . number_format($cpm, 2) : 'N/A'; ?></td></tr>
                    <tr><th>Start Date</th><td><?php echo isset($adset['start_time']) ? date('M j, Y', strtotime($adset['start_time'])) : 'N/A'; ?></td></tr>
                    <tr><th>End Date</th><td><?php echo isset($adset['end_time']) ? date('M j, Y', strtotime($adset['end_time'])) : 'N/A'; ?></td></tr>
                    <tr><th>Targeting</th><td><?php echo summarizeTargeting($targeting); ?></td></tr>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'templates/footer.php'; ?>
