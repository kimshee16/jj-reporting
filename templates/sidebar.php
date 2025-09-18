<?php
// Get current page for active navigation highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!-- Sidebar Navigation -->
<nav class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-chart-line"></i>
            <span>JJ Reporting</span>
        </div>
    </div>
    
    <div class="sidebar-content">
        <ul class="sidebar-nav">
            <!-- Main Dashboard -->
            <li class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Account Management -->
            <li class="nav-item <?php echo $current_page === 'accounts' ? 'active' : ''; ?>">
                <a href="accounts.php" class="nav-link">
                    <i class="fas fa-building"></i>
                    <span>Ad Accounts</span>
                </a>
            </li>
            <li class="nav-item <?php echo $current_page === 'access_tokens' ? 'active' : ''; ?>">
                <a href="access_tokens.php" class="nav-link">
                    <i class="fas fa-key"></i>
                    <span>Access Tokens</span>
                </a>
            </li>
            
            <!-- Campaign Management -->
            <li class="nav-item <?php echo $current_page === 'campaigns' ? 'active' : ''; ?>">
                <a href="campaigns.php" class="nav-link">
                    <i class="fas fa-bullhorn"></i>
                    <span>Campaigns</span>
                </a>
            </li>
            <li class="nav-item <?php echo $current_page === 'adsets' ? 'active' : ''; ?>">
                <a href="adsets.php" class="nav-link">
                    <i class="fas fa-layer-group"></i>
                    <span>Ad Sets</span>
                </a>
            </li>
            <li class="nav-item <?php echo $current_page === 'ads' ? 'active' : ''; ?>">
                <a href="ads.php" class="nav-link">
                    <i class="fas fa-ad"></i>
                    <span>Ads</span>
                </a>
            </li>
            
            <!-- Reports & Analytics -->
            <li class="nav-item <?php echo $current_page === 'cross_account_reports' || $current_page === 'cross_account_reports_db' ? 'active' : ''; ?>">
                <a href="cross_account_reports_db.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Cross-Account Reports</span>
                </a>
            </li>
            <li class="nav-item <?php echo $current_page === 'reports_library' ? 'active' : ''; ?>">
                <a href="reports_library.php" class="nav-link">
                    <i class="fas fa-folder-open"></i>
                    <span>Reports Library</span>
                </a>
            </li>
            <li class="nav-item <?php echo $current_page === 'content_performance' ? 'active' : ''; ?>">
                <a href="content_performance.php" class="nav-link">
                    <i class="fas fa-photo-video"></i>
                    <span>Content Performance</span>
                </a>
            </li>
            
            <!-- Quick Actions -->
            <li class="nav-item <?php echo $current_page === 'quick_export' ? 'active' : ''; ?>">
                <a href="quick_export.php" class="nav-link">
                    <i class="fas fa-file-export"></i>
                    <span>Quick Export</span>
                </a>
            </li>
            
            <!-- Alerts & Notifications -->
            <li class="nav-item <?php echo $current_page === 'alerts' ? 'active' : ''; ?>">
                <a href="alerts.php" class="nav-link">
                    <i class="fas fa-bell"></i>
                    <span>Alerts</span>
                </a>
            </li>
            
            <!-- Export & Data -->
            <li class="nav-item <?php echo $current_page === 'export_center' ? 'active' : ''; ?>">
                <a href="export_center.php" class="nav-link">
                    <i class="fas fa-download"></i>
                    <span>Export Center</span>
                </a>
            </li>
        </ul>
        
        <!-- User Info Section - Now scrollable -->
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                    <span class="user-role">Administrator</span>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</nav>
