<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'report-database';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get admin's access token
$admin_id = $_SESSION['admin_id'];
$access_token = '';

try {
    $stmt = $pdo->prepare("SELECT access_token FROM facebook_access_tokens WHERE admin_id = ?");
    $stmt->execute([$admin_id]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($token_data) {
        $access_token = $token_data['access_token'];
    }
} catch(PDOException $e) {
    // Handle error silently
}

// Handle save report action
if ($_POST['action'] ?? '' === 'save_report') {
    $report_name = $_POST['report_name'] ?? '';
    $report_description = $_POST['report_description'] ?? '';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    if (!empty($report_name)) {
        $filters = [
            'date_preset' => $_POST['date_preset'] ?? 'this_quarter',
            'platform' => $_POST['platform'] ?? 'all',
            'device' => $_POST['device'] ?? 'all',
            'country' => $_POST['country'] ?? 'all',
            'age' => $_POST['age'] ?? 'all',
            'placement' => $_POST['placement'] ?? 'all',
            'format' => $_POST['format'] ?? 'all',
            'objective' => $_POST['objective'] ?? 'all',
            'ctr_threshold' => $_POST['ctr_threshold'] ?? '',
            'roas_threshold' => $_POST['roas_threshold'] ?? '',
            'sort_by' => $_POST['sort_by'] ?? 'spend'
        ];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO saved_reports (name, description, filters_json, custom_view, created_by, is_public) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $report_name,
                $report_description,
                json_encode($filters),
                $_POST['custom_view'] ?? 'all',
                $admin_id,
                $is_public
            ]);
            $success_message = "Report saved successfully!";
        } catch(PDOException $e) {
            $error_message = "Error saving report: " . $e->getMessage();
        }
    } else {
        $error_message = "Report name is required.";
    }
}

// Handle load report
$load_report_id = $_GET['load_report'] ?? '';
if (!empty($load_report_id)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM saved_reports WHERE id = ? AND (created_by = ? OR is_public = 1 OR id IN (SELECT report_id FROM report_sharing WHERE shared_with_admin_id = ?))");
        $stmt->execute([$load_report_id, $admin_id, $admin_id]);
        $saved_report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($saved_report) {
            $filters = json_decode($saved_report['filters_json'], true);
            $date_preset = $filters['date_preset'] ?? 'this_quarter';
            $custom_view = $saved_report['custom_view'] ?? 'all';
            $platform_filter = $filters['platform'] ?? 'all';
            $device_filter = $filters['device'] ?? 'all';
            $country_filter = $filters['country'] ?? 'all';
            $age_filter = $filters['age'] ?? 'all';
            $placement_filter = $filters['placement'] ?? 'all';
            $format_filter = $filters['format'] ?? 'all';
            $objective_filter = $filters['objective'] ?? 'all';
            $ctr_threshold = $filters['ctr_threshold'] ?? '';
            $roas_threshold = $filters['roas_threshold'] ?? '';
            $sort_by = $filters['sort_by'] ?? 'spend';
            $loaded_report_name = $saved_report['name'];
        }
    } catch(PDOException $e) {
        $error_message = "Error loading report: " . $e->getMessage();
    }
}

// Handle filters and custom views (only if not loading a report)
if (empty($load_report_id)) {
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
}

// Get all connected accounts
$accounts = [];
try {
    $stmt = $pdo->prepare("SELECT act_id, act_name FROM facebook_ads_accounts ORDER BY act_name");
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching accounts: " . $e->getMessage());
}

// Fetch cross-account data
$cross_account_data = [];
$error_message = '';

if (!empty($access_token) && !empty($accounts)) {
    $cross_account_data = fetchCrossAccountData($accounts, $access_token, $date_preset, $custom_view, $platform_filter, $device_filter, $country_filter, $age_filter, $placement_filter, $format_filter, $objective_filter, $ctr_threshold, $roas_threshold, $sort_by);
} else {
    if (empty($access_token)) {
        $error_message = 'No access token found. Please update your access token in the Access Tokens page.';
    } elseif (empty($accounts)) {
        $error_message = 'No ad accounts found. Please connect some ad accounts first.';
    }
}

function fetchCrossAccountData($accounts, $access_token, $date_preset, $custom_view, $platform_filter, $device_filter, $country_filter, $age_filter, $placement_filter, $format_filter, $objective_filter, $ctr_threshold, $roas_threshold, $sort_by) {
    $all_ads = [];
    
    foreach ($accounts as $account) {
        $account_id = $account['act_id'];
        $account_name = $account['act_name'];
        
        // Fetch campaigns first
        $campaigns = fetchCampaigns($account_id, $access_token, $date_preset);
        
        // For each campaign, fetch ads with detailed targeting info
        foreach ($campaigns as $campaign) {
            $ads = fetchAdsForCampaign($account_id, $campaign['id'], $access_token, $date_preset);
            
            foreach ($ads as $ad) {
                // Add account and campaign information to each ad
                $ad['account_id'] = $account_id;
                $ad['account_name'] = $account_name;
                $ad['campaign_id'] = $campaign['id'];
                $ad['campaign_name'] = $campaign['name'];
                $ad['campaign_objective'] = $campaign['objective'];
                $ad['campaign_status'] = $campaign['status'];
                $all_ads[] = $ad;
            }
        }
    }
    
    // Apply custom view filters
    $all_ads = applyCustomViewFilters($all_ads, $custom_view);
    
    // Apply advanced filters
    $all_ads = applyAdvancedFilters($all_ads, $platform_filter, $device_filter, $country_filter, $age_filter, $placement_filter, $format_filter, $objective_filter, $ctr_threshold, $roas_threshold);
    
    // Apply sorting
    $all_ads = applyCrossAccountSorting($all_ads, $sort_by);
    
    return $all_ads;
}

function fetchCampaigns($account_id, $access_token, $date_preset) {
    $api_url = "https://graph.facebook.com/v21.0/{$account_id}/campaigns";
    $fields = "id,name,objective,status";
    $params = [
        'fields' => $fields,
        'date_preset' => $date_preset,
        'access_token' => $access_token
    ];
    
    $api_url .= '?' . http_build_query($params);
    
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
        return isset($data['data']) ? $data['data'] : [];
    }
    
    return [];
}

function fetchAdsForCampaign($account_id, $campaign_id, $access_token, $date_preset) {
    $api_url = "https://graph.facebook.com/v21.0/{$campaign_id}/ads";
    $fields = "id,name,status,creative{title,body,image_url,video_id,object_type},targeting{geo_locations,age_min,age_max,device_platforms,placements},insights{spend,actions,purchase_roas,ctr,cpc,cpm,impressions,clicks}";
    $params = [
        'fields' => $fields,
        'date_preset' => $date_preset,
        'access_token' => $access_token
    ];
    
    $api_url .= '?' . http_build_query($params);
    
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
        return isset($data['data']) ? $data['data'] : [];
    }
    
    return [];
}

function applyCustomViewFilters($ads, $custom_view) {
    if ($custom_view === 'all') {
        return $ads;
    }
    
    return array_filter($ads, function($ad) use ($custom_view) {
        $insights = $ad['insights']['data'][0] ?? [];
        $spend = floatval($insights['spend'] ?? 0);
        $roas = floatval($insights['purchase_roas'] ?? 0);
        $ctr = floatval($insights['ctr'] ?? 0);
        $creative = $ad['creative'] ?? [];
        $object_type = $creative['object_type'] ?? '';
        $targeting = $ad['targeting'] ?? [];
        $geo_locations = $targeting['geo_locations'] ?? [];
        $countries = $geo_locations['countries'] ?? [];
        
        switch ($custom_view) {
            case 'top_performing_quarter':
                // Top performing campaigns this quarter (ROAS > 2x and spend > $100)
                return $roas > 2.0 && $spend > 100;
                
            case 'pakistan_roas':
                // All ads targeting Pakistan, sorted by ROAS
                return in_array('PK', $countries) && $roas > 0;
                
            case 'video_ctr_2':
                // Video ads with CTR > 2%
                return $object_type === 'VIDEO' && $ctr > 2.0;
                
            case 'high_spend_low_roas':
                // High spend, low ROAS campaigns (need optimization)
                return $spend > 500 && $roas < 1.5;
                
            case 'mobile_optimized':
                // Mobile-optimized ads with good performance
                $device_platforms = $targeting['device_platforms'] ?? [];
                return in_array('mobile', $device_platforms) && $ctr > 1.5;
                
            default:
                return true;
        }
    });
}

function applyAdvancedFilters($ads, $platform_filter, $device_filter, $country_filter, $age_filter, $placement_filter, $format_filter, $objective_filter, $ctr_threshold, $roas_threshold) {
    return array_filter($ads, function($ad) use ($platform_filter, $device_filter, $country_filter, $age_filter, $placement_filter, $format_filter, $objective_filter, $ctr_threshold, $roas_threshold) {
        $insights = $ad['insights']['data'][0] ?? [];
        $ctr = floatval($insights['ctr'] ?? 0);
        $roas = floatval($insights['purchase_roas'] ?? 0);
        $creative = $ad['creative'] ?? [];
        $targeting = $ad['targeting'] ?? [];
        
        // Platform filter (Facebook/Instagram)
        if ($platform_filter !== 'all') {
            $placements = $targeting['placements'] ?? [];
            if ($platform_filter === 'facebook' && !in_array('facebook', $placements)) {
                return false;
            }
            if ($platform_filter === 'instagram' && !in_array('instagram', $placements)) {
                return false;
            }
        }
        
        // Device type filter
        if ($device_filter !== 'all') {
            $device_platforms = $targeting['device_platforms'] ?? [];
            if (!in_array($device_filter, $device_platforms)) {
                return false;
            }
        }
        
        // Country filter
        if ($country_filter !== 'all') {
            $geo_locations = $targeting['geo_locations'] ?? [];
            $countries = $geo_locations['countries'] ?? [];
            if (!in_array($country_filter, $countries)) {
                return false;
            }
        }
        
        // Age group filter
        if ($age_filter !== 'all') {
            $age_min = $targeting['age_min'] ?? 0;
            $age_max = $targeting['age_max'] ?? 100;
            
            switch ($age_filter) {
                case '18-24':
                    if ($age_min > 24 || $age_max < 18) return false;
                    break;
                case '25-34':
                    if ($age_min > 34 || $age_max < 25) return false;
                    break;
                case '35-44':
                    if ($age_min > 44 || $age_max < 35) return false;
                    break;
                case '45-54':
                    if ($age_min > 54 || $age_max < 45) return false;
                    break;
                case '55+':
                    if ($age_max < 55) return false;
                    break;
            }
        }
        
        // Placement filter
        if ($placement_filter !== 'all') {
            $placements = $targeting['placements'] ?? [];
            if (!in_array($placement_filter, $placements)) {
                return false;
            }
        }
        
        // Ad format filter
        if ($format_filter !== 'all') {
            $object_type = $creative['object_type'] ?? '';
            if ($object_type !== $format_filter) {
                return false;
            }
        }
        
        // Objective filter
        if ($objective_filter !== 'all' && $ad['campaign_objective'] !== $objective_filter) {
            return false;
        }
        
        // CTR threshold filter
        if (!empty($ctr_threshold) && $ctr < floatval($ctr_threshold)) {
            return false;
        }
        
        // ROAS threshold filter
        if (!empty($roas_threshold) && $roas < floatval($roas_threshold)) {
            return false;
        }
        
        return true;
    });
}

function applyCrossAccountSorting($ads, $sort_by) {
    usort($ads, function($a, $b) use ($sort_by) {
        $a_value = 0;
        $b_value = 0;
        
        switch ($sort_by) {
            case 'spend':
                $a_value = isset($a['insights']['data'][0]['spend']) ? floatval($a['insights']['data'][0]['spend']) : 0;
                $b_value = isset($b['insights']['data'][0]['spend']) ? floatval($b['insights']['data'][0]['spend']) : 0;
                break;
            case 'roas':
                $a_value = isset($a['insights']['data'][0]['purchase_roas']) ? floatval($a['insights']['data'][0]['purchase_roas']) : 0;
                $b_value = isset($b['insights']['data'][0]['purchase_roas']) ? floatval($b['insights']['data'][0]['purchase_roas']) : 0;
                break;
            case 'ctr':
                $a_value = isset($a['insights']['data'][0]['ctr']) ? floatval($a['insights']['data'][0]['ctr']) : 0;
                $b_value = isset($b['insights']['data'][0]['ctr']) ? floatval($b['insights']['data'][0]['ctr']) : 0;
                break;
            case 'cpc':
                $a_value = isset($a['insights']['data'][0]['cpc']) ? floatval($a['insights']['data'][0]['cpc']) : 0;
                $b_value = isset($b['insights']['data'][0]['cpc']) ? floatval($b['insights']['data'][0]['cpc']) : 0;
                break;
            case 'account':
                $a_value = strtolower($a['account_name'] ?? '');
                $b_value = strtolower($b['account_name'] ?? '');
                return strcmp($a_value, $b_value);
            case 'campaign':
                $a_value = strtolower($a['campaign_name'] ?? '');
                $b_value = strtolower($b['campaign_name'] ?? '');
                return strcmp($a_value, $b_value);
            case 'name':
                $a_value = strtolower($a['name'] ?? '');
                $b_value = strtolower($b['name'] ?? '');
                return strcmp($a_value, $b_value);
        }
        
        return $b_value <=> $a_value; // Descending order
    });
    
    return $ads;
}

function formatCurrency($amount) {
    if ($amount === null || $amount === '') {
        return 'N/A';
    }
    return '$' . number_format(floatval($amount), 2);
}

function formatPercentage($value) {
    if ($value === null || $value === '') {
        return 'N/A';
    }
    return number_format(floatval($value), 2) . '%';
}

function formatDate($date_string) {
    if (empty($date_string) || $date_string === null) return 'N/A';
    
    // Handle Unix timestamp 0 (1970-01-01)
    if ($date_string === '1970-01-01T00:00:00+0000' || $date_string === '1970-01-01T07:59:59+0800') {
        return 'N/A';
    }
    
    try {
        $timestamp = strtotime($date_string);
        if ($timestamp === false || $timestamp <= 0) {
            return 'N/A';
        }
        return date('M j, Y', $timestamp);
    } catch (Exception $e) {
        return 'N/A';
    }
}

function getStatusClass($status) {
    if (empty($status) || $status === null) {
        return 'status-unknown';
    }
    
    switch (strtolower($status)) {
        case 'active':
            return 'status-active';
        case 'paused':
            return 'status-paused';
        case 'completed':
            return 'status-completed';
        default:
            return 'status-unknown';
    }
}

// Calculate summary statistics
$total_spend = 0;
$total_ads = count($cross_account_data);
$active_ads = 0;
$avg_roas = 0;
$avg_ctr = 0;
$roas_values = [];
$ctr_values = [];
$unique_campaigns = [];
$unique_accounts = [];

foreach ($cross_account_data as $ad) {
    $insights = $ad['insights']['data'][0] ?? [];
    $spend = floatval($insights['spend'] ?? 0);
    $roas = floatval($insights['purchase_roas'] ?? 0);
    $ctr = floatval($insights['ctr'] ?? 0);
    
    $total_spend += $spend;
    
    if (strtolower($ad['status'] ?? '') === 'active') {
        $active_ads++;
    }
    
    if ($roas > 0) {
        $roas_values[] = $roas;
    }
    
    if ($ctr > 0) {
        $ctr_values[] = $ctr;
    }
    
    // Track unique campaigns and accounts
    if (!in_array($ad['campaign_id'], $unique_campaigns)) {
        $unique_campaigns[] = $ad['campaign_id'];
    }
    if (!in_array($ad['account_id'], $unique_accounts)) {
        $unique_accounts[] = $ad['account_id'];
    }
}

if (!empty($roas_values)) {
    $avg_roas = array_sum($roas_values) / count($roas_values);
}

if (!empty($ctr_values)) {
    $avg_ctr = array_sum($ctr_values) / count($ctr_values);
}

// Set page variables for template
$page_title = "Cross-Account Reports - JJ Reporting Dashboard";

// Include header template
include 'templates/header.php';
?>

    <!-- Include sidebar template -->
    <?php include 'templates/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <?php 
        $header_actions = '
            <div class="header-actions">
                <button class="btn btn-primary" onclick="saveReport()">
                    <i class="fas fa-save"></i>
                    Save Report
                </button>
                <button class="btn btn-success" onclick="exportCrossAccountData()">
                    <i class="fas fa-download"></i>
                    Export Report
                </button>
                <button class="btn btn-secondary" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i>
                    Refresh Data
                </button>
            </div>';
        include 'templates/top_header.php'; 
        ?>

        <!-- Cross-Account Reports Content -->
        <div class="dashboard-content">
            <div class="cross-account-container">
                
                <?php if (!empty($success_message)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($loaded_report_name)): ?>
                    <div class="loaded-report-indicator">
                        <i class="fas fa-file-alt"></i>
                        <span>Loaded Report: <strong><?php echo htmlspecialchars($loaded_report_name); ?></strong></span>
                        <a href="cross_account_reports.php" class="btn btn-sm btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                <?php endif; ?>
                <!-- Summary Stats -->
                <div class="summary-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Total Accounts</h3>
                            <p class="stat-value"><?php echo count($unique_accounts); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Total Campaigns</h3>
                            <p class="stat-value"><?php echo count($unique_campaigns); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-ad"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Total Ads</h3>
                            <p class="stat-value"><?php echo $total_ads; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Active Ads</h3>
                            <p class="stat-value"><?php echo $active_ads; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Total Spend</h3>
                            <p class="stat-value"><?php echo formatCurrency($total_spend); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Avg. ROAS</h3>
                            <p class="stat-value"><?php echo $avg_roas > 0 ? number_format($avg_roas, 2) . 'x' : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-mouse-pointer"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Avg. CTR</h3>
                            <p class="stat-value"><?php echo $avg_ctr > 0 ? number_format($avg_ctr, 2) . '%' : 'N/A'; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Custom Views Section -->
                <div class="custom-views-section">
                    <div class="views-card">
                        <h3>Custom Views</h3>
                        <div class="views-grid">
                            <a href="?custom_view=all" class="view-card <?php echo $custom_view === 'all' ? 'active' : ''; ?>">
                                <i class="fas fa-list"></i>
                                <span>All Ads</span>
                            </a>
                            <a href="?custom_view=top_performing_quarter" class="view-card <?php echo $custom_view === 'top_performing_quarter' ? 'active' : ''; ?>">
                                <i class="fas fa-trophy"></i>
                                <span>Top Performing This Quarter</span>
                            </a>
                            <a href="?custom_view=pakistan_roas" class="view-card <?php echo $custom_view === 'pakistan_roas' ? 'active' : ''; ?>">
                                <i class="fas fa-flag"></i>
                                <span>Pakistan Ads by ROAS</span>
                            </a>
                            <a href="?custom_view=video_ctr_2" class="view-card <?php echo $custom_view === 'video_ctr_2' ? 'active' : ''; ?>">
                                <i class="fas fa-video"></i>
                                <span>Video Ads CTR > 2%</span>
                            </a>
                            <a href="?custom_view=high_spend_low_roas" class="view-card <?php echo $custom_view === 'high_spend_low_roas' ? 'active' : ''; ?>">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>High Spend, Low ROAS</span>
                            </a>
                            <a href="?custom_view=mobile_optimized" class="view-card <?php echo $custom_view === 'mobile_optimized' ? 'active' : ''; ?>">
                                <i class="fas fa-mobile-alt"></i>
                                <span>Mobile Optimized</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Advanced Filters Section -->
                <div class="filters-section">
                    <div class="filters-card">
                        <h3>Advanced Filters</h3>
                        <form method="GET" class="filters-form">
                            <input type="hidden" name="custom_view" value="<?php echo htmlspecialchars($custom_view); ?>">
                            
                            <div class="filter-grid">
                                <div class="filter-group">
                                    <label for="date_preset">Date Range</label>
                                    <select name="date_preset" id="date_preset">
                                        <option value="today" <?php echo $date_preset === 'today' ? 'selected' : ''; ?>>Today</option>
                                        <option value="yesterday" <?php echo $date_preset === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                        <option value="this_week" <?php echo $date_preset === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                        <option value="last_week" <?php echo $date_preset === 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                                        <option value="this_month" <?php echo $date_preset === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                        <option value="last_month" <?php echo $date_preset === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                        <option value="this_quarter" <?php echo $date_preset === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                                        <option value="last_quarter" <?php echo $date_preset === 'last_quarter' ? 'selected' : ''; ?>>Last Quarter</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="platform">Platform</label>
                                    <select name="platform" id="platform">
                                        <option value="all" <?php echo $platform_filter === 'all' ? 'selected' : ''; ?>>All Platforms</option>
                                        <option value="facebook" <?php echo $platform_filter === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                                        <option value="instagram" <?php echo $platform_filter === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="device">Device Type</label>
                                    <select name="device" id="device">
                                        <option value="all" <?php echo $device_filter === 'all' ? 'selected' : ''; ?>>All Devices</option>
                                        <option value="mobile" <?php echo $device_filter === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                                        <option value="desktop" <?php echo $device_filter === 'desktop' ? 'selected' : ''; ?>>Desktop</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="country">Country</label>
                                    <select name="country" id="country">
                                        <option value="all" <?php echo $country_filter === 'all' ? 'selected' : ''; ?>>All Countries</option>
                                        <option value="PK" <?php echo $country_filter === 'PK' ? 'selected' : ''; ?>>Pakistan</option>
                                        <option value="US" <?php echo $country_filter === 'US' ? 'selected' : ''; ?>>United States</option>
                                        <option value="GB" <?php echo $country_filter === 'GB' ? 'selected' : ''; ?>>United Kingdom</option>
                                        <option value="CA" <?php echo $country_filter === 'CA' ? 'selected' : ''; ?>>Canada</option>
                                        <option value="AU" <?php echo $country_filter === 'AU' ? 'selected' : ''; ?>>Australia</option>
                                        <option value="DE" <?php echo $country_filter === 'DE' ? 'selected' : ''; ?>>Germany</option>
                                        <option value="FR" <?php echo $country_filter === 'FR' ? 'selected' : ''; ?>>France</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="age">Age Group</label>
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
                                    <label for="placement">Placement</label>
                                    <select name="placement" id="placement">
                                        <option value="all" <?php echo $placement_filter === 'all' ? 'selected' : ''; ?>>All Placements</option>
                                        <option value="facebook_feed" <?php echo $placement_filter === 'facebook_feed' ? 'selected' : ''; ?>>Facebook Feed</option>
                                        <option value="instagram_feed" <?php echo $placement_filter === 'instagram_feed' ? 'selected' : ''; ?>>Instagram Feed</option>
                                        <option value="facebook_stories" <?php echo $placement_filter === 'facebook_stories' ? 'selected' : ''; ?>>Facebook Stories</option>
                                        <option value="instagram_stories" <?php echo $placement_filter === 'instagram_stories' ? 'selected' : ''; ?>>Instagram Stories</option>
                                        <option value="messenger" <?php echo $placement_filter === 'messenger' ? 'selected' : ''; ?>>Messenger</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="format">Ad Format</label>
                                    <select name="format" id="format">
                                        <option value="all" <?php echo $format_filter === 'all' ? 'selected' : ''; ?>>All Formats</option>
                                        <option value="IMAGE" <?php echo $format_filter === 'IMAGE' ? 'selected' : ''; ?>>Image</option>
                                        <option value="VIDEO" <?php echo $format_filter === 'VIDEO' ? 'selected' : ''; ?>>Video</option>
                                        <option value="CAROUSEL" <?php echo $format_filter === 'CAROUSEL' ? 'selected' : ''; ?>>Carousel</option>
                                        <option value="SLIDESHOW" <?php echo $format_filter === 'SLIDESHOW' ? 'selected' : ''; ?>>Slideshow</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="objective">Objective</label>
                                    <select name="objective" id="objective">
                                        <option value="all" <?php echo $objective_filter === 'all' ? 'selected' : ''; ?>>All Objectives</option>
                                        <option value="CONVERSIONS" <?php echo $objective_filter === 'CONVERSIONS' ? 'selected' : ''; ?>>Conversions</option>
                                        <option value="TRAFFIC" <?php echo $objective_filter === 'TRAFFIC' ? 'selected' : ''; ?>>Traffic</option>
                                        <option value="AWARENESS" <?php echo $objective_filter === 'AWARENESS' ? 'selected' : ''; ?>>Awareness</option>
                                        <option value="REACH" <?php echo $objective_filter === 'REACH' ? 'selected' : ''; ?>>Reach</option>
                                        <option value="LEAD_GENERATION" <?php echo $objective_filter === 'LEAD_GENERATION' ? 'selected' : ''; ?>>Lead Generation</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="ctr_threshold">Min CTR (%)</label>
                                    <input type="number" name="ctr_threshold" id="ctr_threshold" value="<?php echo htmlspecialchars($ctr_threshold); ?>" placeholder="e.g., 2.0" step="0.1" min="0">
                                </div>
                                
                                <div class="filter-group">
                                    <label for="roas_threshold">Min ROAS</label>
                                    <input type="number" name="roas_threshold" id="roas_threshold" value="<?php echo htmlspecialchars($roas_threshold); ?>" placeholder="e.g., 2.0" step="0.1" min="0">
                                </div>
                                
                                <div class="filter-group">
                                    <label for="sort_by">Sort By</label>
                                    <select name="sort_by" id="sort_by">
                                        <option value="spend" <?php echo $sort_by === 'spend' ? 'selected' : ''; ?>>Spend</option>
                                        <option value="roas" <?php echo $sort_by === 'roas' ? 'selected' : ''; ?>>ROAS</option>
                                        <option value="ctr" <?php echo $sort_by === 'ctr' ? 'selected' : ''; ?>>CTR</option>
                                        <option value="cpc" <?php echo $sort_by === 'cpc' ? 'selected' : ''; ?>>CPC</option>
                                        <option value="account" <?php echo $sort_by === 'account' ? 'selected' : ''; ?>>Account</option>
                                        <option value="campaign" <?php echo $sort_by === 'campaign' ? 'selected' : ''; ?>>Campaign</option>
                                        <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Ad Name</option>
                                    </select>
                                </div>
                                
                                <div class="filter-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <a href="cross_account_reports.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear All
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Section -->
                <div class="results-section">
                    <div class="results-header">
                        <h2>Cross-Account Ads (<?php echo count($cross_account_data); ?> found)</h2>
                    </div>

                    <?php if (!empty($error_message)): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php elseif (empty($cross_account_data)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-line"></i>
                            <h3>No Ads Found</h3>
                            <p>No ads match your current filters or there are no ads across your connected accounts.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="cross-account-table">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th>Campaign</th>
                                        <th>Ad Name</th>
                                        <th>Format</th>
                                        <th>Platform</th>
                                        <th>Targeting</th>
                                        <th>Status</th>
                                        <th>Spend</th>
                                        <th>ROAS</th>
                                        <th>CTR</th>
                                        <th>CPC</th>
                                        <th>Impressions</th>
                                        <th>Clicks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cross_account_data as $ad): ?>
                                        <?php 
                                        $insights = $ad['insights']['data'][0] ?? [];
                                        $spend = $insights['spend'] ?? 0;
                                        $roas = $insights['purchase_roas'] ?? 0;
                                        $ctr = $insights['ctr'] ?? 0;
                                        $cpc = $insights['cpc'] ?? 0;
                                        $cpm = $insights['cpm'] ?? 0;
                                        $impressions = $insights['impressions'] ?? 0;
                                        $clicks = $insights['clicks'] ?? 0;
                                        
                                        $creative = $ad['creative'] ?? [];
                                        $targeting = $ad['targeting'] ?? [];
                                        
                                        // Safe access to ad data
                                        $ad_name = $ad['name'] ?? 'Unknown Ad';
                                        $ad_status = $ad['status'] ?? 'Unknown';
                                        $ad_id = $ad['id'] ?? '';
                                        $account_name = $ad['account_name'] ?? 'Unknown Account';
                                        $account_id = $ad['account_id'] ?? '';
                                        $campaign_name = $ad['campaign_name'] ?? 'Unknown Campaign';
                                        $campaign_id = $ad['campaign_id'] ?? '';
                                        $campaign_objective = $ad['campaign_objective'] ?? 'Unknown';
                                        
                                        // Extract targeting info
                                        $object_type = $creative['object_type'] ?? 'Unknown';
                                        $placements = $targeting['placements'] ?? [];
                                        $device_platforms = $targeting['device_platforms'] ?? [];
                                        $geo_locations = $targeting['geo_locations'] ?? [];
                                        $countries = $geo_locations['countries'] ?? [];
                                        $age_min = $targeting['age_min'] ?? 0;
                                        $age_max = $targeting['age_max'] ?? 0;
                                        
                                        // Determine platform
                                        $platform = 'Unknown';
                                        if (in_array('facebook', $placements)) $platform = 'Facebook';
                                        if (in_array('instagram', $placements)) $platform = 'Instagram';
                                        if (in_array('facebook', $placements) && in_array('instagram', $placements)) $platform = 'Both';
                                        
                                        // Create targeting summary
                                        $targeting_summary = [];
                                        if (!empty($countries)) {
                                            $targeting_summary[] = implode(', ', array_slice($countries, 0, 2));
                                            if (count($countries) > 2) $targeting_summary[] = '+';
                                        }
                                        if ($age_min > 0 || $age_max > 0) {
                                            $targeting_summary[] = $age_min . '-' . $age_max;
                                        }
                                        if (!empty($device_platforms)) {
                                            $targeting_summary[] = implode(', ', $device_platforms);
                                        }
                                        $targeting_text = !empty($targeting_summary) ? implode(' | ', $targeting_summary) : 'N/A';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="account-name">
                                                    <i class="fas fa-building"></i>
                                                    <?php echo htmlspecialchars($account_name); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="campaign-name">
                                                    <div class="campaign-title"><?php echo htmlspecialchars($campaign_name); ?></div>
                                                    <div class="campaign-objective">
                                                        <span class="objective-badge objective-<?php echo strtolower($campaign_objective); ?>">
                                                            <?php echo htmlspecialchars($campaign_objective); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="ad-name">
                                                    <?php echo htmlspecialchars($ad_name); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="format-badge format-<?php echo strtolower($object_type); ?>">
                                                    <?php echo htmlspecialchars($object_type); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="platform-badge">
                                                    <?php echo htmlspecialchars($platform); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="targeting-info" title="<?php echo htmlspecialchars($targeting_text); ?>">
                                                    <?php echo htmlspecialchars($targeting_text); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo getStatusClass($ad_status); ?>">
                                                    <?php echo htmlspecialchars($ad_status); ?>
                                                </span>
                                            </td>
                                            <td class="spend-cell">
                                                <?php echo formatCurrency($spend); ?>
                                            </td>
                                            <td class="roas-cell">
                                                <?php echo $roas > 0 ? number_format($roas, 2) . 'x' : 'N/A'; ?>
                                            </td>
                                            <td class="ctr-cell">
                                                <?php echo formatPercentage($ctr); ?>
                                            </td>
                                            <td class="cpc-cell">
                                                <?php echo $cpc > 0 ? formatCurrency($cpc) : 'N/A'; ?>
                                            </td>
                                            <td class="impressions-cell">
                                                <?php echo number_format($impressions); ?>
                                            </td>
                                            <td class="clicks-cell">
                                                <?php echo number_format($clicks); ?>
                                            </td>
                                            <td>
                                                <div class="table-actions">
                                                    <button class="action-btn" onclick="viewAdDetails('<?php echo htmlspecialchars($ad_id); ?>')" title="View Ad Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="action-btn" onclick="viewCampaignDetails('<?php echo htmlspecialchars($campaign_id); ?>')" title="View Campaign">
                                                        <i class="fas fa-bullhorn"></i>
                                                    </button>
                                                    <button class="action-btn" onclick="addToReport('<?php echo htmlspecialchars($ad_id); ?>', '<?php echo htmlspecialchars($account_id); ?>')" title="Add to Report">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Save Report Modal -->
    <div id="saveReportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Save Report</h3>
                <button class="modal-close" onclick="closeSaveModal()">&times;</button>
            </div>
            <form method="POST" id="saveReportForm">
                <input type="hidden" name="action" value="save_report">
                <input type="hidden" name="custom_view" value="<?php echo htmlspecialchars($custom_view); ?>">
                <input type="hidden" name="date_preset" value="<?php echo htmlspecialchars($date_preset); ?>">
                <input type="hidden" name="platform" value="<?php echo htmlspecialchars($platform_filter); ?>">
                <input type="hidden" name="device" value="<?php echo htmlspecialchars($device_filter); ?>">
                <input type="hidden" name="country" value="<?php echo htmlspecialchars($country_filter); ?>">
                <input type="hidden" name="age" value="<?php echo htmlspecialchars($age_filter); ?>">
                <input type="hidden" name="placement" value="<?php echo htmlspecialchars($placement_filter); ?>">
                <input type="hidden" name="format" value="<?php echo htmlspecialchars($format_filter); ?>">
                <input type="hidden" name="objective" value="<?php echo htmlspecialchars($objective_filter); ?>">
                <input type="hidden" name="ctr_threshold" value="<?php echo htmlspecialchars($ctr_threshold); ?>">
                <input type="hidden" name="roas_threshold" value="<?php echo htmlspecialchars($roas_threshold); ?>">
                <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="report_name">Report Name *</label>
                        <input type="text" name="report_name" id="report_name" required placeholder="Enter report name...">
                    </div>
                    
                    <div class="form-group">
                        <label for="report_description">Description</label>
                        <textarea name="report_description" id="report_description" rows="3" placeholder="Optional description..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_public" value="1">
                            <span class="checkmark"></span>
                            Make this report public (share with other admins)
                        </label>
                    </div>
                    
                    <div class="current-filters">
                        <h4>Current Filters:</h4>
                        <div class="filters-summary" id="filtersSummary">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeSaveModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php 
    $inline_scripts = '
        function exportCrossAccountData() {
            // Open export modal
            openExportModal();
        }
        
        function refreshData() {
            try {
                window.location.reload();
            } catch (error) {
                console.error("Error refreshing data:", error);
                alert("Error refreshing data. Please try again.");
            }
        }
        
        function saveReport() {
            try {
                // Update filters summary
                updateFiltersSummary();
                
                // Show modal
                document.getElementById("saveReportModal").style.display = "block";
            } catch (error) {
                console.error("Error opening save modal:", error);
                alert("Error opening save modal. Please try again.");
            }
        }
        
        function closeSaveModal() {
            try {
                document.getElementById("saveReportModal").style.display = "none";
            } catch (error) {
                console.error("Error closing modal:", error);
            }
        }
        
        function updateFiltersSummary() {
            try {
                const form = document.querySelector(".filters-form");
                const summary = document.getElementById("filtersSummary");
                
                const filters = [];
                
                // Get current filter values
                const datePreset = form.querySelector("#date_preset").value;
                const platform = form.querySelector("#platform").value;
                const device = form.querySelector("#device").value;
                const country = form.querySelector("#country").value;
                const age = form.querySelector("#age").value;
                const placement = form.querySelector("#placement").value;
                const format = form.querySelector("#format").value;
                const objective = form.querySelector("#objective").value;
                const ctrThreshold = form.querySelector("#ctr_threshold").value;
                const roasThreshold = form.querySelector("#roas_threshold").value;
                const sortBy = form.querySelector("#sort_by").value;
                
                // Build filter summary
                if (datePreset !== "this_quarter") filters.push("Date: " + datePreset.replace("_", " "));
                if (platform !== "all") filters.push("Platform: " + platform);
                if (device !== "all") filters.push("Device: " + device);
                if (country !== "all") filters.push("Country: " + country);
                if (age !== "all") filters.push("Age: " + age);
                if (placement !== "all") filters.push("Placement: " + placement);
                if (format !== "all") filters.push("Format: " + format);
                if (objective !== "all") filters.push("Objective: " + objective);
                if (ctrThreshold) filters.push("Min CTR: " + ctrThreshold + "%");
                if (roasThreshold) filters.push("Min ROAS: " + roasThreshold + "x");
                if (sortBy !== "spend") filters.push("Sort: " + sortBy);
                
                if (filters.length === 0) {
                    summary.innerHTML = "<span class=\"no-filters\">No filters applied (showing all data)</span>";
                } else {
                    summary.innerHTML = filters.map(filter => "<span class=\"filter-tag\">" + filter + "</span>").join("");
                }
            } catch (error) {
                console.error("Error updating filters summary:", error);
            }
        }
        
        function viewAdDetails(adId) {
            try {
                if (!adId) {
                    alert("Ad ID not found.");
                    return;
                }
                // Open ad details in a new window or modal
                window.open("ad_details.php?id=" + encodeURIComponent(adId), "_blank");
            } catch (error) {
                console.error("Error viewing ad details:", error);
                alert("Error opening ad details. Please try again.");
            }
        }
        
        function viewCampaignDetails(campaignId) {
            try {
                if (!campaignId) {
                    alert("Campaign ID not found.");
                    return;
                }
                // Open campaign details in a new window or modal
                window.open("campaign_details.php?id=" + encodeURIComponent(campaignId), "_blank");
            } catch (error) {
                console.error("Error viewing campaign details:", error);
                alert("Error opening campaign details. Please try again.");
            }
        }
        
        function addToReport(adId, accountId) {
            try {
                if (!adId) {
                    alert("Ad ID not found.");
                    return;
                }
                // Add ad to custom report
                alert("Ad " + adId + " from account " + accountId + " added to custom report!");
            } catch (error) {
                console.error("Error adding to report:", error);
                alert("Error adding ad to report. Please try again.");
            }
        }
        
        // Auto-submit form when filters change
        document.addEventListener("DOMContentLoaded", function() {
            try {
                const filterSelects = document.querySelectorAll(".filters-form select");
                filterSelects.forEach(select => {
                    select.addEventListener("change", function() {
                        // Optional: Auto-submit on filter change
                        // this.form.submit();
                    });
                });
            } catch (error) {
                console.error("Error setting up filter listeners:", error);
            }
        });
    ';
?>

<!-- Export Modal -->
<div id="exportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Export Cross-Account Data</h2>
            <button class="modal-close" onclick="closeExportModal()">&times;</button>
        </div>
        <form id="exportForm" method="POST" action="export_handler.php">
            <div class="modal-body">
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="export_type" value="cross_account">
                <input type="hidden" name="filters" id="exportFilters" value='<?php echo json_encode($current_filters); ?>'>
                
                <div class="form-group">
                    <label for="exportFormat">Export Format</label>
                    <select id="exportFormat" name="export_format" required>
                        <option value="csv">CSV</option>
                        <option value="excel">Excel (XLSX)</option>
                        <option value="pdf">PDF</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="exportView">Export View</label>
                    <select id="exportView" name="export_view" required>
                        <option value="table">Table View</option>
                        <option value="chart">Chart View</option>
                        <option value="raw">Raw Data</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Include Fields</label>
                    <div class="field-selection">
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="account_name" checked>
                            <span class="checkmark"></span>
                            Account Name
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="campaign_name" checked>
                            <span class="checkmark"></span>
                            Campaign Name
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="ad_name" checked>
                            <span class="checkmark"></span>
                            Ad Name
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="platform" checked>
                            <span class="checkmark"></span>
                            Platform
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="objective" checked>
                            <span class="checkmark"></span>
                            Objective
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="spend" checked>
                            <span class="checkmark"></span>
                            Spend
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="impressions" checked>
                            <span class="checkmark"></span>
                            Impressions
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="clicks" checked>
                            <span class="checkmark"></span>
                            Clicks
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="ctr" checked>
                            <span class="checkmark"></span>
                            CTR
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="cpc" checked>
                            <span class="checkmark"></span>
                            CPC
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="cpa" checked>
                            <span class="checkmark"></span>
                            CPA
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="roas" checked>
                            <span class="checkmark"></span>
                            ROAS
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="targeting" checked>
                            <span class="checkmark"></span>
                            Targeting
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeExportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Export Data</button>
            </div>
        </form>
    </div>
</div>

<script>
function openExportModal() {
    document.getElementById('exportModal').style.display = 'block';
}

function closeExportModal() {
    document.getElementById('exportModal').style.display = 'none';
}

// Handle export form submission
document.getElementById('exportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
    submitBtn.disabled = true;
    
    fetch('export_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert('Export completed successfully! File: ' + data.file_name);
            
            // Create download link
            const downloadLink = document.createElement('a');
            downloadLink.href = 'export_handler.php?download=1&file=' + encodeURIComponent(data.file_name);
            downloadLink.download = data.file_name;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            
            closeExportModal();
        } else {
            alert('Export failed: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Export error:', error);
        alert('Export failed: ' + error.message);
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('exportModal');
    if (event.target === modal) {
        closeExportModal();
    }
}
</script>

<?php
    include 'templates/footer.php'; 
    ?>
