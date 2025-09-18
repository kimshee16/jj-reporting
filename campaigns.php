<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Get account ID from URL parameter
$account_id = $_GET['account_id'] ?? '';
if (empty($account_id)) {
    header('Location: accounts.php');
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

// Get account name from facebook_ads_accounts table
$account_name = 'Unknown Account';
try {
    // Debug: Log the account_id being used
    error_log("Looking for account with ID: " . $account_id);
    
    $stmt = $pdo->prepare("SELECT act_name FROM facebook_ads_accounts WHERE act_id = ?");
    $stmt->execute([$account_id]);
    $account_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account_data && !empty($account_data['act_name'])) {
        $account_name = $account_data['act_name'];
        error_log("Found account name: " . $account_name);
    } else {
        error_log("No account found with ID: " . $account_id);
        
        // Try alternative table names in case the table structure is different
        $alternative_tables = ['ad_accounts', 'facebook_accounts', 'accounts'];
        foreach ($alternative_tables as $table) {
            try {
                $stmt = $pdo->prepare("SELECT act_name, account_name, name FROM {$table} WHERE act_id = ? OR id = ?");
                $stmt->execute([$account_id, $account_id]);
                $alt_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($alt_data) {
                    $account_name = $alt_data['act_name'] ?? $alt_data['account_name'] ?? $alt_data['name'] ?? 'Unknown Account';
                    error_log("Found account in {$table}: " . $account_name);
                    break;
                }
            } catch (PDOException $e) {
                // Table doesn't exist, try next one
                continue;
            }
        }
    }
} catch(PDOException $e) {
    error_log("Error fetching account name: " . $e->getMessage());
}

// Handle filters
$date_preset = $_GET['date_preset'] ?? 'this_month';
$status_filter = $_GET['status'] ?? 'all';
$objective_filter = $_GET['objective'] ?? 'all';
$min_spend = $_GET['min_spend'] ?? '';
$max_spend = $_GET['max_spend'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'spend';

// Build Facebook Graph API URL
$api_url = "https://graph.facebook.com/v21.0/{$account_id}/campaigns";
$fields = "id,name,objective,status,daily_budget,lifetime_budget,start_time,stop_time,insights{spend,actions,purchase_roas,ctr,cpc,cpm}";
$params = [
    'fields' => $fields,
    'date_preset' => $date_preset,
    'access_token' => $access_token
];

$api_url .= '?' . http_build_query($params);

// Fetch campaigns data - try database first, then API
$campaigns = [];
$error_message = '';

// First, try to get campaigns from local database
try {
    require_once 'classes/CampaignSync.php';
    $campaign_sync = new CampaignSync($pdo, $access_token);
    
    $filters = [
        'status' => $status_filter,
        'objective' => $objective_filter,
        'min_spend' => $min_spend,
        'max_spend' => $max_spend,
        'sort_by' => $sort_by
    ];
    
    $campaigns = $campaign_sync->getCampaigns($account_id, $filters);
    
    // If no campaigns in database, try to sync from API
    if (empty($campaigns) && !empty($access_token)) {
        $sync_result = $campaign_sync->syncCampaigns($account_id, $date_preset);
        if ($sync_result['success']) {
            $campaigns = $campaign_sync->getCampaigns($account_id, $filters);
        }
    }
    
} catch (Exception $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// If still no campaigns and we have access token, try direct API call
if (empty($campaigns) && !empty($access_token) && empty($error_message)) {
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
        
        if (isset($data['data']) && is_array($data['data'])) {
            $campaigns = $data['data'];
            
            // Apply filters
            $campaigns = applyFilters($campaigns, $status_filter, $objective_filter, $min_spend, $max_spend);
            
            // Apply sorting
            $campaigns = applySorting($campaigns, $sort_by);
        } elseif (isset($data['error'])) {
            $error_message = 'API Error: ' . ($data['error']['message'] ?? 'Unknown error');
        } else {
            $error_message = 'No campaigns found for this account.';
        }
    } else {
        $error_message = 'Failed to fetch campaigns data. Please check your access token and try again.';
    }
} elseif (empty($access_token)) {
    $error_message = 'No access token found. Please update your access token in the Access Tokens page.';
}

function applyFilters($campaigns, $status_filter, $objective_filter, $min_spend, $max_spend) {
    return array_filter($campaigns, function($campaign) use ($status_filter, $objective_filter, $min_spend, $max_spend) {
        // Status filter
        if ($status_filter !== 'all' && ($campaign['status'] ?? '') !== $status_filter) {
            return false;
        }
        
        // Objective filter
        if ($objective_filter !== 'all' && ($campaign['objective'] ?? '') !== $objective_filter) {
            return false;
        }
        
        // Spend filters
        $spend = 0;
        if (isset($campaign['insights']['data'][0]['spend'])) {
            $spend = floatval($campaign['insights']['data'][0]['spend']);
        }
        
        if (!empty($min_spend) && $spend < floatval($min_spend)) {
            return false;
        }
        
        if (!empty($max_spend) && $spend > floatval($max_spend)) {
            return false;
        }
        
        return true;
    });
}

function applySorting($campaigns, $sort_by) {
    usort($campaigns, function($a, $b) use ($sort_by) {
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
            case 'name':
                $a_value = strtolower($a['name'] ?? '');
                $b_value = strtolower($b['name'] ?? '');
                return strcmp($a_value, $b_value);
        }
        
        return $b_value <=> $a_value; // Descending order
    });
    
    return $campaigns;
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

// Set page variables for template
$page_title = "Campaigns - " . htmlspecialchars($account_name) . " - JJ Reporting Dashboard";

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
            <button class="btn btn-outline" onclick="openExportModal()">
                <i class="fas fa-download"></i>
                Export Data
            </button>
            <a href="accounts.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back to Accounts
            </a>';
        include 'templates/top_header.php'; 
        ?>

        <!-- Campaigns Content -->
        <div class="dashboard-content">
            <div class="campaigns-container">
                <!-- Filters Section -->
                <div class="filters-section">
                    <div class="filters-card">
                        <form method="GET" class="filters-form">
                            <input type="hidden" name="account_id" value="<?php echo htmlspecialchars($account_id); ?>">
                            
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
                                    <label for="status">Status</label>
                                    <select name="status" id="status">
                                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="ACTIVE" <?php echo $status_filter === 'ACTIVE' ? 'selected' : ''; ?>>Active</option>
                                        <option value="PAUSED" <?php echo $status_filter === 'PAUSED' ? 'selected' : ''; ?>>Paused</option>
                                        <option value="COMPLETED" <?php echo $status_filter === 'COMPLETED' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="objective">Objective</label>
                                    <select name="objective" id="objective">
                                        <option value="all" <?php echo $objective_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="CONVERSIONS" <?php echo $objective_filter === 'CONVERSIONS' ? 'selected' : ''; ?>>Conversions</option>
                                        <option value="TRAFFIC" <?php echo $objective_filter === 'TRAFFIC' ? 'selected' : ''; ?>>Traffic</option>
                                        <option value="AWARENESS" <?php echo $objective_filter === 'AWARENESS' ? 'selected' : ''; ?>>Awareness</option>
                                        <option value="REACH" <?php echo $objective_filter === 'REACH' ? 'selected' : ''; ?>>Reach</option>
                                        <option value="LEAD_GENERATION" <?php echo $objective_filter === 'LEAD_GENERATION' ? 'selected' : ''; ?>>Lead Generation</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="min_spend">Min Spend</label>
                                    <input type="number" name="min_spend" id="min_spend" value="<?php echo htmlspecialchars($min_spend); ?>" placeholder="0" step="0.01">
                                </div>
                                
                                <div class="filter-group">
                                    <label for="max_spend">Max Spend</label>
                                    <input type="number" name="max_spend" id="max_spend" value="<?php echo htmlspecialchars($max_spend); ?>" placeholder="1000" step="0.01">
                                </div>
                                
                                <div class="filter-group">
                                    <label for="sort_by">Sort By</label>
                                    <select name="sort_by" id="sort_by">
                                        <option value="spend" <?php echo $sort_by === 'spend' ? 'selected' : ''; ?>>Spend</option>
                                        <option value="roas" <?php echo $sort_by === 'roas' ? 'selected' : ''; ?>>ROAS</option>
                                        <option value="ctr" <?php echo $sort_by === 'ctr' ? 'selected' : ''; ?>>CTR</option>
                                        <option value="cpc" <?php echo $sort_by === 'cpc' ? 'selected' : ''; ?>>CPC</option>
                                        <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                                    </select>
                                </div>
                                
                                <div class="filter-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply
                                    </button>
                                    <a href="campaigns.php?account_id=<?php echo htmlspecialchars($account_id); ?>" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Section -->
                <div class="results-section">
                    <div class="results-header">
                        <h2>Campaigns (<?php echo count($campaigns); ?> found)</h2>
                        <div class="results-actions">
                            <a href="sync_campaigns.php?account_id=<?php echo urlencode($account_id); ?>" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i>
                                Sync Data
                            </a>
                            <button class="btn btn-success" onclick="exportCampaigns()">
                                <i class="fas fa-download"></i>
                                Export
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($error_message)): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php elseif (empty($campaigns)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <h3>No Campaigns Found</h3>
                            <p>No campaigns match your current filters or there are no campaigns in this account.</p>
                        </div>
                    <?php else: ?>
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
                                        <th>CPC</th>
                                        <th>CPM</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <?php 
                                        $insights = $campaign['insights']['data'][0] ?? [];
                                        $spend = $insights['spend'] ?? 0;
                                        $roas = $insights['purchase_roas'] ?? 0;
                                        $ctr = $insights['ctr'] ?? 0;
                                        $cpc = $insights['cpc'] ?? 0;
                                        $cpm = $insights['cpm'] ?? 0;
                                        $actions = $insights['actions'] ?? [];
                                        
                                        // Safe access to campaign data
                                        $campaign_name = $campaign['name'] ?? 'Unknown Campaign';
                                        $campaign_objective = $campaign['objective'] ?? 'Unknown';
                                        $campaign_status = $campaign['status'] ?? 'Unknown';
                                        $campaign_daily_budget = $campaign['daily_budget'] ?? null;
                                        $campaign_lifetime_budget = $campaign['lifetime_budget'] ?? null;
                                        $campaign_start_time = $campaign['start_time'] ?? null;
                                        $campaign_stop_time = $campaign['stop_time'] ?? null;
                                        $campaign_id = $campaign['campaign_id'] ?? '';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="campaign-name">
                                                    <?php echo htmlspecialchars($campaign_name); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="objective-badge objective-<?php echo strtolower($campaign_objective); ?>">
                                                    <?php echo htmlspecialchars($campaign_objective); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo getStatusClass($campaign_status); ?>">
                                                    <?php echo htmlspecialchars($campaign_status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($campaign_daily_budget)): ?>
                                                    <?php echo formatCurrency($campaign_daily_budget); ?>/day
                                                <?php elseif (!empty($campaign_lifetime_budget)): ?>
                                                    <?php echo formatCurrency($campaign_lifetime_budget); ?> lifetime
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
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
                                            <td class="cpm-cell">
                                                <?php echo $cpm > 0 ? formatCurrency($cpm) : 'N/A'; ?>
                                            </td>
                                            <td>
                                                <?php echo formatDate($campaign_start_time); ?>
                                            </td>
                                            <td>
                                                <?php echo formatDate($campaign_stop_time); ?>
                                            </td>
                                            <td>
                                                <div class="table-actions">
                                                    <button class="action-btn" onclick="viewAdSets('<?php echo htmlspecialchars($campaign_id); ?>', '<?php echo htmlspecialchars($account_id); ?>')" title="View Ad Sets">
                                                        <i class="fas fa-layer-group"></i>
                                                    </button>
                                                    <button class="action-btn" onclick="viewCampaignDetails('<?php echo htmlspecialchars($campaign_id); ?>')" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="action-btn" onclick="addToReport('<?php echo htmlspecialchars($campaign_id); ?>')" title="Add to Report">
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

    <?php 
    $inline_scripts = '
        function exportCampaigns() {
            try {
                // Open the export modal instead of redirecting
                openExportModal();
            } catch (error) {
                console.error("Error exporting campaigns:", error);
                alert("Error exporting campaigns. Please try again.");
            }
        }
        
        function viewAdSets(campaignId, accountId) {
            try {
                if (!campaignId) {
                    alert("Campaign ID not found.");
                    return;
                }
                if (!accountId) {
                    alert("Account ID not found.");
                    return;
                }
                // Navigate to ad sets page
                window.location.href = "adsets.php?campaign_id=" + encodeURIComponent(campaignId) + "&account_id=" + encodeURIComponent(accountId);
            } catch (error) {
                console.error("Error viewing ad sets:", error);
                alert("Error opening ad sets. Please try again.");
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
            <h2>Export Campaigns Data</h2>
            <button class="modal-close" onclick="closeExportModal()">&times;</button>
        </div>
        <form id="exportForm" method="POST" action="export_handler.php">
            <div class="modal-body">
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="export_type" value="campaigns">
                <input type="hidden" name="filters[account_filter]" value="<?php echo htmlspecialchars($account_id); ?>">
                
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
                            <input type="checkbox" name="selected_fields[]" value="name" checked>
                            <span class="checkmark"></span>
                            Campaign Name
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="status" checked>
                            <span class="checkmark"></span>
                            Status
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
