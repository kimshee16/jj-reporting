<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

// Include database configuration
require_once 'config.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
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

// Get Facebook pages
$pages = [];
$error_message = '';

if (!empty($access_token)) {
    try {
        $api_url = "https://graph.facebook.com/v21.0/me/accounts?access_token=" . $access_token;
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
                $pages = $data['data'];
            } elseif (isset($data['error'])) {
                $error_message = 'Facebook API Error: ' . ($data['error']['message'] ?? 'Unknown error');
            }
        } else {
            $error_message = 'Failed to fetch Facebook pages. Please check your access token.';
        }
    } catch (Exception $e) {
        $error_message = 'Error fetching pages: ' . $e->getMessage();
    }
}

// Handle filters
$page_id = $_GET['page_id'] ?? '';
$post_type = $_GET['post_type'] ?? 'all';
$date_range = $_GET['date_range'] ?? 'last_30_days';
$format_filter = $_GET['format'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'created_time';

// Get posts data
$posts = [];
$summary_stats = [
    'total_posts' => 0,
    'total_reach' => 0,
    'total_likes' => 0,
    'total_comments' => 0,
    'total_saves' => 0,
    'organic_reach' => 0,
    'paid_reach' => 0,
    'avg_engagement_rate' => 0
];

if (!empty($page_id) && !empty($access_token)) {
    try {
        // Date range for published_posts
        $since = '';
        $until = '';
        $now = time();
        switch ($date_range) {
            case 'last_7_days':
                $since = date('Y-m-d', $now - 7 * 24 * 60 * 60);
                break;
            case 'last_30_days':
                $since = date('Y-m-d', $now - 30 * 24 * 60 * 60);
                break;
            case 'last_90_days':
                $since = date('Y-m-d', $now - 90 * 24 * 60 * 60);
                break;
        }
        $until = date('Y-m-d', $now);

        // Build published_posts API URL
        $posts_url = "https://graph.facebook.com/v20.0/{$page_id}/published_posts";
        $params = [
            'fields' => 'id,message,created_time,permalink_url',
            'since' => $since,
            'until' => $until,
            'limit' => 50,
            'access_token' => $access_token
        ];
        $posts_url .= '?' . http_build_query($params);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: JJ-Reporting-Dashboard/1.0',
                'timeout' => 30
            ]
        ]);

        $response = @file_get_contents($posts_url, false, $context);

        if ($response !== false) {
            $data = json_decode($response, true);
            if (isset($data['data']) && is_array($data['data'])) {
                $posts = $data['data'];
                // For each post, fetch insights
                foreach ($posts as &$post) {
                    $insights_url = "https://graph.facebook.com/v20.0/{$post['id']}/insights?metric=post_impressions_organic,post_impressions_paid,post_engaged_users,post_reactions_by_type_total,post_saves&access_token={$access_token}";
                    $insights_response = @file_get_contents($insights_url, false, $context);
                    $insights_data = $insights_response ? json_decode($insights_response, true) : [];
                    $post['metrics'] = [];
                    if (isset($insights_data['data'])) {
                        foreach ($insights_data['data'] as $insight) {
                            $post['metrics'][$insight['name']] = $insight['values'][0]['value'] ?? 0;
                        }
                    }
                    // Determine post format (basic)
                    $post['format'] = 'text';
                    if (strpos($post['message'] ?? '', 'video') !== false) {
                        $post['format'] = 'video';
                    } elseif (strpos($post['message'] ?? '', 'photo') !== false) {
                        $post['format'] = 'image';
                    }
                    // Calculate engagement rate
                    $reach = $post['metrics']['post_impressions_organic'] + $post['metrics']['post_impressions_paid'];
                    $likes = $post['metrics']['post_reactions_by_type_total']['like'] ?? 0;
                    $comments = $post['metrics']['post_reactions_by_type_total']['comment'] ?? 0;
                    $saves = $post['metrics']['post_saves'] ?? 0;
                    $total_engagement = $likes + $comments + $saves;
                    $post['engagement_rate'] = $reach > 0 ? ($total_engagement / $reach) * 100 : 0;
                    $post['organic_reach'] = $post['metrics']['post_impressions_organic'] ?? 0;
                    $post['paid_reach'] = $post['metrics']['post_impressions_paid'] ?? 0;
                }
                // Apply filters
                $posts = applyContentFilters($posts, $post_type, $format_filter, $date_range);
                // Apply sorting
                $posts = applyContentSorting($posts, $sort_by);
                // Calculate summary stats
                $summary_stats = calculateContentSummaryStats($posts);
            } elseif (isset($data['error'])) {
                $error_message = 'Facebook API Error: ' . ($data['error']['message'] ?? 'Unknown error');
            } else {
                $error_message = 'No posts found for this page.';
            }
        } else {
            $error_message = 'Failed to fetch posts data. Please check your access token and try again.';
        }
    } catch (Exception $e) {
        $error_message = 'Error fetching posts: ' . $e->getMessage();
    }
}

function applyContentFilters($posts, $post_type, $format_filter, $date_range) {
    return array_filter($posts, function($post) use ($post_type, $format_filter, $date_range) {
        // Post type filter
        if ($post_type !== 'all') {
            if ($post_type === 'organic' && ($post['paid_reach'] ?? 0) > 0) {
                return false;
            }
            if ($post_type === 'paid' && ($post['paid_reach'] ?? 0) == 0) {
                return false;
            }
        }
        
        // Format filter
        if ($format_filter !== 'all' && ($post['format'] ?? '') !== $format_filter) {
            return false;
        }
        
        // Date range filter
        $post_date = strtotime($post['created_time'] ?? '');
        $now = time();
        
        switch ($date_range) {
            case 'last_7_days':
                if ($post_date < ($now - 7 * 24 * 60 * 60)) return false;
                break;
            case 'last_30_days':
                if ($post_date < ($now - 30 * 24 * 60 * 60)) return false;
                break;
            case 'last_90_days':
                if ($post_date < ($now - 90 * 24 * 60 * 60)) return false;
                break;
        }
        
        return true;
    });
}

function applyContentSorting($posts, $sort_by) {
    usort($posts, function($a, $b) use ($sort_by) {
        switch ($sort_by) {
            case 'created_time':
                return strtotime($b['created_time'] ?? '') - strtotime($a['created_time'] ?? '');
            case 'reach':
                return ($b['metrics']['reach'] ?? 0) - ($a['metrics']['reach'] ?? 0);
            case 'engagement_rate':
                return ($b['engagement_rate'] ?? 0) - ($a['engagement_rate'] ?? 0);
            case 'likes':
                return ($b['metrics']['likes'] ?? 0) - ($a['metrics']['likes'] ?? 0);
            case 'comments':
                return ($b['metrics']['comments'] ?? 0) - ($a['metrics']['comments'] ?? 0);
            default:
                return 0;
        }
    });
    
    return $posts;
}

function calculateContentSummaryStats($posts) {
    $stats = [
        'total_posts' => count($posts),
        'total_reach' => 0,
        'total_likes' => 0,
        'total_comments' => 0,
        'total_saves' => 0,
        'organic_reach' => 0,
        'paid_reach' => 0,
        'avg_engagement_rate' => 0
    ];
    
    $total_engagement = 0;
    
    foreach ($posts as $post) {
        $stats['total_reach'] += $post['metrics']['reach'] ?? 0;
        $stats['total_likes'] += $post['metrics']['likes'] ?? 0;
        $stats['total_comments'] += $post['metrics']['comments'] ?? 0;
        $stats['total_saves'] += $post['metrics']['saves'] ?? 0;
        $stats['organic_reach'] += $post['organic_reach'] ?? 0;
        $stats['paid_reach'] += $post['paid_reach'] ?? 0;
        
        $total_engagement += ($post['metrics']['likes'] ?? 0) + 
                           ($post['metrics']['comments'] ?? 0) + 
                           ($post['metrics']['shares'] ?? 0) + 
                           ($post['metrics']['saves'] ?? 0);
    }
    
    if ($stats['total_reach'] > 0) {
        $stats['avg_engagement_rate'] = ($total_engagement / $stats['total_reach']) * 100;
    }
    
    return $stats;
}

function formatNumber($number) {
    if ($number >= 1000000) {
        return number_format($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return number_format($number / 1000, 1) . 'K';
    }
    return number_format($number);
}

function formatPercentage($value) {
    return number_format($value, 2) . '%';
}

function formatDate($date_string) {
    if (empty($date_string)) return 'N/A';
    return date('M j, Y', strtotime($date_string));
}

// Set page variables for template
$page_title = "Content Performance - JJ Reporting Dashboard";

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
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>';
        include 'templates/top_header.php'; 
        ?>

        <!-- Content Performance Content -->
        <div class="dashboard-content">
            <div class="content-performance-container">
                <!-- Filters Section -->
                <div class="filters-section">
                    <div class="filters-card">
                        <form method="GET" class="filters-form">
                            <div class="filter-grid">
                                <div class="filter-group">
                                    <label for="page_id">Facebook Page</label>
                                    <select name="page_id" id="page_id" required>
                                        <option value="">Select a Page</option>
                                        <?php foreach ($pages as $page): ?>
                                            <option value="<?php echo htmlspecialchars($page['id'] ?? ''); ?>" 
                                                    <?php echo $page_id === ($page['id'] ?? '') ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($page['name'] ?? 'Unknown Page'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="post_type">Post Type</label>
                                    <select name="post_type" id="post_type">
                                        <option value="all" <?php echo $post_type === 'all' ? 'selected' : ''; ?>>All Posts</option>
                                        <option value="organic" <?php echo $post_type === 'organic' ? 'selected' : ''; ?>>Organic Only</option>
                                        <option value="paid" <?php echo $post_type === 'paid' ? 'selected' : ''; ?>>Paid Only</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="date_range">Date Range</label>
                                    <select name="date_range" id="date_range">
                                        <option value="last_7_days" <?php echo $date_range === 'last_7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                                        <option value="last_30_days" <?php echo $date_range === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                        <option value="last_90_days" <?php echo $date_range === 'last_90_days' ? 'selected' : ''; ?>>Last 90 Days</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="format">Content Format</label>
                                    <select name="format" id="format">
                                        <option value="all" <?php echo $format_filter === 'all' ? 'selected' : ''; ?>>All Formats</option>
                                        <option value="image" <?php echo $format_filter === 'image' ? 'selected' : ''; ?>>Images</option>
                                        <option value="video" <?php echo $format_filter === 'video' ? 'selected' : ''; ?>>Videos</option>
                                        <option value="text" <?php echo $format_filter === 'text' ? 'selected' : ''; ?>>Text Only</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="sort_by">Sort By</label>
                                    <select name="sort_by" id="sort_by">
                                        <option value="created_time" <?php echo $sort_by === 'created_time' ? 'selected' : ''; ?>>Date Created</option>
                                        <option value="reach" <?php echo $sort_by === 'reach' ? 'selected' : ''; ?>>Reach</option>
                                        <option value="engagement_rate" <?php echo $sort_by === 'engagement_rate' ? 'selected' : ''; ?>>Engagement Rate</option>
                                        <option value="likes" <?php echo $sort_by === 'likes' ? 'selected' : ''; ?>>Likes</option>
                                        <option value="comments" <?php echo $sort_by === 'comments' ? 'selected' : ''; ?>>Comments</option>
                                    </select>
                                </div>
                                
                                <div class="filter-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <a href="content_performance.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Stats -->
                <?php if (!empty($posts)): ?>
                <div class="summary-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $summary_stats['total_posts']; ?></div>
                            <div class="stat-label">Total Posts</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo formatNumber($summary_stats['total_reach']); ?></div>
                            <div class="stat-label">Total Reach</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo formatNumber($summary_stats['total_likes']); ?></div>
                            <div class="stat-label">Total Likes</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo formatNumber($summary_stats['total_comments']); ?></div>
                            <div class="stat-label">Total Comments</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-bookmark"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo formatNumber($summary_stats['total_saves']); ?></div>
                            <div class="stat-label">Total Saves</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo formatPercentage($summary_stats['avg_engagement_rate']); ?></div>
                            <div class="stat-label">Avg Engagement Rate</div>
                        </div>
                    </div>
                </div>

                <!-- Organic vs Paid Split -->
                <div class="organic-paid-split">
                    <div class="split-card">
                        <h3>Organic vs Paid Reach</h3>
                        <div class="split-stats">
                            <div class="split-item organic">
                                <div class="split-label">Organic</div>
                                <div class="split-value"><?php echo formatNumber($summary_stats['organic_reach']); ?></div>
                                <div class="split-percentage">
                                    <?php 
                                    $total_reach = $summary_stats['organic_reach'] + $summary_stats['paid_reach'];
                                    echo $total_reach > 0 ? formatPercentage(($summary_stats['organic_reach'] / $total_reach) * 100) : '0%';
                                    ?>
                                </div>
                            </div>
                            <div class="split-item paid">
                                <div class="split-label">Paid</div>
                                <div class="split-value"><?php echo formatNumber($summary_stats['paid_reach']); ?></div>
                                <div class="split-percentage">
                                    <?php 
                                    echo $total_reach > 0 ? formatPercentage(($summary_stats['paid_reach'] / $total_reach) * 100) : '0%';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Results Section -->
                <div class="results-section">
                    <div class="results-header">
                        <h2>Posts (<?php echo count($posts); ?> found)</h2>
                    </div>

                    <?php if (!empty($error_message)): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php elseif (empty($posts)): ?>
                        <div class="empty-state">
                            <i class="fas fa-image"></i>
                            <h3>No Posts Found</h3>
                            <p>No posts match your current filters or there are no posts in this page.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="content-performance-table">
                                <thead>
                                    <tr>
                                        <th>Post</th>
                                        <th>Format</th>
                                        <th>Created</th>
                                        <th>Reach</th>
                                        <th>Organic</th>
                                        <th>Paid</th>
                                        <th>Likes</th>
                                        <th>Comments</th>
                                        <th>Saves</th>
                                        <th>Engagement Rate</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($posts as $post): ?>
                                        <tr>
                                            <td>
                                                <div class="post-content">
                                                    <div class="post-message">
                                                        <?php echo htmlspecialchars(substr($post['message'] ?? 'No message', 0, 100)); ?>
                                                        <?php if (strlen($post['message'] ?? '') > 100): ?>...<?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="format-badge format-<?php echo $post['format']; ?>">
                                                    <?php echo ucfirst($post['format']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo formatDate($post['created_time']); ?>
                                            </td>
                                            <td class="reach-cell">
                                                <?php echo formatNumber($post['metrics']['reach'] ?? 0); ?>
                                            </td>
                                            <td class="organic-cell">
                                                <?php echo formatNumber($post['organic_reach'] ?? 0); ?>
                                            </td>
                                            <td class="paid-cell">
                                                <?php echo formatNumber($post['paid_reach'] ?? 0); ?>
                                            </td>
                                            <td class="likes-cell">
                                                <?php echo formatNumber($post['metrics']['likes'] ?? 0); ?>
                                            </td>
                                            <td class="comments-cell">
                                                <?php echo formatNumber($post['metrics']['comments'] ?? 0); ?>
                                            </td>
                                            <td class="saves-cell">
                                                <?php echo formatNumber($post['metrics']['saves'] ?? 0); ?>
                                            </td>
                                            <td class="engagement-cell">
                                                <?php echo formatPercentage($post['engagement_rate'] ?? 0); ?>
                                            </td>
                                            <td>
                                                <div class="table-actions">
                                                    <a href="<?php echo htmlspecialchars($post['permalink_url'] ?? '#'); ?>" 
                                                       target="_blank" class="action-btn" title="View Post">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
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
        function openExportModal() {
            document.getElementById("exportModal").style.display = "block";
        }
        
        function closeExportModal() {
            document.getElementById("exportModal").style.display = "none";
        }
        
        // Auto-submit form when page is selected
        document.addEventListener("DOMContentLoaded", function() {
            const pageSelect = document.getElementById("page_id");
            if (pageSelect) {
                pageSelect.addEventListener("change", function() {
                    if (this.value) {
                        this.form.submit();
                    }
                });
            }
        });
    ';
?>

<!-- Export Modal -->
<div id="exportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Export Content Performance Data</h2>
            <button class="modal-close" onclick="closeExportModal()">&times;</button>
        </div>
        <form id="exportForm" method="POST" action="export_handler.php">
            <div class="modal-body">
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="export_type" value="content_performance">
                <input type="hidden" name="filters" id="exportFilters" value="">
                
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
                            <input type="checkbox" name="selected_fields[]" value="message" checked>
                            <span class="checkmark"></span>
                            Post Message
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="format" checked>
                            <span class="checkmark"></span>
                            Format
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="created_time" checked>
                            <span class="checkmark"></span>
                            Created Time
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="reach" checked>
                            <span class="checkmark"></span>
                            Reach
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="likes" checked>
                            <span class="checkmark"></span>
                            Likes
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="comments" checked>
                            <span class="checkmark"></span>
                            Comments
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="saves" checked>
                            <span class="checkmark"></span>
                            Saves
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="selected_fields[]" value="engagement_rate" checked>
                            <span class="checkmark"></span>
                            Engagement Rate
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeExportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Export Data</button>
            </div>
        </form>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
