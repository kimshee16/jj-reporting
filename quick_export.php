<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$current_page = 'export';
$admin_id = $_SESSION['admin_id'];

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $export_type = $_POST['export_type'] ?? '';
    $export_format = $_POST['export_format'] ?? 'csv';
    $export_view = $_POST['export_view'] ?? 'table';
    
    // Handle filters - they come as an array from the form
    $filters = $_POST['filters'] ?? [];
    if (is_string($filters)) {
        $filters = json_decode($filters, true) ?: [];
    }
    
    // Handle selected fields - they come as an array from the form
    $selected_fields = $_POST['selected_fields'] ?? [];
    if (is_string($selected_fields)) {
        $selected_fields = json_decode($selected_fields, true) ?: [];
    }
    
    // Process export
    require_once 'export_handler.php';
    $export_handler = new ExportHandler($pdo, $admin_id);
    $result = $export_handler->processExport($export_type, $export_format, $export_view, $filters, $selected_fields);
    echo $_POST['export_type'];
    if ($result['success']) {
        $success_message = "Export completed successfully! File: " . $result['file_name'];
    } else {
        $error_message = "Export failed: " . $result['error'];
    }
}

include 'templates/header.php';
include 'templates/sidebar.php';
?>

<div class="main-content with-sidebar">
    <div class="quick-export-container">
        <div class="page-header">
            <div class="header-content">
                <h1><i class="fas fa-file-export"></i> Quick Export</h1>
                <p>Export data immediately in your preferred format</p>
            </div>
        </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            <a href="export_handler.php?download=1&file=<?php echo urlencode(basename($result['file_path'])); ?>" class="btn btn-primary btn-sm ml-2">
                <i class="fas fa-download"></i> Download
            </a>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="export-form-container">
        <div class="export-form-card">
            <div class="form-header">
                <h2>Export Configuration</h2>
                <p>Configure your export settings and click Export to download your data</p>
            </div>

            <form method="POST" action="" id="exportForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="export_type">Export Type</label>
                        <select id="export_type" name="export_type" required>
                            <option value="">Select export type...</option>
                            <option value="campaigns">Campaigns</option>
                            <option value="adsets">Ad Sets</option>
                            <option value="ads">Ads</option>
                            <option value="cross_account">Cross-Account Reports</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="export_format">Format</label>
                        <select id="export_format" name="export_format" required>
                            <option value="csv">CSV</option>
                            <option value="excel">Excel (XLSX)</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="export_view">View</label>
                        <select id="export_view" name="export_view" required>
                            <option value="table">Table View</option>
                            <option value="chart">Chart View</option>
                            <option value="raw">Raw Data</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_range">Date Range</label>
                        <select id="date_range" name="filters[date_range]">
                            <option value="all" selected>All</option>
                            <option value="last_1_days">Last 1 Day</option>
                            <option value="last_7_days">Last 7 Days</option>
                            <option value="last_30_days">Last 30 Days</option>
                            <option value="last_90_days">Last 90 Days</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="account_filter">Account</label>
                        <select id="account_filter" name="filters[account_filter]">
                            <option value="all">All Accounts</option>
                            <option value="specific">Specific Account</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status_filter">Status</label>
                        <select id="status_filter" name="filters[status_filter]">
                            <option value="all">All Statuses</option>
                            <option value="active">Active Only</option>
                            <option value="paused">Paused Only</option>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Field Selection</h3>
                    <p>Choose which fields to include in your export</p>
                    
                    <div class="field-selection">
                        <div class="field-category">
                            <h4>Basic Information</h4>
                            <div class="field-checkboxes">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="selected_fields[]" value="name" checked>
                                    <span class="checkmark"></span>
                                    Name
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="selected_fields[]" value="status" checked>
                                    <span class="checkmark"></span>
                                    Status
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="selected_fields[]" value="created_time">
                                    <span class="checkmark"></span>
                                    Created Time
                                </label>
                            </div>
                        </div>

                        <div class="field-category">
                            <h4>Performance Metrics</h4>
                            <div class="field-checkboxes">
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

                        <div class="field-category">
                            <h4>Additional Fields</h4>
                            <div class="field-checkboxes">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="selected_fields[]" value="objective">
                                    <span class="checkmark"></span>
                                    Objective
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="selected_fields[]" value="platform">
                                    <span class="checkmark"></span>
                                    Platform
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="selected_fields[]" value="targeting">
                                    <span class="checkmark"></span>
                                    Targeting
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="resetForm()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i> Export Data
                    </button>
                </div>
            </form>
        </div>

        <div class="export-info-card">
            <div class="info-header">
                <h3>Export Information</h3>
            </div>
            <div class="info-content">
                <div class="info-item">
                    <h4><i class="fas fa-file-csv"></i> CSV Format</h4>
                    <p>Plain text format, compatible with Excel and Google Sheets. Best for data analysis.</p>
                </div>
                <div class="info-item">
                    <h4><i class="fas fa-file-excel"></i> Excel Format</h4>
                    <p>Native Excel format with formatting. Includes charts and multiple sheets for complex reports.</p>
                </div>
                <div class="info-item">
                    <h4><i class="fas fa-file-pdf"></i> PDF Format</h4>
                    <p>Formatted report with charts and tables. Perfect for sharing and presentations.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>

<style>
.quick-export-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.quick-export-container .page-header {
    margin-bottom: 30px;
    padding: 20px 0;
    border-bottom: 1px solid #e1e5e9;
}

.quick-export-container .header-content h1 {
    font-size: 28px;
    color: #1a1a1a;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.quick-export-container .header-content h1 i {
    color: #1877f2;
}

.quick-export-container .header-content p {
    color: #666;
    font-size: 16px;
}

.export-form-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.export-form-card, .export-info-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.form-header {
    padding: 20px;
    border-bottom: 1px solid #f0f0f0;
}

.form-header h2 {
    font-size: 20px;
    color: #1a1a1a;
    margin-bottom: 8px;
}

.form-header p {
    color: #666;
    font-size: 14px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    padding: 20px;
}

.form-section {
    padding: 20px;
    border-top: 1px solid #f0f0f0;
}

.form-section h3 {
    font-size: 18px;
    color: #1a1a1a;
    margin-bottom: 8px;
}

.form-section p {
    color: #666;
    font-size: 14px;
    margin-bottom: 20px;
}

.field-selection {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.field-category h4 {
    font-size: 16px;
    color: #1a1a1a;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f0f0f0;
}

.field-checkboxes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: #666;
    padding: 8px;
    border-radius: 6px;
    transition: background-color 0.2s ease;
}

.checkbox-label:hover {
    background-color: #f8f9fa;
}

.checkbox-label input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #1877f2;
}

.form-actions {
    padding: 20px;
    border-top: 1px solid #f0f0f0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.info-header {
    padding: 20px;
    border-bottom: 1px solid #f0f0f0;
}

.info-header h3 {
    font-size: 18px;
    color: #1a1a1a;
}

.info-content {
    padding: 20px;
}

.info-item {
    margin-bottom: 20px;
}

.info-item h4 {
    font-size: 14px;
    color: #1a1a1a;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-item h4 i {
    color: #1877f2;
}

.info-item p {
    color: #666;
    font-size: 13px;
    line-height: 1.5;
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert-success {
    background: #e8f5e8;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
}

.alert-error {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #ffcdd2;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.ml-2 {
    margin-left: 8px;
}

@media (max-width: 768px) {
    .export-form-container {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .field-checkboxes {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function resetForm() {
    document.getElementById('exportForm').reset();
    
    // Reset checkboxes to default checked state
    const defaultFields = ['name', 'status', 'spend', 'impressions', 'clicks', 'ctr', 'cpc', 'cpa', 'roas'];
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = defaultFields.includes(checkbox.value);
    });
}

// Handle form submission
document.getElementById('exportForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
    submitBtn.disabled = true;
    
    // Re-enable after 5 seconds in case of error
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 5000);
});
</script>
