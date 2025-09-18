<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

require_once 'config.php';

// Get adset ID, campaign ID and account ID from URL
$adset_id = $_GET['adset_id'] ?? '';
$campaign_id = $_GET['campaign_id'] ?? '';
$account_id = $_GET['account_id'] ?? '';

if (empty($adset_id) || empty($campaign_id) || empty($account_id)) {
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

// Get account name, campaign name, and adset name
$account_name = 'Unknown Account';
$campaign_name = 'Unknown Campaign';
$adset_name = 'Unknown Ad Set';

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
    
    // Get adset name from Facebook API
    try {
        $adset_api_url = "https://graph.facebook.com/v21.0/{$adset_id}?fields=name&access_token={$access_token}";
        $adset_response = file_get_contents($adset_api_url);
        $adset_data = json_decode($adset_response, true);
        
        if (isset($adset_data['name']) && !empty($adset_data['name'])) {
            $adset_name = $adset_data['name'];
        }
    } catch(Exception $e) {
        error_log("Error fetching adset name: " . $e->getMessage());
    }
    
} catch(PDOException $e) {
    error_log("Error fetching account data: " . $e->getMessage());
}

// Handle filters
$date_preset = $_GET['date_preset'] ?? 'this_month';
$status_filter = $_GET['status'] ?? 'all';
$format_filter = $_GET['format'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'spend';

// Fetch ads data from Facebook Graph API
$ads = [];
$error_message = '';

try {
    // Build API URL
    $api_url = "https://graph.facebook.com/v21.0/{$adset_id}/ads?";
    $api_url .= "fields=id,name,creative{object_story_spec},status,ad_review_feedback,insights{impressions,reach,clicks,ctr,cpc,spend,actions,purchase_roas}";
    $api_url .= "&date_preset={$date_preset}";
    $api_url .= "&access_token={$access_token}";
    
    // Fetch data
    $response = file_get_contents($api_url);
    $data = json_decode($response, true);
    
    if (isset($data['data']) && is_array($data['data'])) {
        $ads = $data['data'];
    } else {
        $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Failed to fetch ads data';
    }
    
} catch(Exception $e) {
    $error_message = "Error fetching ads: " . $e->getMessage();
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

function getAdFormat($creative) {
    if (empty($creative) || !isset($creative['object_story_spec'])) {
        return 'Unknown';
    }
    
    $spec = $creative['object_story_spec'];
    
    if (isset($spec['link_data']['child_attachments'])) {
        return 'Carousel';
    } elseif (isset($spec['video_data'])) {
        return 'Video';
    } elseif (isset($spec['photo_data'])) {
        return 'Single Image';
    } elseif (isset($spec['link_data'])) {
        return 'Link';
    }
    
    return 'Unknown';
}

function getCreativePreview($creative) {
    if (empty($creative) || !isset($creative['object_story_spec'])) {
        return ['type' => 'none', 'url' => '', 'title' => 'No Preview Available'];
    }
    
    $spec = $creative['object_story_spec'];
    
    // Handle different creative types
    if (isset($spec['photo_data']['url'])) {
        return [
            'type' => 'image',
            'url' => $spec['photo_data']['url'],
            'title' => $spec['photo_data']['caption'] ?? 'Image Ad'
        ];
    } elseif (isset($spec['video_data']['video_url'])) {
        return [
            'type' => 'video',
            'url' => $spec['video_data']['video_url'],
            'title' => $spec['video_data']['title'] ?? 'Video Ad'
        ];
    } elseif (isset($spec['link_data']['picture'])) {
        return [
            'type' => 'link',
            'url' => $spec['link_data']['picture'],
            'title' => $spec['link_data']['name'] ?? 'Link Ad'
        ];
    } elseif (isset($spec['link_data']['child_attachments'][0]['picture'])) {
        return [
            'type' => 'carousel',
            'url' => $spec['link_data']['child_attachments'][0]['picture'],
            'title' => 'Carousel Ad'
        ];
    }
    
    return ['type' => 'none', 'url' => '', 'title' => 'No Preview Available'];
}

function getAdCopy($creative) {
    if (empty($creative) || !isset($creative['object_story_spec'])) {
        return ['headline' => 'N/A', 'description' => 'N/A', 'cta' => 'N/A'];
    }
    
    $spec = $creative['object_story_spec'];
    $copy = ['headline' => 'N/A', 'description' => 'N/A', 'cta' => 'N/A'];
    
    // Extract headline
    if (isset($spec['link_data']['name'])) {
        $copy['headline'] = $spec['link_data']['name'];
    } elseif (isset($spec['photo_data']['caption'])) {
        $copy['headline'] = $spec['photo_data']['caption'];
    } elseif (isset($spec['video_data']['title'])) {
        $copy['headline'] = $spec['video_data']['title'];
    }
    
    // Extract description
    if (isset($spec['link_data']['description'])) {
        $copy['description'] = $spec['link_data']['description'];
    } elseif (isset($spec['photo_data']['caption'])) {
        $copy['description'] = $spec['photo_data']['caption'];
    }
    
    // Extract CTA
    if (isset($spec['link_data']['call_to_action']['type'])) {
        $copy['cta'] = $spec['link_data']['call_to_action']['type'];
    }
    
    return $copy;
}

function getEngagementMetrics($insights) {
    if (empty($insights) || !isset($insights['data'][0])) {
        return ['likes' => 0, 'shares' => 0, 'comments' => 0];
    }
    
    $data = $insights['data'][0];
    $actions = $data['actions'] ?? [];
    
    $engagement = ['likes' => 0, 'shares' => 0, 'comments' => 0];
    
    foreach ($actions as $action) {
        switch ($action['action_type']) {
            case 'post_reaction':
                $engagement['likes'] += $action['value'] ?? 0;
                break;
            case 'post_share':
                $engagement['shares'] += $action['value'] ?? 0;
                break;
            case 'post_comment':
                $engagement['comments'] += $action['value'] ?? 0;
                break;
        }
    }
    
    return $engagement;
}

function applyFilters($ads, $status_filter, $format_filter) {
    return array_filter($ads, function($ad) use ($status_filter, $format_filter) {
        // Status filter
        if ($status_filter !== 'all' && strtoupper($ad['status'] ?? '') !== strtoupper($status_filter)) {
            return false;
        }
        
        // Format filter
        if ($format_filter !== 'all') {
            $format = getAdFormat($ad['creative'] ?? []);
            if (strtolower($format) !== strtolower($format_filter)) {
                return false;
            }
        }
        
        return true;
    });
}

function applySorting($ads, $sort_by) {
    usort($ads, function($a, $b) use ($sort_by) {
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
            case 'clicks':
                $a_value = $a['insights']['data'][0]['clicks'] ?? 0;
                $b_value = $b['insights']['data'][0]['clicks'] ?? 0;
                break;
            case 'ctr':
                $a_value = $a['insights']['data'][0]['ctr'] ?? 0;
                $b_value = $b['insights']['data'][0]['ctr'] ?? 0;
                break;
            case 'cpc':
                $a_value = $a['insights']['data'][0]['cpc'] ?? 0;
                $b_value = $b['insights']['data'][0]['cpc'] ?? 0;
                break;
            case 'name':
                $a_value = $a['name'] ?? '';
                $b_value = $b['name'] ?? '';
                return strcmp($a_value, $b_value);
        }
        
        return $b_value <=> $a_value; // Descending order
    });
    
    return $ads;
}

// Apply filters and sorting
$filtered_ads = applyFilters($ads, $status_filter, $format_filter);
$sorted_ads = applySorting($filtered_ads, $sort_by);

// Set page variables for template
$page_title = "Ads - " . htmlspecialchars($adset_name) . " - JJ Reporting Dashboard";

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
            <a href="adsets.php?campaign_id=' . urlencode($campaign_id) . '&account_id=' . urlencode($account_id) . '" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Ad Sets
            </a>
        ';
        include 'templates/top_header.php'; 
        ?>

        <!-- Ads Content -->
        <div class="ads-container">
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
                            <input type="hidden" name="adset_id" value="<?php echo htmlspecialchars($adset_id); ?>">
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
                                    <label for="format">Format:</label>
                                    <select name="format" id="format">
                                        <option value="all" <?php echo $format_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="single image" <?php echo $format_filter === 'single image' ? 'selected' : ''; ?>>Single Image</option>
                                        <option value="video" <?php echo $format_filter === 'video' ? 'selected' : ''; ?>>Video</option>
                                        <option value="carousel" <?php echo $format_filter === 'carousel' ? 'selected' : ''; ?>>Carousel</option>
                                        <option value="link" <?php echo $format_filter === 'link' ? 'selected' : ''; ?>>Link</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="sort_by">Sort By:</label>
                                    <select name="sort_by" id="sort_by">
                                        <option value="spend" <?php echo $sort_by === 'spend' ? 'selected' : ''; ?>>Spend</option>
                                        <option value="impressions" <?php echo $sort_by === 'impressions' ? 'selected' : ''; ?>>Impressions</option>
                                        <option value="reach" <?php echo $sort_by === 'reach' ? 'selected' : ''; ?>>Reach</option>
                                        <option value="clicks" <?php echo $sort_by === 'clicks' ? 'selected' : ''; ?>>Clicks</option>
                                        <option value="ctr" <?php echo $sort_by === 'ctr' ? 'selected' : ''; ?>>CTR</option>
                                        <option value="cpc" <?php echo $sort_by === 'cpc' ? 'selected' : ''; ?>>CPC</option>
                                        <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                                    </select>
                                </div>
                                
                                <div class="filter-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <a href="ads.php?adset_id=<?php echo urlencode($adset_id); ?>&campaign_id=<?php echo urlencode($campaign_id); ?>&account_id=<?php echo urlencode($account_id); ?>" class="btn btn-secondary">
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
                            <h3>Ads (<?php echo count($sorted_ads); ?> found)</h3>
                            <div class="header-actions">
                                <button class="btn btn-primary" onclick="syncAds()" id="syncAdsBtn">
                                    <i class="fas fa-sync-alt"></i> Sync Data
                                </button>
                                <button class="btn btn-success" onclick="exportAds()">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                        </div>
                        
                        <?php if (empty($sorted_ads)): ?>
                            <div class="empty-state">
                                <i class="fas fa-ad"></i>
                                <h4>No Ads Found</h4>
                                <p>No ads match your current filter criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="ads-grid">
                                <?php foreach ($sorted_ads as $ad): 
                                    $ad_id = $ad['id'] ?? '';
                                    $ad_name = $ad['name'] ?? 'Unknown Ad';
                                    $ad_status = $ad['status'] ?? '';
                                    $creative = $ad['creative'] ?? [];
                                    $insights = $ad['insights']['data'][0] ?? [];
                                    
                                    $preview = getCreativePreview($creative);
                                    $copy = getAdCopy($creative);
                                    $format = getAdFormat($creative);
                                    $engagement = getEngagementMetrics($insights);
                                    
                                    $impressions = $insights['impressions'] ?? 0;
                                    $reach = $insights['reach'] ?? 0;
                                    $clicks = $insights['clicks'] ?? 0;
                                    $ctr = $insights['ctr'] ?? 0;
                                    $cpc = $insights['cpc'] ?? 0;
                                    $spend = $insights['spend'] ?? 0;
                                    $roas = $insights['purchase_roas'] ?? 0;
                                ?>
                                    <div class="ad-card">
                                        <div class="ad-header">
                                            <div class="ad-title">
                                                <h4><?php echo htmlspecialchars($ad_name); ?></h4>
                                                <span class="ad-id">ID: <?php echo htmlspecialchars($ad_id); ?></span>
                                            </div>
                                            <div class="ad-status">
                                                <span class="status-badge <?php echo getStatusClass($ad_status); ?>">
                                                    <?php echo htmlspecialchars($ad_status); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="ad-content">
                                            <div class="creative-preview">
                                                <?php if ($preview['type'] === 'image'): ?>
                                                    <img src="<?php echo htmlspecialchars($preview['url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($preview['title']); ?>"
                                                         class="preview-image"
                                                         onclick="openPreviewModal('<?php echo htmlspecialchars($preview['url']); ?>', '<?php echo htmlspecialchars($preview['title']); ?>')">
                                                <?php elseif ($preview['type'] === 'video'): ?>
                                                    <video class="preview-video" controls>
                                                        <source src="<?php echo htmlspecialchars($preview['url']); ?>" type="video/mp4">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                <?php elseif ($preview['type'] === 'carousel'): ?>
                                                    <div class="preview-carousel">
                                                        <img src="<?php echo htmlspecialchars($preview['url']); ?>" 
                                                             alt="<?php echo htmlspecialchars($preview['title']); ?>"
                                                             class="preview-image"
                                                             onclick="openPreviewModal('<?php echo htmlspecialchars($preview['url']); ?>', '<?php echo htmlspecialchars($preview['title']); ?>')">
                                                        <div class="carousel-indicator">Carousel</div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="preview-placeholder">
                                                        <i class="fas fa-image"></i>
                                                        <span>No Preview</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="ad-details">
                                                <div class="ad-copy">
                                                    <div class="copy-item">
                                                        <strong>Headline:</strong> <?php echo htmlspecialchars($copy['headline']); ?>
                                                    </div>
                                                    <div class="copy-item">
                                                        <strong>Description:</strong> <?php echo htmlspecialchars($copy['description']); ?>
                                                    </div>
                                                    <div class="copy-item">
                                                        <strong>CTA:</strong> <?php echo htmlspecialchars($copy['cta']); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="ad-metrics">
                                                    <div class="metric-row">
                                                        <div class="metric-item">
                                                            <span class="metric-label">Format:</span>
                                                            <span class="metric-value"><?php echo htmlspecialchars($format); ?></span>
                                                        </div>
                                                        <div class="metric-item">
                                                            <span class="metric-label">Impressions:</span>
                                                            <span class="metric-value"><?php echo formatNumber($impressions); ?></span>
                                                        </div>
                                                        <div class="metric-item">
                                                            <span class="metric-label">Reach:</span>
                                                            <span class="metric-value"><?php echo formatNumber($reach); ?></span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="metric-row">
                                                        <div class="metric-item">
                                                            <span class="metric-label">Clicks:</span>
                                                            <span class="metric-value"><?php echo formatNumber($clicks); ?></span>
                                                        </div>
                                                        <div class="metric-item">
                                                            <span class="metric-label">CTR:</span>
                                                            <span class="metric-value ctr-value"><?php echo formatPercentage($ctr); ?></span>
                                                        </div>
                                                        <div class="metric-item">
                                                            <span class="metric-label">CPC:</span>
                                                            <span class="metric-value cpc-value"><?php echo formatCurrency($cpc); ?></span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="metric-row">
                                                        <div class="metric-item">
                                                            <span class="metric-label">Spend:</span>
                                                            <span class="metric-value spend-value"><?php echo formatCurrency($spend); ?></span>
                                                        </div>
                                                        <div class="metric-item">
                                                            <span class="metric-label">ROAS:</span>
                                                            <span class="metric-value roas-value"><?php echo formatNumber($roas); ?>x</span>
                                                        </div>
                                                        <div class="metric-item">
                                                            <span class="metric-label">Engagement:</span>
                                                            <span class="metric-value"><?php echo formatNumber($engagement['likes'] + $engagement['shares'] + $engagement['comments']); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="ad-actions">
                                            <button class="action-btn" onclick="viewAdDetails('<?php echo htmlspecialchars($ad_id); ?>')" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn" onclick="viewCreativeHistory('<?php echo htmlspecialchars($ad_id); ?>')" title="Creative History">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Preview Modal -->
<div id="previewModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closePreviewModal()">&times;</span>
        <img id="modalImage" src="" alt="" class="modal-image">
        <div class="modal-caption" id="modalCaption"></div>
    </div>
</div>

<?php 
$inline_scripts = '
    function syncAds() {
        try {
            const syncBtn = document.getElementById("syncAdsBtn");
            const originalText = syncBtn.innerHTML;
            
            // Show loading state
            syncBtn.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Syncing...";
            syncBtn.disabled = true;
            
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Make AJAX request to sync endpoint
            fetch("sync_ads.php?" + urlParams.toString(), {
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
                    alert("Successfully synced " + data.synced_count + " ads to database!");
                } else {
                    alert("Error syncing ads: " + (data.message || "Unknown error"));
                }
            })
            .catch(error => {
                console.error("Error syncing ads:", error);
                alert("Error syncing ads. Please try again.");
            })
            .finally(() => {
                // Reset button state
                syncBtn.innerHTML = originalText;
                syncBtn.disabled = false;
            });
        } catch (error) {
            console.error("Error syncing ads:", error);
            alert("Error syncing ads. Please try again.");
        }
    }
    
    function exportAds() {
        try {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Add export parameter
            urlParams.set("export", "csv");
            
            // Redirect to export endpoint
            window.location.href = "ads_export.php?" + urlParams.toString();
        } catch (error) {
            console.error("Error exporting ads:", error);
            alert("Error exporting ads. Please try again.");
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
    
    function viewCreativeHistory(adId) {
        try {
            if (!adId) {
                alert("Ad ID not found.");
                return;
            }
            // Open creative history in a new window or modal
            window.open("creative_history.php?id=" + encodeURIComponent(adId), "_blank");
        } catch (error) {
            console.error("Error viewing creative history:", error);
            alert("Error opening creative history. Please try again.");
        }
    }
    
    
    function openPreviewModal(imageUrl, title) {
        try {
            const modal = document.getElementById("previewModal");
            const modalImage = document.getElementById("modalImage");
            const modalCaption = document.getElementById("modalCaption");
            
            modalImage.src = imageUrl;
            modalCaption.textContent = title;
            modal.style.display = "block";
        } catch (error) {
            console.error("Error opening preview modal:", error);
        }
    }
    
    function closePreviewModal() {
        try {
            const modal = document.getElementById("previewModal");
            modal.style.display = "none";
        } catch (error) {
            console.error("Error closing preview modal:", error);
        }
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById("previewModal");
        if (event.target === modal) {
            modal.style.display = "none";
        }
    };
    
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


