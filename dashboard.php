<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}


// Set admin_id from session for use in queries
$admin_id = $_SESSION['admin_id'];

// Set page variables for template
$page_title = 'JJ Reporting Dashboard';
$additional_scripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
    'assets/js/dashboard.js'
];

// Fetch dashboard data directly
$dashboard_data = null;
$error_message = '';

try {
    // Database connection
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get total ad spend across admin's accounts
    $total_spend_sql = "
        SELECT 
            COALESCE(SUM(c.total_spend), 0) as total_spend,
            COUNT(DISTINCT c.account_id) as total_accounts,
            COUNT(c.id) as total_campaigns
        FROM facebook_ads_accounts_campaigns c
        INNER JOIN facebook_ads_accounts fa ON c.account_id COLLATE utf8mb4_unicode_ci = fa.act_id COLLATE utf8mb4_unicode_ci
        INNER JOIN facebook_access_tokens fat ON fa.access_token_id = fat.id
        WHERE fat.admin_id = :admin_id
    ";
    $stmt = $pdo->prepare($total_spend_sql);
    $stmt->bindParam(':admin_id', $admin_id);
    $stmt->execute();
    $total_spend_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get total conversions from admin's ads
    $total_conversions_sql = "
        SELECT 
            COALESCE(SUM(a.results), 0) as total_conversions,
            COALESCE(SUM(a.clicks), 0) as total_clicks,
            COALESCE(SUM(a.impressions), 0) as total_impressions
        FROM facebook_ads_accounts_ads a
        INNER JOIN facebook_ads_accounts fa ON a.account_id COLLATE utf8mb4_unicode_ci = fa.act_id COLLATE utf8mb4_unicode_ci
        INNER JOIN facebook_access_tokens fat ON fa.access_token_id = fat.id
        WHERE fat.admin_id = :admin_id
    ";
    $stmt = $pdo->prepare($total_conversions_sql);
    $stmt->bindParam(':admin_id', $admin_id);
    $stmt->execute();
    $conversions_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get best performing campaigns (top 5 by ROAS) for admin
    $best_campaigns_sql = "
        SELECT 
            c.name,
            c.status,
            c.total_spend,
            c.results,
            c.roas,
            c.ctr,
            c.cpc,
            a.act_name as account_name
        FROM facebook_ads_accounts_campaigns c
        INNER JOIN facebook_ads_accounts a ON c.account_id COLLATE utf8mb4_unicode_ci = a.act_id COLLATE utf8mb4_unicode_ci
        INNER JOIN facebook_access_tokens fat ON a.access_token_id = fat.id
        WHERE fat.admin_id = :admin_id AND c.total_spend > 0
        ORDER BY c.roas DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($best_campaigns_sql);
    $stmt->bindParam(':admin_id', $admin_id);
    $stmt->execute();
    $best_campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get worst performing campaigns (bottom 5 by ROAS) for admin
    $worst_campaigns_sql = "
        SELECT 
            c.name,
            c.status,
            c.total_spend,
            c.results,
            c.roas,
            c.ctr,
            c.cpc,
            a.act_name as account_name
        FROM facebook_ads_accounts_campaigns c
        INNER JOIN facebook_ads_accounts a ON c.account_id COLLATE utf8mb4_unicode_ci = a.act_id COLLATE utf8mb4_unicode_ci
        INNER JOIN facebook_access_tokens fat ON a.access_token_id = fat.id
        WHERE fat.admin_id = :admin_id AND c.total_spend > 0
        ORDER BY c.roas ASC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($worst_campaigns_sql);
    $stmt->bindParam(':admin_id', $admin_id);
    $stmt->execute();
    $worst_campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent performance data for charts (last 7 days) for admin
    $chart_data_sql = "
        SELECT 
            DATE(c.updated_at) as date,
            SUM(c.total_spend) as daily_spend,
            SUM(c.results) as daily_conversions,
            AVG(c.roas) as avg_roas
        FROM facebook_ads_accounts_campaigns c
        INNER JOIN facebook_ads_accounts a ON c.account_id COLLATE utf8mb4_unicode_ci = a.act_id COLLATE utf8mb4_unicode_ci
        INNER JOIN facebook_access_tokens fat ON a.access_token_id = fat.id
        WHERE fat.admin_id = :admin_id AND c.updated_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(c.updated_at)
        ORDER BY date ASC
    ";
    $stmt = $pdo->prepare($chart_data_sql);
    $stmt->bindParam(':admin_id', $admin_id);
    $stmt->execute();
    $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get alerts summary (campaigns with low performance)
    $alerts_sql = "
        SELECT 
            c.name as campaign_name,
            c.status,
            c.total_spend,
            c.roas,
            c.ctr,
            a.act_name as account_name,
            CASE 
                WHEN c.roas < 1.0 AND c.total_spend > 100 THEN 'Low ROAS'
                WHEN c.ctr < 1.0 AND c.total_spend > 50 THEN 'Low CTR'
                WHEN c.status = 'PAUSED' AND c.total_spend > 0 THEN 'Campaign Paused'
                ELSE 'Normal'
            END as alert_type
        FROM facebook_ads_accounts_campaigns c
        INNER JOIN facebook_ads_accounts a ON c.account_id COLLATE utf8mb4_unicode_ci = a.act_id COLLATE utf8mb4_unicode_ci
        INNER JOIN facebook_access_tokens fat ON a.access_token_id = fat.id
        WHERE fat.admin_id = :admin_id AND ((c.roas < 1.0 AND c.total_spend > 100) 
           OR (c.ctr < 1.0 AND c.total_spend > 50)
           OR (c.status = 'PAUSED' AND c.total_spend > 0))
        ORDER BY c.total_spend DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($alerts_sql);
    $stmt->bindParam(':admin_id', $admin_id);
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate overall CTR
    $overall_ctr = 0;
    if ($conversions_result['total_impressions'] > 0) {
        $overall_ctr = ($conversions_result['total_clicks'] / $conversions_result['total_impressions']) * 100;
    }
    
    // Calculate average ROAS
    $avg_roas_sql = "SELECT AVG(roas) as avg_roas FROM facebook_ads_accounts_campaigns WHERE roas > 0 AND total_spend > 0";
    $avg_roas_result = $pdo->query($avg_roas_sql)->fetch(PDO::FETCH_ASSOC);
    
    // Platform data is now loaded dynamically via API
    $platform_data = null;
    
    $dashboard_data = [
        'summary' => [
            'total_spend' => floatval($total_spend_result['total_spend']),
            'total_conversions' => intval($conversions_result['total_conversions']),
            'total_clicks' => intval($conversions_result['total_clicks']),
            'total_impressions' => intval($conversions_result['total_impressions']),
            'overall_ctr' => round($overall_ctr, 2),
            'avg_roas' => round(floatval($avg_roas_result['avg_roas']), 2),
            'total_accounts' => intval($total_spend_result['total_accounts']),
            'total_campaigns' => intval($total_spend_result['total_campaigns'])
        ],
        'best_campaigns' => $best_campaigns,
        'worst_campaigns' => $worst_campaigns,
        'chart_data' => $chart_data,
        'platform_data' => $platform_data,
        'alerts' => $alerts
    ];
    
} catch (Exception $e) {
    $error_message = 'Error loading dashboard data: ' . $e->getMessage();
}

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
                <button class="btn btn-primary" id="connectAccountBtn">
                    <i class="fas fa-plus"></i>
                    Connect Meta Account
                </button>
                <button class="btn btn-secondary" id="refreshDataBtn">
                    <i class="fas fa-sync-alt"></i>
                    Refresh Data
                </button>
            </div>';
        include 'templates/top_header.php'; 
        ?>
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <?php if (isset($_GET['oauth_success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($_GET['oauth_success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['oauth_error'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($_GET['oauth_error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            
            <?php elseif ($dashboard_data && $dashboard_data['summary']['total_spend'] == 0): ?>
                <div class="info-message">
                    <i class="fas fa-info-circle"></i>
                    No campaign data found. Please sync some campaigns, ad sets, and ads first to see dashboard metrics.
                    <a href="campaigns.php" class="btn btn-primary btn-sm ml-2">
                        <i class="fas fa-sync-alt"></i> Sync Data
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- High-Level Metrics -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="metric-content">
                        <h3>Total Ad Spend</h3>
                        <p class="metric-value">$<?php echo $dashboard_data ? number_format($dashboard_data['summary']['total_spend'], 2) : '0.00'; ?></p>
                        <p class="metric-subtitle">Across <?php echo $dashboard_data ? $dashboard_data['summary']['total_accounts'] : '0'; ?> accounts</p>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="metric-content">
                        <h3>Total Conversions</h3>
                        <p class="metric-value"><?php echo $dashboard_data ? number_format($dashboard_data['summary']['total_conversions']) : '0'; ?></p>
                        <p class="metric-subtitle"><?php echo $dashboard_data ? number_format($dashboard_data['summary']['total_clicks']) : '0'; ?> total clicks</p>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="metric-content">
                        <h3>Overall CTR</h3>
                        <p class="metric-value"><?php echo $dashboard_data ? number_format($dashboard_data['summary']['overall_ctr'], 2) : '0.00'; ?>%</p>
                        <p class="metric-subtitle"><?php echo $dashboard_data ? number_format($dashboard_data['summary']['total_impressions']) : '0'; ?> impressions</p>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="metric-content">
                        <h3>Average ROAS</h3>
                        <p class="metric-value"><?php echo $dashboard_data ? number_format($dashboard_data['summary']['avg_roas'], 2) : '0.00'; ?>x</p>
                        <p class="metric-subtitle"><?php echo $dashboard_data ? $dashboard_data['summary']['total_campaigns'] : '0'; ?> campaigns</p>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Performance Overview</h3>
                        <div class="chart-controls">
                            <select id="chartPeriod">
                                <option value="7">Last 7 days</option>
                                <option value="30" selected>Last 30 days</option>
                                <option value="90">Last 90 days</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container platform-chart">
                    <div class="chart-header">
                        <h3>Spend by Platform</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="platformChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top/Bottom Performers -->
            <div class="performance-section">
                <div class="performance-card">
                    <div class="card-header">
                        <h3>Best Performing Campaigns</h3>
                        <a href="campaigns.php" class="view-all">View All</a>
                    </div>
                    <div class="performance-list">
                        <?php if ($dashboard_data && !empty($dashboard_data['best_campaigns'])): ?>
                            <?php foreach ($dashboard_data['best_campaigns'] as $campaign): ?>
                                <div class="performance-item">
                                    <div class="campaign-info">
                                        <h4><?php echo htmlspecialchars($campaign['name']); ?></h4>
                                        <p class="account-name"><?php echo htmlspecialchars($campaign['account_name']); ?></p>
                                    </div>
                                    <div class="campaign-metrics">
                                        <div class="metric">
                                            <span class="metric-label">ROAS</span>
                                            <span class="metric-value"><?php if(!empty($campaign['roas'])) {echo number_format($campaign['roas'], 2);} else { echo 0;} ?>x</span>
                                        </div>
                                        <div class="metric">
                                            <span class="metric-label">Spend</span>
                                            <span class="metric-value">$<?php if(!empty($campaign['total_spend'])) {echo number_format($campaign['total_spend'], 2);} else { echo 0;} ?></span>
                                        </div>
                                        <div class="metric">
                                            <span class="metric-label">CTR</span>
                                            <span class="metric-value"><?php if(!empty($campaign['ctr'])) { echo number_format($campaign['ctr'], 2);} else { echo 0;} ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">No campaign data available</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="performance-card">
                    <div class="card-header">
                        <h3>Worst Performing Campaigns</h3>
                        <a href="campaigns.php" class="view-all">View All</a>
                    </div>
                    <div class="performance-list">
                        <?php if ($dashboard_data && !empty($dashboard_data['worst_campaigns'])): ?>
                            <?php foreach ($dashboard_data['worst_campaigns'] as $campaign): ?>
                                <div class="performance-item">
                                    <div class="campaign-info">
                                        <h4><?php echo htmlspecialchars($campaign['name']); ?></h4>
                                        <p class="account-name"><?php echo htmlspecialchars($campaign['account_name']); ?></p>
                                    </div>
                                    <div class="campaign-metrics">
                                        <div class="metric">
                                            <span class="metric-label">ROAS</span>
                                            <span class="metric-value"><?php if(!empty($campaign['roas'])) {echo number_format($campaign['roas'], 2);} else { echo 0;} ?>x</span>
                                        </div>
                                        <div class="metric">
                                            <span class="metric-label">Spend</span>
                                            <span class="metric-value">$<?php if(!empty($campaign['total_spend'])) {echo number_format($campaign['total_spend'], 2);} else { echo 0;} ?></span>
                                        </div>
                                        <div class="metric">
                                            <span class="metric-label">CTR</span>
                                            <span class="metric-value"><?php if(!empty($campaign['ctr'])) {echo number_format($campaign['ctr'], 2);} else { echo 0;} ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">No campaign data available</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Alerts Summary -->
            <?php if ($dashboard_data && !empty($dashboard_data['alerts'])): ?>
            <div class="alerts-section">
                <div class="alerts-card">
                    <div class="card-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Alerts Summary</h3>
                        <span class="alert-count"><?php echo count($dashboard_data['alerts']); ?> alerts</span>
                    </div>
                    <div class="alerts-list">
                        <?php foreach ($dashboard_data['alerts'] as $alert): ?>
                            <div class="alert-item">
                                <div class="alert-icon">
                                    <i class="fas fa-<?php echo $alert['alert_type'] === 'Low ROAS' ? 'chart-line-down' : ($alert['alert_type'] === 'Low CTR' ? 'mouse-pointer' : 'pause-circle'); ?>"></i>
                                </div>
                                <div class="alert-content">
                                    <h4><?php echo htmlspecialchars($alert['campaign_name']); ?></h4>
                                    <p class="alert-type"><?php echo htmlspecialchars($alert['alert_type']); ?></p>
                                    <p class="alert-details">
                                        Account: <?php echo htmlspecialchars($alert['account_name']); ?> | 
                                        Spend: $<?php if(!empty($alert['total_spend'])) {echo number_format($alert['total_spend'], 2);} else { echo 0;} ?> | 
                                        ROAS: <?php if(!empty($alert['roas'])) {echo number_format($alert['roas'], 2);} else { echo 0;} ?>x
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Alerts -->
            <div class="alerts-section">
                <div class="card-header">
                    <h3>Recent Alerts</h3>
                    <a href="alerts.php" class="view-all">View All Alerts</a>
                </div>
                <div class="alerts-list" id="recentAlerts">
                    <!-- Dynamically populated -->
                </div>
            </div>
        </div>
    </main>

    <!-- Connect Account Modal -->
    <div id="connectAccountModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Connect Meta Ad Account</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Add your Meta Ad account manually or connect through Facebook.</p>
                
                <!-- Manual Entry Section -->
                <div class="manual-entry-section">
                    <h4>Manual Entry</h4>
                    <form id="manualAccountForm">
                        <div class="form-group">
                            <label for="accountName">Account Name</label>
                            <input type="text" id="accountName" name="accountName" placeholder="Enter account name" required>
                        </div>
                        <div class="form-group">
                            <label for="accountId">Account ID</label>
                            <input type="text" id="accountId" name="accountId" placeholder="Enter account ID (e.g., 123456789)" required>
                        </div>
                        <button type="submit" class="btn btn-primary" id="saveManualAccountBtn">
                            <i class="fas fa-save"></i>
                            Save Account
                        </button>
                    </form>
                </div>
                
                <!-- Divider -->
                <div class="divider">
                    <span>OR</span>
                </div>
                
                <!-- Facebook OAuth Section -->
                <div class="oauth-section">
                    <button class="btn btn-facebook" id="metaOAuthBtn">
                        <i class="fab fa-facebook"></i>
                        Connect with Facebook
                    </button>
                </div>
                
                <div class="account-selection" id="accountSelection" style="display: none;">
                    <h4>Select Accounts to Connect</h4>
                    <div id="availableAccounts"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Pass PHP data to JavaScript
    window.dashboardData = <?php echo json_encode($dashboard_data); ?>;
    window.chartData = <?php echo json_encode($chart_data); ?>;
    </script>

    <?php include 'templates/footer.php'; ?>
