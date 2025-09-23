<?php
/**
 * Saved Reports Management
 * Interface for managing saved reports and their schedules
 */

session_start();
require_once 'config.php';
require_once 'classes/ReportScheduler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$current_page = 'saved_reports';
$error_message = '';
$success_message = '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $scheduler = new ReportScheduler();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_schedule':
                try {
                    $report_id = $_POST['report_id'];
                    $frequency = $_POST['frequency'];
                    $time_of_day = $_POST['time_of_day'];
                    $day_of_week = $_POST['day_of_week'] ?? null;
                    $day_of_month = $_POST['day_of_month'] ?? null;
                    $email_recipients = array_filter(explode(',', $_POST['email_recipients']));
                    
                    $scheduler->createSchedule($report_id, $frequency, $time_of_day, $day_of_week, $day_of_month, $email_recipients);
                    $success_message = "Schedule created successfully!";
                } catch (Exception $e) {
                    $error_message = "Error creating schedule: " . $e->getMessage();
                }
                break;
                
            case 'update_schedule':
                try {
                    $schedule_id = $_POST['schedule_id'];
                    $frequency = $_POST['frequency'];
                    $time_of_day = $_POST['time_of_day'];
                    $day_of_week = $_POST['day_of_week'] ?? null;
                    $day_of_month = $_POST['day_of_month'] ?? null;
                    $email_recipients = array_filter(explode(',', $_POST['email_recipients']));
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    $scheduler->updateSchedule($schedule_id, $frequency, $time_of_day, $day_of_week, $day_of_month, $email_recipients, $is_active);
                    $success_message = "Schedule updated successfully!";
                } catch (Exception $e) {
                    $error_message = "Error updating schedule: " . $e->getMessage();
                }
                break;
                
            case 'delete_schedule':
                try {
                    $schedule_id = $_POST['schedule_id'];
                    $scheduler->deleteSchedule($schedule_id);
                    $success_message = "Schedule deleted successfully!";
                } catch (Exception $e) {
                    $error_message = "Error deleting schedule: " . $e->getMessage();
                }
                break;
        }
    }
    
    // Get saved reports
    $sql = "SELECT * FROM saved_reports WHERE is_active = 1 ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $saved_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all schedules with report names
    $sql = "SELECT rs.*, sr.name as report_name 
            FROM report_schedules rs 
            JOIN saved_reports sr ON rs.report_id = sr.id 
            ORDER BY rs.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Include header
$page_title = "Saved Reports & Schedules";
include 'templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header">
                <h1><i class="fas fa-chart-line"></i> Saved Reports & Schedules</h1>
                <p>Manage your saved reports and email schedules</p>
            </div>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <!-- Saved Reports -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-bookmark"></i> Saved Reports</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($saved_reports)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No saved reports found. Create reports from the Cross-Account Reports page.</p>
                        <a href="cross_account_reports_db.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Report
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>View</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saved_reports as $report): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($report['name']); ?></strong>
                                        <?php if ($report['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($report['description'], 0, 50)); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo ucfirst(str_replace('_', ' ', $report['custom_view'])); ?></span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($report['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewReport(<?php echo $report['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" onclick="scheduleReport(<?php echo $report['id']; ?>, '<?php echo htmlspecialchars($report['name']); ?>')">
                                            <i class="fas fa-clock"></i>
                                        </button>
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
        
        <!-- Email Schedules -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-clock"></i> Email Schedules</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($schedules)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No email schedules found. Create a schedule for any saved report.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Report</th>
                                    <th>Frequency</th>
                                    <th>Next Run</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($schedule['report_name']); ?></td>
                                    <td>
                                        <span class="badge badge-secondary"><?php echo ucfirst($schedule['frequency']); ?></span>
                                        <br><small class="text-muted"><?php echo $schedule['time_of_day']; ?></small>
                                    </td>
                                    <td>
                                        <?php if ($schedule['next_run']): ?>
                                        <?php echo date('M j, Y H:i', strtotime($schedule['next_run'])); ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($schedule['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editSchedule(<?php echo $schedule['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
    </div>
</div>

<!-- Schedule Report Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Report</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" id="scheduleForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_schedule">
                    <input type="hidden" name="report_id" id="schedule_report_id">
                    
                    <div class="form-group">
                        <label>Report Name</label>
                        <input type="text" class="form-control" id="schedule_report_name" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Frequency *</label>
                                <select class="form-control" name="frequency" id="frequency" required>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Time of Day *</label>
                                <input type="time" class="form-control" name="time_of_day" value="09:00" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" id="weekly_options" style="display: none;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Day of Week</label>
                                <select class="form-control" name="day_of_week">
                                    <option value="1">Monday</option>
                                    <option value="2">Tuesday</option>
                                    <option value="3">Wednesday</option>
                                    <option value="4">Thursday</option>
                                    <option value="5">Friday</option>
                                    <option value="6">Saturday</option>
                                    <option value="7">Sunday</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" id="monthly_options" style="display: none;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Day of Month</label>
                                <input type="number" class="form-control" name="day_of_month" min="1" max="31" value="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Recipients *</label>
                        <textarea class="form-control" name="email_recipients" rows="3" 
                                  placeholder="Enter email addresses separated by commas" required></textarea>
                        <small class="form-text text-muted">Example: admin@company.com, manager@company.com</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Schedule</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" id="editScheduleForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_schedule">
                    <input type="hidden" name="schedule_id" id="edit_schedule_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Frequency *</label>
                                <select class="form-control" name="frequency" id="edit_frequency" required>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Time of Day *</label>
                                <input type="time" class="form-control" name="time_of_day" id="edit_time_of_day" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" id="edit_weekly_options" style="display: none;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Day of Week</label>
                                <select class="form-control" name="day_of_week" id="edit_day_of_week">
                                    <option value="1">Monday</option>
                                    <option value="2">Tuesday</option>
                                    <option value="3">Wednesday</option>
                                    <option value="4">Thursday</option>
                                    <option value="5">Friday</option>
                                    <option value="6">Saturday</option>
                                    <option value="7">Sunday</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" id="edit_monthly_options" style="display: none;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Day of Month</label>
                                <input type="number" class="form-control" name="day_of_month" id="edit_day_of_month" min="1" max="31">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Recipients *</label>
                        <textarea class="form-control" name="email_recipients" id="edit_email_recipients" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Schedule report
function scheduleReport(reportId, reportName) {
    document.getElementById('schedule_report_id').value = reportId;
    document.getElementById('schedule_report_name').value = reportName;
    $('#scheduleModal').modal('show');
}

// Edit schedule
function editSchedule(scheduleId) {
    // This would need AJAX to fetch schedule details
    // For now, just show the modal
    document.getElementById('edit_schedule_id').value = scheduleId;
    $('#editScheduleModal').modal('show');
}

// Delete schedule
function deleteSchedule(scheduleId) {
    if (confirm('Are you sure you want to delete this schedule?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_schedule">
            <input type="hidden" name="schedule_id" value="${scheduleId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// View report
function viewReport(reportId) {
    window.open(`cross_account_reports_db.php?report_id=${reportId}`, '_blank');
}

// Handle frequency change
document.getElementById('frequency').addEventListener('change', function() {
    const frequency = this.value;
    document.getElementById('weekly_options').style.display = frequency === 'weekly' ? 'block' : 'none';
    document.getElementById('monthly_options').style.display = frequency === 'monthly' ? 'block' : 'none';
});

document.getElementById('edit_frequency').addEventListener('change', function() {
    const frequency = this.value;
    document.getElementById('edit_weekly_options').style.display = frequency === 'weekly' ? 'block' : 'none';
    document.getElementById('edit_monthly_options').style.display = frequency === 'monthly' ? 'block' : 'none';
});
</script>

<?php include 'templates/footer.php'; ?>
