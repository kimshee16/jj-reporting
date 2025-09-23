<?php
session_start();
require_once 'config.php';

// Check if user is logged in
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

$current_page = 'export_center';
$admin_id = $_SESSION['admin_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_job':
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $export_type = $_POST['export_type'];
            $export_format = $_POST['export_format'];
            $export_view = $_POST['export_view'];
            $schedule_type = $_POST['schedule_type'];
            $schedule_time = $_POST['schedule_time'];
            $email_recipients = $_POST['email_recipients'];
            
            // Calculate next run time
            $next_run = calculateNextRun($schedule_type, $schedule_time);
            
            $sql = "INSERT INTO export_jobs (name, description, export_type, export_format, export_view, schedule_type, schedule_time, email_recipients, created_by, next_run) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $export_type, $export_format, $export_view, $schedule_type, $schedule_time, $email_recipients, $admin_id, $next_run]);
            
            $success_message = "Export job created successfully!";
            break;
            
        case 'toggle_job':
            $job_id = $_POST['job_id'];
            $is_active = $_POST['is_active'];
            
            $sql = "UPDATE export_jobs SET is_active = ? WHERE id = ? AND created_by = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$is_active, $job_id, $admin_id]);
            
            $success_message = "Export job updated successfully!";
            break;
            
        case 'delete_job':
            $job_id = $_POST['job_id'];
            
            $sql = "DELETE FROM export_jobs WHERE id = ? AND created_by = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$job_id, $admin_id]);
            
            $success_message = "Export job deleted successfully!";
            break;
            
        case 'run_job':
            $job_id = $_POST['job_id'];
            
            // Add to queue for immediate execution
            $sql = "UPDATE export_jobs SET next_run = NOW() WHERE id = ? AND created_by = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$job_id, $admin_id]);
            
            $success_message = "Export job queued for immediate execution!";
            break;
    }
}

// Function to calculate next run time
function calculateNextRun($schedule_type, $schedule_time) {
    $time = new DateTime($schedule_time);
    
    switch ($schedule_type) {
        case 'daily':
            $next_run = new DateTime('tomorrow');
            break;
        case 'weekly':
            $next_run = new DateTime('next monday');
            break;
        case 'monthly':
            $next_run = new DateTime('first day of next month');
            break;
        default:
            return null;
    }
    
    $next_run->setTime($time->format('H'), $time->format('i'));
    return $next_run->format('Y-m-d H:i:s');
}

// Fetch export jobs
$sql = "SELECT * FROM export_jobs WHERE created_by = ? ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$admin_id]);
$export_jobs = $stmt->fetchAll();

// Fetch export templates
$sql = "SELECT * FROM export_templates WHERE is_active = 1 AND (is_public = 1 OR created_by = ?) ORDER BY usage_count DESC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$admin_id]);
$export_templates = $stmt->fetchAll();

// Fetch export history
$sql = "SELECT eh.*, ej.name as job_name 
        FROM export_history eh 
        LEFT JOIN export_jobs ej ON eh.job_id = ej.id 
        WHERE eh.created_by = ? 
        ORDER BY eh.created_at DESC 
        LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute([$admin_id]);
$export_history = $stmt->fetchAll();

// Get statistics
$stats = [
    'total_jobs' => count($export_jobs),
    'active_jobs' => count(array_filter($export_jobs, fn($job) => $job['is_active'])),
    'total_exports' => count($export_history),
    'recent_exports' => count(array_filter($export_history, fn($export) => strtotime($export['created_at']) > strtotime('-7 days')))
];

include 'templates/header.php';
include 'templates/sidebar.php';
?>

<div class="main-content with-sidebar">
<div class="export-center-container">
    <div class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-download"></i> Export Center</h1>
            <p>Manage scheduled exports, templates, and download history</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openCreateJobModal()">
                <i class="fas fa-plus"></i> Create Export Job
            </button>
            <button class="btn btn-secondary" onclick="openCreateTemplateModal()">
                <i class="fas fa-save"></i> Save Template
            </button>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['total_jobs']; ?></div>
                <div class="stat-label">Total Jobs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-play-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['active_jobs']; ?></div>
                <div class="stat-label">Active Jobs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-file-export"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['total_exports']; ?></div>
                <div class="stat-label">Total Exports</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['recent_exports']; ?></div>
                <div class="stat-label">This Week</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs">
            <button class="tab-button active" onclick="switchTab('jobs')">
                <i class="fas fa-clock"></i> Scheduled Jobs
            </button>
            <button class="tab-button" onclick="switchTab('templates')">
                <i class="fas fa-bookmark"></i> Templates
            </button>
            <button class="tab-button" onclick="switchTab('history')">
                <i class="fas fa-history"></i> Export History
            </button>
        </div>

        <!-- Jobs Tab -->
        <div id="jobs-tab" class="tab-content active">
            <div class="section-header">
                <h2>Scheduled Export Jobs</h2>
                <div class="section-actions">
                    <button class="btn btn-outline" onclick="refreshData()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>

            <?php if (empty($export_jobs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>No Export Jobs</h3>
                    <p>Create your first scheduled export job to get started.</p>
                    <button class="btn btn-primary" onclick="openCreateJobModal()">
                        <i class="fas fa-plus"></i> Create Export Job
                    </button>
                </div>
            <?php else: ?>
                <div class="jobs-grid">
                    <?php foreach ($export_jobs as $job): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <div class="job-title">
                                    <h3><?php echo htmlspecialchars($job['name']); ?></h3>
                                    <span class="job-status <?php echo $job['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $job['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                                <div class="job-actions">
                                    <div class="dropdown">
                                        <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="#" onclick="runJob(<?php echo $job['id']; ?>)">
                                                <i class="fas fa-play"></i> Run Now
                                            </a>
                                            <a href="#" onclick="toggleJobStatus(<?php echo $job['id']; ?>, <?php echo $job['is_active'] ? 'false' : 'true'; ?>)">
                                                <i class="fas fa-<?php echo $job['is_active'] ? 'pause' : 'play'; ?>"></i> 
                                                <?php echo $job['is_active'] ? 'Pause' : 'Activate'; ?>
                                            </a>
                                            <a href="#" onclick="editJob(<?php echo $job['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="#" onclick="deleteJob(<?php echo $job['id']; ?>)" class="danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="job-content">
                                <p class="job-description"><?php echo htmlspecialchars($job['description']); ?></p>
                                <div class="job-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Type:</span>
                                        <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $job['export_type'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Format:</span>
                                        <span class="detail-value"><?php echo strtoupper($job['export_format']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Schedule:</span>
                                        <span class="detail-value"><?php echo ucfirst($job['schedule_type']); ?></span>
                                    </div>
                                    <?php if ($job['schedule_time']): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Time:</span>
                                            <span class="detail-value"><?php echo date('g:i A', strtotime($job['schedule_time'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($job['next_run']): ?>
                                    <div class="next-run">
                                        <i class="fas fa-clock"></i>
                                        Next run: <?php echo date('M j, Y g:i A', strtotime($job['next_run'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($job['last_run']): ?>
                                    <div class="last-run">
                                        <i class="fas fa-check-circle"></i>
                                        Last run: <?php echo date('M j, Y g:i A', strtotime($job['last_run'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Templates Tab -->
        <div id="templates-tab" class="tab-content">
            <div class="section-header">
                <h2>Export Templates</h2>
                <div class="section-actions">
                    <button class="btn btn-outline" onclick="refreshData()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>

            <?php if (empty($export_templates)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <h3>No Templates</h3>
                    <p>Create your first export template to save time on future exports.</p>
                    <button class="btn btn-primary" onclick="openCreateTemplateModal()">
                        <i class="fas fa-plus"></i> Create Template
                    </button>
                </div>
            <?php else: ?>
                <div class="templates-grid">
                    <?php foreach ($export_templates as $template): ?>
                        <div class="template-card">
                            <div class="template-header">
                                <div class="template-title">
                                    <h3><?php echo htmlspecialchars($template['name']); ?></h3>
                                    <?php if ($template['is_public']): ?>
                                        <span class="public-badge">Public</span>
                                    <?php endif; ?>
                                </div>
                                <div class="template-actions">
                                    <div class="dropdown">
                                        <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="#" onclick="useTemplate(<?php echo $template['id']; ?>)">
                                                <i class="fas fa-play"></i> Use Template
                                            </a>
                                            <a href="#" onclick="editTemplate(<?php echo $template['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="#" onclick="duplicateTemplate(<?php echo $template['id']; ?>)">
                                                <i class="fas fa-copy"></i> Duplicate
                                            </a>
                                            <?php if ($template['created_by'] == $admin_id): ?>
                                                <a href="#" onclick="deleteTemplate(<?php echo $template['id']; ?>)" class="danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="template-content">
                                <p class="template-description"><?php echo htmlspecialchars($template['description']); ?></p>
                                <div class="template-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Type:</span>
                                        <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $template['export_type'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Format:</span>
                                        <span class="detail-value"><?php echo strtoupper($template['export_format']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">View:</span>
                                        <span class="detail-value"><?php echo ucfirst($template['export_view']); ?></span>
                                    </div>
                                </div>
                                <div class="template-meta">
                                    <span class="usage-count">
                                        <i class="fas fa-download"></i>
                                        Used <?php echo $template['usage_count']; ?> times
                                    </span>
                                    <span class="created-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M j, Y', strtotime($template['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- History Tab -->
        <div id="history-tab" class="tab-content">
            <div class="section-header">
                <h2>Export History</h2>
                <div class="section-actions">
                    <button class="btn btn-outline" onclick="refreshData()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>

            <?php if (empty($export_history)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3>No Export History</h3>
                    <p>Your completed exports will appear here.</p>
                </div>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($export_history as $export): ?>
                        <div class="history-item">
                            <div class="history-icon">
                                <i class="fas fa-file-<?php echo $export['export_format'] === 'excel' ? 'excel' : ($export['export_format'] === 'pdf' ? 'pdf' : 'csv'); ?>"></i>
                            </div>
                            <div class="history-content">
                                <div class="history-header">
                                    <h4><?php echo htmlspecialchars($export['file_name']); ?></h4>
                                    <span class="status-badge <?php echo $export['status']; ?>">
                                        <?php echo ucfirst($export['status']); ?>
                                    </span>
                                </div>
                                <div class="history-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Type:</span>
                                        <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $export['export_type'])); ?></span>
                                        <span class="detail-label">Format:</span>
                                        <span class="detail-value"><?php echo strtoupper($export['export_format']); ?></span>
                                        <span class="detail-label">Records:</span>
                                        <span class="detail-value"><?php echo number_format($export['record_count']); ?></span>
                                    </div>
                                    <?php if ($export['job_name']): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Job:</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($export['job_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="history-meta">
                                    <span class="created-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($export['created_at'])); ?>
                                    </span>
                                    <?php if ($export['file_size']): ?>
                                        <span class="file-size">
                                            <i class="fas fa-weight"></i>
                                            <?php echo formatFileSize($export['file_size']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($export['download_count'] > 0): ?>
                                        <span class="download-count">
                                            <i class="fas fa-download"></i>
                                            Downloaded <?php echo $export['download_count']; ?> times
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($export['error_message']): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php echo htmlspecialchars($export['error_message']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="history-actions">
                                <?php if ($export['status'] === 'completed'): ?>
                                    <a href="/exports/<?php echo basename($export['file_path']); ?>" class="btn btn-primary" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php endif; ?>
                                <button class="btn btn-outline" onclick="viewExportDetails(<?php echo $export['id']; ?>)">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Job Modal -->
<div id="createJobModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Create Export Job</h2>
            <button class="modal-close" onclick="closeCreateJobModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="action" value="create_job">
                
                <div class="form-group">
                    <label for="job_name">Job Name</label>
                    <input type="text" id="job_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="job_description">Description</label>
                    <textarea id="job_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="export_type">Export Type</label>
                        <select id="export_type" name="export_type" required>
                            <option value="campaigns">Campaigns</option>
                            <option value="adsets">Ad Sets</option>
                            <option value="ads">Ads</option>
                            <option value="cross_account">Cross-Account</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="export_format">Format</label>
                        <select id="export_format" name="export_format" required>
                            <option value="excel">Excel (XLSX)</option>
                            <option value="csv">CSV</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="export_view">View</label>
                        <select id="export_view" name="export_view" required>
                            <option value="table">Table View</option>
                            <option value="chart">Chart View</option>
                            <option value="raw">Raw Data</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="schedule_type">Schedule</label>
                        <select id="schedule_type" name="schedule_type" required>
                            <option value="once">Run Once</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="schedule_time">Time (for recurring schedules)</label>
                    <input type="time" id="schedule_time" name="schedule_time">
                </div>
                
                <div class="form-group">
                    <label for="email_recipients">Email Recipients (comma-separated)</label>
                    <input type="text" id="email_recipients" name="email_recipients" placeholder="admin@company.com, manager@company.com">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeCreateJobModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Job</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Template Modal -->
<div id="createTemplateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Save Export Template</h2>
            <button class="modal-close" onclick="closeCreateTemplateModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="action" value="create_template">
                
                <div class="form-group">
                    <label for="template_name">Template Name</label>
                    <input type="text" id="template_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="template_description">Description</label>
                    <textarea id="template_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="template_export_type">Export Type</label>
                        <select id="template_export_type" name="export_type" required>
                            <option value="campaigns">Campaigns</option>
                            <option value="adsets">Ad Sets</option>
                            <option value="ads">Ads</option>
                            <option value="cross_account">Cross-Account</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_export_format">Format</label>
                        <select id="template_export_format" name="export_format" required>
                            <option value="excel">Excel (XLSX)</option>
                            <option value="csv">CSV</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="template_export_view">View</label>
                    <select id="template_export_view" name="export_view" required>
                        <option value="table">Table View</option>
                        <option value="chart">Chart View</option>
                        <option value="raw">Raw Data</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_public" value="1">
                        <span class="checkmark"></span>
                        Make this template public (visible to other users)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeCreateTemplateModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Template</button>
            </div>
        </form>
    </div>
</div>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

include 'templates/footer.php';
?>

<script>
// Tab switching
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}

// Modal functions
function openCreateJobModal() {
    document.getElementById('createJobModal').style.display = 'block';
}

function closeCreateJobModal() {
    document.getElementById('createJobModal').style.display = 'none';
}

function openCreateTemplateModal() {
    document.getElementById('createTemplateModal').style.display = 'block';
}

function closeCreateTemplateModal() {
    document.getElementById('createTemplateModal').style.display = 'none';
}

// Job management functions
function runJob(jobId) {
    if (confirm('Run this export job now?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="run_job">
            <input type="hidden" name="job_id" value="${jobId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleJobStatus(jobId, isActive) {
    const action = isActive === 'true' ? 'activate' : 'pause';
    if (confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} this export job?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_job">
            <input type="hidden" name="job_id" value="${jobId}">
            <input type="hidden" name="is_active" value="${isActive}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteJob(jobId) {
    if (confirm('Delete this export job? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_job">
            <input type="hidden" name="job_id" value="${jobId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Template functions
function useTemplate(templateId) {
    // Redirect to export page with template pre-filled
    window.location.href = `export.php?template_id=${templateId}`;
}

function editTemplate(templateId) {
    // TODO: Implement template editing
    alert('Template editing will be implemented soon.');
}

function duplicateTemplate(templateId) {
    // TODO: Implement template duplication
    alert('Template duplication will be implemented soon.');
}

function deleteTemplate(templateId) {
    if (confirm('Delete this template? This action cannot be undone.')) {
        // TODO: Implement template deletion
        alert('Template deletion will be implemented soon.');
    }
}

// Utility functions
function refreshData() {
    window.location.reload();
}

function toggleDropdown(element) {
    const dropdown = element.parentElement.querySelector('.dropdown-menu');
    dropdown.classList.toggle('show');
    
    // Close other dropdowns
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        if (menu !== dropdown) {
            menu.classList.remove('show');
        }
    });
}

function viewExportDetails(exportId) {
    // TODO: Implement export details modal
    alert('Export details will be implemented soon.');
}

// Close dropdowns when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('.dropdown-toggle')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
}
</script>
