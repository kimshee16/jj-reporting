<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'all';
$results = [];
$total_results = 0;

if (!empty($query)) {
    // Make API call to search
    $api_url = "global_search_api.php?q=" . urlencode($query) . "&type=" . urlencode($type);
    $response = @file_get_contents($api_url);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['results'])) {
            $results = $data['results'];
            $total_results = $data['total'] ?? 0;
        }
    }
}

// Page title
$page_title = "Search Results - JJ Reporting Dashboard";

// Include header
include 'templates/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-title">
            <h1>Search Results</h1>
            <p>Search query: "<strong><?php echo htmlspecialchars($query); ?></strong>"</p>
        </div>
        <div class="page-actions">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="search-container">
        <!-- Search Form -->
        <div class="search-form-container">
            <form method="GET" action="search_results.php" class="search-form">
                <div class="search-input-group">
                    <input type="text" 
                           name="q" 
                           value="<?php echo htmlspecialchars($query); ?>" 
                           placeholder="Search campaigns, ads, content..." 
                           class="search-input"
                           autofocus>
                    <select name="type" class="search-type-select">
                        <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="campaigns" <?php echo $type === 'campaigns' ? 'selected' : ''; ?>>Campaigns</option>
                        <option value="ads" <?php echo $type === 'ads' ? 'selected' : ''; ?>>Ads</option>
                        <option value="adsets" <?php echo $type === 'adsets' ? 'selected' : ''; ?>>Ad Sets</option>
                        <option value="content" <?php echo $type === 'content' ? 'selected' : ''; ?>>Content</option>
                    </select>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Results -->
        <div class="search-results">
            <?php if (empty($query)): ?>
                <div class="search-placeholder">
                    <div class="search-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Search Everything</h3>
                    <p>Enter a search term to find campaigns, ads, ad sets, or content across your accounts.</p>
                </div>
            <?php elseif ($total_results === 0): ?>
                <div class="no-results">
                    <div class="no-results-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>No Results Found</h3>
                    <p>No items found for "<strong><?php echo htmlspecialchars($query); ?></strong>"</p>
                    <p>Try different keywords or check your spelling.</p>
                </div>
            <?php else: ?>
                <div class="results-header">
                    <h3><?php echo $total_results; ?> result<?php echo $total_results !== 1 ? 's' : ''; ?> found</h3>
                </div>
                
                <div class="results-list">
                    <?php foreach ($results as $result): ?>
                        <div class="result-item" data-type="<?php echo htmlspecialchars($result['type']); ?>">
                            <div class="result-icon">
                                <?php
                                $icon_class = 'fas fa-';
                                switch ($result['type']) {
                                    case 'campaign':
                                        $icon_class .= 'bullhorn';
                                        break;
                                    case 'ad':
                                        $icon_class .= 'ad';
                                        break;
                                    case 'adset':
                                        $icon_class .= 'layer-group';
                                        break;
                                    case 'content':
                                        $icon_class .= 'file-alt';
                                        break;
                                    default:
                                        $icon_class .= 'search';
                                }
                                ?>
                                <i class="<?php echo $icon_class; ?>"></i>
                            </div>
                            <div class="result-content">
                                <h4 class="result-title">
                                    <a href="<?php echo htmlspecialchars($result['url']); ?>">
                                        <?php echo htmlspecialchars($result['title']); ?>
                                    </a>
                                </h4>
                                <p class="result-description">
                                    <?php echo htmlspecialchars($result['description']); ?>
                                </p>
                                <div class="result-meta">
                                    <span class="result-type"><?php echo ucfirst($result['type']); ?></span>
                                    <?php if (isset($result['created_time'])): ?>
                                        <span class="result-date">
                                            <?php echo date('M j, Y', strtotime($result['created_time'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.search-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.search-form-container {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.search-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-input-group {
    display: flex;
    flex: 1;
    gap: 10px;
}

.search-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 16px;
    transition: border-color 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #007bff;
}

.search-type-select {
    padding: 12px 16px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    background: white;
    font-size: 16px;
    min-width: 120px;
}

.search-btn {
    padding: 12px 24px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-btn:hover {
    background: #0056b3;
}

.search-results {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.search-placeholder,
.no-results {
    text-align: center;
    padding: 60px 20px;
}

.search-icon,
.no-results-icon {
    font-size: 48px;
    color: #6c757d;
    margin-bottom: 20px;
}

.search-placeholder h3,
.no-results h3 {
    color: #495057;
    margin-bottom: 10px;
}

.search-placeholder p,
.no-results p {
    color: #6c757d;
    margin-bottom: 10px;
}

.results-header {
    padding: 20px;
    border-bottom: 1px solid #e1e5e9;
    background: #f8f9fa;
}

.results-header h3 {
    margin: 0;
    color: #495057;
}

.results-list {
    max-height: 600px;
    overflow-y: auto;
}

.result-item {
    display: flex;
    align-items: flex-start;
    padding: 20px;
    border-bottom: 1px solid #e1e5e9;
    transition: background-color 0.2s ease;
}

.result-item:hover {
    background: #f8f9fa;
}

.result-item:last-child {
    border-bottom: none;
}

.result-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
    flex-shrink: 0;
}

.result-item[data-type="campaign"] .result-icon {
    background: #007bff;
    color: white;
}

.result-item[data-type="ad"] .result-icon {
    background: #28a745;
    color: white;
}

.result-item[data-type="adset"] .result-icon {
    background: #ffc107;
    color: #212529;
}

.result-item[data-type="content"] .result-icon {
    background: #6f42c1;
    color: white;
}

.result-content {
    flex: 1;
}

.result-title {
    margin: 0 0 8px 0;
    font-size: 18px;
}

.result-title a {
    color: #007bff;
    text-decoration: none;
    transition: color 0.2s ease;
}

.result-title a:hover {
    color: #0056b3;
    text-decoration: underline;
}

.result-description {
    margin: 0 0 12px 0;
    color: #6c757d;
    line-height: 1.4;
}

.result-meta {
    display: flex;
    gap: 16px;
    align-items: center;
}

.result-type {
    background: #e9ecef;
    color: #495057;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.result-date {
    color: #6c757d;
    font-size: 14px;
}

@media (max-width: 768px) {
    .search-form {
        flex-direction: column;
        gap: 15px;
    }
    
    .search-input-group {
        flex-direction: column;
    }
    
    .result-item {
        flex-direction: column;
        text-align: center;
    }
    
    .result-icon {
        margin: 0 0 16px 0;
    }
}
</style>

<?php include 'templates/footer.php'; ?>

