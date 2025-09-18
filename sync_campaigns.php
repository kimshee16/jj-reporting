<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

require_once 'config.php';
require_once 'classes/CampaignSync.php';

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

$admin_id = $_SESSION['admin_id'] ?? null;
$account_id = $_GET['account_id'] ?? '';
$date_preset = $_GET['date_preset'] ?? 'this_month';

// Initialize error message
$error_message = '';
$success_message = '';
$warning_message = '';

// Check if admin_id exists
if (empty($admin_id)) {
    $error_message = "Admin ID not found. Please log in again.";
}

// Get access token
$access_token = '';
if (!empty($admin_id)) {
    try {
        $stmt = $pdo->prepare("SELECT access_token FROM facebook_access_tokens WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($token_data) {
            $access_token = $token_data['access_token'];
        }
    } catch(PDOException $e) {
        $error_message = "Error fetching access token: " . $e->getMessage();
    }
}

if (empty($access_token)) {
    $error_message = "No access token found. Please update your access token first.";
}

if (empty($account_id)) {
    $error_message = "Account ID is required.";
}

// Handle sync request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($access_token) && !empty($account_id) && empty($error_message)) {
    try {
        $campaign_sync = new CampaignSync($pdo, $access_token);
        $result = $campaign_sync->syncCampaigns($account_id, $date_preset);
        
        if ($result['success']) {
            $success_message = "Successfully synced {$result['synced_count']} campaigns out of {$result['total_campaigns']} total.";
            if (!empty($result['errors'])) {
                $warning_message = "Some campaigns had errors: " . implode(', ', $result['errors']);
            }
            
            // Refresh campaigns and stats after sync
            $campaigns = $campaign_sync->getCampaigns($account_id);
            $stats = $campaign_sync->getCampaignStats($account_id);
        } else {
            $error_message = $result['message'];
        }
    } catch (Exception $e) {
        $error_message = "Sync failed: " . $e->getMessage();
    }
}

// Get account name
$account_name = 'Unknown Account';
if (!empty($account_id)) {
    try {
        $stmt = $pdo->prepare("SELECT act_name FROM facebook_ads_accounts WHERE act_id = ?");
        $stmt->execute([$account_id]);
        $account_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($account_data && !empty($account_data['act_name'])) {
            $account_name = $account_data['act_name'];
        }
    } catch(PDOException $e) {
        // Handle error silently
    }
}

// Get current campaigns from database
$campaigns = [];
$stats = [];
if (!empty($account_id) && !empty($access_token)) {
    try {
        $campaign_sync = new CampaignSync($pdo, $access_token);
        $campaigns = $campaign_sync->getCampaigns($account_id);
        $stats = $campaign_sync->getCampaignStats($account_id);
    } catch (Exception $e) {
        $error_message = "Error fetching campaigns: " . $e->getMessage();
    }
}

// Set page variables for template
$page_title = "Sync Campaigns - " . htmlspecialchars($account_name) . " - JJ Reporting Dashboard";

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
            <a href="campaigns.php?account_id=' . urlencode($account_id) . '" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back to Campaigns
            </a>';
        include 'templates/top_header.php'; 
        ?>

        <!-- Sync Content -->
        <div class="dashboard-content">
            <div class="sync-container">
                <!-- Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($warning_message)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($warning_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Sync Form -->
                <div class="sync-form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-sync-alt"></i> Sync Campaign Data</h2>
                        <p>Sync campaign data from Facebook API to local database for better performance</p>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="date_preset">Date Range</label>
                            <select name="date_preset" id="date_preset" required>
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

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" <?php echo empty($access_token) || empty($account_id) ? 'disabled' : ''; ?>>
                                <i class="fas fa-sync-alt"></i>
                                Sync Campaigns
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Current Data Stats -->
                <?php if (!empty($stats)): ?>
                <div class="stats-card">
                    <div class="stats-header">
                        <h3>Current Database Stats</h3>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($stats['total_campaigns'] ?? 0); ?></div>
                            <div class="stat-label">Total Campaigns</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($stats['active_campaigns'] ?? 0); ?></div>
                            <div class="stat-label">Active</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">$<?php echo number_format($stats['total_spend'] ?? 0, 2); ?></div>
                            <div class="stat-label">Total Spend</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($stats['avg_roas'] ?? 0, 2); ?>x</div>
                            <div class="stat-label">Avg ROAS</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Current Campaigns Table -->
                <?php if (!empty($campaigns)): ?>
                <div class="campaigns-table-card">
                    <div class="table-header">
                        <h3>Current Campaigns in Database</h3>
                        <span class="count"><?php echo count($campaigns); ?> campaigns</span>
                    </div>
                    <div class="table-container">
                        <table class="campaigns-table">
                            <thead>
                                <tr>
                                    <th>Campaign Name</th>
                                    <th>Objective</th>
                                    <th>Status</th>
                                    <th>Budget</th>
                                    <th>Spend</th>
                                    <th>ROAS</th>
                                    <th>CTR</th>
                                    <th>Results</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                    <td>
                                        <span class="objective-badge objective-<?php echo strtolower($campaign['objective']); ?>">
                                            <?php echo htmlspecialchars($campaign['objective']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($campaign['status']); ?>">
                                            <?php echo htmlspecialchars($campaign['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($campaign['budget_type'] === 'daily'): ?>
                                            $<?php echo number_format($campaign['daily_budget'] ?? 0, 2); ?>/day
                                        <?php else: ?>
                                            $<?php echo number_format($campaign['lifetime_budget'] ?? 0, 2); ?> lifetime
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format($campaign['total_spend'] ?? 0, 2); ?></td>
                                    <td><?php echo $campaign['roas'] ? number_format($campaign['roas'] ?? 0, 2) . 'x' : 'N/A'; ?></td>
                                    <td><?php echo $campaign['ctr'] ? number_format($campaign['ctr'] ?? 0, 2) . '%' : 'N/A'; ?></td>
                                    <td><?php echo number_format($campaign['results'] ?? 0); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <style>
    .sync-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .sync-form-card, .stats-card, .campaigns-table-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        overflow: hidden;
    }

    .form-header, .stats-header, .table-header {
        padding: 20px;
        border-bottom: 1px solid #f0f0f0;
        background: #f8f9fa;
    }

    .form-header h2, .stats-header h3, .table-header h3 {
        margin: 0;
        color: #1a1a1a;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-header p {
        margin: 8px 0 0 0;
        color: #666;
    }

    .form-group {
        padding: 20px;
        margin-bottom: 0;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
    }

    .form-group select {
        width: 100%;
        padding: 12px;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 14px;
    }

    .form-actions {
        padding: 20px;
        border-top: 1px solid #f0f0f0;
        text-align: center;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
        padding: 20px;
    }

    .stat-item {
        text-align: center;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #1877f2;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .count {
        background: #1877f2;
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .campaigns-table {
        width: 100%;
        border-collapse: collapse;
    }

    .campaigns-table th,
    .campaigns-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
    }

    .campaigns-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #333;
    }

    .objective-badge, .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
    }

    .objective-conversions { background: #e8f5e8; color: #2e7d32; }
    .objective-traffic { background: #e3f2fd; color: #1976d2; }
    .objective-awareness { background: #fff3e0; color: #f57c00; }
    .objective-lead_generation { background: #f3e5f5; color: #7b1fa2; }

    .status-active { background: #e8f5e8; color: #2e7d32; }
    .status-paused { background: #fff3e0; color: #f57c00; }
    .status-completed { background: #e3f2fd; color: #1976d2; }

    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-success {
        background: #e8f5e8;
        color: #2e7d32;
        border: 1px solid #c8e6c9;
    }

    .alert-warning {
        background: #fff3e0;
        color: #f57c00;
        border: 1px solid #ffcc02;
    }

    .alert-error {
        background: #ffebee;
        color: #c62828;
        border: 1px solid #ffcdd2;
    }
    </style>

<?php include 'templates/footer.php'; ?>
