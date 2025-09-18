<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
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
                        <p class="stat-value">3</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Spend This Month</h3>
                        <p class="stat-value">$45,230</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Active Campaigns</h3>
                        <p class="stat-value">24</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Avg. ROAS</h3>
                        <p class="stat-value">3.2x</p>
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
                            <!-- Dynamically populated -->
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