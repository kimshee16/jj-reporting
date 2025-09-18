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
$report_id = $_GET['report_id'] ?? '';

if ($action === 'delete' && !empty($report_id)) {
    try {
        $stmt = $pdo->prepare("DELETE FROM saved_reports WHERE id = ? AND created_by = ?");
        $stmt->execute([$report_id, $admin_id]);
        $success_message = "Report deleted successfully.";
    } catch(PDOException $e) {
        $error_message = "Error deleting report: " . $e->getMessage();
    }
}

if ($action === 'duplicate' && !empty($report_id)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM saved_reports WHERE id = ? AND (created_by = ? OR id IN (SELECT report_id FROM report_sharing WHERE shared_with_admin_id = ?))");
        $stmt->execute([$report_id, $admin_id, $admin_id]);
        $original_report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($original_report) {
            $stmt = $pdo->prepare("INSERT INTO saved_reports (name, description, filters_json, custom_view, created_by, is_public) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $original_report['name'] . ' (Copy)',
                $original_report['description'],
                $original_report['filters_json'],
                $original_report['custom_view'],
                $admin_id,
                0
            ]);
            $success_message = "Report duplicated successfully.";
        }
    } catch(PDOException $e) {
        $error_message = "Error duplicating report: " . $e->getMessage();
    }
}

// Get saved reports
try {
    $stmt = $pdo->prepare("
        SELECT sr.*, 
               (SELECT COUNT(*) FROM report_schedules rs WHERE rs.report_id = sr.id AND rs.is_active = 1) as schedule_count,
               (SELECT MAX(rel.executed_at) FROM report_execution_logs rel WHERE rel.report_id = sr.id) as last_executed
        FROM saved_reports sr 
        WHERE sr.is_active = 1 AND (sr.created_by = ? OR sr.is_public = 1 OR sr.id IN (SELECT report_id FROM report_sharing WHERE shared_with_admin_id = ?))
        ORDER BY sr.updated_at DESC
    ");
    $stmt->execute([$admin_id, $admin_id]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching reports: " . $e->getMessage();
    $reports = [];
}

// Get report schedules
$schedules = [];
try {
    $stmt = $pdo->prepare("
        SELECT rs.*, sr.name as report_name 
        FROM report_schedules rs 
        JOIN saved_reports sr ON rs.report_id = sr.id 
        WHERE rs.is_active = 1 AND (sr.created_by = ? OR sr.is_public = 1 OR sr.id IN (SELECT report_id FROM report_sharing WHERE shared_with_admin_id = ?))
        ORDER BY rs.next_run ASC
    ");
    $stmt->execute([$admin_id, $admin_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Handle error silently
}

// Set page variables for template
$page_title = "Reports Library - JJ Reporting Dashboard";

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
                <button class="btn btn-primary" onclick="createNewReport()">
                    <i class="fas fa-plus"></i>
                    Create New Report
                </button>
                <button class="btn btn-secondary" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i>
                    Refresh
                </button>
            </div>';
        include 'templates/top_header.php'; 
        ?>

        <!-- Reports Library Content -->
        <div class="dashboard-content">
            <div class="reports-library-container">
                
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
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Total Reports</h3>
                            <p class="stat-value"><?php echo count($reports); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Scheduled Reports</h3>
                            <p class="stat-value"><?php echo count($schedules); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-share-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Shared Reports</h3>
                            <p class="stat-value"><?php echo count(array_filter($reports, function($r) { return $r['is_public'] == 1; })); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Reports Grid -->
                <div class="reports-section">
                    <div class="section-header">
                        <h2>Saved Reports</h2>
                        <div class="section-actions">
                            <select id="filterBy" onchange="filterReports()">
                                <option value="all">All Reports</option>
                                <option value="my">My Reports</option>
                                <option value="shared">Shared Reports</option>
                                <option value="scheduled">Scheduled Reports</option>
                            </select>
                        </div>
                    </div>

                    <?php if (empty($reports)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>No Reports Found</h3>
                            <p>You haven't created any reports yet. Create your first report to get started.</p>
                            <button class="btn btn-primary" onclick="createNewReport()">
                                <i class="fas fa-plus"></i>
                                Create Your First Report
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="reports-grid">
                            <?php foreach ($reports as $report): ?>
                                <div class="report-card" data-type="<?php echo $report['is_public'] ? 'shared' : 'my'; ?>" data-scheduled="<?php echo $report['schedule_count'] > 0 ? 'yes' : 'no'; ?>">
                                    <div class="report-header">
                                        <div class="report-title">
                                            <h3><?php echo htmlspecialchars($report['name']); ?></h3>
                                            <?php if ($report['is_public']): ?>
                                                <span class="shared-badge">
                                                    <i class="fas fa-share-alt"></i>
                                                    Shared
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="report-actions">
                                            <div class="dropdown">
                                                <button class="action-btn" onclick="toggleDropdown(this)">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a href="cross_account_reports.php?load_report=<?php echo $report['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-play"></i> Run Report
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="editReport(<?php echo $report['id']; ?>)" class="dropdown-item">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="duplicateReport(<?php echo $report['id']; ?>)" class="dropdown-item">
                                                        <i class="fas fa-copy"></i> Duplicate
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="scheduleReport(<?php echo $report['id']; ?>)" class="dropdown-item">
                                                        <i class="fas fa-clock"></i> Schedule
                                                    </a>
                                                    <?php if ($report['created_by'] == $admin_id): ?>
                                                        <a href="javascript:void(0)" onclick="deleteReport(<?php echo $report['id']; ?>)" class="dropdown-item danger">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="report-content">
                                        <p class="report-description">
                                            <?php echo htmlspecialchars($report['description'] ?: 'No description provided'); ?>
                                        </p>
                                        
                                        <div class="report-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-calendar"></i>
                                                <span>Created <?php echo date('M j, Y', strtotime($report['created_at'])); ?></span>
                                            </div>
                                            
                                            <?php if ($report['last_executed']): ?>
                                                <div class="meta-item">
                                                    <i class="fas fa-history"></i>
                                                    <span>Last run <?php echo date('M j, Y g:i A', strtotime($report['last_executed'])); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($report['schedule_count'] > 0): ?>
                                                <div class="meta-item">
                                                    <i class="fas fa-clock"></i>
                                                    <span><?php echo $report['schedule_count']; ?> schedule(s)</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="report-filters">
                                            <?php 
                                            $filters = json_decode($report['filters_json'], true);
                                            $filter_labels = [];
                                            if ($filters['platform'] !== 'all') $filter_labels[] = 'Platform: ' . ucfirst($filters['platform']);
                                            if ($filters['device'] !== 'all') $filter_labels[] = 'Device: ' . ucfirst($filters['device']);
                                            if ($filters['country'] !== 'all') $filter_labels[] = 'Country: ' . $filters['country'];
                                            if ($filters['format'] !== 'all') $filter_labels[] = 'Format: ' . $filters['format'];
                                            if ($filters['objective'] !== 'all') $filter_labels[] = 'Objective: ' . $filters['objective'];
                                            if (!empty($filters['ctr_threshold'])) $filter_labels[] = 'Min CTR: ' . $filters['ctr_threshold'] . '%';
                                            if (!empty($filters['roas_threshold'])) $filter_labels[] = 'Min ROAS: ' . $filters['roas_threshold'] . 'x';
                                            
                                            $filter_text = !empty($filter_labels) ? implode(' • ', array_slice($filter_labels, 0, 3)) : 'All filters';
                                            if (count($filter_labels) > 3) $filter_text .= ' +' . (count($filter_labels) - 3) . ' more';
                                            ?>
                                            <span class="filters-preview"><?php echo htmlspecialchars($filter_text); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Scheduled Reports Section -->
                <?php if (!empty($schedules)): ?>
                    <div class="schedules-section">
                        <div class="section-header">
                            <h2>Scheduled Reports</h2>
                        </div>
                        
                        <div class="schedules-list">
                            <?php foreach ($schedules as $schedule): ?>
                                <div class="schedule-item">
                                    <div class="schedule-info">
                                        <h4><?php echo htmlspecialchars($schedule['report_name']); ?></h4>
                                        <p>
                                            <i class="fas fa-clock"></i>
                                            <?php echo ucfirst($schedule['frequency']); ?> at <?php echo date('g:i A', strtotime($schedule['time_of_day'])); ?>
                                            <?php if ($schedule['frequency'] === 'weekly'): ?>
                                                (<?php echo date('l', strtotime('Sunday +' . $schedule['day_of_week'] . ' days')); ?>)
                                            <?php elseif ($schedule['frequency'] === 'monthly'): ?>
                                                (Day <?php echo $schedule['day_of_month']; ?>)
                                            <?php endif; ?>
                                        </p>
                                        <p>
                                            <i class="fas fa-envelope"></i>
                                            <?php echo count(json_decode($schedule['email_recipients'], true)); ?> recipient(s)
                                        </p>
                                    </div>
                                    <div class="schedule-actions">
                                        <span class="next-run">
                                            Next: <?php echo $schedule['next_run'] ? date('M j, Y g:i A', strtotime($schedule['next_run'])) : 'Not scheduled'; ?>
                                        </span>
                                        <button class="btn btn-sm btn-secondary" onclick="editSchedule(<?php echo $schedule['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php 
    $inline_scripts = '
        function createNewReport() {
            window.location.href = "cross_account_reports.php";
        }
        
        function refreshData() {
            window.location.reload();
        }
        
        function filterReports() {
            const filter = document.getElementById("filterBy").value;
            const cards = document.querySelectorAll(".report-card");
            
            cards.forEach(card => {
                const isMy = card.dataset.type === "my";
                const isShared = card.dataset.type === "shared";
                const isScheduled = card.dataset.scheduled === "yes";
                
                let show = true;
                if (filter === "my" && !isMy) show = false;
                if (filter === "shared" && !isShared) show = false;
                if (filter === "scheduled" && !isScheduled) show = false;
                
                card.style.display = show ? "block" : "none";
            });
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
        
        function editReport(reportId) {
            // Open edit modal or redirect to edit page
            window.open("edit_report.php?id=" + reportId, "_blank");
        }
        
        function duplicateReport(reportId) {
            if (confirm("Are you sure you want to duplicate this report?")) {
                window.location.href = "?action=duplicate&report_id=" + reportId;
            }
        }
        
        function deleteReport(reportId) {
            if (confirm("Are you sure you want to delete this report? This action cannot be undone.")) {
                window.location.href = "?action=delete&report_id=" + reportId;
            }
        }
        
        function scheduleReport(reportId) {
            // Open schedule modal
            window.open("schedule_report.php?report_id=" + reportId, "_blank", "width=600,height=500");
        }
        
        function editSchedule(scheduleId) {
            // Open schedule edit modal
            window.open("edit_schedule.php?id=" + scheduleId, "_blank", "width=600,height=500");
        }
        
        function deleteSchedule(scheduleId) {
            if (confirm("Are you sure you want to delete this schedule?")) {
                // Implement delete schedule
                console.log("Delete schedule:", scheduleId);
            }
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener("click", function(e) {
            if (!e.target.closest(".dropdown")) {
                document.querySelectorAll(".dropdown-menu").forEach(menu => {
                    menu.style.display = "none";
                });
            }
        });
    ';
    include 'templates/footer.php'; 
    ?>
