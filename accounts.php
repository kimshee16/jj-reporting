<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

require_once 'config.php';

// Get admin ID from session
$admin_id = $_SESSION['user_id'] ?? 1; // Default to 1 if not set

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch accounts data for this admin
$accounts = [];
$stats = [
    'total_accounts' => 0,
    'total_spend' => 0,
    'active_campaigns' => 0,
    'avg_roas' => 0
];

try {
    // Get accounts for this admin through the access_token relationship
    $stmt = $pdo->prepare("
        SELECT 
            fa.id,
            fa.access_token_id,
            fa.act_name,
            fa.act_id,
            fa.account_expiry_date,
            fa.created_at,
            fa.updated_at
        FROM facebook_ads_accounts fa
        INNER JOIN facebook_access_tokens fat ON fa.access_token_id = fat.id
        WHERE fat.admin_id = :admin_id
        ORDER BY fa.created_at DESC
    ");
    $stmt->bindParam(':admin_id', $admin_id);
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats for this admin
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM facebook_ads_accounts fa
        INNER JOIN facebook_access_tokens fat ON fa.access_token_id = fat.id
        WHERE fat.admin_id = :admin_id
    ");
    $countStmt->bindParam(':admin_id', $admin_id);
    $countStmt->execute();
    $stats['total_accounts'] = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total spend for this admin's accounts
    $spendStmt = $pdo->prepare("
        SELECT COALESCE(SUM(c.total_spend), 0) as total_spend
        FROM facebook_ads_accounts_campaigns c
        INNER JOIN facebook_ads_accounts fa ON c.account_id = fa.act_id
        INNER JOIN facebook_access_tokens fat ON fa.access_token_id = fat.id
        WHERE fat.admin_id = :admin_id
    ");
    $spendStmt->bindParam(':admin_id', $admin_id);
    $spendStmt->execute();
    $spendResult = $spendStmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_spend'] = $spendResult['total_spend'];
    
    // Get active campaigns count
    $campaignsStmt = $pdo->prepare("
        SELECT COUNT(*) as active_campaigns
        FROM facebook_ads_accounts_campaigns c
        INNER JOIN facebook_ads_accounts fa ON c.account_id = fa.act_id
        INNER JOIN facebook_access_tokens fat ON fa.access_token_id = fat.id
        WHERE fat.admin_id = :admin_id AND c.status = 'ACTIVE'
    ");
    $campaignsStmt->bindParam(':admin_id', $admin_id);
    $campaignsStmt->execute();
    $campaignsResult = $campaignsStmt->fetch(PDO::FETCH_ASSOC);
    $stats['active_campaigns'] = $campaignsResult['active_campaigns'];
    
    // Get average ROAS
    $roasStmt = $pdo->prepare("
        SELECT COALESCE(AVG(c.roas), 0) as avg_roas
        FROM facebook_ads_accounts_campaigns c
        INNER JOIN facebook_ads_accounts fa ON c.account_id = fa.act_id
        INNER JOIN facebook_access_tokens fat ON fa.access_token_id = fat.id
        WHERE fat.admin_id = :admin_id AND c.roas > 0
    ");
    $roasStmt->bindParam(':admin_id', $admin_id);
    $roasStmt->execute();
    $roasResult = $roasStmt->fetch(PDO::FETCH_ASSOC);
    $stats['avg_roas'] = $roasResult['avg_roas'];
    
} catch(PDOException $e) {
    error_log("Database error in accounts.php: " . $e->getMessage());
}

// Set page variables for template
$page_title = 'Ad Accounts - JJ Reporting Dashboard';
$additional_css = ['assets/css/accounts.css'];

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
                    Connect New Account
                </button>
                <button class="btn btn-secondary" id="refreshDataBtn">
                    <i class="fas fa-sync-alt"></i>
                    Refresh Data
                </button>
            </div>';
        include 'templates/top_header.php'; 
        ?>

        <!-- Accounts Content -->
        <div class="accounts-content">
            <!-- Account Stats Overview -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Accounts</h3>
                        <p class="stat-value"><?php echo number_format($stats['total_accounts']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Spend</h3>
                        <p class="stat-value">$<?php echo number_format($stats['total_spend'], 2); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Active Campaigns</h3>
                        <p class="stat-value"><?php echo number_format($stats['active_campaigns']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Avg. ROAS</h3>
                        <p class="stat-value"><?php echo number_format($stats['avg_roas'], 1); ?>x</p>
                    </div>
                </div>
            </div>

            <!-- Accounts List -->
            <div class="accounts-list">
                <div class="list-header">
                    <h2>Connected Ad Accounts</h2>
                    <div class="list-controls">
                        <select id="accountFilter">
                            <option value="all">All Accounts</option>
                            <option value="active">Active Only</option>
                            <option value="inactive">Inactive Only</option>
                        </select>
                        <button class="btn btn-secondary" id="exportAccountsBtn">
                            <i class="fas fa-download"></i>
                            Export
                        </button>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="accounts-table" id="accountsTable">
                        <thead>
                            <tr>
                                <th>ACCOUNT</th>
                                <th>LAST SYNC</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="accountsTableBody">
                            <?php if (empty($accounts)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4">
                                    <i class="fas fa-info-circle text-muted mb-2"></i>
                                    <p class="text-muted">No ad accounts connected yet.</p>
                                    <button class="btn btn-primary btn-sm" id="connectAccountBtn">
                                        <i class="fas fa-plus"></i> Connect Account
                                    </button>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($accounts as $account): ?>
                                <tr>
                                    <td>
                                        <div class="account-info">
                                            <div class="account-name">
                                                <strong><?php echo htmlspecialchars($account['act_name']); ?></strong>
                                            </div>
                                            <div class="account-details">
                                                <span class="account-id">ID: <?php echo htmlspecialchars($account['act_id']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="sync-info">
                                            <span class="sync-time">
                                                <?php echo date('M j, Y H:i', strtotime($account['updated_at'])); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewAccountDetails('<?php echo $account['act_id']; ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="syncAccount('<?php echo $account['act_id']; ?>')">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="disconnectAccount('<?php echo $account['act_id']; ?>')">
                                                <i class="fas fa-unlink"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                <p>Connect your Meta Ad accounts to start analyzing performance data.</p>
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

    <?php 
    $additional_scripts = ['assets/js/accounts.js'];
    include 'templates/footer.php'; 
    ?>