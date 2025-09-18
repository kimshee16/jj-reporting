<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

require_once 'config.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle filters and custom views
$date_preset = $_GET['date_preset'] ?? 'this_quarter';
$custom_view = $_GET['custom_view'] ?? 'all';
$platform_filter = $_GET['platform'] ?? 'all';
$device_filter = $_GET['device'] ?? 'all';
$country_filter = $_GET['country'] ?? 'all';
$age_filter = $_GET['age'] ?? 'all';
$placement_filter = $_GET['placement'] ?? 'all';
$format_filter = $_GET['format'] ?? 'all';
$objective_filter = $_GET['objective'] ?? 'all';
$ctr_threshold = $_GET['ctr_threshold'] ?? '';
$roas_threshold = $_GET['roas_threshold'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'spend';

// Get all connected accounts
$accounts = [];
try {
    $stmt = $pdo->prepare("SELECT act_id, act_name FROM facebook_ads_accounts ORDER BY act_name");
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching accounts: " . $e->getMessage());
}

// Fetch cross-account data from database
$cross_account_data = [];
$error_message = '';

if (!empty($accounts)) {
    $cross_account_data = fetchCrossAccountDataFromDB($pdo, $date_preset, $custom_view, $platform_filter, $device_filter, $country_filter, $age_filter, $placement_filter, $format_filter, $objective_filter, $ctr_threshold, $roas_threshold, $sort_by);
} else {
    $error_message = 'No ad accounts found. Please connect some ad accounts first.';
}

function fetchCrossAccountDataFromDB($pdo, $date_preset, $custom_view, $platform_filter, $device_filter, $country_filter, $age_filter, $placement_filter, $format_filter, $objective_filter, $ctr_threshold, $roas_threshold, $sort_by) {
    $all_ads = [];
    
    // Build the main query with joins across all tables
    $sql = "
        SELECT 
            a.id as ad_id,
            a.name as ad_name,
            a.status as ad_status,
            a.copy_text,
            a.headline,
            a.cta_button,
            a.format,
            a.creative_type,
            a.impressions,
            a.reach,
            a.clicks,
            a.ctr,
            a.cpc,
            a.spend,
            a.likes,
            a.shares,
            a.comments,
            a.reactions,
            a.cost_per_result,
            a.conversion_value,
            a.roas,
            a.results,
            a.created_time as ad_created_time,
            
            -- Ad Set Information
            adset.id as adset_id,
            adset.name as adset_name,
            adset.status as adset_status,
            adset.daily_budget,
            adset.lifetime_budget,
            adset.age_min,
            adset.age_max,
            adset.gender,
            adset.interests,
            adset.placement as adset_placement,
            adset.device_breakdown,
            
            -- Campaign Information
            camp.id as campaign_id,
            camp.name as campaign_name,
            camp.status as campaign_status,
            camp.objective,
            camp.total_spend as campaign_spend,
            camp.roas as campaign_roas,
            
            -- Account Information
            acc.act_id,
            acc.act_name
        FROM facebook_ads_accounts_ads a
        LEFT JOIN facebook_ads_accounts_adsets adset ON a.adset_id = adset.id
        LEFT JOIN facebook_ads_accounts_campaigns camp ON a.campaign_id = camp.id
        LEFT JOIN facebook_ads_accounts acc ON a.account_id = acc.act_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply custom view filters
    switch ($custom_view) {
        case 'top_performing_quarter':
            $sql .= " AND camp.total_spend > 0 AND camp.roas > 1.0";
            break;
        case 'pakistan_targeting':
            $sql .= " AND (adset.interests LIKE :pakistan OR adset.placement LIKE :pakistan2)";
            $params[':pakistan'] = '%Pakistan%';
            $params[':pakistan2'] = '%Pakistan%';
            break;
        case 'video_high_ctr':
            $sql .= " AND a.format = 'Video' AND a.ctr > 2.0";
            break;
        case 'high_roas':
            $sql .= " AND a.roas > 3.0";
            break;
        case 'low_performing':
            $sql .= " AND a.ctr < 1.0 AND a.spend > 100";
            break;
    }
    
    // Apply platform filter
    if ($platform_filter !== 'all') {
        $sql .= " AND adset.placement LIKE :platform";
        $params[':platform'] = '%' . $platform_filter . '%';
    }
    
    // Apply device filter
    if ($device_filter !== 'all') {
        $sql .= " AND adset.device_breakdown LIKE :device";
        $params[':device'] = '%' . $device_filter . '%';
    }
    
    // Apply age filter
    if ($age_filter !== 'all') {
        switch ($age_filter) {
            case '18-24':
                $sql .= " AND adset.age_min <= 18 AND adset.age_max >= 24";
                break;
            case '25-34':
                $sql .= " AND adset.age_min <= 25 AND adset.age_max >= 34";
                break;
            case '35-44':
                $sql .= " AND adset.age_min <= 35 AND adset.age_max >= 44";
                break;
            case '45-54':
                $sql .= " AND adset.age_min <= 45 AND adset.age_max >= 54";
                break;
            case '55+':
                $sql .= " AND adset.age_min >= 55";
                break;
        }
    }
    
    // Apply placement filter
    if ($placement_filter !== 'all') {
        $sql .= " AND adset.placement LIKE :placement";
        $params[':placement'] = '%' . $placement_filter . '%';
    }
    
    // Apply format filter
    if ($format_filter !== 'all') {
        $sql .= " AND a.format = :format";
        $params[':format'] = $format_filter;
    }
    
    // Apply objective filter
    if ($objective_filter !== 'all') {
        $sql .= " AND camp.objective = :objective";
        $params[':objective'] = $objective_filter;
    }
    
    // Apply CTR threshold
    if (!empty($ctr_threshold)) {
        $sql .= " AND a.ctr >= :ctr_threshold";
        $params[':ctr_threshold'] = floatval($ctr_threshold);
    }
    
    // Apply ROAS threshold
    if (!empty($roas_threshold)) {
        $sql .= " AND a.roas >= :roas_threshold";
        $params[':roas_threshold'] = floatval($roas_threshold);
    }
    
    // Apply date filter based on preset
    $date_condition = getDateCondition($date_preset);
    if ($date_condition) {
        $sql .= " AND " . $date_condition;
    }
    
    // Apply sorting
    switch ($sort_by) {
        case 'spend':
            $sql .= " ORDER BY a.spend DESC";
            break;
        case 'roas':
            $sql .= " ORDER BY a.roas DESC";
            break;
        case 'ctr':
            $sql .= " ORDER BY a.ctr DESC";
            break;
        case 'impressions':
            $sql .= " ORDER BY a.impressions DESC";
            break;
        case 'clicks':
            $sql .= " ORDER BY a.clicks DESC";
            break;
        case 'name':
            $sql .= " ORDER BY a.name ASC";
            break;
        default:
            $sql .= " ORDER BY a.spend DESC";
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $all_ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching cross-account data: " . $e->getMessage());
        return [];
    }
    
    return $all_ads;
}

function getDateCondition($date_preset) {
    $now = new DateTime();
    
    switch ($date_preset) {
        case 'today':
            return "DATE(a.created_time) = CURDATE()";
        case 'yesterday':
            return "DATE(a.created_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        case 'this_week':
            return "a.created_time >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
        case 'last_week':
            return "a.created_time >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) + 7 DAY) AND a.created_time < DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
        case 'this_month':
            return "MONTH(a.created_time) = MONTH(CURDATE()) AND YEAR(a.created_time) = YEAR(CURDATE())";
        case 'last_month':
            return "a.created_time >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY), INTERVAL 1 MONTH) AND a.created_time < DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY)";
        case 'this_quarter':
            $quarter = ceil($now->format('n') / 3);
            $start_month = ($quarter - 1) * 3 + 1;
            return "MONTH(a.created_time) >= $start_month AND MONTH(a.created_time) < " . ($start_month + 3) . " AND YEAR(a.created_time) = " . $now->format('Y');
        case 'last_quarter':
            $quarter = ceil($now->format('n') / 3) - 1;
            if ($quarter == 0) {
                $quarter = 4;
                $year = $now->format('Y') - 1;
            } else {
                $year = $now->format('Y');
            }
            $start_month = ($quarter - 1) * 3 + 1;
            return "MONTH(a.created_time) >= $start_month AND MONTH(a.created_time) < " . ($start_month + 3) . " AND YEAR(a.created_time) = $year";
        case 'this_year':
            return "YEAR(a.created_time) = " . $now->format('Y');
        case 'last_year':
            return "YEAR(a.created_time) = " . ($now->format('Y') - 1);
        default:
            return "a.created_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    }
}

// Get summary statistics
$summary_stats = [];
if (!empty($cross_account_data)) {
    $total_ads = count($cross_account_data);
    $total_spend = array_sum(array_column($cross_account_data, 'spend'));
    $total_impressions = array_sum(array_column($cross_account_data, 'impressions'));
    $total_clicks = array_sum(array_column($cross_account_data, 'clicks'));
    $avg_ctr = $total_impressions > 0 ? ($total_clicks / $total_impressions) * 100 : 0;
    $avg_roas = array_sum(array_column($cross_account_data, 'roas')) / $total_ads;
    
    $summary_stats = [
        'total_ads' => $total_ads,
        'total_spend' => $total_spend,
        'total_impressions' => $total_impressions,
        'total_clicks' => $total_clicks,
        'avg_ctr' => $avg_ctr,
        'avg_roas' => $avg_roas
    ];
}

// Set page title
$page_title = "Cross-Account Reports - JJ Reporting Dashboard";

// Include header
include 'templates/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-title">
            <h1>Cross-Account Reports</h1>
            <p>Compare performance across multiple ad accounts and campaigns</p>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="custom_view">Custom View:</label>
                        <select name="custom_view" id="custom_view">
                            <option value="all" <?php echo $custom_view === 'all' ? 'selected' : ''; ?>>All Data</option>
                            <option value="top_performing_quarter" <?php echo $custom_view === 'top_performing_quarter' ? 'selected' : ''; ?>>Top Performing This Quarter</option>
                            <option value="pakistan_targeting" <?php echo $custom_view === 'pakistan_targeting' ? 'selected' : ''; ?>>Pakistan Targeting</option>
                            <option value="video_high_ctr" <?php echo $custom_view === 'video_high_ctr' ? 'selected' : ''; ?>>Video Ads with CTR > 2%</option>
                            <option value="high_roas" <?php echo $custom_view === 'high_roas' ? 'selected' : ''; ?>>High ROAS (>3.0)</option>
                            <option value="low_performing" <?php echo $custom_view === 'low_performing' ? 'selected' : ''; ?>>Low Performing</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_preset">Date Range:</label>
                        <select name="date_preset" id="date_preset">
                            <option value="today" <?php echo $date_preset === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $date_preset === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="this_week" <?php echo $date_preset === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="last_week" <?php echo $date_preset === 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                            <option value="this_month" <?php echo $date_preset === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="last_month" <?php echo $date_preset === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="this_quarter" <?php echo $date_preset === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                            <option value="last_quarter" <?php echo $date_preset === 'last_quarter' ? 'selected' : ''; ?>>Last Quarter</option>
                            <option value="this_year" <?php echo $date_preset === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                            <option value="last_year" <?php echo $date_preset === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="platform">Platform:</label>
                        <select name="platform" id="platform">
                            <option value="all" <?php echo $platform_filter === 'all' ? 'selected' : ''; ?>>All Platforms</option>
                            <option value="facebook" <?php echo $platform_filter === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                            <option value="instagram" <?php echo $platform_filter === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                            <option value="messenger" <?php echo $platform_filter === 'messenger' ? 'selected' : ''; ?>>Messenger</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="device">Device:</label>
                        <select name="device" id="device">
                            <option value="all" <?php echo $device_filter === 'all' ? 'selected' : ''; ?>>All Devices</option>
                            <option value="mobile" <?php echo $device_filter === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                            <option value="desktop" <?php echo $device_filter === 'desktop' ? 'selected' : ''; ?>>Desktop</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="age">Age Group:</label>
                        <select name="age" id="age">
                            <option value="all" <?php echo $age_filter === 'all' ? 'selected' : ''; ?>>All Ages</option>
                            <option value="18-24" <?php echo $age_filter === '18-24' ? 'selected' : ''; ?>>18-24</option>
                            <option value="25-34" <?php echo $age_filter === '25-34' ? 'selected' : ''; ?>>25-34</option>
                            <option value="35-44" <?php echo $age_filter === '35-44' ? 'selected' : ''; ?>>35-44</option>
                            <option value="45-54" <?php echo $age_filter === '45-54' ? 'selected' : ''; ?>>45-54</option>
                            <option value="55+" <?php echo $age_filter === '55+' ? 'selected' : ''; ?>>55+</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="placement">Placement:</label>
                        <select name="placement" id="placement">
                            <option value="all" <?php echo $placement_filter === 'all' ? 'selected' : ''; ?>>All Placements</option>
                            <option value="feed" <?php echo $placement_filter === 'feed' ? 'selected' : ''; ?>>Feed</option>
                            <option value="story" <?php echo $placement_filter === 'story' ? 'selected' : ''; ?>>Story</option>
                            <option value="reels" <?php echo $placement_filter === 'reels' ? 'selected' : ''; ?>>Reels</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="format">Ad Format:</label>
                        <select name="format" id="format">
                            <option value="all" <?php echo $format_filter === 'all' ? 'selected' : ''; ?>>All Formats</option>
                            <option value="Single Image" <?php echo $format_filter === 'Single Image' ? 'selected' : ''; ?>>Single Image</option>
                            <option value="Video" <?php echo $format_filter === 'Video' ? 'selected' : ''; ?>>Video</option>
                            <option value="Carousel" <?php echo $format_filter === 'Carousel' ? 'selected' : ''; ?>>Carousel</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="objective">Objective:</label>
                        <select name="objective" id="objective">
                            <option value="all" <?php echo $objective_filter === 'all' ? 'selected' : ''; ?>>All Objectives</option>
                            <option value="OUTCOME_LEADS" <?php echo $objective_filter === 'OUTCOME_LEADS' ? 'selected' : ''; ?>>Leads</option>
                            <option value="OUTCOME_SALES" <?php echo $objective_filter === 'OUTCOME_SALES' ? 'selected' : ''; ?>>Sales</option>
                            <option value="OUTCOME_AWARENESS" <?php echo $objective_filter === 'OUTCOME_AWARENESS' ? 'selected' : ''; ?>>Awareness</option>
                            <option value="OUTCOME_TRAFFIC" <?php echo $objective_filter === 'OUTCOME_TRAFFIC' ? 'selected' : ''; ?>>Traffic</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="ctr_threshold">Min CTR (%):</label>
                        <input type="number" name="ctr_threshold" id="ctr_threshold" value="<?php echo htmlspecialchars($ctr_threshold); ?>" step="0.1" min="0">
                    </div>
                    
                    <div class="filter-group">
                        <label for="roas_threshold">Min ROAS:</label>
                        <input type="number" name="roas_threshold" id="roas_threshold" value="<?php echo htmlspecialchars($roas_threshold); ?>" step="0.1" min="0">
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort_by">Sort By:</label>
                        <select name="sort_by" id="sort_by">
                            <option value="spend" <?php echo $sort_by === 'spend' ? 'selected' : ''; ?>>Spend</option>
                            <option value="roas" <?php echo $sort_by === 'roas' ? 'selected' : ''; ?>>ROAS</option>
                            <option value="ctr" <?php echo $sort_by === 'ctr' ? 'selected' : ''; ?>>CTR</option>
                            <option value="impressions" <?php echo $sort_by === 'impressions' ? 'selected' : ''; ?>>Impressions</option>
                            <option value="clicks" <?php echo $sort_by === 'clicks' ? 'selected' : ''; ?>>Clicks</option>
                            <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="cross_account_reports_db.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stats -->
    <?php if (!empty($summary_stats)): ?>
    <div class="summary-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($summary_stats['total_ads']); ?></div>
                <div class="stat-label">Total Ads</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($summary_stats['total_spend'], 2); ?></div>
                <div class="stat-label">Total Spend</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($summary_stats['total_impressions']); ?></div>
                <div class="stat-label">Total Impressions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($summary_stats['avg_ctr'], 2); ?>%</div>
                <div class="stat-label">Avg CTR</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($summary_stats['avg_roas'], 2); ?>x</div>
                <div class="stat-label">Avg ROAS</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Results Section -->
    <div class="results-section">
        <div class="results-card">
            <div class="results-header">
                <h3>Cross-Account Results (<?php echo count($cross_account_data); ?> found)</h3>
                <div class="results-actions">
                    <button class="btn btn-success" onclick="exportResults()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php elseif (empty($cross_account_data)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h4>No Data Found</h4>
                    <p>No ads match your current filter criteria. Try adjusting your filters or sync some data first.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="cross-account-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Campaign</th>
                                <th>Ad Set</th>
                                <th>Ad Name</th>
                                <th>Format</th>
                                <th>Status</th>
                                <th>Targeting</th>
                                <th>Spend</th>
                                <th>Impressions</th>
                                <th>Clicks</th>
                                <th>CTR</th>
                                <th>CPC</th>
                                <th>ROAS</th>
                                <th>Results</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cross_account_data as $ad): ?>
                                <tr>
                                    <td class="account-name"><?php echo htmlspecialchars($ad['act_name']); ?></td>
                                    <td class="campaign-name"><?php echo htmlspecialchars($ad['campaign_name']); ?></td>
                                    <td class="adset-name"><?php echo htmlspecialchars($ad['adset_name']); ?></td>
                                    <td class="ad-name"><?php echo htmlspecialchars($ad['ad_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ad['format'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($ad['ad_status']); ?>">
                                            <?php echo htmlspecialchars($ad['ad_status']); ?>
                                        </span>
                                    </td>
                                    <td class="targeting-info">
                                        <?php 
                                        $targeting = [];
                                        if ($ad['age_min'] && $ad['age_max']) {
                                            $targeting[] = "Age: {$ad['age_min']}-{$ad['age_max']}";
                                        }
                                        if ($ad['gender']) {
                                            $targeting[] = "Gender: {$ad['gender']}";
                                        }
                                        echo implode(' | ', $targeting);
                                        ?>
                                    </td>
                                    <td class="spend-cell">$<?php echo number_format($ad['spend'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($ad['impressions'] ?? 0); ?></td>
                                    <td><?php echo number_format($ad['clicks'] ?? 0); ?></td>
                                    <td class="ctr-cell"><?php echo number_format($ad['ctr'] ?? 0, 2); ?>%</td>
                                    <td class="cpc-cell">$<?php echo number_format($ad['cpc'] ?? 0, 2); ?></td>
                                    <td class="roas-cell"><?php echo number_format($ad['roas'] ?? 0, 2); ?>x</td>
                                    <td><?php echo number_format($ad['results'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
$inline_scripts = '
    function exportResults() {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set("export", "csv");
            window.location.href = "cross_account_export.php?" + urlParams.toString();
        } catch (error) {
            console.error("Error exporting results:", error);
            alert("Error exporting results. Please try again.");
        }
    }
';
include 'templates/footer.php'; 
?>
