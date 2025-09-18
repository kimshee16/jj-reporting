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

$admin_id = $_SESSION['admin_id'];

// Handle actions
$action = $_GET['action'] ?? '';
$rule_id = $_GET['rule_id'] ?? '';
$notification_id = $_GET['notification_id'] ?? '';

if ($action === 'delete_rule' && !empty($rule_id)) {
    try {
        $stmt = $pdo->prepare("DELETE FROM alert_rules WHERE id = ? AND created_by = ?");
        $stmt->execute([$rule_id, $admin_id]);
        $success_message = "Alert rule deleted successfully.";
    } catch(PDOException $e) {
        $error_message = "Error deleting alert rule: " . $e->getMessage();
    }
}

if ($action === 'toggle_rule' && !empty($rule_id)) {
    try {
        $stmt = $pdo->prepare("UPDATE alert_rules SET is_active = NOT is_active WHERE id = ? AND created_by = ?");
        $stmt->execute([$rule_id, $admin_id]);
        $success_message = "Alert rule status updated.";
    } catch(PDOException $e) {
        $error_message = "Error updating alert rule: " . $e->getMessage();
    }
}

if ($action === 'mark_read' && !empty($notification_id)) {
    try {
        $stmt = $pdo->prepare("UPDATE alert_notifications SET status = 'read', read_at = NOW() WHERE id = ?");
        $stmt->execute([$notification_id]);
        $success_message = "Notification marked as read.";
    } catch(PDOException $e) {
        $error_message = "Error updating notification: " . $e->getMessage();
    }
}

if ($action === 'dismiss' && !empty($notification_id)) {
    try {
        $stmt = $pdo->prepare("UPDATE alert_notifications SET status = 'dismissed', dismissed_at = NOW() WHERE id = ?");
        $stmt->execute([$notification_id]);
        $success_message = "Notification dismissed.";
    } catch(PDOException $e) {
        $error_message = "Error dismissing notification: " . $e->getMessage();
    }
}

// Handle form submissions
if ($_POST['action'] ?? '' === 'create_rule') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $rule_type = $_POST['rule_type'] ?? '';
    $condition = $_POST['condition'] ?? '';
    $threshold_value = $_POST['threshold_value'] ?? '';
    $scope = $_POST['scope'] ?? 'ad';
    $platform_filter = $_POST['platform_filter'] ?? 'all';
    $country_filter = $_POST['country_filter'] ?? 'all';
    $objective_filter = $_POST['objective_filter'] ?? 'all';
    
    if (!empty($name) && !empty($rule_type) && !empty($condition) && !empty($threshold_value)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO alert_rules (name, description, rule_type, condition, threshold_value, scope, platform_filter, country_filter, objective_filter, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $rule_type, $condition, $threshold_value, $scope, $platform_filter, $country_filter, $objective_filter, $admin_id]);
            $success_message = "Alert rule created successfully!";
        } catch(PDOException $e) {
            $error_message = "Error creating alert rule: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

if ($_POST['action'] ?? '' === 'update_settings') {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $in_app_notifications = isset($_POST['in_app_notifications']) ? 1 : 0;
    $email_frequency = $_POST['email_frequency'] ?? 'immediate';
    $quiet_hours_start = $_POST['quiet_hours_start'] ?: null;
    $quiet_hours_end = $_POST['quiet_hours_end'] ?: null;
    $max_alerts_per_hour = $_POST['max_alerts_per_hour'] ?? 10;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO alert_settings (admin_id, email_notifications, in_app_notifications, email_frequency, quiet_hours_start, quiet_hours_end, max_alerts_per_hour) 
            VALUES (?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            email_notifications = VALUES(email_notifications),
            in_app_notifications = VALUES(in_app_notifications),
            email_frequency = VALUES(email_frequency),
            quiet_hours_start = VALUES(quiet_hours_start),
            quiet_hours_end = VALUES(quiet_hours_end),
            max_alerts_per_hour = VALUES(max_alerts_per_hour)
        ");
        $stmt->execute([$admin_id, $email_notifications, $in_app_notifications, $email_frequency, $quiet_hours_start, $quiet_hours_end, $max_alerts_per_hour]);
        $success_message = "Alert settings updated successfully!";
    } catch(PDOException $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Get alert rules
try {
    $stmt = $pdo->prepare("SELECT * FROM alert_rules WHERE created_by = ? ORDER BY created_at DESC");
    $stmt->execute([$admin_id]);
    $alert_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching alert rules: " . $e->getMessage();
    $alert_rules = [];
}

// Get notifications
try {
    $stmt = $pdo->prepare("
        SELECT an.*, ar.name as rule_name, ar.rule_type, ar.condition, ar.threshold_value
        FROM alert_notifications an
        JOIN alert_rules ar ON an.rule_id = ar.id
        WHERE ar.created_by = ?
        ORDER BY an.triggered_at DESC
        LIMIT 50
    ");
    $stmt->execute([$admin_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $notifications = [];
}

// Get alert settings
try {
    $stmt = $pdo->prepare("SELECT * FROM alert_settings WHERE admin_id = ?");
    $stmt->execute([$admin_id]);
    $alert_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $alert_settings = null;
}

// Get stats
$stats = [
    'total_rules' => count($alert_rules),
    'active_rules' => count(array_filter($alert_rules, function($rule) { return $rule['is_active'] == 1; })),
    'new_notifications' => count(array_filter($notifications, function($notif) { return $notif['status'] == 'new'; })),
    'total_notifications' => count($notifications)
];

// Set page variables for template
$page_title = "Alerts - JJ Reporting Dashboard";

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
                <button class="btn btn-primary" onclick="openCreateRuleModal()">
                    <i class="fas fa-plus"></i>
                    Create Alert Rule
                </button>
                <button class="btn btn-secondary" onclick="openSettingsModal()">
                    <i class="fas fa-cog"></i>
                    Settings
                </button>
                <button class="btn btn-success" onclick="testAlerts()">
                    <i class="fas fa-play"></i>
                    Test Alerts
                </button>
            </div>';
        include 'templates/top_header.php'; 
        ?>

        <!-- Alerts Content -->
        <div class="dashboard-content">
            <div class="alerts-container">
                
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

                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Total Rules</h3>
                            <p class="stat-value"><?php echo $stats['total_rules']; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Active Rules</h3>
                            <p class="stat-value"><?php echo $stats['active_rules']; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>New Alerts</h3>
                            <p class="stat-value"><?php echo $stats['new_notifications']; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Total Alerts</h3>
                            <p class="stat-value"><?php echo $stats['total_notifications']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="tabs-container">
                    <div class="tabs">
                        <button class="tab-button active" onclick="switchTab('rules')">
                            <i class="fas fa-cog"></i>
                            Alert Rules
                        </button>
                        <button class="tab-button" onclick="switchTab('notifications')">
                            <i class="fas fa-bell"></i>
                            Notifications
                            <?php if ($stats['new_notifications'] > 0): ?>
                                <span class="badge"><?php echo $stats['new_notifications']; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- Alert Rules Tab -->
                    <div id="rules-tab" class="tab-content active">
                        <div class="section-header">
                            <h2>Alert Rules</h2>
                            <div class="section-actions">
                                <select id="ruleFilter" onchange="filterRules()">
                                    <option value="all">All Rules</option>
                                    <option value="active">Active Only</option>
                                    <option value="inactive">Inactive Only</option>
                                </select>
                            </div>
                        </div>

                        <?php if (empty($alert_rules)): ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash"></i>
                                <h3>No Alert Rules</h3>
                                <p>Create your first alert rule to get notified about important changes in your campaigns.</p>
                                <button class="btn btn-primary" onclick="openCreateRuleModal()">
                                    <i class="fas fa-plus"></i>
                                    Create Your First Rule
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="rules-grid">
                                <?php foreach ($alert_rules as $rule): ?>
                                    <div class="rule-card" data-status="<?php echo $rule['is_active'] ? 'active' : 'inactive'; ?>">
                                        <div class="rule-header">
                                            <div class="rule-title">
                                                <h3><?php echo htmlspecialchars($rule['name']); ?></h3>
                                                <span class="status-badge <?php echo $rule['is_active'] ? 'active' : 'inactive'; ?>">
                                                    <?php echo $rule['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </div>
                                            <div class="rule-actions">
                                                <div class="dropdown">
                                                    <button class="action-btn" onclick="toggleDropdown(this)">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a href="javascript:void(0)" onclick="toggleRule(<?php echo $rule['id']; ?>)" class="dropdown-item">
                                                            <i class="fas fa-power-off"></i>
                                                            <?php echo $rule['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                        </a>
                                                        <a href="javascript:void(0)" onclick="editRule(<?php echo $rule['id']; ?>)" class="dropdown-item">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="javascript:void(0)" onclick="deleteRule(<?php echo $rule['id']; ?>)" class="dropdown-item danger">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="rule-content">
                                            <p class="rule-description">
                                                <?php echo htmlspecialchars($rule['description'] ?: 'No description provided'); ?>
                                            </p>
                                            
                                            <div class="rule-conditions">
                                                <div class="condition-item">
                                                    <strong>Metric:</strong> <?php echo strtoupper($rule['rule_type']); ?>
                                                </div>
                                                <div class="condition-item">
                                                    <strong>Condition:</strong> <?php echo ucfirst(str_replace('_', ' ', $rule['condition'])); ?>
                                                </div>
                                                <div class="condition-item">
                                                    <strong>Threshold:</strong> 
                                                    <?php if (in_array($rule['rule_type'], ['cpa', 'cpc', 'cpm', 'spend'])): ?>
                                                        $<?php echo number_format($rule['threshold_value'], 2); ?>
                                                    <?php elseif (in_array($rule['rule_type'], ['roas'])): ?>
                                                        <?php echo number_format($rule['threshold_value'], 2); ?>x
                                                    <?php else: ?>
                                                        <?php echo number_format($rule['threshold_value'], 2); ?>%
                                                    <?php endif; ?>
                                                </div>
                                                <div class="condition-item">
                                                    <strong>Scope:</strong> <?php echo ucfirst($rule['scope']); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="rule-meta">
                                                <div class="meta-item">
                                                    <i class="fas fa-calendar"></i>
                                                    <span>Created <?php echo date('M j, Y', strtotime($rule['created_at'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Notifications Tab -->
                    <div id="notifications-tab" class="tab-content">
                        <div class="section-header">
                            <h2>Recent Notifications</h2>
                            <div class="section-actions">
                                <button class="btn btn-secondary" onclick="markAllRead()">
                                    <i class="fas fa-check"></i>
                                    Mark All Read
                                </button>
                            </div>
                        </div>

                        <?php if (empty($notifications)): ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash"></i>
                                <h3>No Notifications</h3>
                                <p>You don't have any alert notifications yet. Create some alert rules to get started.</p>
                            </div>
                        <?php else: ?>
                            <div class="notifications-list">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item <?php echo $notification['status'] === 'new' ? 'new' : ''; ?>">
                                        <div class="notification-icon">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-header">
                                                <h4><?php echo htmlspecialchars($notification['rule_name']); ?></h4>
                                                <span class="notification-time">
                                                    <?php echo date('M j, Y g:i A', strtotime($notification['triggered_at'])); ?>
                                                </span>
                                            </div>
                                            <p class="notification-message">
                                                <?php echo htmlspecialchars($notification['alert_message']); ?>
                                            </p>
                                            <div class="notification-details">
                                                <span class="entity-name">
                                                    <i class="fas fa-<?php echo $notification['entity_type'] === 'campaign' ? 'bullhorn' : 'ad'; ?>"></i>
                                                    <?php echo htmlspecialchars($notification['entity_name']); ?>
                                                </span>
                                                <span class="account-name">
                                                    <i class="fas fa-building"></i>
                                                    <?php echo htmlspecialchars($notification['account_name']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="notification-actions">
                                            <?php if ($notification['status'] === 'new'): ?>
                                                <button class="btn btn-sm btn-primary" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-secondary" onclick="dismissNotification(<?php echo $notification['id']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Create Rule Modal -->
    <div id="createRuleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create Alert Rule</h3>
                <button class="modal-close" onclick="closeCreateRuleModal()">&times;</button>
            </div>
            <form method="POST" id="createRuleForm">
                <input type="hidden" name="action" value="create_rule">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="rule_name">Rule Name *</label>
                        <input type="text" name="name" id="rule_name" required placeholder="e.g., High CPA Alert">
                    </div>
                    
                    <div class="form-group">
                        <label for="rule_description">Description</label>
                        <textarea name="description" id="rule_description" rows="2" placeholder="Optional description..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rule_type">Metric *</label>
                            <select name="rule_type" id="rule_type" required onchange="updateThresholdLabel()">
                                <option value="">Select metric</option>
                                <option value="cpa">CPA (Cost Per Acquisition)</option>
                                <option value="roas">ROAS (Return on Ad Spend)</option>
                                <option value="ctr">CTR (Click-Through Rate)</option>
                                <option value="cpc">CPC (Cost Per Click)</option>
                                <option value="cpm">CPM (Cost Per Mille)</option>
                                <option value="spend">Spend</option>
                                <option value="impressions">Impressions</option>
                                <option value="clicks">Clicks</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="condition">Condition *</label>
                            <select name="condition" id="condition" required>
                                <option value="">Select condition</option>
                                <option value="greater_than">Greater than</option>
                                <option value="less_than">Less than</option>
                                <option value="equals">Equals</option>
                                <option value="not_equals">Not equals</option>
                                <option value="greater_than_or_equal">Greater than or equal</option>
                                <option value="less_than_or_equal">Less than or equal</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="threshold_value">Threshold Value *</label>
                            <input type="number" name="threshold_value" id="threshold_value" required step="0.01" placeholder="0.00">
                            <small id="threshold_help">Enter the threshold value</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="scope">Scope *</label>
                            <select name="scope" id="scope" required>
                                <option value="ad">Ad Level</option>
                                <option value="campaign">Campaign Level</option>
                                <option value="account">Account Level</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="platform_filter">Platform Filter</label>
                            <select name="platform_filter" id="platform_filter">
                                <option value="all">All Platforms</option>
                                <option value="facebook">Facebook Only</option>
                                <option value="instagram">Instagram Only</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="country_filter">Country Filter</label>
                            <select name="country_filter" id="country_filter">
                                <option value="all">All Countries</option>
                                <option value="PK">Pakistan</option>
                                <option value="US">United States</option>
                                <option value="GB">United Kingdom</option>
                                <option value="CA">Canada</option>
                                <option value="AU">Australia</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="objective_filter">Objective Filter</label>
                        <select name="objective_filter" id="objective_filter">
                            <option value="all">All Objectives</option>
                            <option value="CONVERSIONS">Conversions</option>
                            <option value="TRAFFIC">Traffic</option>
                            <option value="AWARENESS">Awareness</option>
                            <option value="REACH">Reach</option>
                            <option value="LEAD_GENERATION">Lead Generation</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateRuleModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Rule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Alert Settings</h3>
                <button class="modal-close" onclick="closeSettingsModal()">&times;</button>
            </div>
            <form method="POST" id="settingsForm">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="email_notifications" value="1" <?php echo ($alert_settings['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Enable email notifications
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="in_app_notifications" value="1" <?php echo ($alert_settings['in_app_notifications'] ?? 1) ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Enable in-app notifications
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_frequency">Email Frequency</label>
                        <select name="email_frequency" id="email_frequency">
                            <option value="immediate" <?php echo ($alert_settings['email_frequency'] ?? 'immediate') === 'immediate' ? 'selected' : ''; ?>>Immediate</option>
                            <option value="hourly" <?php echo ($alert_settings['email_frequency'] ?? 'immediate') === 'hourly' ? 'selected' : ''; ?>>Hourly Digest</option>
                            <option value="daily" <?php echo ($alert_settings['email_frequency'] ?? 'immediate') === 'daily' ? 'selected' : ''; ?>>Daily Digest</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quiet_hours_start">Quiet Hours Start</label>
                            <input type="time" name="quiet_hours_start" id="quiet_hours_start" value="<?php echo $alert_settings['quiet_hours_start'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="quiet_hours_end">Quiet Hours End</label>
                            <input type="time" name="quiet_hours_end" id="quiet_hours_end" value="<?php echo $alert_settings['quiet_hours_end'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_alerts_per_hour">Max Alerts Per Hour</label>
                        <input type="number" name="max_alerts_per_hour" id="max_alerts_per_hour" value="<?php echo $alert_settings['max_alerts_per_hour'] ?? 10; ?>" min="1" max="100">
                        <small>Maximum number of alerts to send per hour to avoid spam</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeSettingsModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php 
    $inline_scripts = '
        function openCreateRuleModal() {
            document.getElementById("createRuleModal").style.display = "block";
        }
        
        function closeCreateRuleModal() {
            document.getElementById("createRuleModal").style.display = "none";
        }
        
        function openSettingsModal() {
            document.getElementById("settingsModal").style.display = "block";
        }
        
        function closeSettingsModal() {
            document.getElementById("settingsModal").style.display = "none";
        }
        
        function updateThresholdLabel() {
            const ruleType = document.getElementById("rule_type").value;
            const helpText = document.getElementById("threshold_help");
            const thresholdInput = document.getElementById("threshold_value");
            
            switch(ruleType) {
                case "cpa":
                case "cpc":
                case "cpm":
                case "spend":
                    helpText.textContent = "Enter amount in dollars (e.g., 50.00)";
                    thresholdInput.placeholder = "0.00";
                    break;
                case "roas":
                    helpText.textContent = "Enter ROAS multiplier (e.g., 2.50)";
                    thresholdInput.placeholder = "0.00";
                    break;
                case "ctr":
                    helpText.textContent = "Enter percentage (e.g., 2.50)";
                    thresholdInput.placeholder = "0.00";
                    break;
                case "impressions":
                case "clicks":
                    helpText.textContent = "Enter number (e.g., 1000)";
                    thresholdInput.placeholder = "0";
                    break;
                default:
                    helpText.textContent = "Enter the threshold value";
                    thresholdInput.placeholder = "0.00";
            }
        }
        
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll(".tab-content").forEach(tab => {
                tab.classList.remove("active");
            });
            document.querySelectorAll(".tab-button").forEach(button => {
                button.classList.remove("active");
            });
            
            // Show selected tab
            document.getElementById(tabName + "-tab").classList.add("active");
            event.target.classList.add("active");
        }
        
        function filterRules() {
            const filter = document.getElementById("ruleFilter").value;
            const cards = document.querySelectorAll(".rule-card");
            
            cards.forEach(card => {
                const status = card.dataset.status;
                let show = true;
                
                if (filter === "active" && status !== "active") show = false;
                if (filter === "inactive" && status !== "inactive") show = false;
                
                card.style.display = show ? "block" : "none";
            });
        }
        
        function toggleRule(ruleId) {
            if (confirm("Are you sure you want to toggle this rule?")) {
                window.location.href = "?action=toggle_rule&rule_id=" + ruleId;
            }
        }
        
        function deleteRule(ruleId) {
            if (confirm("Are you sure you want to delete this rule? This action cannot be undone.")) {
                window.location.href = "?action=delete_rule&rule_id=" + ruleId;
            }
        }
        
        function editRule(ruleId) {
            // Open edit modal or redirect to edit page
            console.log("Edit rule:", ruleId);
        }
        
        function markAsRead(notificationId) {
            window.location.href = "?action=mark_read&notification_id=" + notificationId;
        }
        
        function dismissNotification(notificationId) {
            if (confirm("Are you sure you want to dismiss this notification?")) {
                window.location.href = "?action=dismiss&notification_id=" + notificationId;
            }
        }
        
        function markAllRead() {
            if (confirm("Are you sure you want to mark all notifications as read?")) {
                // Implement mark all as read
                console.log("Mark all as read");
            }
        }
        
        function testAlerts() {
            if (confirm("This will test all active alert rules. Continue?")) {
                // Implement test alerts
                console.log("Test alerts");
            }
        }
        
        function toggleDropdown(button) {
            const dropdown = button.nextElementSibling;
            const isOpen = dropdown.style.display === "block";
            
            // Close all dropdowns
            document.querySelectorAll(".dropdown-menu").forEach(menu => {
                menu.style.display = "none";
            });
            
            // Toggle current dropdown
            dropdown.style.display = isOpen ? "none" : "block";
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener("click", function(e) {
            if (!e.target.closest(".dropdown")) {
                document.querySelectorAll(".dropdown-menu").forEach(menu => {
                    menu.style.display = "none";
                });
            }
        });
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById("createRuleModal");
            const settingsModal = document.getElementById("settingsModal");
            
            if (event.target === createModal) {
                closeCreateRuleModal();
            }
            if (event.target === settingsModal) {
                closeSettingsModal();
            }
        }
    ';
    include 'templates/footer.php'; 
    ?>
