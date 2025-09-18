<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

require_once 'config.php';

// Get campaign ID and account ID from URL
$campaign_id = $_GET['campaign_id'] ?? '';
$account_id = $_GET['account_id'] ?? '';

if (empty($campaign_id) || empty($account_id)) {
    header('Location: accounts.php');
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get account name and campaign name
$account_name = 'Unknown Account';
$campaign_name = 'Unknown Campaign';

try {
    // Get account name
    $stmt = $pdo->prepare("SELECT act_name FROM facebook_ads_accounts WHERE act_id = ?");
    $stmt->execute([$account_id]);
    $account_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account_data && !empty($account_data['act_name'])) {
        $account_name = $account_data['act_name'];
    }
    
    // Get access token
    $stmt = $pdo->prepare("SELECT access_token FROM facebook_access_tokens WHERE admin_id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$token_data || empty($token_data['access_token'])) {
        die("Access token not found. Please update your access token first.");
    }
    
    $access_token = $token_data['access_token'];
    
    // Get campaign name from Facebook API
    try {
        $campaign_api_url = "https://graph.facebook.com/v21.0/{$campaign_id}?fields=name&access_token={$access_token}";
        $campaign_response = file_get_contents($campaign_api_url);
        $campaign_data = json_decode($campaign_response, true);
        
        if (isset($campaign_data['name']) && !empty($campaign_data['name'])) {
            $campaign_name = $campaign_data['name'];
        }
    } catch(Exception $e) {
        error_log("Error fetching campaign name: " . $e->getMessage());
    }
    
} catch(PDOException $e) {
    error_log("Error fetching account data: " . $e->getMessage());
}

// Handle filters
$date_preset = $_GET['date_preset'] ?? 'this_month';
$status_filter = $_GET['status'] ?? 'all';
$budget_filter = $_GET['budget'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'spend';

// Fetch ad sets data from Facebook Graph API
$adsets = [];
$error_message = '';

try {
    // Build API URL
    $api_url = "https://graph.facebook.com/v21.0/{$campaign_id}/adsets?";
    $api_url .= "fields=name,daily_budget,lifetime_budget,status,targeting,start_time,end_time,insights{impressions,reach,clicks,spend,cpc,ctr,cpm,actions}";
    $api_url .= "&date_preset={$date_preset}";
    $api_url .= "&access_token={$access_token}";
    
    // Fetch data
    $response = file_get_contents($api_url);
    $data = json_decode($response, true);
    
    if (isset($data['data']) && is_array($data['data'])) {
        $adsets = $data['data'];
    } else {
        $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Failed to fetch ad sets data';
    }
    
} catch(Exception $e) {
    $error_message = "Error fetching ad sets: " . $e->getMessage();
}

// Helper functions
function formatCurrency($value) {
    if (empty($value) || $value === 0) return 'N/A';
    return '$' . number_format($value, 2);
}

function formatPercentage($value) {
    if (empty($value) || $value === 0) return 'N/A';
    return number_format($value, 2) . '%';
}

function formatNumber($value) {
    if (empty($value) || $value === 0) return 'N/A';
    return number_format($value);
}

function formatDate($value) {
    if (empty($value)) return 'N/A';
    try {
        return date('M j, Y', strtotime($value));
    } catch(Exception $e) {
        return 'N/A';
    }
}

function getStatusClass($status) {
    switch (strtoupper($status)) {
        case 'ACTIVE':
            return 'status-active';
        case 'PAUSED':
            return 'status-paused';
        case 'DELETED':
            return 'status-deleted';
        case 'ARCHIVED':
            return 'status-archived';
        default:
            return 'status-unknown';
    }
}

function getTargetingSummary($targeting) {
    if (empty($targeting)) return 'N/A';
    
    $summary = [];
    
    // Age
    if (isset($targeting['age_min']) && isset($targeting['age_max'])) {
        $summary[] = "Age: {$targeting['age_min']}-{$targeting['age_max']}";
    }
    
    // Gender
    if (isset($targeting['genders'])) {
        $genders = $targeting['genders'];
        if (is_array($genders)) {
            $gender_labels = [];
            foreach ($genders as $gender) {
                switch ($gender) {
                    case 1:
                        $gender_labels[] = 'Male';
                        break;
                    case 2:
                        $gender_labels[] = 'Female';
                        break;
                    default:
                        $gender_labels[] = $gender;
                }
            }
            $summary[] = "Gender: " . implode(', ', $gender_labels);
        } else {
            switch ($genders) {
                case 1:
                    $summary[] = "Gender: Male";
                    break;
                case 2:
                    $summary[] = "Gender: Female";
                    break;
                default:
                    $summary[] = "Gender: {$genders}";
            }
        }
    }
    
    // Interests
    if (isset($targeting['interests']) && is_array($targeting['interests'])) {
        $interest_count = count($targeting['interests']);
        $summary[] = "Interests: {$interest_count} selected";
    }
    
    // Custom Audiences
    if (isset($targeting['custom_audiences']) && is_array($targeting['custom_audiences'])) {
        $ca_count = count($targeting['custom_audiences']);
        $summary[] = "Custom Audiences: {$ca_count}";
    }
    
    // Lookalike Audiences
    if (isset($targeting['lookalike_audiences']) && is_array($targeting['lookalike_audiences'])) {
        $la_count = count($targeting['lookalike_audiences']);
        $summary[] = "Lookalike Audiences: {$la_count}";
    }
    
    return implode(' | ', $summary);
}

function getPlacementSummary($targeting) {
    if (empty($targeting) || !isset($targeting['publisher_platforms'])) {
        return 'N/A';
    }
    
    $platforms = $targeting['publisher_platforms'];
    if (is_array($platforms)) {
        return implode(', ', $platforms);
    }
    
    return $platforms;
}

function applyFilters($adsets, $status_filter, $budget_filter) {
    return array_filter($adsets, function($adset) use ($status_filter, $budget_filter) {
        // Status filter
        if ($status_filter !== 'all' && strtoupper($adset['status'] ?? '') !== strtoupper($status_filter)) {
            return false;
        }
        
        // Budget filter
        if ($budget_filter !== 'all') {
            $budget = $adset['daily_budget'] ?? $adset['lifetime_budget'] ?? 0;
            switch ($budget_filter) {
                case 'low':
                    if ($budget > 50) return false;
                    break;
                case 'medium':
                    if ($budget < 50 || $budget > 200) return false;
                    break;
                case 'high':
                    if ($budget < 200) return false;
                    break;
            }
        }
        
        return true;
    });
}

function applySorting($adsets, $sort_by) {
    usort($adsets, function($a, $b) use ($sort_by) {
        $a_value = 0;
        $b_value = 0;
        
        switch ($sort_by) {
            case 'spend':
                $a_value = $a['insights']['data'][0]['spend'] ?? 0;
                $b_value = $b['insights']['data'][0]['spend'] ?? 0;
                break;
            case 'impressions':
                $a_value = $a['insights']['data'][0]['impressions'] ?? 0;
                $b_value = $b['insights']['data'][0]['impressions'] ?? 0;
                break;
            case 'reach':
                $a_value = $a['insights']['data'][0]['reach'] ?? 0;
                $b_value = $b['insights']['data'][0]['reach'] ?? 0;
                break;
            case 'cpc':
                $a_value = $a['insights']['data'][0]['cpc'] ?? 0;
                $b_value = $b['insights']['data'][0]['cpc'] ?? 0;
                break;
            case 'ctr':
                $a_value = $a['insights']['data'][0]['ctr'] ?? 0;
                $b_value = $b['insights']['data'][0]['ctr'] ?? 0;
                break;
            case 'cpm':
                $a_value = $a['insights']['data'][0]['cpm'] ?? 0;
                $b_value = $b['insights']['data'][0]['cpm'] ?? 0;
                break;
            case 'name':
                $a_value = $a['name'] ?? '';
                $b_value = $b['name'] ?? '';
                return strcmp($a_value, $b_value);
        }
        
        return $b_value <=> $a_value; // Descending order
    });
    
    return $adsets;
}

// Apply filters and sorting
$filtered_adsets = applyFilters($adsets, $status_filter, $budget_filter);
$sorted_adsets = applySorting($filtered_adsets, $sort_by);

// Set page variables for template
$page_title = "Ad Sets - " . htmlspecialchars($campaign_name) . " - JJ Reporting Dashboard";

// Include header template
include 'templates/header.php';
?>

    <!-- Include sidebar template -->
    <?php include 'templates/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="dashboard-content">
        <!-- Top Header -->
        <?php 
        $header_actions = '
            <a href="campaigns.php?account_id=' . urlencode($account_id) . '" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Campaigns
            </a>
        ';
        include 'templates/top_header.php'; 
        ?>

        <!-- Ad Sets Content -->
        <div class="adsets-container">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php else: ?>
                <!-- Filters Section -->
                <div class="filters-section">
                    <div class="filters-card">
                        <form method="GET" class="filters-form">
                            <input type="hidden" name="campaign_id" value="<?php echo htmlspecialchars($campaign_id); ?>">
                            <input type="hidden" name="account_id" value="<?php echo htmlspecialchars($account_id); ?>">
                            
                            <div class="filter-grid">
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
                                    <label for="status">Status:</label>
                                    <select name="status" id="status">
                                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="paused" <?php echo $status_filter === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                        <option value="deleted" <?php echo $status_filter === 'deleted' ? 'selected' : ''; ?>>Deleted</option>
                                        <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="budget">Budget:</label>
                                    <select name="budget" id="budget">
                                        <option value="all" <?php echo $budget_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="low" <?php echo $budget_filter === 'low' ? 'selected' : ''; ?>>Low ($0-$50)</option>
                                        <option value="medium" <?php echo $budget_filter === 'medium' ? 'selected' : ''; ?>>Medium ($50-$200)</option>
                                        <option value="high" <?php echo $budget_filter === 'high' ? 'selected' : ''; ?>>High ($200+)</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="sort_by">Sort By:</label>
                                    <select name="sort_by" id="sort_by">
                                        <option value="spend" <?php echo $sort_by === 'spend' ? 'selected' : ''; ?>>Spend</option>
                                        <option value="impressions" <?php echo $sort_by === 'impressions' ? 'selected' : ''; ?>>Impressions</option>
                                        <option value="reach" <?php echo $sort_by === 'reach' ? 'selected' : ''; ?>>Reach</option>
                                        <option value="cpc" <?php echo $sort_by === 'cpc' ? 'selected' : ''; ?>>CPC</option>
                                        <option value="ctr" <?php echo $sort_by === 'ctr' ? 'selected' : ''; ?>>CTR</option>
                                        <option value="cpm" <?php echo $sort_by === 'cpm' ? 'selected' : ''; ?>>CPM</option>
                                        <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                                    </select>
                                </div>
                                
                                <div class="filter-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <a href="adsets.php?campaign_id=<?php echo urlencode($campaign_id); ?>&account_id=<?php echo urlencode($account_id); ?>" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Section -->
                <div class="results-section">
                    <div class="results-card">
                        <div class="results-header">
                            <h3>Ad Sets (<?php echo count($sorted_adsets); ?> found)</h3>
                            <div class="header-actions">
                                <button class="btn btn-primary" onclick="syncAdSets()" id="syncBtn">
                                    <i class="fas fa-sync-alt"></i> Sync Data
                                </button>
                                <button class="btn btn-success" onclick="exportAdSets()">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                        </div>
                        
                        <?php if (empty($sorted_adsets)): ?>
                            <div class="empty-state">
                                <i class="fas fa-layer-group"></i>
                                <h4>No Ad Sets Found</h4>
                                <p>No ad sets match your current filter criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="adsets-table">
                                    <thead>
                                        <tr>
                                            <th>Ad Set Name</th>
                                            <th>Budget</th>
                                            <th>Status</th>
                                            <th>Targeting Summary</th>
                                            <th>Placement</th>
                                            <th>Impressions</th>
                                            <th>Reach</th>
                                            <th>Spend</th>
                                            <th>CPC</th>
                                            <th>CTR</th>
                                            <th>CPM</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sorted_adsets as $adset): 
                                            $adset_id = $adset['id'] ?? '';
                                            $adset_name = $adset['name'] ?? 'Unknown Ad Set';
                                            $adset_status = $adset['status'] ?? '';
                                            $adset_budget = $adset['daily_budget'] ?? $adset['lifetime_budget'] ?? 0;
                                            $adset_start_time = $adset['start_time'] ?? '';
                                            $adset_end_time = $adset['end_time'] ?? '';
                                            $adset_targeting = $adset['targeting'] ?? [];
                                            
                                            $insights = $adset['insights']['data'][0] ?? [];
                                            $impressions = $insights['impressions'] ?? 0;
                                            $reach = $insights['reach'] ?? 0;
                                            $spend = $insights['spend'] ?? 0;
                                            $cpc = $insights['cpc'] ?? 0;
                                            $ctr = $insights['ctr'] ?? 0;
                                            $cpm = $insights['cpm'] ?? 0;
                                        ?>
                                            <tr>
                                                <td class="adset-name"><?php echo htmlspecialchars($adset_name); ?></td>
                                                <td><?php echo formatCurrency($adset_budget); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo getStatusClass($adset_status); ?>">
                                                        <?php echo htmlspecialchars($adset_status); ?>
                                                    </span>
                                                </td>
                                                <td class="targeting-summary"><?php echo htmlspecialchars(getTargetingSummary($adset_targeting)); ?></td>
                                                <td><?php echo htmlspecialchars(getPlacementSummary($adset_targeting)); ?></td>
                                                <td><?php echo formatNumber($impressions); ?></td>
                                                <td><?php echo formatNumber($reach); ?></td>
                                                <td class="spend-cell"><?php echo formatCurrency($spend); ?></td>
                                                <td class="cpc-cell"><?php echo formatCurrency($cpc); ?></td>
                                                <td class="ctr-cell"><?php echo formatPercentage($ctr); ?></td>
                                                <td class="cpm-cell"><?php echo formatCurrency($cpm); ?></td>
                                                <td><?php echo formatDate($adset_start_time); ?></td>
                                                <td><?php echo formatDate($adset_end_time); ?></td>
                                                <td>
                                                    <div class="table-actions">
                                                        <button class="action-btn" onclick="viewAdSetDetails('<?php echo htmlspecialchars($adset_id); ?>')" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="action-btn" onclick="viewAds('<?php echo htmlspecialchars($adset_id); ?>')" title="View Ads">
                                                            <i class="fas fa-ad"></i>
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
            <?php endif; ?>
        </div>
    </div>
</main>

<?php 
$inline_scripts = '
    function syncAdSets() {
        try {
            const syncBtn = document.getElementById("syncBtn");
            const originalText = syncBtn.innerHTML;
            
            // Show loading state
            syncBtn.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Syncing...";
            syncBtn.disabled = true;
            
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Make AJAX request to sync endpoint
            fetch("sync_adsets.php?" + urlParams.toString(), {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                }
            })
            .then(response => {
                console.log("Response status:", response.status);
                return response.json();
            })
            .then(data => {
                console.log("Response data:", data);
                if (data.success) {
                    alert("Successfully synced " + data.synced_count + " ad sets to database!");
                } else {
                    alert("Error syncing ad sets: " + (data.message || "Unknown error"));
                }
            })
            .catch(error => {
                console.error("Error syncing ad sets:", error);
                alert("Error syncing ad sets. Please try again.");
            })
            .finally(() => {
                // Reset button state
                syncBtn.innerHTML = originalText;
                syncBtn.disabled = false;
            });
        } catch (error) {
            console.error("Error syncing ad sets:", error);
            alert("Error syncing ad sets. Please try again.");
        }
    }
    
    function exportAdSets() {
        try {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Add export parameter
            urlParams.set("export", "csv");
            
            // Redirect to export endpoint
            window.location.href = "adsets_export.php?" + urlParams.toString();
        } catch (error) {
            console.error("Error exporting ad sets:", error);
            alert("Error exporting ad sets. Please try again.");
        }
    }
    
    function viewAdSetDetails(adsetId) {
        try {
            if (!adsetId) {
                alert("Ad Set ID not found.");
                return;
            }
            // Open ad set details in a new window or modal
            window.open("adset_details.php?id=" + encodeURIComponent(adsetId), "_blank");
        } catch (error) {
            console.error("Error viewing ad set details:", error);
            alert("Error opening ad set details. Please try again.");
        }
    }
    
    function viewAds(adsetId) {
        try {
            if (!adsetId) {
                alert("Ad Set ID not found.");
                return;
            }
            // Navigate to ads page
            const urlParams = new URLSearchParams(window.location.search);
            window.location.href = "ads.php?adset_id=" + encodeURIComponent(adsetId) + "&campaign_id=" + urlParams.get("campaign_id") + "&account_id=" + urlParams.get("account_id");
        } catch (error) {
            console.error("Error viewing ads:", error);
            alert("Error opening ads. Please try again.");
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
include 'templates/footer.php'; 
?>