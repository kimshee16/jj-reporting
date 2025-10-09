<?php
require_once 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Check if we have OAuth data
if (!isset($_SESSION['available_accounts']) || !isset($_SESSION['meta_access_token'])) {
    header('Location: dashboard.php?oauth_error=' . urlencode('OAuth session expired'));
    exit();
}

$available_accounts = $_SESSION['available_accounts'];
$access_token = $_SESSION['meta_access_token'];

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_accounts'])) {
    $selected_accounts = $_POST['selected_accounts'];
    
    if (empty($selected_accounts)) {
        $error_message = 'Please select at least one account to connect.';
    } else {
        try {
            // Insert selected accounts into existing facebook_ads_accounts table
            $stmt = $pdo->prepare("
                INSERT INTO facebook_ads_accounts 
                (access_token_id, act_name, act_id, account_expiry_date, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
    
        // Ensure sidebar is included for consistent menu
        include 'templates/sidebar.php';
                act_name = VALUES(act_name),
                account_expiry_date = VALUES(account_expiry_date),
                updated_at = NOW()
            ");
            
            // Get access_token_id from session or use default value
            $access_token_id = $_SESSION['access_token_id'] ?? 1; // Default to 1 if not set
            $connected_count = 0;
            
            foreach ($selected_accounts as $account_id) {
                // Find account details
                $account_details = null;
                foreach ($available_accounts as $account) {
                    if ($account['id'] === $account_id) {
                        $account_details = $account;
                        break;
                    }
                }
                
                if ($account_details) {
                    $stmt->execute([
                        $access_token_id,
                        $account_details['name'],
                        $account_details['id'],
                        null // account_expiry_date - set to NULL for now
                    ]);
                    $connected_count++;
                }
            }
            
            // Clear OAuth session data
            unset($_SESSION['meta_access_token']);
            unset($_SESSION['available_accounts']);
            
            // Redirect to dashboard with success message
            header('Location: dashboard.php?oauth_success=' . urlencode("Successfully connected {$connected_count} account(s)!"));
            exit();
            
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}
// Include header template
include 'templates/header.php';
?>

    <!-- Include sidebar template -->
    <?php include 'templates/sidebar.php'; ?>
    
<!-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Ad Accounts - JJ Reporting Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard-page">
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
                <span>JJ Reporting</span>
            </div>
        </div>
        
        <ul class="sidebar-nav">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item active">
                <a href="#accounts" class="nav-link">
                    <i class="fas fa-building"></i>
                    <span>Ad Accounts</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="access_tokens.php" class="nav-link">
                    <i class="fas fa-key"></i>
                    <span>Access Tokens</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#reports" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#alerts" class="nav-link">
                    <i class="fas fa-bell"></i>
                    <span>Alerts</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#content" class="nav-link">
                    <i class="fas fa-photo-video"></i>
                    <span>Content</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#export" class="nav-link">
                    <i class="fas fa-download"></i>
                    <span>Export</span>
                </a>
            </li>
        </ul>
        
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
    </nav> -->

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <button class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>Connect Meta Ad Accounts</h1>
            </div>
            
            <div class="header-right">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </header>

        <!-- Account Selection Content -->
        <div class="dashboard-content">
            <div class="account-selection-container">
                <div class="selection-header">
                    <h2>Select Ad Accounts to Connect</h2>
                    <p>Choose which Meta Ad accounts you want to connect to JJ Reporting. You can select multiple accounts.</p>
                    <?php if (isset($available_accounts[0]['id']) && $available_accounts[0]['id'] === 'no_accounts'): ?>
                        <div class="warning-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>No Ad Accounts Found:</strong> Your Facebook account doesn't have any ad accounts, or the app doesn't have permission to access them. Make sure your Facebook account has ad accounts and the app has the necessary permissions.
                        </div>
                    <?php elseif (isset($available_accounts[0]['id']) && strpos($available_accounts[0]['id'], 'act_') === false): ?>
                        <div class="info-message">
                            <i class="fas fa-info-circle"></i>
                            <strong>Demo Mode:</strong> This is a demonstration account. To see your real ad accounts, make sure your Facebook app has Marketing API permissions approved.
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="accounts-grid">
                        <?php foreach ($available_accounts as $account): ?>
                            <div class="account-card">
                                <div class="account-checkbox">
                                    <input type="checkbox" 
                                           id="account_<?php echo htmlspecialchars($account['id']); ?>" 
                                           name="selected_accounts[]" 
                                           value="<?php echo htmlspecialchars($account['id']); ?>"
                                           class="account-checkbox-input">
                                    <label for="account_<?php echo htmlspecialchars($account['id']); ?>" class="account-checkbox-label"></label>
                                </div>
                                
                                <div class="account-info">
                                    <h3><?php echo htmlspecialchars($account['name']); ?></h3>
                                    <div class="account-details">
                                        <div class="detail-item">
                                            <i class="fas fa-hashtag"></i>
                                            <span>ID: <?php echo htmlspecialchars($account['id']); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-coins"></i>
                                            <span>Currency: <?php echo htmlspecialchars($account['currency'] ?? 'USD'); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-clock"></i>
                                            <span>Timezone: <?php echo htmlspecialchars($account['timezone'] ?? 'UTC'); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-info-circle"></i>
                                            <span>Status: <?php echo htmlspecialchars($account['account_status'] ?? 'ACTIVE'); ?></span>
                                        </div>
                                        <?php if (isset($account['amount_spent'])): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-dollar-sign"></i>
                                            <span>Spent: $<?php echo number_format($account['amount_spent'] / 100, 2); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="selection-actions">
                        <button type="button" class="btn btn-secondary" id="selectAllBtn">
                            <i class="fas fa-check-square"></i>
                            Select All
                        </button>
                        <button type="button" class="btn btn-secondary" id="selectNoneBtn">
                            <i class="fas fa-square"></i>
                            Select None
                        </button>
                        <button type="submit" class="btn btn-primary" id="connectBtn">
                            <i class="fas fa-link"></i>
                            Connect Selected Accounts
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllBtn = document.getElementById('selectAllBtn');
            const selectNoneBtn = document.getElementById('selectNoneBtn');
            const connectBtn = document.getElementById('connectBtn');
            const checkboxes = document.querySelectorAll('.account-checkbox-input');

            // Select All functionality
            selectAllBtn.addEventListener('click', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
                updateConnectButton();
            });

            // Select None functionality
            selectNoneBtn.addEventListener('click', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                updateConnectButton();
            });

            // Update connect button based on selections
            function updateConnectButton() {
                const selectedCount = document.querySelectorAll('.account-checkbox-input:checked').length;
                if (selectedCount > 0) {
                    connectBtn.innerHTML = `<i class="fas fa-link"></i> Connect ${selectedCount} Account${selectedCount > 1 ? 's' : ''}`;
                    connectBtn.disabled = false;
                } else {
                    connectBtn.innerHTML = '<i class="fas fa-link"></i> Connect Selected Accounts';
                    connectBtn.disabled = true;
                }
            }

            // Add event listeners to checkboxes
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateConnectButton);
            });

            // Initial button state
            updateConnectButton();

            // Form submission
            document.querySelector('form').addEventListener('submit', function(e) {
                const selectedCount = document.querySelectorAll('.account-checkbox-input:checked').length;
                if (selectedCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one account to connect.');
                    return false;
                }
                
                connectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';
                connectBtn.disabled = true;
            });
        });
    </script>

    <style>
        .account-selection-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .selection-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .selection-header h2 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .selection-header p {
            color: #666;
            font-size: 16px;
        }

        .accounts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .account-card {
            background: white;
            border: 2px solid #e1e5e9;
            border-radius: 15px;
            padding: 25px;
            display: flex;
            align-items: flex-start;
            gap: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .account-card:hover {
            border-color: #1877f2;
            box-shadow: 0 5px 20px rgba(24, 119, 242, 0.1);
        }

        .account-card.selected {
            border-color: #1877f2;
            background: #f8f9ff;
        }

        .account-checkbox {
            margin-top: 5px;
        }

        .account-checkbox-input {
            display: none;
        }

        .account-checkbox-label {
            width: 20px;
            height: 20px;
            border: 2px solid #ddd;
            border-radius: 4px;
            display: block;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }

        .account-checkbox-input:checked + .account-checkbox-label {
            background: #1877f2;
            border-color: #1877f2;
        }

        .account-checkbox-input:checked + .account-checkbox-label::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        .account-info {
            flex: 1;
        }

        .account-info h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .account-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }

        .detail-item i {
            width: 16px;
            color: #1877f2;
        }

        .selection-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warning-message {
            background: #fff3cd;
            color: #856404;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-message {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .accounts-grid {
                grid-template-columns: 1fr;
            }
            
            .selection-actions {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
